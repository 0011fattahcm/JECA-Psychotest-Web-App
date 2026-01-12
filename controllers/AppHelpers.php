<?php
// controllers/AppHelpers.php

if (!function_exists('sendNoCacheHeaders')) {
    function sendNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['_csrf_admin'])) {
            $_SESSION['_csrf_admin'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf_admin'];
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $sess = (string)($_SESSION['_csrf_admin'] ?? '');
        if ($sess === '' || $token === '') return false;
        return hash_equals($sess, $token);
    }
}

function kraeplinIntervalSecondsFromSettings($settings): int {
    $s = (int)($settings['interval_seconds'] ?? 10);
    $allowed = [5,10,15,20,25,30];
    if (!in_array($s, $allowed, true)) $s = 10;
    return $s;
}

