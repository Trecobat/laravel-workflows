<?php

namespace the42coders\Workflows\DataBuses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DataBusResource implements Resource
{
    public function getData(string $name, string $value, Model $model, DataBus $dataBus)
    {
        return $dataBus->data[$dataBus->data[$value]];
    }

    public static function checkCondition(Model $element, DataBus $dataBus, string $field, string $operator, string $value)
    {
        Log::channel("workflow")->debug("====> Model" . json_encode( $element, JSON_PRETTY_PRINT ));
        Log::channel("workflow")->debug("====> DataBus" . json_encode( $dataBus, JSON_PRETTY_PRINT ));
        Log::channel("workflow")->debug("====> field" . json_encode( $field, JSON_PRETTY_PRINT ));
        Log::channel("workflow")->debug("====> operator" . json_encode( $operator, JSON_PRETTY_PRINT ));
        Log::channel("workflow")->debug("====> value" . json_encode( $value, JSON_PRETTY_PRINT ));
        switch ($operator) {
            case 'equal':
                return $dataBus->data[$dataBus->data[$field]] == $value;
            case 'not_equal':
                return $dataBus->data[$dataBus->data[$field]] != $value;
            default:
                return true;
        }
    }

    public static function getValues(Model $element, $value, $field)
    {
        return $element->getParentDataBusKeys();
    }

    public static function loadResourceIntelligence(Model $element, $value, $field)
    {
        $fields = self::getValues($element, $value, $field);

        return view('workflows::fields.data_bus_resource_field', [
            'fields' => $fields,
            'value' => $value,
            'field' => $field,
        ])->render();
    }
}
