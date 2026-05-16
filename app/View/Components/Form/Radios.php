<?php

namespace App\View\Components\Form;

use App\View\Components\Component;

class Radios extends Component
{
    public function render(): string
    {   
        $radiosHtml = '';
        foreach ($this->data['options'] as $optionValue => $optionLabel) {
            $radiosHtml .= view("components/form/radio", [
                'type' => 'radio',
                'name' => $this->data['name'],
                'option' => $optionValue,
                'option_name' => $optionLabel,
                'checked' => in_array($optionValue, (array)$this->data['value']) ? 'checked' : '',
            ]);
        }
        $this->data['radio_selectors'] = $radiosHtml;
        return $this->view('form/radios', $this->data);
    }
}
