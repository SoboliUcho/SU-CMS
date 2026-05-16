<?php

namespace App\Services;

class FormValidator
{
    public function validate(array $definition, array $payload): array
    {
        $errors = [];
        $values = [];

        foreach (($definition['fields'] ?? []) as $fieldKey => $fieldConfig) {
            if (!is_array($fieldConfig)) {
                continue;
            }

            $name = (string)($fieldConfig['name'] ?? $fieldKey);
            if ($name === '') {
                continue;
            }

            $type = strtolower((string)($fieldConfig['type'] ?? 'text'));
            $rules = is_array($fieldConfig['rules'] ?? null) ? $fieldConfig['rules'] : [];
            $value = $payload[$name] ?? null;
            $sanitized = $this->sanitize($value, $type);
            $fieldLabel = (string)($fieldConfig['label'] ?? $name);

            if ($this->hasOptions($fieldConfig) && !$this->isEmpty($sanitized)) {
                $optionsError = $this->validateOptions($sanitized, $fieldConfig, $fieldLabel);
                if ($optionsError !== null) {
                    $errors[$name] = $optionsError;
                    continue;
                }
            }

            foreach ($rules as $rule) {
                $ruleError = $this->applyRule((string)$rule, $sanitized, $fieldLabel);
                if ($ruleError !== null) {
                    $errors[$name] = $ruleError;
                    break;
                }
            }

            if (!isset($errors[$name])) {
                $values[$name] = $sanitized;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'values' => $values,
        ];
    }

    private function sanitize(mixed $value, string $type): mixed
    {
        if ($type === 'checkboxes') {
            $items = is_array($value) ? $value : ($value === null ? [] : [$value]);
            return array_values(array_filter(array_map(fn($item) => is_string($item) ? trim($item) : $item, $items), fn($item) => $item !== '' && $item !== null));
        }

        if (is_array($value)) {
            return array_map(fn($item) => is_string($item) ? trim($item) : $item, $value);
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function applyRule(string $rule, mixed $value, string $fieldLabel): ?string
    {
        $parts = explode(':', $rule, 2);
        $name = strtolower(trim($parts[0]));
        $param = $parts[1] ?? null;

        if ($name === 'required' && $this->isEmpty($value)) {
            return "Pole {$fieldLabel} je povinné.";
        }

        if ($name === 'email' && !$this->isEmpty($value) && !filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
            return "Pole {$fieldLabel} musí obsahovat validní e-mail.";
        }

        if ($name === 'min' && $param !== null && !$this->isEmpty($value)) {
            $min = (int)$param;
            if (is_array($value) && count($value) < $min) {
                return "Pole {$fieldLabel} musí mít alespoň {$min} položek.";
            }
            if (is_string($value) && mb_strlen($value) < $min) {
                return "Pole {$fieldLabel} musí mít alespoň {$min} znaků.";
            }
        }

        if ($name === 'max' && $param !== null && !$this->isEmpty($value)) {
            $max = (int)$param;
            if (is_array($value) && count($value) > $max) {
                return "Pole {$fieldLabel} může mít maximálně {$max} položek.";
            }
            if (is_string($value) && mb_strlen($value) > $max) {
                return "Pole {$fieldLabel} může mít maximálně {$max} znaků.";
            }
        }

        if ($name === 'in' && $param !== null && !$this->isEmpty($value)) {
            $allowed = array_map('trim', explode(',', $param));
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!in_array((string)$item, $allowed, true)) {
                        return "Pole {$fieldLabel} obsahuje nepovolenou hodnotu.";
                    }
                }
            } elseif (!in_array((string)$value, $allowed, true)) {
                return "Pole {$fieldLabel} obsahuje nepovolenou hodnotu.";
            }
        }

        return null;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return count($value) === 0;
        }
        return $value === null || $value === '';
    }

    private function hasOptions(array $fieldConfig): bool
    {
        return isset($fieldConfig['options']) && is_array($fieldConfig['options']) && !empty($fieldConfig['options']);
    }

    private function validateOptions(mixed $value, array $fieldConfig, string $fieldLabel): ?string
    {
        $options = $fieldConfig['options'] ?? [];
        if (!is_array($options)) {
            return null;
        }

        $allowed = array_unique(array_map('strval', array_merge(array_keys($options), array_values($options))));

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!in_array((string)$item, $allowed, true)) {
                    return "Pole {$fieldLabel} obsahuje nepovolenou hodnotu.";
                }
            }
            return null;
        }

        if (!in_array((string)$value, $allowed, true)) {
            return "Pole {$fieldLabel} obsahuje nepovolenou hodnotu.";
        }

        return null;
    }
}
