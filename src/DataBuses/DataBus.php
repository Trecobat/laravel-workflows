<?php

namespace the42coders\Workflows\DataBuses;

use Illuminate\Database\Eloquent\Model;

class DataBus
{
    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collectData(Model $model, $fields): void
    {
        foreach ($fields as $name => $field) {
            //TODO: Quick fix to remove description but handle/filter this better in the future :(

            if ($name === 'description') {
                continue;
            }

            $field_value = $field['value'] ?? '';

            if ($name === 'file' && ! $field_value) {
                continue;
            }

            $className = $field['type'] ?? ValueResource::class;
            $resource = new $className();

            if(is_array($field_value)){
                $this->data[$name] = $field_value;
            }else{
                $this->data[$name] = $resource->getData($name, $field_value, $model, $this);
            }

        }
    }

    public function toString()
    {
        $output = '';

        foreach ($this->data as $line) {
            $output .= $line.'\n';
        }

        return $output;
    }

    public function get(string $key, string $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function setOutput(string $key, $value)
    {
        $this->data[$this->get($key, $key)] = $value;
    }

    public function setOutputArray(string $key, string $value)
    {
        $this->data[$this->get($key, $key)][] = $value;
    }
}
