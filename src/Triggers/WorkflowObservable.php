<?php

namespace the42coders\Workflows\Triggers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use the42coders\Workflows\DataBuses\DataBus;
use the42coders\Workflows\DataBuses\ModelResource;

trait WorkflowObservable
{
    public static function bootWorkflowObservable()
    {
        static::retrieved(function (Model $model) {
            self::startWorkflows($model, 'retrieved');
        });
        static::creating(function (Model $model) {
            self::startWorkflows($model, 'creating');
        });
        static::created(function (Model $model) {
            self::startWorkflows($model, 'created');
        });
        static::updating(function (Model $model) {
            self::startWorkflows($model, 'updating');
        });
        static::updated(function (Model $model) {
            self::startWorkflows($model, 'updated');
        });
        static::saving(function (Model $model) {
            self::startWorkflows($model, 'saving');
        });
        static::saved(function (Model $model) {
            self::startWorkflows($model, 'saved');
        });
        static::deleting(function (Model $model) {
            self::startWorkflows($model, 'deleting');
        });
        static::deleted(function (Model $model) {
            self::startWorkflows($model, 'deleted');
        });
        //TODO: check why they are not available here
        /*static::restoring(function (Model $model) {
           self::startWorkflows($model, 'restoring');
        });
        static::restored(function (Model $model) {
           self::startWorkflows($model, 'restored');
        });
        static::forceDeleted(function (Model $model) {
            self::startWorkflows($model, 'forceDeleted');
        });*/
    }

    public static function getRegisteredTriggers(string $class, string $event)
    {
        $class_array = explode('\\', $class);

        $className = $class_array[count($class_array) - 1];

        return Trigger::where('type', 'the42coders\Workflows\Triggers\ObserverTrigger')
            ->where('data_fields->class->value', 'like', '%'.$className.'%')
            ->where('data_fields->event->value', $event)
            ->get();
    }

    public static function startWorkflows(Model $model, string $event)
    {
        if (! in_array($event, config('workflows.triggers.Observers.events'))) {
            return false;
        }

        foreach (self::getRegisteredTriggers(get_class($model), $event) as $trigger) {
            Log::channel("workflow")->debug("Exécution d'un trigger pour " . get_class($model) . " et " . $event);
            //Log::channel("workflow")->debug(json_encode($trigger, JSON_PRETTY_PRINT));
            $tabTrigger = $trigger;
            //$monModel = $model;
            try {
                //$trigger->start($model, $trigger);
                if( self::checkConditions($model, $trigger )){
                    Log::channel("workflow")->debug("Trigger Condition check OK => lancement du workflow");
                    //Log::channel("workflow")->debug(json_encode($trigger, JSON_PRETTY_PRINT));
                    //Log::channel("workflow")->debug(json_encode($tabTrigger, JSON_PRETTY_PRINT));
                    $trigger->start($model);
                }else{
                    Log::channel("workflow")->debug("Les conditions du trigger pour le workflow ne sont pas respectées.");
                }
            }catch (\Exception $exception){
                Log::channel("workflow")->debug("Les conditions du trigger pour le workflow ".
                    " ne sont pas respectées. " . $exception->getMessage());
            }


        }
    }

    /**
     * Check if all Conditions for this Action pass.
     *
     * @param  Model  $model
     * @return bool
     */
    public static function checkConditions(Model $model, $trigger): bool
    {
        //TODO: This needs to get smoother :(


        if (empty($trigger->conditions)) {
            return true;
        }

        //Ajout da possibilité d'utiliser le AND et le OR dans les conditions.
        $conditions = json_decode($trigger->conditions);
        $typeCondition = $conditions->condition;
        Log::channel("workflow")->debug("TRIGGER Traitement de mes règles de conditions " . json_encode( $conditions, JSON_PRETTY_PRINT ));
        foreach ($conditions->rules as $rule) {
            $ruleDetails = explode('-', $rule->id);
            $DataBus = $ruleDetails[0];
            $field = $ruleDetails[1];
            //$model->model = $model;

            $result = ModelResource::checkCondition($model, null, $field, $rule->operator, $rule->value);

            if (! $result && $typeCondition == "AND") {
                Log::channel("workflow")->debug("AND Condition non respecté => Fin du traitement ");
                throw new \Exception('The Condition for Trigger  with the field '.$rule->field.' '.$rule->operator.' '.$rule->value.' failed.');
            }else if ( $result && $typeCondition == "OR" ) {
                Log::channel("workflow")->debug("OR Condition respectée => Je continue ");

                return true;
            }
        }
        Log::channel("workflow")->debug("TRIGGER $typeCondition " .
            ($typeCondition == "AND" ? "Condition respectée je continue" : "Condition non respectée fin du traitement") );
        return $typeCondition == "AND";
    }
}
