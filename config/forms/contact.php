<?php

return [
    'action' => '/contact/submit',
    'method' => 'POST',
    'name' => 'contact',
    'ajax' => true,
    'submit_on_change' => true,
    'fields' => [
        'name' => [
            'label' => 'Your Name',
            'name' => 'your_name',
            'type' => 'text',
            'value' => '',
            'error' => null,
            'rules' => ['required', 'min:3']
        ],
        'email' => [
            'label' => 'Your Email',
            'type' => 'email',
            'value' => '',
            'error' => null,
            'name' => 'your_email',
        ],
        'message' => [
            'label' => 'Your Message',
            'type' => 'textarea',
            'value' => '',
            'error' => null,
            'name' => 'your_message',
        ],
        'select' => [
            'label' => 'Select Option',
            'type' => 'select',
            'options' => [
                '' => 'Please select',
                'option1' => 'Option 1',
                'option2' => 'Option 2'
            ],
            'value' => 'otpion1',
            'error' => null,
            'name' => 'select_option',
        ],
        'checkbox' => [
            'label' => 'Accept Terms',
            'type' => 'checkboxes',
            'value' => '1',
            'error' => null,
            'name' => 'accept_terms',
            'options' => [
                '1' => 'I accept the terms and conditions',
                '2' => 'I want to receive newsletters',
            ]
        ],
        'radios' => [
            'label' => 'Choose an option',
            'type' => 'radios',
            'options' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2'
            ],
            'value' => '',
            'error' => null,
            'name' => 'radio_option',
        ],
        'button' => [
            'label' => 'Submit',
            'type' => 'submit',
            'value' => 'Submit',
            'error' => null,
            'name' => 'submit_button',
        ],
    ]
];