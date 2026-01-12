<?php
// controllers/ActivityLogger.php

function activityLogOnce(mysqli $conn, int $userId, string $onceKey, string $eventType, string $eventName, ?string $testCode = null, array $detail = []): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    if (!isset($_SESSION['_activity_once']) || !is_array($_SESSION['_activity_once'])) {
        $_SESSION['_activity_once'] = [];
    }
    if (!empty($_SESSION['_activity_once'][$onceKey])) return;

    $_SESSION['_activity_once'][$onceKey] = 1;
    activityLog($conn, $userId, $eventType, $eventName, $testCode, $detail);
}

function activityLog(mysqli $conn, int $userId, string $eventType, string $eventName, ?string $testCode = null, array $detail = []): void
{
    $eventType = substr(trim($eventType), 0, 30);
    $eventName = substr(trim($eventName), 0, 80);
    $testCode  = $testCode !== null ? substr(trim($testCode), 0, 20) : null;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($ua !== null) $ua = substr($ua, 0, 255);

    $sid = session_id();
    if ($sid !== '') $sid = substr($sid, 0, 128); else $sid = null;

    $detailJson = null;
    if (!empty($detail)) {
        $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);
        if ($detailJson !== null && strlen($detailJson) > 12000) {
            $detailJson = substr($detailJson, 0, 12000);
        }
    }

    $sql = "INSERT INTO user_activity_logs
            (user_id, event_time, event_type, event_name, test_code, detail_json, ip, user_agent, session_id)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isssssss", $userId, $eventType, $eventName, $testCode, $detailJson, $ip, $ua, $sid);
        $stmt->execute();
        $stmt->close();
    }
}
