<?php

namespace Core;

use Core\Http\Response;

class Controller
{
    protected function view(string $view, array $data = [])
    {
        $body = View::render($view, $data);
        return new Response($body, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
