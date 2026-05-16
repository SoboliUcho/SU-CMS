<?php

namespace App\View\Components;

abstract class Component
{
    protected array $data = [];
    protected ?string $slot = null;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function withSlot(string $slot): static
    {
        $this->slot = $slot;
        return $this;
    }

    abstract public function render(): string;

    protected function view(string $path, array $data = []): string
    {
        return view("components/{$path}", array_merge($this->data, $data));
    }   
}
