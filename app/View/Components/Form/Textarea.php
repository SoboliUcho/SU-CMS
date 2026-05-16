<?php

namespace App\View\Components\Form;

use App\View\Components\Component;

class Textarea extends Component
{
    public function render(): string
    {
        // logger('debug', "Rendering Input component with data", ['data' => $this->data]);
        return $this->view('form/textarea', $this->data);
    }
}
