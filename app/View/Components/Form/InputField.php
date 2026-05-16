<?php

namespace App\View\Components\Form;

class InputField extends Field
{
    public function render(): string
    {   
        // logger('debug', "Rendering InputField with config", ['config' => $this->field_congfigures]);
        return (new Input($this->field_congfigures))->render();
    }
}
