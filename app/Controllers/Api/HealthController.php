<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Http\Response;

class HealthController extends Controller
{
    public function index()
    {
        return new Response(json_encode([
            'status' => 'ok'
        ]), 200, ['Content-Type' => 'application/json']);
    }
}
