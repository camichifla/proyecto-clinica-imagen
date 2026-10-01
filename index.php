<?php
require_once __DIR__ . '/includes/session_bootstrap.php';
require_once __DIR__ . '/includes/menu.php';

$usuario = usuario_actual();
require __DIR__ . '/views/index.php';
