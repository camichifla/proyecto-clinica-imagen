<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth.php';
requireRole('admin');

echo json_encode([
    'success' => true,
    'user' => [
        'name' => $_SESSION['name'] ?? '',
        'surname' => $_SESSION['surname'] ?? '',
    ],
]);