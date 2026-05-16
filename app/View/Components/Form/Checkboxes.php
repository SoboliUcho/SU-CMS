<?php

namespace App\View\Components\Form;

use App\View\Components\Component;

class Checkboxes extends Component
{
    public function render(): string
    {   
        $checkboxesHtml = '';
        foreach ($this->data['options'] as $optionValue => $optionLabel) {
            $checkboxesHtml .= view("components/form/checkbox", [
                'name' => $this->data['name'],
                'option' => $optionValue,
                'option_name' => $optionLabel,
                'checked' => in_array($optionValue, (array)$this->data['value']) ? 'checked' : '',
            ]);
        }
        $this->data['checkboxes'] = $checkboxesHtml;
        return $this->view('form/checkboxes', $this->data);
    }
}
