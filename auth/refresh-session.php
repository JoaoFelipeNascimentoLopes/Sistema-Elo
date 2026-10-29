<?php
require_once __DIR__ . '/access.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

appValidateCsrf();
$user = appCurrentUser(true);
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'expiresAt' => (int) $user['sessionExpiresAt'],
    'mustChangePassword' => (int) $user['mustChangePassword'] === 1,
], JSON_THROW_ON_ERROR);
