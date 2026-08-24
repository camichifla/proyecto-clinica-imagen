<?php
require_once __DIR__ . '/auth.php';
requireRole('patient');

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/../public/paciente-dashboard.html');
exit;
