<?php

namespace App\View\Components\Form;

use App\Services\FormDefinitionService;

class Form
{
    protected string $action;
    protected string $method;
    protected string $class = '';
    protected array $fields = [];
    protected array $errors = [];
    protected array $form_data = [];
    protected string $name;
    protected bool $ajax = false;
    protected bool $submit_on_change = false;
    protected string $ajax_script = "";
    protected int $debounce_ms = 500;

    public function __construct(string $name, mixed $form_data = [])
    {
        $this->name = $name;
        $this->form_data = is_array($form_data) ? $form_data : [];
        $this->setup();
    }

    protected function setup(): void
    {
        $baseDefinition = (new FormDefinitionService())->load($this->name) ?? config("forms/{$this->name}", [
            'action' => '',
            'method' => 'POST',
            'fields' => []
        ]);

        if (!is_array($baseDefinition)) {
            $baseDefinition = [
                'action' => '',
                'method' => 'POST',
                'fields' => []
            ];
        }

        if (!empty($this->form_data)) {
            $this->form_data = array_replace_recursive($baseDefinition, $this->form_data);
        } else {
            $this->form_data = $baseDefinition;
        }

        $this->action = $this->form_data['action'] ?? '';
        $this->method = strtoupper((string)($this->form_data['method'] ?? 'POST'));
        $this->class = (string)($this->form_data['class'] ?? '');
        $this->ajax = (bool)($this->form_data['ajax'] ?? false);
        $this->submit_on_change = (bool)($this->form_data['submit_on_change'] ?? false);
        $this->debounce_ms = (int)($this->form_data['debounce_ms'] ?? 500);

        foreach ($this->form_data['fields'] ?? [] as $fieldConfig) {
            $field = $this->make_field($fieldConfig);
            $this->add($field);
        }

        if ($this->ajax) {
            $this->ajax_script = view("components/form/ajax_script", [
                'form_id' => $this->name . '-form',
                'submit_on_change' => $this->submit_on_change ? '1' : '0',
                'debounce_ms' => (string)$this->debounce_ms,
            ]);
        }
    }

    public function make_field(array $fieldConfig): Field
    {

        $inputTypes = ['text', 'button', 'checkbox', 'color', 'date', 'datetime-local', 'email', 'file', 'hidden', 'image', 'month', 'number', 'password', 'radio', 'range', 'reset', 'search', 'submit', 'tel', 'time', 'url', 'week'];
        if (in_array($fieldConfig['type'] ?? 'text', $inputTypes)) {
            return new InputField($fieldConfig);
        }
        return match ($fieldConfig['type'] ?? 'text') {
            'textarea' => new TextareaField($fieldConfig),
            'select' => new SelectField($fieldConfig),
            'checkboxes' => new CheckboxesField($fieldConfig),
            'radios' => new RadiosField($fieldConfig),
            default => throw new \InvalidArgumentException("Neznamy typ pole: " . ($fieldConfig['type'] ?? 'undefined')),
        };
    }

    public function add(Field $field): static
    {
        $this->fields[$field->name()] = $field;
        return $this;
    }

    public function render(): string
    {
        $html = '';
        foreach ($this->fields as $field) {
            $html .= $field->render();
            // logger('debug', "Render field: {$field->name()}");
        }
        $form = view("components/form/form", [
            'action' => $this->action,
            'method' => $this->method,
            'name' => $this->name,
            'class' => $this->class,
            'fields' => $html,
            'ajax' => $this->ajax ? '1' : '0',
            'submit_on_change' => $this->submit_on_change ? '1' : '0',
            'debounce_ms' => (string)$this->debounce_ms,
            'ajax_script' => $this->ajax_script,
        ]);
        return $form;
    }
}
