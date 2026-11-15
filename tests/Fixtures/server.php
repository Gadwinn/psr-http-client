<?php

/**
 * Router script of the PHP built-in server used by the tests.
 * Every route echoes the received request as JSON.
 */

declare(strict_types=1);

$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/cookies') {
    header('Set-Cookie: a=1', false);
    header('Set-Cookie: b=2', false);
} elseif ($path === '/redirect') {
    header('Location: /echo', true, 302);
} elseif ($path === '/status') {
    http_response_code((int) ($_GET['code'] ?? 200));
}

header('Content-Type: application/json');
header('X-Request-Count: 1');

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'target' => $_SERVER['REQUEST_URI'],
    'protocol' => $_SERVER['SERVER_PROTOCOL'],
    'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
    'body' => file_get_contents('php://input'),
]);
