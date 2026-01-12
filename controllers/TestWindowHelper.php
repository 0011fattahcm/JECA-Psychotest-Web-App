<?php
// controllers/TestWindowHelper.php

function getTestWindowSetting(mysqli $conn): array
{
    $sql = "SELECT is_open, open_date, start_time, end_time
            FROM test_window_settings
            WHERE id = 1
            LIMIT 1";
    $res = $conn->query($sql);
    $row = $res ? $res->fetch_assoc() : null;

    return $row ?: [
        'is_open'    => 0,
        'open_date'  => null,
        'start_time' => null,
        'end_time'   => null,
    ];
}

function isTestWindowOpenNow(mysqli $conn): bool
{
    $w = getTestWindowSetting($conn);

    if ((int)($w['is_open'] ?? 0) !== 1) return false;

    $date  = trim((string)($w['open_date'] ?? ''));
    $start = trim((string)($w['start_time'] ?? ''));
    $end   = trim((string)($w['end_time'] ?? ''));

    if ($date === '' || $start === '' || $end === '') return false;

    // Normalisasi TIME: HH:MM => HH:MM:SS
    $start = (strlen($start) === 5) ? $start . ':00' : $start;
    $end   = (strlen($end) === 5)   ? $end   . ':00' : $end;

    $tz = new DateTimeZone(date_default_timezone_get());
    $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $date.' '.$start, $tz);
    $endDt   = DateTime::createFromFormat('Y-m-d H:i:s', $date.' '.$end, $tz);

    if (!$startDt || !$endDt) return false;

    // Jika Anda ingin melarang lewat tengah malam, cukup return false saat end <= start.
    // Kalau mau boleh lewat tengah malam, pakai ini:
    if ($endDt <= $startDt) $endDt->modify('+1 day');

    $now = new DateTime('now', $tz);
    return ($now >= $startDt && $now <= $endDt);
}
