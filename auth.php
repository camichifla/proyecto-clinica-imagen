<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['CI']) && isset($_SESSION['role']);
}

function getUserType(): ?string {
    return $_SESSION['role'] ?? null;
}

function getUserId(): ?int {
    return isset($_SESSION['CI']) ? (int)$_SESSION['CI'] : null;
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect . '?error=session_required');
        exit;
    }
}

function requireRole(string $role, string $redirect = '/login.php'): void {
    requireLogin($redirect);
    if (getUserType() !== $role) {
        http_response_code(403);
        header('Location: ' . $redirect . '?error=access_denied');
        exit;
    }
}

function hasRole(string ...$roles): bool {
    return isLoggedIn() && in_array(getUserType(), $roles, true);
}