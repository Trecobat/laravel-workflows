<?php

namespace the42coders\Workflows\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use the42coders\Workflows\DataBuses\DataBus;
use the42coders\Workflows\Loggers\WorkflowLog;
use the42coders\Workflows\Triggers\Trigger;

class ProcessWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $dataBus;
    protected $trigger;
    protected $log;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Model $model, DataBus $dataBus, Trigger $trigger, WorkflowLog $log)
    {
        $this->model = $model;
        $this->dataBus = $dataBus;
        $this->trigger = $trigger;
        $this->log = $log;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::beginTransaction();

        Log::channel("workflow")->debug("Je vais devoir appeler les enfants de mon trigger");
        Log::channel("workflow")->debug("TRIGGER => " . json_encode($this->trigger));
        Log::channel("workflow")->debug("ENFANTS => " . json_encode($this->trigger->children));

        $numChildren = 1;
        $totalChildren = count($this->trigger->children);
        $totalError = 0;


        try {
            $msgError = "-";
            foreach ($this->trigger->children as $task) {
                Log::channel("workflow")->debug("Traitement de mon enfant $numChildren en cours ...");
                $taskError = false;
                try {
                    $task->init($this->model, $this->dataBus, $this->log);
                    $task->execute();
                    $task->pastExecute();
                }catch (\Throwable $e) {
                    $totalError ++;
                    //$taskError = true;
                    Log::channel("workflow")->debug("Traitement de mon enfant $numChildren en erreur ==> " . $e->getMessage());
                    $msgError = $msgError . " " . $e->getMessage();
                } finally {
                    Log::channel("workflow")->debug("Traitement de mon enfant $numChildren FIN.");
                    Log::channel("workflow")->debug("Total erreur = $totalError, Total Children = $totalChildren");
                    Log::channel("workflow")->debug("Traitement de mon enfant $numChildren FIN.");

                    //Pas de traitement d'enfant, je dois arretér le process.
                    if( $totalError ==  $totalChildren ){
                        throw new \Exception("Aucun enfant n'a répondu aux conditions : $msgError");
                    }
                    $numChildren++;
                }

            }
        } catch (\Throwable $e) {
            //DB::rollBack();
            $this->log->setError($e->getMessage(), $this->dataBus);
            $this->log->createTaskLogsFromMemory();
            //dd($e);
        }


/*        try {
            foreach ($this->trigger->children as $task) {
                $task->init($this->model, $this->dataBus, $this->log);
                $task->execute();
                $task->pastExecute();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->log->setError($e->getMessage(), $this->dataBus);
            $this->log->createTaskLogsFromMemory();
            //dd($e);
        }*/

        $this->log->finish();
        DB::commit();
    }
}
