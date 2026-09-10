<?php
require_once __DIR__ . '/auth.php';
requireRole('professional');
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/../public/profesional-dashboard.html');
exit;
