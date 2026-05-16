<?php

namespace App\View\Components\Form;

class SelectField extends Field
{
    public function render(): string
    {   
        // logger('debug', "Rendering CheckboxField with config", ['config' => $this->field_congfigures]);
        return (new Select($this->field_congfigures))->render();
    }
}