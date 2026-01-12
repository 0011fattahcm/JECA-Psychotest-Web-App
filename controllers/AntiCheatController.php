<?php
// =============================
// ANTI CHEAT – AJAX ENDPOINT
// Policy: strike 1-2 = WARN, strike 3 = INVALIDATE (autosubmit)
// =============================
if (!function_exists('acGetOrCreateAttemptId')) {
    function acGetOrCreateAttemptId(string $testCode): string {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $testCode = strtoupper(trim($testCode));
        if (!isset($_SESSION['ac_attempt']) || !is_array($_SESSION['ac_attempt'])) {
            $_SESSION['ac_attempt'] = [];
        }

        if (!empty($_SESSION['ac_attempt'][$testCode])) {
            return (string)$_SESSION['ac_attempt'][$testCode];
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $rand   = bin2hex(random_bytes(8));
        $id     = $testCode . '_' . $userId . '_' . $rand . '_' . dechex(time());

        $_SESSION['ac_attempt'][$testCode] = $id;
        return $id;
    }
}

if (!function_exists('acClearAttemptId')) {
    function acClearAttemptId(string $testCode): void {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $testCode = strtoupper(trim($testCode));
        if (isset($_SESSION['ac_attempt'][$testCode])) {
            unset($_SESSION['ac_attempt'][$testCode]);
        }
    }
}


if (!function_exists('sendNoCacheHeaders')) {
    function sendNoCacheHeaders(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!function_exists('jsonOut')) {
    function jsonOut(array $data, int $code = 200): void {
        sendNoCacheHeaders();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('readJsonBody')) {
    function readJsonBody(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('ac_clean_id')) {
    function ac_clean_id(string $s, int $maxLen = 80): string {
        $s = trim($s);
        // allow: alnum _ - : .
        $s = preg_replace('/[^a-zA-Z0-9_\-:\.]/', '', $s);
        if (strlen($s) > $maxLen) $s = substr($s, 0, $maxLen);
        return $s;
    }
}

if (!function_exists('ac_clean_event')) {
    function ac_clean_event(string $s, int $maxLen = 60): string {
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9_]/', '', $s);
        if (strlen($s) > $maxLen) $s = substr($s, 0, $maxLen);
        return $s ?: 'UNKNOWN';
    }
}

if (!function_exists('ac_clean_test')) {
    function ac_clean_test(string $s): string {
        $s = strtoupper(trim($s));
        // samakan penamaan test code
        $map = [
            'TPA' => 'TPA',
            'TAM' => 'TAM',
            'KRAEPLIN' => 'KRAEPLIN',
            'KRAEPELIN' => 'KRAEPLIN'
        ];
        $s = preg_replace('/[^A-Z]/', '', $s);
        return $map[$s] ?? 'UNKNOWN';
    }
}

/**
 * Endpoint yang dipanggil AntiCheatLite.js (fetch POST JSON).
 * Response selalu memuat:
 * - violations, warningLimit, maxViolations, invalidated, action (WARN/AUTOSUBMIT)
 */
function ajaxAntiCheatEvent(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['ok' => false, 'err' => 'METHOD_NOT_ALLOWED'], 405);
    }

    // Wajib user login
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        jsonOut(['ok' => false, 'err' => 'UNAUTHORIZED'], 401);
    }

    global $conn;

    $body = readJsonBody();

    $attemptId = ac_clean_id((string)($body['attemptId'] ?? ''));
    $testCode  = ac_clean_test((string)($body['test'] ?? ''));
    $eventName = ac_clean_event((string)($body['event'] ?? 'UNKNOWN'));
    $isStrike  = !empty($body['strike']) ? 1 : 0;

    $clientTs  = $body['clientTs'] ?? null;
    $clientTs  = is_numeric($clientTs) ? (int)$clientTs : null;

    $detail    = $body['detail'] ?? null;
    $cycleId   = '';
    if (is_array($detail)) {
        $cycleId = (string)($detail['cycleId'] ?? $detail['cycle_id'] ?? '');
    }
    $cycleId = ac_clean_id($cycleId, 80);

    if ($attemptId === '' || $testCode === 'UNKNOWN') {
        jsonOut(['ok' => false, 'err' => 'BAD_REQUEST'], 400);
    }

    // Policy server (hard rule)
    $warningLimit  = 2;
    $maxViolations = 3;

    // MULTI TAB = langsung invalidasi (tanpa nunggu strike)
    $forceInvalidate = (strpos($eventName, 'MULTI_TAB_') === 0);

    try {
        $conn->begin_transaction();

        // Upsert attempt (lock row untuk konsistensi)
        $stmt = $conn->prepare("
            SELECT id, user_id, test_code, violations, invalidated, last_event, last_cycle_id
            FROM anti_cheat_attempts
            WHERE attempt_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("s", $attemptId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $stmt = $conn->prepare("
                INSERT INTO anti_cheat_attempts
                (attempt_id, user_id, test_code, violations, warning_limit, max_violations, invalidated)
                VALUES (?, ?, ?, 0, ?, ?, 0)
            ");
            $stmt->bind_param("sisii", $attemptId, $userId, $testCode, $warningLimit, $maxViolations);
            $stmt->execute();
            $stmt->close();

            $row = [
                'user_id' => $userId,
                'test_code' => $testCode,
                'violations' => 0,
                'invalidated' => 0,
                'last_event' => null,
                'last_cycle_id' => null
            ];
        } else {
            // attempt harus milik user ini
            if ((int)$row['user_id'] !== $userId) {
                // Security: jangan bocorkan data attempt orang lain
                $conn->rollback();
                jsonOut(['ok' => false, 'err' => 'FORBIDDEN'], 403);
            }
        }

        $violations = (int)($row['violations'] ?? 0);
        $invalidated = (int)($row['invalidated'] ?? 0);

        // Jika sudah invalidated, jangan increment lagi
        if ($invalidated === 1) {
            $conn->commit();
            jsonOut([
                'ok' => true,
                'violations' => $violations,
                'warningLimit' => $warningLimit,
                'maxViolations' => $maxViolations,
                'invalidated' => 1,
                'action' => 'AUTOSUBMIT',
                'reason' => 'ALREADY_INVALIDATED'
            ]);
        }

        // Jika multi-tab -> langsung invalidasi
        if ($forceInvalidate) {
            $reason = $eventName;

            $stmt = $conn->prepare("
                UPDATE anti_cheat_attempts
                SET invalidated=1, invalidated_reason=?, warning_limit=?, max_violations=?,
                    last_event=?, last_cycle_id=?, last_client_ts=?, last_strike_at=NOW()
                WHERE attempt_id = ?
                LIMIT 1
            ");
            $lastEvent = $eventName;
            $lastCycle = $cycleId ?: null;
            $lastTs    = $clientTs;
            $stmt->bind_param("siississ", $reason, $warningLimit, $maxViolations, $lastEvent, $lastCycle, $lastTs, $attemptId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            jsonOut([
                'ok' => true,
                'violations' => $violations,
                'warningLimit' => $warningLimit,
                'maxViolations' => $maxViolations,
                'invalidated' => 1,
                'action' => 'AUTOSUBMIT',
                'reason' => $reason
            ]);
        }

        // HELLO: hanya sinkronisasi angka dari server
        if ($eventName === 'HELLO') {
            // pastikan policy server tersimpan juga
            $stmt = $conn->prepare("
                UPDATE anti_cheat_attempts
                SET warning_limit=?, max_violations=?, last_event=?, last_client_ts=?
                WHERE attempt_id=?
                LIMIT 1
            ");
            $lastTs = $clientTs;
            $stmt->bind_param("iisds", $warningLimit, $maxViolations, $eventName, $lastTs, $attemptId);
            // catatan: bind "d" untuk BIGINT kadang aman di mysqli; jika strict, ganti jadi "i" dan cast int
            // kita amankan:
            $stmt->close();

            // versi aman: pakai "sisis"
            $stmt = $conn->prepare("
                UPDATE anti_cheat_attempts
                SET warning_limit=?, max_violations=?, last_event=?, last_client_ts=?
                WHERE attempt_id=?
                LIMIT 1
            ");
            $lastTsStr = ($clientTs === null) ? null : (string)$clientTs;
            $stmt->bind_param("iisss", $warningLimit, $maxViolations, $eventName, $lastTsStr, $attemptId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            jsonOut([
                'ok' => true,
                'violations' => $violations,
                'warningLimit' => $warningLimit,
                'maxViolations' => $maxViolations,
                'invalidated' => 0,
                'action' => ($violations >= $maxViolations) ? 'AUTOSUBMIT' : 'WARN'
            ]);
        }

        // Non-strike event: hanya update last_event untuk audit ringan
        if ($isStrike !== 1) {
            $stmt = $conn->prepare("
                UPDATE anti_cheat_attempts
                SET warning_limit=?, max_violations=?, last_event=?, last_client_ts=?
                WHERE attempt_id=?
                LIMIT 1
            ");
            $lastTsStr = ($clientTs === null) ? null : (string)$clientTs;
            $stmt->bind_param("iisss", $warningLimit, $maxViolations, $eventName, $lastTsStr, $attemptId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            jsonOut([
                'ok' => true,
                'violations' => $violations,
                'warningLimit' => $warningLimit,
                'maxViolations' => $maxViolations,
                'invalidated' => 0,
                'action' => 'WARN'
            ]);
        }

        // STRIKE event: dedupe pakai (eventName + cycleId)
        // Jika cycleId kosong, buat fallback berdasarkan clientTs bucket agar tidak dobel (1 hidden-cycle tetap 1 strike)
        if ($cycleId === '') {
            $bucket = $clientTs ? (int)floor($clientTs / 1500) : (int)floor(microtime(true) * 1000 / 1500);
            $cycleId = "tsb_" . $bucket;
        }

        $lastEvent = (string)($row['last_event'] ?? '');
        $lastCycle = (string)($row['last_cycle_id'] ?? '');

        if ($lastEvent === $eventName && $lastCycle === $cycleId) {
            // duplicate strike (jangan nambah violations)
            $conn->commit();
            jsonOut([
                'ok' => true,
                'violations' => $violations,
                'warningLimit' => $warningLimit,
                'maxViolations' => $maxViolations,
                'invalidated' => 0,
                'action' => 'WARN',
                'deduped' => 1
            ]);
        }

        $violationsNew = $violations + 1;
        $invalidateNow = ($violationsNew >= $maxViolations);

        $reason = $invalidateNow ? 'MAX_VIOLATIONS_REACHED' : null;

        $stmt = $conn->prepare("
            UPDATE anti_cheat_attempts
            SET violations=?,
                warning_limit=?,
                max_violations=?,
                invalidated=?,
                invalidated_reason=?,
                last_event=?,
                last_cycle_id=?,
                last_client_ts=?,
                last_strike_at=NOW()
            WHERE attempt_id=?
            LIMIT 1
        ");

        $inv = $invalidateNow ? 1 : 0;
        $lastTsStr = ($clientTs === null) ? null : (string)$clientTs;
        $stmt->bind_param(
            "iiiisssss",
            $violationsNew,
            $warningLimit,
            $maxViolations,
            $inv,
            $reason,
            $eventName,
            $cycleId,
            $lastTsStr,
            $attemptId
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        jsonOut([
            'ok' => true,
            'violations' => $violationsNew,
            'warningLimit' => $warningLimit,
            'maxViolations' => $maxViolations,
            'invalidated' => $inv,
            'action' => $inv ? 'AUTOSUBMIT' : 'WARN',
            'reason' => $reason
        ]);

    } catch (Throwable $e) {
        if ($conn && $conn->errno === 0) {
            try { $conn->rollback(); } catch (Throwable $_) {}
        }
        jsonOut(['ok' => false, 'err' => 'SERVER_ERROR'], 500);
    }
}
