<?php

namespace App\View\Components\Form;

class CheckboxesField extends Field
{
    public function render(): string
    {
        return (new Checkboxes($this->field_congfigures))->render();
    }
}