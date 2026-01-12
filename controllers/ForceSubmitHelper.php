<?php
// controllers/ForceSubmitHelper.php
//
// Catatan perbaikan utama:
// 1) Menghapus duplikasi definisi function (penyebab: Cannot redeclare ...).
// 2) Tidak ada kode eksekusi di luar function (hindari redirect/exit saat file di-include).
// 3) Saat user dinonaktifkan:
//    - Jika request berasal dari halaman/endpoint tes => force finalize test tsb.
//    - Jika user sedang di dashboard/non-test => hanya logout (tanpa force submit).
// 4) Logout user tidak menghancurkan seluruh session (tidak mengganggu session admin).

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../models/TPA.php';
require_once __DIR__ . '/../models/TAM.php';
require_once __DIR__ . '/../models/Kraeplin.php';

/** Cek status aktif user di DB */
function fs_is_user_active(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare("SELECT is_active FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)($row['is_active'] ?? 0) === 1);
}

/** Cek apakah result sudah ada (menghindari double insert) */
function fs_has_result(mysqli $conn, string $table, int $userId): bool
{
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    if ($table === '') return false;

    $stmt = $conn->prepare("SELECT 1 FROM {$table} WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $ok;
}

/** Ambil progress terakhir per testType */
function fs_get_progress(mysqli $conn, int $userId, string $testType): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id, user_id, test_type, phase,
            started_at, end_at,
            payload_json, answers_json,
            last_seen_at, is_submitted, submitted_at
        FROM test_progress
        WHERE user_id=? AND test_type=?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $userId, $testType);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/** Tandai semua progress testType sebagai submitted (yang belum) */
function fs_mark_progress_submitted(mysqli $conn, int $userId, string $testType): void
{
    $stmt = $conn->prepare("
        UPDATE test_progress
        SET is_submitted=1, submitted_at=NOW()
        WHERE user_id=? AND test_type=? AND is_submitted=0
    ");
    $stmt->bind_param("is", $userId, $testType);
    $stmt->execute();
    $stmt->close();
}

/** Hitung durasi dari started_at (fallback aman) */
function fs_duration_seconds(?string $startedAt): int
{
    if (!$startedAt) return 0;
    $s = strtotime($startedAt);
    if (!$s) return 0;
    return max(0, time() - $s);
}

/**
 * Infer testType dari page saat ini.
 * Penting: supaya kalau user sedang di dashboard => NULL (tidak force submit),
 * tapi kalau sedang di halaman tes => terdeteksi testType.
 */
function fs_infer_test_type_from_request(): ?string
{
    $page = (string)($_GET['page'] ?? '');
    if ($page === '') return null;

    $map = [
        // TPA
        'user-tpa-start'       => 'TPA',
        'user-tpa-test'        => 'TPA',
        'user-tpa-submit'      => 'TPA',
        'ajax-save-tpa-progress' => 'TPA',

        // TAM
        'user-tam-stimulus'    => 'TAM',
        'user-tam-test'        => 'TAM',
        'submit-tam'           => 'TAM',
        'ajax-save-tam-answers'  => 'TAM',

        // KRAEPLIN
        'user-kraeplin-start'  => 'KRAEPLIN',
        'user-kraeplin-test'   => 'KRAEPLIN',
        'submit-kraeplin'      => 'KRAEPLIN',
        'ajax-save-kraeplin-lines' => 'KRAEPLIN',
    ];

    return $map[$page] ?? null;
}

/**
 * Force finalize 1 test_type dari progress (kalau ada dan belum submitted).
 * Return true jika ada tindakan finalize/mark submitted.
 */
function fs_force_finalize(mysqli $conn, int $userId, string $testType): bool
{
    $progress = fs_get_progress($conn, $userId, $testType);
    if (!$progress) return false;

    if ((int)($progress['is_submitted'] ?? 0) === 1) return false;

    $durationSeconds = fs_duration_seconds($progress['started_at'] ?? null);

    // -------------------- TPA --------------------
    if ($testType === 'TPA') {
        if (fs_has_result($conn, 'tpa_results', $userId)) {
            fs_mark_progress_submitted($conn, $userId, 'TPA');
            return true;
        }

        $answers = json_decode((string)($progress['answers_json'] ?? '[]'), true);
        if (!is_array($answers)) $answers = [];

        $questionIds = array_map('intval', array_keys($answers));

        // Ambil meta soal (coba 2 kemungkinan signature agar kompatibel)
        $meta = [];
        try {
            $meta = !empty($questionIds) ? TPA::getQuestionsMeta($questionIds) : [];
        } catch (\Throwable $e) {
            try {
                $meta = !empty($questionIds) ? TPA::getQuestionsMeta($conn, $questionIds) : [];
            } catch (\Throwable $e2) {
                $meta = [];
            }
        }
        if (!is_array($meta)) $meta = [];

        $score = 0;
        $detail = [];

        foreach ($answers as $qid => $ans) {
            $qid = (int)$qid;
            $ans = is_numeric($ans) ? (int)$ans : $ans;

            $correct = $meta[$qid]['correct_option'] ?? null;
            $isCorrect = ($correct !== null && (string)$ans === (string)$correct);

            $detail[] = [
                'qid'      => $qid,
                'selected' => $ans,
                'correct'  => $correct,
                'ok'       => $isCorrect ? 1 : 0
            ];

            if ($isCorrect) $score++;
        }

        $answersJson = json_encode($detail, JSON_UNESCAPED_UNICODE);

        // Save result (coba 2 kemungkinan signature agar kompatibel)
        $saved = false;
        try {
            // signature yang umum dipakai di code Anda: saveResult(user_id, category, session, score, answers_json)
            TPA::saveResult($userId, 'full', 'all', $score, (string)$answersJson);
            $saved = true;
        } catch (\Throwable $e) {
            try {
                // fallback: ada sebagian implementasi yang butuh $conn (jika model pakai $conn sebagai arg)
                TPA::saveResult($conn, $userId, 'full', 'all', $score, (string)$answersJson, $durationSeconds);
                $saved = true;
            } catch (\Throwable $e2) {
                $saved = false;
            }
        }

        // Walaupun gagal save, minimal progress ditandai agar tidak loop fatal
        fs_mark_progress_submitted($conn, $userId, 'TPA');
        return $saved || true;
    }

    // -------------------- TAM --------------------
    if ($testType === 'TAM') {
        if (fs_has_result($conn, 'tam_results', $userId)) {
            fs_mark_progress_submitted($conn, $userId, 'TAM');
            return true;
        }

        $answers = json_decode((string)($progress['answers_json'] ?? '[]'), true);
        if (!is_array($answers)) $answers = [];

        $correct = 0;
        $wrong   = 0;

        foreach ($answers as $qid => $ans) {
            $qid = (int)$qid;
            $ans = is_numeric($ans) ? (int)$ans : $ans;

            $kunci = null;
            try {
                $kunci = TAM::getCorrect($qid);
            } catch (\Throwable $e) {
                try {
                    $kunci = TAM::getCorrect($conn, $qid);
                } catch (\Throwable $e2) {
                    $kunci = null;
                }
            }

            if ($kunci !== null && (string)$ans === (string)$kunci) $correct++;
            else $wrong++;
        }

        $score = $correct; // sesuai pola Anda: score = total_correct

        $saved = false;
        try {
            // signature yang umum di code Anda: saveResult(user_id, total_correct, total_wrong, score)
            TAM::saveResult($userId, $correct, $wrong, $score);
            $saved = true;
        } catch (\Throwable $e) {
            try {
                TAM::saveResult($conn, $userId, $correct, $wrong, $score, $durationSeconds);
                $saved = true;
            } catch (\Throwable $e2) {
                $saved = false;
            }
        }

        fs_mark_progress_submitted($conn, $userId, 'TAM');
        return $saved || true;
    }

    // -------------------- KRAEPLIN --------------------
    if ($testType === 'KRAEPLIN') {
        if (fs_has_result($conn, 'kraeplin_results', $userId)) {
            fs_mark_progress_submitted($conn, $userId, 'KRAEPLIN');
            return true;
        }

        $rawLines = json_decode((string)($progress['answers_json'] ?? '[]'), true);
        if (!is_array($rawLines)) $rawLines = [];

        // Normalisasi minimal: pastikan array of lines dengan field 'answers' (array)
        $lines = [];
        foreach ($rawLines as $line) {
            if (!is_array($line)) continue;
            $answers = $line['answers'] ?? [];
            if (!is_array($answers)) $answers = [];

            $correct = 0;
            $wrong   = 0;
            $blank   = 0;

            foreach ($answers as $a) {
                // asumsi item: {a,b,ans,user,ok} atau mirip
                if (!is_array($a)) {
                    $blank++;
                    continue;
                }
                if (($a['ok'] ?? null) === 1 || ($a['ok'] ?? null) === true) $correct++;
                elseif (($a['ok'] ?? null) === 0 || ($a['ok'] ?? null) === false) $wrong++;
                else $blank++;
            }

            $lines[] = [
                'correct' => $correct,
                'wrong'   => $wrong,
                'blank'   => $blank,
                'answers' => $answers,
            ];
        }

        $totals = [];
        foreach ($lines as $ln) $totals[] = (int)($ln['correct'] ?? 0);

        $n = count($totals);
        $total_correct = array_sum($totals);
        $total_wrong = 0;
        $total_blank = 0;

        foreach ($lines as $ln) {
            $total_wrong += (int)($ln['wrong'] ?? 0);
            $total_blank += (int)($ln['blank'] ?? 0);
        }

        $total_items = $total_correct + $total_wrong + $total_blank;
        $accuracy = $total_items > 0 ? ($total_correct / $total_items) : 0.0;

        // variance sederhana
        $variance = 0.0;
        if ($n > 1) {
            $mean = $total_correct / $n;
            $sumSq = 0.0;
            foreach ($totals as $v) $sumSq += ($v - $mean) ** 2;
            $variance = $sumSq / ($n - 1);
        }

        // trend & adapt (heuristic sederhana, kompatibel)
        $trend = 'stable';
        if ($n >= 3) {
            $first = array_sum(array_slice($totals, 0, (int)floor($n / 3)));
            $last  = array_sum(array_slice($totals, (int)floor(2 * $n / 3)));
            if ($last > $first) $trend = 'up';
            elseif ($last < $first) $trend = 'down';
        }

        $adapt = 'normal';
        if ($n >= 4) {
            $half = (int)floor($n / 2);
            $a = array_sum(array_slice($totals, 0, $half));
            $b = array_sum(array_slice($totals, $half));
            if ($b > $a) $adapt = 'improving';
            elseif ($b < $a) $adapt = 'declining';
        }

        $pattern = 'normal'; // placeholder (bisa Anda mapping lebih lanjut)

        $saved = false;
        try {
            // signature yang umum di code Anda: saveResult(user_id, total_items, total_correct, accuracy, variance, trend, adapt, pattern, raw_lines_json)
            Kraeplin::saveResult(
                $userId,
                $total_items,
                $total_correct,
                $accuracy,
                $variance,
                $trend,
                $adapt,
                $pattern,
                json_encode($rawLines, JSON_UNESCAPED_UNICODE)
            );
            $saved = true;
        } catch (\Throwable $e) {
            try {
                Kraeplin::saveResult(
                    $conn,
                    $userId,
                    $total_items,
                    $total_correct,
                    $accuracy,
                    $variance,
                    $trend,
                    $adapt,
                    $pattern,
                    json_encode($rawLines, JSON_UNESCAPED_UNICODE),
                    $durationSeconds
                );
                $saved = true;
            } catch (\Throwable $e2) {
                $saved = false;
            }
        }

        fs_mark_progress_submitted($conn, $userId, 'KRAEPLIN');
        return $saved || true;
    }

    return false;
}

/**
 * Dipakai oleh admin saat disable user: submit semua progress test yang masih open (tanpa infer page).
 * Return array daftar test yang diproses.
 */
function force_submit_all_open_progress(mysqli $conn, int $userId): array
{
    $submitted = [];
    foreach (['TPA', 'TAM', 'KRAEPLIN'] as $tt) {
        if (fs_force_finalize($conn, $userId, $tt)) $submitted[] = $tt;
    }
    return $submitted;
}

/**
 * Dipakai oleh user side (autosave/polling/page):
 * - Kalau user sudah dinonaktifkan, maka:
 *   - Jika sedang di halaman/endpoint tes => force submit test tsb
 *   - Jika tidak => jangan force submit (sesuai catatan Anda), hanya logout
 * Lalu logout user dan redirect ke login peserta.
 */
function force_submit_and_logout_if_disabled(mysqli $conn, ?string $testType = null, bool $asJson = false): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) return;

    if (fs_is_user_active($conn, $uid)) return;

    // Jika tidak dikirim testType, coba infer dari page.
    if ($testType === null) {
        $testType = fs_infer_test_type_from_request();
    }

    $submitted = [];
    if ($testType !== null) {
        if (fs_force_finalize($conn, $uid, $testType)) $submitted[] = $testType;
    }
    // NOTE: jika testType NULL => dashboard/non-test => tidak force submit apa pun.

    // Logout USER SAJA (jangan session_destroy total agar tidak mengganggu admin session)
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_code'],
        $_SESSION['user_name'],
        $_SESSION['is_user_logged_in']
    );

    // Jika sistem Anda pakai flag khusus lain, tambahkan di unset sesuai kebutuhan.

    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode([
            'ok' => true,
            'is_active' => false,
            'forced_logout' => true,
            'forced_submit' => $submitted,
            'server_time' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: index.php?page=user-login&error=disabled&msg=' . urlencode('Akun Anda dinonaktifkan oleh admin.'));
    exit;
}
