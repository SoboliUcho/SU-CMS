<?php

namespace App\Services;

class HealthService
{
    public function status(): array
    {
        return ['status' => 'ok'];
    }
}
