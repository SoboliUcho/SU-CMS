<?php

namespace App\View\Components\Form;

class RadiosField extends Field
{
    public function render(): string
    {
        return (new Radios($this->field_congfigures))->render();
    }
}