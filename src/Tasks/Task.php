<?php

namespace the42coders\Workflows\Tasks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use the42coders\Workflows\DataBuses\DataBus;
use the42coders\Workflows\DataBuses\DataBussable;
use the42coders\Workflows\Fields\Fieldable;
use the42coders\Workflows\Loggers\TaskLog;
use the42coders\Workflows\Loggers\WorkflowLog;

class Task extends Model implements TaskInterface
{
    use DataBussable, Fieldable;

    protected $table = 'tasks';

    public $family = 'task';

    public static $icon = '<i class="fas fa-question"></i>';

    public $dataBus = null;
    public $model = null;
    public $workflowLog = null;

    protected $fillable = [
        'workflow_id',
        'parent_id',
        'type',
        'name',
        'data',
        'node_id',
        'pos_x',
        'pos_y',
    ];

    public static $commonFields = [
        'Description' => 'description',
    ];

    protected $casts = [
        'data_fields' => 'array',
    ];

    public static $fields = [];
    public static $output = [];

    public function __construct(array $attributes = [])
    {
        $this->table = config('workflows.db_prefix').$this->table;
        parent::__construct($attributes);
    }

    public function workflow()
    {
        return $this->belongsTo('the42coders\Workflows\Workflow');
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function parentable()
    {
        return $this->morphTo();
    }

    public function children()
    {
        return $this->morphMany('the42coders\Workflows\Tasks\Task', 'parentable');
    }

    /**
     * Return Collection of models by type.
     *
     * @param  array  $attributes
     * @param  null  $connection
     * @return \App\Models\Action
     */
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $entryClassName = '\\'.Arr::get((array) $attributes, 'type');

        if (class_exists($entryClassName)
            && is_subclass_of($entryClassName, self::class)
        ) {
            $model = new $entryClassName();
        } else {
            $model = $this->newInstance();
        }

        $model->exists = true;
        $model->setRawAttributes((array) $attributes, true);
        $model->setConnection($connection ?: $this->connection);

        return $model;
    }

    /**
     * Check if all Conditions for this Action pass.
     *
     * @param  Model  $model
     * @return bool
     */
    public function checkConditions(Model $model, DataBus $data): bool
    {
        //TODO: This needs to get smoother :(

        if (empty($this->conditions)) {
            return true;
        }

        //Ajout da possibilité d'utiliser le AND et le OR dans les conditions.
        $conditions = json_decode($this->conditions);
        $typeCondition = $conditions->condition;
        Log::channel("workflow")->debug("Traitement de mes règles de conditions " . json_encode( $conditions, JSON_PRETTY_PRINT ));
        Log::channel("workflow")->debug("Traitement de mes règles de conditions  => TASK" . json_encode( $this, JSON_PRETTY_PRINT ));
        foreach ($conditions->rules as $rule) {
            $ruleDetails = explode('-', $rule->id);
            $DataBus = $ruleDetails[0];
            $field = $ruleDetails[1];

            $result = config('workflows.data_resources')[$DataBus]::checkCondition($this, $data, $field, $rule->operator, $rule->value);

            if (! $result && $typeCondition == "AND") {
                Log::channel("workflow")->debug("AND Condition non respecté => Fin du traitement ");
                throw new \Exception('The Condition for Task '.$this->name.' ['.$this->data_fields['description']['value'].'] with the field '.$rule->field.' '.$rule->operator.' '.$rule->value.' failed.');
            }else if ( $result && $typeCondition == "OR" ) {
                Log::channel("workflow")->debug("OR Condition respectée => Je continue ");

                return true;
            }
        }
        Log::channel("workflow")->debug("$typeCondition " .
            ($typeCondition == "AND" ? "Condition respectée je continue" : "Condition non respectée fin du traitement") );
        return $typeCondition == "AND";
    }

    public function init(Model $model, DataBus $data, WorkflowLog $log)
    {
        Log::channel("workflow")->debug("Initiatilisation de ma tâche : ". json_encode($this));

        $this->model = $model;
        $this->dataBus = $data;
        $this->workflowLog = $log;
        $this->workflowLog->addTaskLog($this->workflowLog->id, $this->id, $this->name, TaskLog::$STATUS_START, json_encode($this->data_fields), \Illuminate\Support\Carbon::now());

        $this->log = TaskLog::createHelper($log->id, $this->id, $this->name);

        $this->dataBus->collectData($model, $this->data_fields);


        try {
            $this->checkConditions($this->model, $this->dataBus);
        } catch (ConditionFailedError $e) {
            throw $e;
        }
    }

    /**
     * Execute the Action return Value tells you about the success.
     *
     * @return bool
     */
    public function execute(): void
    {
    }

    public function pastExecute()
    {
        if (empty($this->children)) {
            return 'nothing to do'; //TODO: TASK IS FINISHED
        }
        $this->log->finish();
        $this->workflowLog->updateTaskLog($this->id, '', TaskLog::$STATUS_FINISHED, \Illuminate\Support\Carbon::now());

        Log::channel("workflow")->debug("TASK : Je vais devoir appeler les enfants de mon trigger");
        Log::channel("workflow")->debug("TASK :  => " . json_encode($this));
        Log::channel("workflow")->debug("TASK : ENFANTS => " . json_encode($this->children));

        $numChildren = 1;
        $totalChildren = count($this->children);
        $totalErrorCheck = 0;
        $msgError = "-";
        $taskConditionsError = false;
        foreach ($this->children as $child) {
            Log::channel("workflow")->debug("TASK : Je vais initialiser mon enfant $numChildren/$totalChildren");
            $taskConditionsError = false;

            try {
                $child->init($this->model, $this->dataBus, $this->workflowLog);
            }catch (\Throwable $e ){
                Log::channel("workflow")->debug("Erreur lors de l'initialisation de ma tache $numChildren/$totalChildren : " . $e->getMessage());
                $msgError = $msgError . " $numChildren/$totalChildren " . $e->getMessage() . " - ";
                $taskConditionsError = true;
                $totalErrorCheck++;
            } finally {
                $numChildren++;
            }


            try {
                if( $totalErrorCheck == $totalChildren ){
                    throw new \Exception( $msgError);
                }
                $child->execute();
            } catch (\Throwable $e) {
                $child->workflowLog->updateTaskLog($child->id, $e->getMessage(), TaskLog::$STATUS_ERROR, \Illuminate\Support\Carbon::now());
                throw $e;
            }
            if(!$taskConditionsError){
                $child->pastExecute();
            }

        }

//        foreach ($this->children as $child) {
//            $child->init($this->model, $this->dataBus, $this->workflowLog);
//            try {
//                $child->execute();
//            } catch (\Throwable $e) {
//                $child->workflowLog->updateTaskLog($child->id, $e->getMessage(), TaskLog::$STATUS_ERROR, \Illuminate\Support\Carbon::now());
//                throw $e;
//            }
//            $child->pastExecute();
//        }
    }

    public function getSettings()
    {
        return view('workflows::layouts.settings_overlay', [
            'element' => $this,
        ]);
    }

    public static function getTranslation(): string
    {
        return __(static::getTranslationKey());
    }

    public static function getTranslationKey(): string
    {
        $className = (new \ReflectionClass(new static))->getShortName();

        return "workflows::workflows.Elements.{$className}";
    }
}
