<?php
require_once __DIR__ . '/auth.php';
requireRole('admin');

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/../public/admin-citas.html');
exit;
