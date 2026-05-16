<?php
// phpinfo();
// die();
require_once __DIR__ . '/../core/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (($_SERVER['SERVER_PORT'] ?? null) == 443);

	session_set_cookie_params([
		'lifetime' => 0,
		'path' => '/',
		'secure' => $isHttps,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	session_start();
}

require __DIR__ . '/../core/Autoload.php';

use Core\Http\Request;

$app = require __DIR__ . '/../app/Bootstrap.php';

$request = Request::capture();

$response = $app->handle($request);

$response->send();
