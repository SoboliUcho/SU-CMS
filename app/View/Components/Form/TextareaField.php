<?php

namespace App\View\Components\Form;

class TextareaField extends Field
{
    public function render(): string
    {   
        // logger('debug', "Rendering CheckboxField with config", ['config' => $this->field_congfigures]);
        return (new Textarea($this->field_congfigures))->render();
    }
}