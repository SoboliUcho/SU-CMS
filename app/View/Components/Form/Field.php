<?php

namespace App\View\Components\Form;

abstract class Field
{
    protected string $name;
    protected array $field_congfigures = [];

    public function __construct(array $field_congfig)
    {
        if (empty($field_congfig['name'])) {
            logger('error', "Field configuration missing 'name'", ['field_config' => $field_congfig]);
            throw new \InvalidArgumentException("Field configuration must include a 'name' key.");
        }
        $this->name = $field_congfig["name"];
        $this->field_congfigures = $field_congfig;
    }

    // public function rules(array $rules): static
    // {
    //     $this->rules = $rules;
    //     return $this;
    // }

    // public function validate($value): bool
    // {
    //     foreach ($this->rules as $rule) {
    //         if ($rule === 'required' && (is_null($value) || $value === '' )) {
    //             $this->error = 'Pole je povinné';
    //             return false;
    //         }

    //         if (is_string($value) && str_starts_with($rule, 'min:')) {
    //             $min = (int) explode(':', $rule)[1];
    //             if (mb_strlen($value) < $min) {
    //                 $this->error = "Minimálně $min znaků";
    //                 return false;
    //             }
    //         }
    //     }
    //     return true;
    // }

    // public function error(): ?string
    // {
    //     return $this->error;
    // }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function render(): string;
}
