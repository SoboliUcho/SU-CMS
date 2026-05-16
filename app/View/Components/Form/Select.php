<?php

namespace App\View\Components\Form;

use App\View\Components\Component;

class Select extends Component
{
    public function render(): string
    {   
        $optionValues = "";
        foreach ($this->data['options'] ?? [] as $optionValue => $optionLabel) {
            $optionValues .= view('components/form/option', [
                'value' => $optionValue,
                'label' => $optionLabel,
                'selected' => (string)$optionValue === (string)($this->data['value'] ?? '')
            ]);
        }
        // logger('debug', "Rendering Select component with data", ['data' => $this->data, 'optionValues' => $optionValues]);

        $this->data['optionValues'] = $optionValues;
        return $this->view('form/select', $this->data);
    }
}
