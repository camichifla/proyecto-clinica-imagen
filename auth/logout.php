<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// POST-only: logout is a form submission, not a plain link, so it cannot
// be triggered by an <img> tag or a bare GET request.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_check($_POST['csrf'] ?? null);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: index.php');
exit;
