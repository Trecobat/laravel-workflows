<?php

namespace the42coders\Workflows\DataBuses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ModelResource implements Resource
{
    public function getData(string $name, string $value, Model $model, DataBus $dataBus)
    {
        return $model->{$value};
    }

    public static function getValues(Model $element, $value, $field_name)
    {
        $classes = [];
        foreach ($element->workflow->triggers as $trigger) {
            if (isset($trigger->data_fields['class']['value'])) {
                $classes[] = $trigger->data_fields['class']['value'];
            }
        }

        $variables = [];
        foreach ($classes as $class) {
            $model = new $class;
            foreach (Schema::getColumnListing($model->getTable()) as $item) {
                $variables[$class.'->'.$item] = $item;
            }
        }

        return $variables;
    }

    public static function checkCondition(Model $element, DataBus $dataBus, string $field, string $operator, string $value)
    {
        Log::channel("workflow")->debug("Je vais tester ma condition sur mon model");
        Log::channel("workflow")->debug("==> Condition : $field $operator $value");
        Log::channel("workflow")->debug("==> Valeur de mon model : " . $element->model->{$field});
        Log::channel("workflow")->debug("==> Historique de mon model : " . json_encode($element->model->histories->last(), JSON_PRETTY_PRINT) );

        //$test = true;
        switch ($operator) {
            case 'equal':
                $test = $element->model->{$field} == $value;
                break;
            case 'not_equal':
                $test = $element->model->{$field} != $value;
                break;
            case 'change':
                if ($value == '' ||  $value == 'O' || $value == 'OUI' || $value == 'o' || $value == 'oui' || $value == '1'){
                    Log::channel("workflow")->debug("==> Test si mon dernier historique contient mon champs $field" );
                    $test = false;
                    foreach ( $element->model->histories->last()->meta as $modif){
                        Log::channel("workflow")->debug("== ==> Test de la modification : " . json_encode($modif,JSON_PRETTY_PRINT) );
                        Log::channel("workflow")->debug("== ==> Test  " . $modif['key'] . " et " . $field );
                        if($modif['key'] == $field ){
                            $test = true;
                            break;
                        }
                    }
                }elseif ($value == '-' ||  $value == 'N' || $value == 'NON' || $value == 'n' || $value == 'non' || $value == '0'){
                    Log::channel("workflow")->debug("==> Test si mon dernier historique ne contient pas mon champs " );


                }else{
                    Log::channel("workflow")->warning("La valeur $value n'est pas permise pour le traitement de ma condition $operator");
                }
                break;

            default:
                $test = true;
        }

        Log::channel("workflow")->debug("==|==>Résultat de mon test : " . ($test ? "OUI" : "NON"));
        return $test;
    }

    public static function loadResourceIntelligence(Model $element, $value, $field_name)
    {
        $variables = self::getValues($element, $value, $field_name);

        return view('workflows::fields.data_bus_resource_field', [
            'fields' => $variables,
            'value' => $value,
            'field' => $field_name,
        ])->render();
    }
}
