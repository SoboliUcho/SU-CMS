<?php
use Core\Config;
use Core\Logger;
use App\View\Components\Form\Form;

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $view, $data = []) {
        // Logger::getInstance()->debug("Rendering view: $view", ['data' => $data]);
        return \Core\View::render($view, $data);
    }
}

if (!function_exists('logger')) {
    function logger(string $level, string $message, array $context = []) {
        return Logger::getInstance()->$level($message, $context);
    }
}

if (!function_exists('form')) {
    function form(string $name, mixed $data = []) {
        $form = new Form($name, $data);
        return $form->render();
    }
}