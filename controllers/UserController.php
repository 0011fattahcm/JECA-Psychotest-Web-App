<?php
require_once './config/database.php';
require_once './models/User.php';
require_once './models/TPA.php';
require_once './models/TAM.php';
require_once './models/Kraeplin.php';
require_once './controllers/TestWindowHelper.php';
require_once './controllers/ActivityLogger.php';

/* ============================================================
   USER LOGIN PAGE
   ============================================================ */
function userLogin() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    if (!empty($_SESSION['user_id'])) {
        header("Location: index.php?page=user-dashboard");
        exit;
    }

    // ambil error dari URL
    $error = $_GET['error'] ?? '';
    $error = preg_replace('/[^a-z0-9_]/i', '', $error);

    // kalau sebelumnya error=test_closed, tapi sekarang sudah open -> bersihkan notif
    if ($error === 'test_closed') {
        require_once './controllers/TestWindowHelper.php';
        if (isTestWindowOpenNow($conn)) {
            header("Location: index.php?page=user-login"); // bersih tanpa query error
            exit;
        }
    }

    require './views/user/login.php';
}

/**
 * Ambil kuota retake user dari table users.
 * Return: ['TPA'=>int,'TAM'=>int,'KRAEPLIN'=>int]
 */
function getUserRetakeQuotas(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare("
        SELECT
          COALESCE(tpa_retake_quota,0) AS tpa_q,
          COALESCE(tam_retake_quota,0) AS tam_q,
          COALESCE(kraeplin_retake_quota,0) AS kraeplin_q
        FROM users
        WHERE id=? LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'TPA'      => (int)($row['tpa_q'] ?? 0),
        'TAM'      => (int)($row['tam_q'] ?? 0),
        'KRAEPLIN' => (int)($row['kraeplin_q'] ?? 0),
    ];
}

/**
 * Consume 1 kuota retake + reset data tes (DELETE result + DELETE progress) secara atomic.
 * Return true jika sukses consume & reset.
 */


function ajaxUserStatus() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (empty($_SESSION['user_id'])) {
        echo json_encode(['ok' => true, 'active' => 0]);
        exit;
    }

    $uid = (int)$_SESSION['user_id'];

    // optional: dipakai kalau request dari halaman tes
    $testType = strtoupper(trim($_GET['test'] ?? ''));
    if (!in_array($testType, ['TPA','TAM','KRAEPLIN'], true)) {
        $testType = null;
    }

    require_once './controllers/ForceSubmitHelper.php';

    // Jika sudah nonaktif -> paksa logout user (dan finalize test jika testType ada)
    if (!fs_is_user_active($conn, $uid)) {
        force_submit_and_logout_if_disabled($conn, $testType, true); // return 403 + json lalu exit
        exit;
    }

    echo json_encode(['ok' => true, 'active' => 1]);
    exit;
}

// =========================
// Guard: cek user aktif
// =========================
function isUserActive(mysqli $conn, int $userId): bool {
  $stmt = $conn->prepare("SELECT is_active FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return (int)($row['is_active'] ?? 1) === 1;
}

function requireActiveUser(mysqli $conn): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0 && !isUserActive($conn, $uid)) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_code']);
        header("Location: index.php?page=user-login&error=disabled");
        exit;
    }
}


function ajaxTestWindowStatus() {
    header('Content-Type: application/json; charset=utf-8');
    global $conn;

    require_once './controllers/TestWindowHelper.php';
    $open = isTestWindowOpenNow($conn);

    echo json_encode([
        'ok' => true,
        'is_open_now' => $open,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/* ============================================================
   USER LOGIN PROCESS
   User login hanya menggunakan user_code
   ============================================================ */
function userLoginProcess() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    require_once './controllers/TestWindowHelper.php';
    if (!isTestWindowOpenNow($conn)) {
        header("Location: index.php?page=user-login&error=test_closed");
        exit;
    }

    $user_code = trim($_POST['user_code'] ?? '');
    if ($user_code === '') {
        echo "<script>alert('ID tidak boleh kosong'); window.location='index.php?page=user-login';</script>";
        exit;
    }

    $stmt = $conn->prepare("SELECT id, user_code, name, is_active FROM users WHERE user_code=? LIMIT 1");
    $stmt->bind_param("s", $user_code);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header("Location: index.php?page=user-login&error=not_found");
        exit;
    }
    if ((int)$user['is_active'] !== 1) {
        header("Location: index.php?page=user-login&error=disabled");
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'] ?? null;
    $_SESSION['user_code'] = $user['user_code'] ?? $user_code;

    if (function_exists('activityLog')) {
        activityLog($conn, (int)$user['id'], 'auth', 'login_success', null, [
            'user_code' => $_SESSION['user_code']
        ]);
    }

    header("Location: index.php?page=user-dashboard");
    exit;
}


/* ============================================================
   USER LOGOUT
   ============================================================ */
function userLogout(): void
{
  global $conn;

  if (session_status() !== PHP_SESSION_ACTIVE) session_start();

  $userId = (int)($_SESSION['user_id'] ?? 0);

  // pastikan logger ada sebelum dipanggil
  require_once __DIR__ . '/ActivityLogger.php';
  if (function_exists('activityLog') && $userId > 0) {
    activityLog($conn, $userId, 'AUTH', 'User Logout', null, []);
  }

  // jangan session_destroy() agar tidak menghapus session admin jika share cookie
  unset(
    $_SESSION['user_id'],
    $_SESSION['user_code'],
    $_SESSION['user_name'],
    $_SESSION['name'],
    $_SESSION['has_tpa'],
    $_SESSION['has_tam'],
    $_SESSION['has_kraeplin'],
    $_SESSION['_activity_once']
  );

  header("Location: index.php?page=user-login&success=logged_out");
  exit;
}

function ajaxSaveTpaProgress()
{
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TPA', true);
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $p = getProgress($conn, $user_id, 'TPA');
    if (!$p || (int)$p['is_submitted'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'no_progress']);
        exit;
    }

    $payload = json_decode($p['payload_json'] ?? '[]', true);
    if (!is_array($payload)) $payload = [];

    $attemptNo = isset($payload['attempt_no']) ? (int)$payload['attempt_no'] : max(1, getMaxAttemptNo($conn, 'tpa_results', $user_id));
    if ($attemptNo <= 0) $attemptNo = 1;

    // kalau attempt ini sudah result => ignore
    if (hasResultAttempt($conn, 'tpa_results', $user_id, $attemptNo)) {
        echo json_encode(['ok' => true, 'ignored' => true]);
        exit;
    }

    $raw = $_POST['answers_json'] ?? '';
    $arr = json_decode($raw, true);
    if (!is_array($arr)) $arr = [];

    updateProgressAnswers($conn, $user_id, 'TPA', json_encode($arr, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true]);
    exit;
}


function requireUserLogin(?string $testType = null, bool $asJson = false): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        if ($asJson) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok'=>false,'error'=>'unauthorized']);
            exit;
        }
        header("Location: index.php?page=user-login");
        exit;
    }

    // Kalau user sudah dinonaktifkan admin -> force submit + logout
    require_once './controllers/ForceSubmitHelper.php';
    force_submit_and_logout_if_disabled($conn, $testType, $asJson);
}


function requireAckOrBackToDashboard(string $msg): void {
    if (!isset($_GET['ack']) || $_GET['ack'] !== '1') {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode($msg));
        exit;
    }
}

function hasResult(mysqli $conn, string $table, int $user_id): bool {
    $sql = "SELECT COUNT(*) AS n FROM {$table} WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($row['n'] ?? 0) > 0);
}

function getProgress(mysqli $conn, int $user_id, string $test_type): ?array {
    $stmt = $conn->prepare("SELECT * FROM test_progress WHERE user_id = ? AND test_type = ? LIMIT 1");
    $stmt->bind_param('is', $user_id, $test_type);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function upsertProgress(mysqli $conn, int $user_id, string $test_type, string $started_at, string $end_at, ?string $phase, ?string $payload_json, ?string $answers_json, int $is_submitted = 0): void {
    $stmt = $conn->prepare("
        INSERT INTO test_progress (user_id, test_type, phase, started_at, end_at, payload_json, answers_json, last_seen_at, is_submitted)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
          phase = VALUES(phase),
          started_at = VALUES(started_at),
          end_at = VALUES(end_at),
          payload_json = COALESCE(VALUES(payload_json), payload_json),
          answers_json = COALESCE(VALUES(answers_json), answers_json),
          last_seen_at = NOW(),
          is_submitted = VALUES(is_submitted)
    ");
    $stmt->bind_param('issssssi', $user_id, $test_type, $phase, $started_at, $end_at, $payload_json, $answers_json, $is_submitted);
    $stmt->execute();
    $stmt->close();
}

function updateProgressAnswers(mysqli $conn, int $user_id, string $test_type, string $answers_json): void {
    $stmt = $conn->prepare("UPDATE test_progress SET answers_json = ?, last_seen_at = NOW() WHERE user_id = ? AND test_type = ? AND is_submitted = 0");
    $stmt->bind_param('sis', $answers_json, $user_id, $test_type);
    $stmt->execute();
    $stmt->close();
}

function markProgressSubmitted(mysqli $conn, int $user_id, string $test_type): void {
    $stmt = $conn->prepare("UPDATE test_progress SET is_submitted = 1, submitted_at = NOW(), last_seen_at = NOW() WHERE user_id = ? AND test_type = ?");
    $stmt->bind_param('is', $user_id, $test_type);
    $stmt->execute();
    $stmt->close();
}


/* ============================================================
   DASHBOARD USER (Proteksi Login)
   ============================================================ */
function userDashboard() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    if (empty($_SESSION['user_id'])) {
        header("Location: index.php?page=user-login");
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];

    // Ambil data user + cek status aktif + kuota retake
    $stmt = $conn->prepare("
        SELECT name, user_code, is_active,
               tpa_retake_quota, tam_retake_quota, kraeplin_retake_quota
        FROM users
        WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
        unset($_SESSION['user_id'], $_SESSION['name'], $_SESSION['user_code'], $_SESSION['user_name']);
        header("Location: index.php?page=user-login&msg=" . urlencode("Akun Anda dinonaktifkan oleh admin."));
        exit;
    }

    autoFinalizeExpiredTests($conn, $user_id);

    $countByUser = function(string $table) use ($conn, $user_id): int {
        $sql = "SELECT COUNT(*) AS n FROM {$table} WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($r['n'] ?? 0);
    };

    $name      = $row['name']      ?? ($_SESSION['name'] ?? '');
    $user_code = $row['user_code'] ?? ($_SESSION['user_code'] ?? '');

    $_SESSION['name'] = $name;
    $_SESSION['user_code'] = $user_code;

    // Status tes
    $has_tpa      = $countByUser('tpa_results') > 0;
    $has_tam      = $countByUser('tam_results') > 0;
    $has_kraeplin = $countByUser('kraeplin_results') > 0;

    // Kuota retake untuk view
    $tpa_retake_quota      = (int)($row['tpa_retake_quota'] ?? 0);
    $tam_retake_quota      = (int)($row['tam_retake_quota'] ?? 0);
    $kraeplin_retake_quota = (int)($row['kraeplin_retake_quota'] ?? 0);

    require './views/user/dashboard.php';
}

/* ============================================================
   SUBMIT TPA (Autoscore)
   ============================================================ */

function userTpaStart()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin();
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    requireAckOrBackToDashboard("Silakan baca panduan TPA dan klik Mengerti sebelum memulai.");
 
    // Kalau ada progress aktif (belum submit) => resume (jangan consume kuota lagi)
    $p = getProgress($conn, $user_id, 'TPA');
    if ($p && (int)$p['is_submitted'] === 0) {
        header("Location: index.php?page=user-tpa-test", true, 303);
        exit;
    }
   acClearAttemptId('TPA');
    // Start baru: tentukan attempt
    $hasAnyResult = hasResult($conn, 'tpa_results', $user_id);
    $isRetakeReq  = (isset($_GET['retake']) && $_GET['retake'] === '1');

    $attemptNo = 1;
    $isRetake  = 0;

    if ($hasAnyResult) {
        if (!$isRetakeReq) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("TPA sudah selesai. Jika ingin mengulang, gunakan tombol Ulangi di Dashboard."));
            exit;
        }

        $nextAttempt = consumeRetakeQuotaAndNextAttempt($conn, $user_id, 'TPA');
        if (!$nextAttempt) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kuota ulang TPA tidak tersedia."));
            exit;
        }

        $attemptNo = (int)$nextAttempt;
        $isRetake  = 1;

        if (function_exists('activityLog')) {
            activityLog($conn, $user_id, 'test', 'retake_begin', 'TPA', [
                'attempt_no' => $attemptNo
            ]);
        }
    }

    // Buat progress baru
    $durationMinutes = 90;
    $sections = TPA::buildSectionsForFullTest(); // random sekali di awal

    // payload dibungkus agar attempt info aman
    $payload = [
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'sections'   => $sections
    ];

    $started_at = date('Y-m-d H:i:s');
    $end_at     = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));

    upsertProgress(
        $conn, $user_id, 'TPA',
        $started_at, $end_at,
        'test',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        json_encode(new stdClass(), JSON_UNESCAPED_UNICODE),
        0
    );

    header("Location: index.php?page=user-tpa-test", true, 303);
    exit;
}




function userTpaTest()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TPA');
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    autoFinalizeExpiredTests($conn, $user_id);

    $p = getProgress($conn, $user_id, 'TPA');
    if (!$p || (int)$p['is_submitted'] === 1) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Silakan mulai TPA dari Dashboard."), true, 303);
        exit;
    }

    // Payload: support format lama (array sections) dan format baru (object with sections)
    $payload = json_decode($p['payload_json'] ?? '[]', true);
    if (!is_array($payload)) $payload = [];

    $attemptNo = 1;
    $isRetake  = 0;
    $sections  = [];

    if (isset($payload['sections'])) {
        $sections  = is_array($payload['sections']) ? $payload['sections'] : [];
        $attemptNo = (int)($payload['attempt_no'] ?? 1);
        $isRetake  = (int)($payload['is_retake'] ?? 0);
    } else {
        // format lama: payload langsung list sections
        $sections = $payload;

        // repair attempt info (tanpa merusak sections)
        $max = getMaxAttemptNo($conn, 'tpa_results', $user_id);
        $attemptNo = max(1, $max > 0 ? $max : 1);
        $isRetake  = ($attemptNo > 1) ? 1 : 0;

        // simpan ulang payload ke format baru agar stabil untuk selanjutnya
        $newPayload = [
            'attempt_no' => $attemptNo,
            'is_retake'  => $isRetake,
            'sections'   => $sections
        ];
        upsertProgress(
            $conn, $user_id, 'TPA',
            ($p['started_at'] ?? date('Y-m-d H:i:s')),
            ($p['end_at'] ?? date('Y-m-d H:i:s', time()+60)),
            ($p['phase'] ?? 'test'),
            json_encode($newPayload, JSON_UNESCAPED_UNICODE),
            ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
            0
        );
    }

    // Jika attempt ini sudah tersimpan => block
    if (hasResultAttempt($conn, 'tpa_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'TPA');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TPA sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    // Waktu habis => finalize attempt ini
    $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;
    if ($endAtTs > 0 && $endAtTs <= time()) {
        finalizeTPAFromProgress($user_id);
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu TPA habis. Tes otomatis diselesaikan."), true, 303);
        exit;
    }

    $savedAnswers = json_decode($p['answers_json'] ?? '{}', true);
    if (!is_array($savedAnswers)) $savedAnswers = [];

    $endAtTs = strtotime($p['end_at'] ?? '') ?: (time() + 60);
    $endAtTs = (int)$endAtTs;

    activityLogOnce($conn, $user_id, 'enter_TPA_' . ($p['started_at'] ?? ''), 'test', 'enter', 'TPA', [
        'phase' => 'test',
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake
    ]);

    require './views/user/tpa/test.php';
}



/* ============================================================
   USER – SUBMIT Tes TPA (manual & auto dari timer)
   ============================================================ */
function userTpaSubmit()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TPA');
    sendNoCacheHeaders();

    global $conn;
    $userId = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Metode tidak valid."));
        exit;
    }

    $p = getProgress($conn, $userId, 'TPA');
    if (!$p || (int)$p['is_submitted'] === 1) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Progress TPA tidak ditemukan."), true, 303);
        exit;
    }

    $payload = json_decode($p['payload_json'] ?? '[]', true);
    if (!is_array($payload)) $payload = [];

    $attemptNo = (int)($payload['attempt_no'] ?? max(1, getMaxAttemptNo($conn, 'tpa_results', $userId)));
    if ($attemptNo <= 0) $attemptNo = 1;
    $isRetake  = (int)($payload['is_retake'] ?? ($attemptNo > 1 ? 1 : 0));

    // anti double submit untuk attempt ini
    if (hasResultAttempt($conn, 'tpa_results', $userId, $attemptNo)) {
        markProgressSubmitted($conn, $userId, 'TPA');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TPA sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    // ambil jawaban dari POST; jika kosong, fallback ke progress
    $answers = (isset($_POST['answer']) && is_array($_POST['answer'])) ? $_POST['answer'] : [];
    if (empty($answers)) {
        $saved = json_decode($p['answers_json'] ?? '{}', true);
        if (is_array($saved)) $answers = $saved;
    }

    $questionIds = array_values(array_filter(array_map('intval', array_keys($answers)), fn($id)=>$id>0));
    $meta = !empty($questionIds) ? TPA::getQuestionsMeta($questionIds) : [];

    $totalCorrect = 0;
    $detail = [];

    foreach ($answers as $qid => $ans) {
        $qid = (int)$qid;
        if ($qid <= 0) continue;

        $ans = strtoupper(trim((string)$ans));
        $m = $meta[$qid] ?? null;
        $correctOption = $m ? strtoupper(trim((string)$m['correct_option'])) : null;

        $isCorrect = ($correctOption && $ans !== '' && $ans === $correctOption);
        if ($isCorrect) $totalCorrect++;

        $detail[] = [
            'question_id'    => $qid,
            'category'       => $m['category'] ?? null,
            'session'        => $m['session'] ?? null,
            'answer'         => $ans,
            'correct_option' => $correctOption,
            'is_correct'     => $isCorrect,
        ];
    }

    $score = $totalCorrect;
    $answersJson = json_encode($detail, JSON_UNESCAPED_UNICODE);

    // Tetap pakai enum yang aman (jangan pakai "full/all" karena enum)
    $categoryDb = 'verbal';
    $sessionDb  = '1';

    saveTpaResultAttempt($conn, $userId, $attemptNo, $isRetake, $categoryDb, $sessionDb, $score, $answersJson);

    activityLog($conn, $userId, 'test', 'complete', 'TPA', [
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'score' => $score,
        'total_correct' => $totalCorrect,
        'mode' => 'submit'
    ]);

    markProgressSubmitted($conn, $userId, 'TPA');
    acClearAttemptId('TPA');
    header("Location: index.php?page=user-dashboard&msg=" . urlencode("Tes TPA selesai!"), true, 303);
    exit;
}



// Helper TAM
function tamDurations(array $package): array {
    $stimulus = (int)($package['duration_display'] ?? 5);
    $test     = (int)($package['duration_answer']  ?? 15);
    if ($stimulus <= 0) $stimulus = 5;
    if ($test <= 0)     $test = 15;
    return [$stimulus, $test];
}

function pickTamQuestionIds(mysqli $conn, int $limit = 20): array {
    $limit = max(1, min(50, $limit));
    $res = $conn->query("SELECT id FROM tam_questions ORDER BY RAND() LIMIT {$limit}");
    $ids = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
    }
    return $ids;
}

function fetchTamQuestionsByIds(mysqli $conn, array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids), fn($v)=>$v>0));
    if (empty($ids)) return [];

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "SELECT id, question, option_a, option_b, option_c, option_d
            FROM tam_questions
            WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();

    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    // reorder sesuai urutan ids
    $map = [];
    foreach ($rows as $r) $map[(int)$r['id']] = $r;

    $ordered = [];
    foreach ($ids as $id) {
        if (isset($map[$id])) $ordered[] = $map[$id];
    }
    return $ordered;
}


/* ============================================================
   TAM - Stimulus (harus dilewati semua user sebelum soal)
   ============================================================ */
/* ============================================================
   TAM - Stimulus (wajib sebelum soal)
   ============================================================ */
function UsertamStimulus() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TAM');
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if (!hasResult($conn, 'tpa_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM terkunci. Selesaikan TPA terlebih dahulu."));
        exit;
    }

    $package = TAM::getPackage();
    [$stimulusMinutes, $testMinutes] = tamDurations(is_array($package) ? $package : []);

    // RESUME kalau progress aktif
    $p = getProgress($conn, $user_id, 'TAM');
    if ($p && (int)$p['is_submitted'] === 0) {

        // pastikan attempt info ada (helper yang kemarin sudah kamu paste)
        [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'TAM', 'tam_results');

        $phase   = (string)($p['phase'] ?? 'stimulus');
        $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

        if ($phase === 'test') {
            header("Location: index.php?page=user-tam-test", true, 303);
            exit;
        }

        if ($endAtTs <= 0) {
            $fixedEnd = date('Y-m-d H:i:s', time() + ($stimulusMinutes * 60));
            upsertProgress(
                $conn, $user_id, 'TAM',
                ($p['started_at'] ?? date('Y-m-d H:i:s')),
                $fixedEnd,
                'stimulus',
                ($p['payload_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                0
            );
            $endAtTs = strtotime($fixedEnd);
        }

        // stimulus habis -> ke test (STRICT)
        if ($endAtTs <= time()) {
            $payload = json_decode($p['payload_json'] ?? '{}', true);
            if (!is_array($payload)) $payload = [];

            if (empty($payload['question_ids'])) {
                $payload['question_ids'] = pickTamQuestionIds($conn, 20);
            }

            $testEndTs = $endAtTs + ($testMinutes * 60);
            $newEnd    = date('Y-m-d H:i:s', $testEndTs);

            $payload['attempt_no'] = (int)($payload['attempt_no'] ?? $attemptNo);
            $payload['is_retake']  = (int)($payload['is_retake']  ?? $isRetake);

            upsertProgress(
                $conn, $user_id, 'TAM',
                ($p['started_at'] ?? date('Y-m-d H:i:s')),
                $newEnd,
                'test',
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                0
            );

            if ($testEndTs <= time()) {
                finalizeTAMFromProgress($user_id);
                header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu TAM habis. Tes otomatis diselesaikan."), true, 303);
                exit;
            }

            header("Location: index.php?page=user-tam-test", true, 303);
            exit;
        }

        $endAtTs = (int)$endAtTs;

        activityLogOnce($conn, $user_id, 'enter_TAM_stimulus_' . ($p['started_at'] ?? ''), 'test', 'enter', 'TAM', [
            'phase' => 'stimulus',
            'attempt_no' => $attemptNo,
            'is_retake'  => $isRetake
        ]);

        require './views/user/tam/stimulus.php';
        exit;
    }

    // START BARU (first/retake)
    requireAckOrBackToDashboard("Silakan baca panduan TAM dan klik Mengerti sebelum memulai.");
    acClearAttemptId('TAM');
    $hasAnyResult = hasResult($conn, 'tam_results', $user_id);
    $isRetakeReq  = (isset($_GET['retake']) && $_GET['retake'] === '1');

    $attemptNo = 1;
    $isRetake  = 0;

    if ($hasAnyResult) {
        if (!$isRetakeReq) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM sudah selesai. Jika ingin mengulang, gunakan tombol Ulangi di Dashboard."));
            exit;
        }

        $nextAttempt = consumeRetakeQuotaAndNextAttempt($conn, $user_id, 'TAM');
        if (!$nextAttempt) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kuota ulang TAM tidak tersedia."));
            exit;
        }

        $attemptNo = (int)$nextAttempt;
        $isRetake  = 1;

        activityLog($conn, $user_id, 'test', 'retake_begin', 'TAM', [
            'attempt_no' => $attemptNo
        ]);
    }

    $started_at = date('Y-m-d H:i:s');
    $end_at     = date('Y-m-d H:i:s', time() + ($stimulusMinutes * 60));

    $payload = [
        'package_id'       => $package['id'] ?? null,
        'stimulus_minutes' => $stimulusMinutes,
        'test_minutes'     => $testMinutes,
        'question_ids'     => [],
        'attempt_no'       => $attemptNo,
        'is_retake'        => $isRetake
    ];

    upsertProgress(
        $conn, $user_id, 'TAM',
        $started_at, $end_at,
        'stimulus',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        json_encode(new stdClass(), JSON_UNESCAPED_UNICODE),
        0
    );

    $endAtTs = strtotime($end_at) ?: (time() + ($stimulusMinutes * 60));
    require './views/user/tam/stimulus.php';
}

/* ============================================================
   TAM - Halaman Soal
   ============================================================ */
function UsertamTest() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TAM');
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if (!hasResult($conn, 'tpa_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM terkunci. Selesaikan TPA terlebih dahulu."));
        exit;
    }

    $p = getProgress($conn, $user_id, 'TAM');
    if (!$p || (int)$p['is_submitted'] === 1) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Silakan mulai TAM dari Dashboard."));
        exit;
    }

    // attempt info
    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'TAM', 'tam_results');

    // kalau attempt ini sudah ada result => block
    if (hasResultAttempt($conn, 'tam_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'TAM');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    $finalized = autoFinalizeExpiredTests($conn, $user_id);
    if (in_array('TAM', $finalized, true)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu TAM habis. Tes otomatis diselesaikan."), true, 303);
        exit;
    }

    $package = TAM::getPackage();
    [$stimulusMinutes, $testMinutes] = tamDurations(is_array($package) ? $package : []);

    $phase   = (string)($p['phase'] ?? 'stimulus');
    $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

    // kalau masih stimulus -> balik stimulus
    if ($phase === 'stimulus' && $endAtTs > time()) {
        header("Location: index.php?page=user-tam-stimulus", true, 303);
        exit;
    }

    // stimulus habis -> transisi ke test (STRICT)
    if ($phase === 'stimulus' && $endAtTs <= time()) {
        $payload = json_decode($p['payload_json'] ?? '{}', true);
        if (!is_array($payload)) $payload = [];

        if (empty($payload['question_ids'])) {
            $payload['question_ids'] = pickTamQuestionIds($conn, 20);
        }

        $payload['attempt_no'] = (int)($payload['attempt_no'] ?? $attemptNo);
        $payload['is_retake']  = (int)($payload['is_retake']  ?? $isRetake);

        $testEndTs = $endAtTs + ($testMinutes * 60);

        upsertProgress(
            $conn, $user_id, 'TAM',
            ($p['started_at'] ?? date('Y-m-d H:i:s')),
            date('Y-m-d H:i:s', $testEndTs),
            'test',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
            0
        );

        if ($testEndTs <= time()) {
            finalizeTAMFromProgress($user_id);
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu TAM habis. Tes otomatis diselesaikan."), true, 303);
            exit;
        }

        $p = getProgress($conn, $user_id, 'TAM');
        $endAtTs = strtotime($p['end_at'] ?? '') ?: $testEndTs;
        $phase = 'test';
    }

    // waktu test habis
    if ($phase === 'test' && $endAtTs > 0 && $endAtTs <= time()) {
        finalizeTAMFromProgress($user_id);
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu TAM habis. Tes otomatis diselesaikan."), true, 303);
        exit;
    }

    $payload = json_decode($p['payload_json'] ?? '{}', true);
    if (!is_array($payload)) $payload = [];
    $questionIds = $payload['question_ids'] ?? [];

    if (empty($questionIds)) {
        $questionIds = pickTamQuestionIds($conn, 20);
        $payload['question_ids'] = $questionIds;

        upsertProgress(
            $conn, $user_id, 'TAM',
            ($p['started_at'] ?? date('Y-m-d H:i:s')),
            ($p['end_at'] ?? date('Y-m-d H:i:s', time()+($testMinutes*60))),
            'test',
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
            0
        );
    }

    $questions = fetchTamQuestionsByIds($conn, $questionIds);

    $savedAnswers = json_decode($p['answers_json'] ?? '{}', true);
    if (!is_array($savedAnswers)) $savedAnswers = [];

    $endAtTs = (int)$endAtTs;

    activityLogOnce($conn, $user_id, 'enter_TAM_test_' . ($p['started_at'] ?? ''), 'test', 'enter', 'TAM', [
        'phase' => 'test',
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake
    ]);

    require './views/user/tam/test.php';
}

/* ============================================================
   TAM - Autosave Progress (AJAX)
   ============================================================ */
function ajaxSaveTamProgress() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TAM', true);
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $p = getProgress($conn, $user_id, 'TAM');
    if (!$p || (int)$p['is_submitted'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'no_progress']);
        exit;
    }

    if (($p['phase'] ?? '') !== 'test') {
        echo json_encode(['ok' => false, 'error' => 'not_in_test_phase']);
        exit;
    }

    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'TAM', 'tam_results');

    if (hasResultAttempt($conn, 'tam_results', $user_id, $attemptNo)) {
        echo json_encode(['ok' => true, 'ignored' => true]);
        exit;
    }

    $raw = $_POST['answers_json'] ?? '';
    $arr = json_decode($raw, true);
    if (!is_array($arr)) $arr = [];

    updateProgressAnswers($conn, $user_id, 'TAM', json_encode($arr, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true]);
    exit;
}

function finalizeTAMFromProgress(int $user_id): void {
    global $conn;

    $p = getProgress($conn, $user_id, 'TAM');
    if (!$p || (int)$p['is_submitted'] === 1) return;

    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'TAM', 'tam_results');

    if (hasResultAttempt($conn, 'tam_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'TAM');
        return;
    }

    $answers = json_decode($p['answers_json'] ?? '{}', true);
    if (!is_array($answers)) $answers = [];

    $total_correct = 0;
    $total_wrong   = 0;

    foreach ($answers as $qid => $u) {
        $qid     = (int)$qid;
        $userAns = trim((string)$u);
        if ($qid <= 0 || $userAns === '') continue;

        $correct = trim((string)TAM::getCorrect($qid));
        if ($userAns === $correct) $total_correct++;
        else $total_wrong++;
    }

    $score = $total_correct;

    saveTamResultAttempt($conn, $user_id, $attemptNo, $isRetake, $total_correct, $total_wrong, $score);

    activityLog($conn, $user_id, 'test', 'complete', 'TAM', [
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'score' => $score,
        'correct' => $total_correct,
        'wrong' => $total_wrong,
        'mode' => 'finalize_timeout'
    ]);

    markProgressSubmitted($conn, $user_id, 'TAM');
    
}



function finalizeTPAFromProgress(int $user_id): void {
    global $conn;

    $p = getProgress($conn, $user_id, 'TPA');
    if (!$p || (int)$p['is_submitted'] === 1) return;

    $payload = json_decode($p['payload_json'] ?? '[]', true);
    if (!is_array($payload)) $payload = [];

    $attemptNo = (int)($payload['attempt_no'] ?? max(1, getMaxAttemptNo($conn, 'tpa_results', $user_id)));
    if ($attemptNo <= 0) $attemptNo = 1;
    $isRetake  = (int)($payload['is_retake'] ?? ($attemptNo > 1 ? 1 : 0));

    // kalau attempt ini sudah ada result, cukup tandai submitted
    if (hasResultAttempt($conn, 'tpa_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'TPA');
        return;
    }

    $answers = json_decode($p['answers_json'] ?? '{}', true);
    if (!is_array($answers)) $answers = [];

    $questionIds = array_values(array_filter(array_map('intval', array_keys($answers)), fn($id)=>$id>0));
    $meta = !empty($questionIds) ? TPA::getQuestionsMeta($questionIds) : [];

    $totalCorrect = 0;
    $detail = [];

    foreach ($answers as $qid => $ans) {
        $qid = (int)$qid;
        if ($qid <= 0) continue;

        $ans = strtoupper(trim((string)$ans));
        $m = $meta[$qid] ?? null;
        $correctOption = $m ? strtoupper(trim((string)$m['correct_option'])) : null;

        $isCorrect = ($correctOption && $ans !== '' && $ans === $correctOption);
        if ($isCorrect) $totalCorrect++;

        $detail[] = [
            'question_id'    => $qid,
            'category'       => $m['category'] ?? null,
            'session'        => $m['session'] ?? null,
            'answer'         => $ans,
            'correct_option' => $correctOption,
            'is_correct'     => $isCorrect,
        ];
    }

    $score = $totalCorrect;
    $answersJson = json_encode($detail, JSON_UNESCAPED_UNICODE);

    // enum aman
    $categoryDb = 'verbal';
    $sessionDb  = '1';

    saveTpaResultAttempt($conn, $user_id, $attemptNo, $isRetake, $categoryDb, $sessionDb, $score, $answersJson);

    activityLog($conn, $user_id, 'test', 'complete', 'TPA', [
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'score' => $score,
        'total_correct' => $totalCorrect,
        'mode' => 'finalize_timeout'
    ]);

    markProgressSubmitted($conn, $user_id, 'TPA');
}


function ajaxForceSubmitIfExpired() {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin();
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    $finalized = autoFinalizeExpiredTests($conn, $user_id);

    echo json_encode([
        'ok' => true,
        'finalized' => $finalized,
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}


function autoFinalizeExpiredTests(mysqli $conn, int $user_id): array {
    $finalized = [];

    // ============ TPA ============
    $p = getProgress($conn, $user_id, 'TPA');
    if ($p && (int)$p['is_submitted'] === 0) {
        $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;
        if ($endAtTs > 0 && $endAtTs <= time()) {
            finalizeTPAFromProgress($user_id);
            $finalized[] = 'TPA';
        }
    }

    // ============ TAM ============
    $p = getProgress($conn, $user_id, 'TAM');
    if ($p && (int)$p['is_submitted'] === 0) {
        $package = TAM::getPackage();
        [$stimulusMinutes, $testMinutes] = tamDurations(is_array($package) ? $package : []);

        $phase   = (string)($p['phase'] ?? 'stimulus');
        $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

        if ($endAtTs > 0 && $endAtTs <= time()) {
            if ($phase === 'stimulus') {
                // stimulus selesai -> transisi ke test (STRICT)
                $payload = json_decode($p['payload_json'] ?? '{}', true);
                if (!is_array($payload)) $payload = [];
                if (empty($payload['question_ids'])) $payload['question_ids'] = pickTamQuestionIds($conn, 20);

                $testEndTs = $endAtTs + ($testMinutes * 60);
                upsertProgress(
                    $conn, $user_id, 'TAM',
                    ($p['started_at'] ?? date('Y-m-d H:i:s')),
                    date('Y-m-d H:i:s', $testEndTs),
                    'test',
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ($p['answers_json'] ?? json_encode(new stdClass())),
                    0
                );

                if ($testEndTs <= time()) {
                    finalizeTAMFromProgress($user_id);
                    $finalized[] = 'TAM';
                }
            } else if ($phase === 'test') {
                finalizeTAMFromProgress($user_id);
                $finalized[] = 'TAM';
            }
        }
    }

    // ============ KRAEPLIN ============
    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if ($p && (int)$p['is_submitted'] === 0) {
        $phase   = (string)($p['phase'] ?? 'trial');
        $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

        if ($endAtTs > 0 && $endAtTs <= time()) {
            if ($phase === 'trial') {
                $settings = Kraeplin::getSettings();
                $mainMinutes = kraeplinMainMinutesFromSettings(is_array($settings) ? $settings : []);

                $payload = json_decode($p['payload_json'] ?? '{}', true);
                if (!is_array($payload)) $payload = [];

                if (empty($payload['seed'])) $payload['seed'] = kraeplinSeed();
                if (empty($payload['main_minutes'])) $payload['main_minutes'] = $mainMinutes;

                $trialEndTs = $endAtTs;
                $testEndTs  = $trialEndTs + ((int)$payload['main_minutes'] * 60);

                upsertProgress(
                    $conn, $user_id, 'KRAEPLIN',
                    date('Y-m-d H:i:s', $trialEndTs),
                    date('Y-m-d H:i:s', $testEndTs),
                    'test',
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ($p['answers_json'] ?? json_encode(new stdClass())),
                    0
                );

                if ($testEndTs <= time()) {
                    finalizeKraeplinFromProgress($user_id);
                    $finalized[] = 'KRAEPLIN';
                }
            } else if ($phase === 'test') {
                finalizeKraeplinFromProgress($user_id);
                $finalized[] = 'KRAEPLIN';
            }
        }
    }

    return array_values(array_unique($finalized));
}


/* ============================================================
   TAM - Submit & Autoscore
   ============================================================ */
function submitTAM()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('TAM');
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int) $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Metode tidak valid."));
        exit;
    }

    if (!hasResult($conn, 'tpa_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM terkunci. Selesaikan TPA terlebih dahulu."));
        exit;
    }

    $p = getProgress($conn, $user_id, 'TAM');
    if (!$p || (int)$p['is_submitted'] === 1) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Progress TAM tidak ditemukan."));
        exit;
    }

    if (($p['phase'] ?? '') !== 'test') {
        header("Location: index.php?page=user-tam-stimulus", true, 303);
        exit;
    }

    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'TAM', 'tam_results');

    if (hasResultAttempt($conn, 'tam_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'TAM');
        acClearAttemptId('TAM');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("TAM sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    $answers = (isset($_POST['answer']) && is_array($_POST['answer'])) ? $_POST['answer'] : [];
    if (empty($answers)) {
        $saved = json_decode($p['answers_json'] ?? '{}', true);
        if (is_array($saved)) $answers = $saved;
    }

    $total_correct = 0;
    $total_wrong   = 0;

    foreach ($answers as $qid => $u) {
        $qid     = (int)$qid;
        $userAns = trim((string)$u);
        if ($qid <= 0 || $userAns === '') continue;

        $correct = trim((string)TAM::getCorrect($qid));
        if ($userAns === $correct) $total_correct++;
        else $total_wrong++;
    }

    $score = $total_correct;

    saveTamResultAttempt($conn, $user_id, $attemptNo, $isRetake, $total_correct, $total_wrong, $score);

    activityLog($conn, $user_id, 'test', 'complete', 'TAM', [
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'score' => $score,
        'correct' => $total_correct,
        'wrong' => $total_wrong,
        'mode' => 'submit'
    ]);

    markProgressSubmitted($conn, $user_id, 'TAM');
    acClearAttemptId('TAM');
    header("Location: index.php?page=user-dashboard&msg=" . urlencode("Tes TAM selesai!"), true, 303);
    exit;
}



// KRAEEPLIN HELPER
function kraeplinMainMinutesFromSettings($settings): int {
    $m = (int)($settings['duration'] ?? 15);
    if ($m <= 0) $m = 15;
    // optional clamp sesuai requirement admin (misal 5–30)
    if ($m < 5)  $m = 5;
    if ($m > 30) $m = 30;
    return $m;
}

function kraeplinSeed(): string {
    try { return bin2hex(random_bytes(8)); }
    catch (\Throwable $e) { return bin2hex((string)microtime(true)); }
}

/* ============================================================
   KRAEPLIN - Trial (1 menit) & Start
   ============================================================ */
function userKraeplinStart() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin();
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    // Prasyarat: TAM harus selesai
    if (!hasResult($conn, 'tam_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin terkunci. Selesaikan TAM terlebih dahulu."));
        exit;
    }

    $settings = Kraeplin::getSettings();
    $mainMinutes = kraeplinMainMinutesFromSettings(is_array($settings) ? $settings : []);
    $intervalSeconds = kraeplinIntervalSecondsFromSettings(is_array($settings) ? $settings : []);
    // Kalau masih ada progress aktif (belum submit) => RESUME, jangan consume kuota lagi
    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if ($p && (int)$p['is_submitted'] === 0) {

        // repair payload attempt jika belum ada
        [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'KRAEPLIN', 'kraeplin_results');

        $phase   = (string)($p['phase'] ?? 'trial');
        $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

        // repair end_at jika kosong supaya tidak “geser”
        if ($endAtTs <= 0) {
            $fixedEnd = date('Y-m-d H:i:s', time() + 60);
            upsertProgress(
                $conn, $user_id, 'KRAEPLIN',
                ($p['started_at'] ?? date('Y-m-d H:i:s')),
                $fixedEnd,
                $phase ?: 'trial',
                ($p['payload_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                0
            );
            $endAtTs = strtotime($fixedEnd);
        }

        // sudah masuk test
        if ($phase === 'test') {
            if ($endAtTs <= time()) {
                finalizeKraeplinFromProgress($user_id);
                header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu Kraeplin habis. Tes otomatis diselesaikan."), true, 303);
                exit;
            }
            header("Location: index.php?page=user-kraeplin-test", true, 303);
            exit;
        }

        // trial selesai -> transisi ke test (STRICT)
        if ($endAtTs <= time()) {
            $payload = json_decode($p['payload_json'] ?? '{}', true);
            if (!is_array($payload)) $payload = [];

            if (empty($payload['seed'])) $payload['seed'] = kraeplinSeed();
            $payload['main_minutes'] = $mainMinutes;

            // pastikan attempt info ada
            $payload['attempt_no'] = (int)($payload['attempt_no'] ?? $attemptNo);
            $payload['is_retake']  = (int)($payload['is_retake']  ?? $isRetake);

            $trialEndTs = $endAtTs;
            $testEndTs  = $trialEndTs + ($mainMinutes * 60);

            upsertProgress(
                $conn, $user_id, 'KRAEPLIN',
                date('Y-m-d H:i:s', $trialEndTs),
                date('Y-m-d H:i:s', $testEndTs),
                'test',
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                0
            );

            if ($testEndTs <= time()) {
                finalizeKraeplinFromProgress($user_id);
                header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu Kraeplin habis. Tes otomatis diselesaikan."), true, 303);
                exit;
            }

            header("Location: index.php?page=user-kraeplin-test", true, 303);
            exit;
        }

        $endAtTs = (int)$endAtTs;

        activityLogOnce($conn, $user_id, 'enter_KRAEPLIN_trial_' . ($p['started_at'] ?? ''), 'test', 'enter', 'KRAEPLIN', [
            'phase' => 'trial',
            'attempt_no' => $attemptNo,
            'is_retake'  => $isRetake
        ]);

        require './views/user/kraeplin/trial.php';
        exit;
    }

    // ====== Tidak ada progress aktif => START BARU (first / retake) ======
    $hasAnyResult = hasResult($conn, 'kraeplin_results', $user_id);
    $isRetakeReq  = (isset($_GET['retake']) && $_GET['retake'] === '1');

    // wajib ack untuk start baru (first/retake)
    requireAckOrBackToDashboard("Silakan baca panduan Kraeplin dan klik Mengerti sebelum memulai.");
     acClearAttemptId('KRAEPLIN');



    $attemptNo = 1;
    $isRetake  = 0;

    if ($hasAnyResult) {
        if (!$isRetakeReq) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin sudah selesai. Jika ingin mengulang, gunakan tombol Ulangi di Dashboard."));
            exit;
        }

        $nextAttempt = consumeRetakeQuotaAndNextAttempt($conn, $user_id, 'KRAEPLIN');
        if (!$nextAttempt) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kuota ulang Kraeplin tidak tersedia."));
            exit;
        }

        $attemptNo = (int)$nextAttempt;
        $isRetake  = 1;

        if (function_exists('activityLog')) {
            activityLog($conn, $user_id, 'test', 'retake_begin', 'KRAEPLIN', [
                'attempt_no' => $attemptNo
            ]);
        }
    }

    // buat progress trial 1 menit
    $started_at = date('Y-m-d H:i:s');
    $end_at     = date('Y-m-d H:i:s', time() + 60);

    $payload = [
        'seed'         => kraeplinSeed(),
        'main_minutes' => $mainMinutes,
        'attempt_no'   => $attemptNo,
        'is_retake'    => $isRetake,
    ];

    upsertProgress(
        $conn, $user_id, 'KRAEPLIN',
        $started_at, $end_at,
        'trial',
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        json_encode([], JSON_UNESCAPED_UNICODE),
        0
    );

    $endAtTs = strtotime($end_at) ?: (time() + 60);
    require './views/user/kraeplin/trial.php';
}




/* ============================================================
   KRAEPLIN - Tes utama (pakai setting admin)
   ============================================================ */
function userKraeplinTest() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin();
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if (!hasResult($conn, 'tam_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin terkunci. Selesaikan TAM terlebih dahulu."));
        exit;
    }

    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if (!$p || (int)$p['is_submitted'] === 1) {
        // kalau memang sudah ada hasil -> arahkan ke dashboard
        if (hasResult($conn, 'kraeplin_results', $user_id)) {
            header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin sudah selesai."), true, 303);
            exit;
        }
        header("Location: index.php?page=user-kraeplin-start", true, 303);
        exit;
    }

    // auto finalize jika expired
    $finalized = autoFinalizeExpiredTests($conn, $user_id);
    if (in_array('KRAEPLIN', $finalized, true)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu Kraeplin habis. Tes otomatis diselesaikan."), true, 303);
        exit;
    }

    // attempt info dari progress
    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'KRAEPLIN', 'kraeplin_results');

    // kalau attempt ini sudah punya result (misal double submit)
    if (hasResultAttempt($conn, 'kraeplin_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    $phase   = (string)($p['phase'] ?? 'trial');
    $endAtTs = strtotime($p['end_at'] ?? '') ?: 0;

    if ($phase === 'trial') {
        header("Location: index.php?page=user-kraeplin-start", true, 303);
        exit;
    }

    if ($endAtTs > 0 && $endAtTs <= time()) {
        finalizeKraeplinFromProgress($user_id);
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Waktu Kraeplin habis. Tes otomatis diselesaikan."), true, 303);
        exit;
    }

    $payload = json_decode($p['payload_json'] ?? '{}', true);
    if (!is_array($payload)) $payload = [];
    $seed = (string)($payload['seed'] ?? kraeplinSeed());

    $savedLines = json_decode($p['answers_json'] ?? '[]', true);
    if (!is_array($savedLines)) $savedLines = [];

    $endAtTs = (int)$endAtTs;
    $settings = Kraeplin::getSettings();

    activityLogOnce($conn, $user_id, 'enter_KRAEPLIN_test_' . ($p['started_at'] ?? ''), 'test', 'enter', 'KRAEPLIN', [
        'phase' => 'test',
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake
    ]);

    require './views/user/kraeplin/test.php';
}

// FUNGSI RETAKE SEMUA DISINI HEELPERNYA

/* ============================================================
   RETAKE & ATTEMPT HELPERS (multi-attempt, no delete results)
   ============================================================ */

function wantsRetake(): bool {
    return isset($_GET['retake']) && $_GET['retake'] === '1';
}

/**
 * Whitelist result tables (avoid SQL injection on table name).
 */
function assertResultTable(string $table): string {
    $allowed = ['tpa_results', 'tam_results', 'kraeplin_results'];
    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException("Invalid table");
    }
    return $table;
}


function nextAttemptNo(mysqli $conn, string $resultTable, int $userId): int {
    $m = getMaxAttemptNo($conn, $resultTable, $userId);
    return max(1, $m + 1);
}

function decodeProgressPayload(?array $p): array {
    if (!$p) return [];
    $raw = (string)($p['payload_json'] ?? '');
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

/**
 * Extract attempt meta from progress payload.
 * Backward compatible:
 * - If old payload format (TPA sections array), attempt_no defaults to 1.
 */
function progressAttemptMeta(?array $p): array {
    $payload = decodeProgressPayload($p);

    $attemptNo = 1;
    $isRetake  = 0;

    if (isset($payload['attempt_no'])) $attemptNo = (int)$payload['attempt_no'];
    if (isset($payload['is_retake']))  $isRetake  = (int)$payload['is_retake'];

    return [
        'attempt_no' => max(1, $attemptNo),
        'is_retake'  => $isRetake ? 1 : 0,
        'payload'    => $payload
    ];
}

/**
 * Consume 1 retake quota ONLY (no deleting results).
 * Atomic with row lock.
 */
function consumeRetakeQuota(mysqli $conn, int $userId, string $testType): bool {
    $map = [
        'TPA'      => 'tpa_retake_quota',
        'TAM'      => 'tam_retake_quota',
        'KRAEPLIN' => 'kraeplin_retake_quota',
    ];

    $testType = strtoupper(trim($testType));
    if (!isset($map[$testType])) return false;

    $quotaCol = $map[$testType];

    $conn->begin_transaction();
    try {
        $sql = "SELECT {$quotaCol} AS q FROM users WHERE id=? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $q = (int)($row['q'] ?? 0);
        if ($q <= 0) {
            $conn->rollback();
            return false;
        }

        $sql = "UPDATE users SET {$quotaCol} = {$quotaCol} - 1 WHERE id=? AND {$quotaCol} > 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        if (function_exists('activityLog')) {
            activityLog($conn, $userId, 'test', 'retake_quota_consumed', $testType, [
                'test_type' => $testType,
                'quota_col' => $quotaCol
            ]);
        }

        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

/* =========================
   INSERT RESULT HELPERS
   (bypass model so attempt_no/is_retake is stored correctly)
   ========================= */

function insertTpaResultAttempt(
    mysqli $conn,
    int $userId,
    int $attemptNo,
    int $isRetake,
    string $categoryDb,
    string $sessionDb,
    int $score,
    string $answersJson
): void {
    $attemptNo = max(1, (int)$attemptNo);
    $isRetake  = $isRetake ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO tpa_results (user_id, attempt_no, is_retake, category, session, score, answers)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiissis', $userId, $attemptNo, $isRetake, $categoryDb, $sessionDb, $score, $answersJson);
    $stmt->execute();
    $stmt->close();
}

function insertTamResultAttempt(
    mysqli $conn,
    int $userId,
    int $attemptNo,
    int $isRetake,
    int $totalCorrect,
    int $totalWrong,
    int $score
): void {
    $attemptNo = max(1, (int)$attemptNo);
    $isRetake  = $isRetake ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO tam_results (user_id, attempt_no, is_retake, total_correct, total_wrong, score)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiiiii', $userId, $attemptNo, $isRetake, $totalCorrect, $totalWrong, $score);
    $stmt->execute();
    $stmt->close();
}

function insertKraeplinResultAttempt(
    mysqli $conn,
    int $userId,
    int $attemptNo,
    int $isRetake,
    int $totalProductivity,
    int $totalCorrect,
    float $accuracyPercentage,
    float $stabilityScore,
    string $concentrationTrend,
    float $adaptationScore,
    string $workPattern,
    string $rawLinesJson
): void {
    $attemptNo = max(1, (int)$attemptNo);
    $isRetake  = $isRetake ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO kraeplin_results (
            user_id, attempt_no, is_retake,
            total_productivity, total_correct, accuracy_percentage, stability_score,
            concentration_trend, adaptation_score, work_pattern, raw_lines
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iiiiiddsdds',
        $userId, $attemptNo, $isRetake,
        $totalProductivity, $totalCorrect, $accuracyPercentage, $stabilityScore,
        $concentrationTrend, $adaptationScore, $workPattern, $rawLinesJson
    );
    $stmt->execute();
    $stmt->close();
}

// =========================
// Attempt/Retake Helpers
// =========================
if (!function_exists('getMaxAttemptNo')) {
    function getMaxAttemptNo(mysqli $conn, string $table, int $userId): int {
        $sql = "SELECT COALESCE(MAX(attempt_no),0) AS m FROM {$table} WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['m'] ?? 0);
    }
}

if (!function_exists('hasResultAttempt')) {
    function hasResultAttempt(mysqli $conn, string $table, int $userId, int $attemptNo): bool {
        $sql = "SELECT 1 FROM {$table} WHERE user_id=? AND attempt_no=? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $userId, $attemptNo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return (bool)$row;
    }
}

if (!function_exists('consumeRetakeQuotaAndNextAttempt')) {
    /**
     * Decrement kuota retake dan kembalikan attempt_no berikutnya (max+1).
     * Return null kalau kuota tidak tersedia.
     */
    function consumeRetakeQuotaAndNextAttempt(mysqli $conn, int $userId, string $testType): ?int {
        $testType = strtoupper(trim($testType));
        $map = [
            'TPA'      => ['quota_col'=>'tpa_retake_quota',      'result_table'=>'tpa_results'],
            'TAM'      => ['quota_col'=>'tam_retake_quota',      'result_table'=>'tam_results'],
            'KRAEPLIN' => ['quota_col'=>'kraeplin_retake_quota', 'result_table'=>'kraeplin_results'],
        ];
        if (!isset($map[$testType])) return null;

        $quotaCol = $map[$testType]['quota_col'];
        $resTable = $map[$testType]['result_table'];

        $conn->begin_transaction();
        try {
            // lock row user
            $stmt = $conn->prepare("SELECT {$quotaCol} AS q FROM users WHERE id=? FOR UPDATE");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $q = (int)($row['q'] ?? 0);
            if ($q <= 0) { $conn->rollback(); return null; }

            // lock rows result user (stabilkan MAX)
            $stmt = $conn->prepare("SELECT COALESCE(MAX(attempt_no),0) AS m FROM {$resTable} WHERE user_id=? FOR UPDATE");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row2 = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $nextAttempt = ((int)($row2['m'] ?? 0)) + 1;

            // decrement kuota
            $stmt = $conn->prepare("UPDATE users SET {$quotaCol}={$quotaCol}-1 WHERE id=? AND {$quotaCol}>0");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return $nextAttempt;
        } catch (Throwable $e) {
            $conn->rollback();
            return null;
        }
    }
}

// =========================
// SAVE RESULT (attempt-based)
// =========================
function saveTpaResultAttempt(
    mysqli $conn,
    int $userId,
    int $attemptNo,
    int $isRetake,
    string $category,
    string $session,
    int $score,
    string $answersJson
): void {
    $sql = "INSERT INTO tpa_results (user_id, attempt_no, is_retake, category, session, score, answers)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiissis', $userId, $attemptNo, $isRetake, $category, $session, $score, $answersJson);
    $stmt->execute();
    $stmt->close();
}

function saveTamResultAttempt(
    mysqli $conn,
    int $userId,
    int $attemptNo,
    int $isRetake,
    int $totalCorrect,
    int $totalWrong,
    int $score
): void {
    $sql = "INSERT INTO tam_results (user_id, attempt_no, is_retake, total_correct, total_wrong, score)
            VALUES (?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiii', $userId, $attemptNo, $isRetake, $totalCorrect, $totalWrong, $score);
    $stmt->execute();
    $stmt->close();
}


if (!function_exists('getAttemptInfoFromProgress')) {
    /**
     * Ambil attempt_no & is_retake dari payload_json progress.
     * Kalau progress lama belum punya field ini, akan “repair” payload otomatis.
     */
    function getAttemptInfoFromProgress(mysqli $conn, int $userId, string $testType, string $resultTable): array {
        $p = getProgress($conn, $userId, $testType);
        $payload = [];
        if ($p) {
            $payload = json_decode($p['payload_json'] ?? '{}', true);
            if (!is_array($payload)) $payload = [];
        }

        $attemptNo = (int)($payload['attempt_no'] ?? 0);
        $isRetake  = (int)($payload['is_retake'] ?? 0);

        if ($attemptNo <= 0) {
            $max = getMaxAttemptNo($conn, $resultTable, $userId);
            $attemptNo = max(1, $max > 0 ? $max : 1);
            $isRetake  = ($attemptNo > 1) ? 1 : 0;

            if ($p) {
                $payload['attempt_no'] = $attemptNo;
                $payload['is_retake']  = $isRetake;

                upsertProgress(
                    $conn, $userId, $testType,
                    ($p['started_at'] ?? date('Y-m-d H:i:s')),
                    ($p['end_at'] ?? date('Y-m-d H:i:s', time()+60)),
                    ($p['phase'] ?? null),
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ($p['answers_json'] ?? json_encode(new stdClass(), JSON_UNESCAPED_UNICODE)),
                    (int)($p['is_submitted'] ?? 0)
                );
            }
        }

        return [$attemptNo, $isRetake];
    }
}

// SELESAI HELPER RETAKE DISINI


function ajaxSaveKraeplinProgress() {
    header('Content-Type: application/json; charset=utf-8');

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin('KRAEPLIN', true);
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if (!$p || (int)$p['is_submitted'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'no_progress']);
        exit;
    }

    if (($p['phase'] ?? '') !== 'test') {
        echo json_encode(['ok' => false, 'error' => 'not_in_test_phase']);
        exit;
    }

    // attempt info
    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'KRAEPLIN', 'kraeplin_results');

    // kalau attempt ini sudah tersimpan, ignore (bukan berdasarkan hasResult global)
    if (hasResultAttempt($conn, 'kraeplin_results', $user_id, $attemptNo)) {
        echo json_encode(['ok' => true, 'ignored' => true]);
        exit;
    }

    $raw = $_POST['raw_lines'] ?? $_POST['raw_lines_json'] ?? '[]';
    $lines = json_decode($raw, true);
    if (!is_array($lines)) $lines = [];

    updateProgressAnswers($conn, $user_id, 'KRAEPLIN', json_encode($lines, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true]);
    exit;
}


function kraeplinClampMinutes(int $m): int {
    if ($m <= 0) $m = 15;
    if ($m < 5)  $m = 5;
    if ($m > 30) $m = 30;
    return $m;
}

function kraeplinExpectedIntervals(int $durationMinutes, int $intervalSeconds): int {
    $durationMinutes  = max(1, $durationMinutes);
    $intervalSeconds  = max(1, $intervalSeconds);
    return max(1, (int)ceil(($durationMinutes * 60) / $intervalSeconds));
}

function kraeplinTrimLines(array $lines, int $durationMinutes, int $intervalSeconds): array {
    $intervalSeconds = max(1, (int)$intervalSeconds);
    $durationMinutes = max(1, (int)$durationMinutes);

    $maxIntervals = (int)ceil(($durationMinutes * 60) / $intervalSeconds);
    if ($maxIntervals < 1) $maxIntervals = 1;

    $isZeroInterval = function($it) {
        $total   = (int)($it['total_items'] ?? 0);
        $correct = (int)($it['correct'] ?? 0);
        $wrong   = (int)($it['wrong'] ?? 0);
        return ($total === 0 && $correct === 0 && $wrong === 0);
    };

    // buang trailing auto-0 (padding) lalu potong ke maxIntervals
    while (count($lines) > $maxIntervals) {
        $last = end($lines);
        if ($last && $isZeroInterval($last)) array_pop($lines);
        else break;
    }

    if (count($lines) > $maxIntervals) {
        $lines = array_slice($lines, 0, $maxIntervals);
    }

    return $lines;
}



function kraeplinSum(array $lines): array {
    $total_items = 0; $total_correct = 0; $total_wrong = 0;
    foreach ($lines as $it) {
        $total_items   += (int)($it['total_items'] ?? 0);
        $total_correct += (int)($it['correct'] ?? 0);
        $total_wrong   += (int)($it['wrong'] ?? 0);
    }
    return [$total_items, $total_correct, $total_wrong];
}


function finalizeKraeplinFromProgress(int $user_id): void {
    global $conn;

    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if (!$p || (int)$p['is_submitted'] === 1) return;
    if (($p['phase'] ?? '') !== 'test') return;

    // attempt info
    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'KRAEPLIN', 'kraeplin_results');

    // kalau attempt ini sudah punya result, cukup tandai submitted
    if (hasResultAttempt($conn, 'kraeplin_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
        return;
    }

    $payload = json_decode($p['payload_json'] ?? '{}', true);
    if (!is_array($payload)) $payload = [];

    // Durasi
    $settings = Kraeplin::getSettings();
    $durationMinutes = kraeplinMainMinutesFromSettings(is_array($settings) ? $settings : []);
    if (!empty($payload['main_minutes'])) $durationMinutes = (int)$payload['main_minutes'];
    $durationMinutes = kraeplinClampMinutes($durationMinutes);

    $lines = json_decode($p['answers_json'] ?? '[]', true);
    if (!is_array($lines)) $lines = [];

    $intervalSeconds = (int)($payload['interval_seconds'] ?? kraeplinIntervalSecondsFromSettings($settings));
$lines = kraeplinTrimLines($lines, $durationMinutes, $intervalSeconds);

    saveKraeplinFromLines($conn, $user_id, $lines, $durationMinutes, $intervalSeconds, $attemptNo, $isRetake);


    [$total_items, $total_correct, $total_wrong] = kraeplinSum($lines);
    activityLog($conn, $user_id, 'test', 'complete', 'KRAEPLIN', [
        'mode' => 'finalize_timeout',
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'duration_minutes' => $durationMinutes,
        'total_items' => $total_items,
        'total_correct' => $total_correct,
        'total_wrong' => $total_wrong
    ]);

    markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
}

function submitKraeplin() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    requireUserLogin();
    sendNoCacheHeaders();

    global $conn;
    $user_id = (int)$_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Metode tidak valid."));
        exit;
    }

    if (!hasResult($conn, 'tam_results', $user_id)) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin terkunci. Selesaikan TAM terlebih dahulu."));
        exit;
    }

    $p = getProgress($conn, $user_id, 'KRAEPLIN');
    if (!$p || (int)$p['is_submitted'] === 1) {
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Progress Kraeplin tidak ditemukan."), true, 303);
        exit;
    }

    // attempt info
    [$attemptNo, $isRetake] = getAttemptInfoFromProgress($conn, $user_id, 'KRAEPLIN', 'kraeplin_results');

    // anti double submit untuk attempt yang sama
    if (hasResultAttempt($conn, 'kraeplin_results', $user_id, $attemptNo)) {
        markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
        acClearAttemptId('KRAEPLIN');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Kraeplin sudah tersimpan (Attempt #{$attemptNo})."), true, 303);
        exit;
    }

    // Durasi: settings -> override payload main_minutes
    $payload = json_decode($p['payload_json'] ?? '{}', true);
    if (!is_array($payload)) $payload = [];

    $settings = Kraeplin::getSettings();
    $durationMinutes = kraeplinMainMinutesFromSettings(is_array($settings) ? $settings : []);
    if (!empty($payload['main_minutes'])) $durationMinutes = (int)$payload['main_minutes'];
    $durationMinutes = kraeplinClampMinutes($durationMinutes);

    // Ambil raw_lines dari POST; jika kosong fallback progress
    $raw = $_POST['raw_lines'] ?? '';
    $lines = json_decode($raw, true);
    if (!is_array($lines)) $lines = [];

    if (empty($lines)) {
        $saved = json_decode($p['answers_json'] ?? '[]', true);
        if (is_array($saved)) $lines = $saved;
    }

    // intervalSeconds wajib masuk SEBELUM attemptNo/isRetake
    $intervalSeconds = (int)($payload['interval_seconds'] ?? kraeplinIntervalSecondsFromSettings($settings));
    $lines = kraeplinTrimLines($lines, $durationMinutes, $intervalSeconds);

    if (empty($lines)) {
        // simpan hasil 0 untuk attempt ini (urutan param BENAR)
        saveKraeplinFromLines($conn, $user_id, [], $durationMinutes, $intervalSeconds, $attemptNo, $isRetake);

        activityLog($conn, $user_id, 'test', 'complete', 'KRAEPLIN', [
            'mode' => 'submit_empty',
            'attempt_no' => $attemptNo,
            'is_retake'  => $isRetake,
            'duration_minutes' => $durationMinutes
        ]);

        markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
        acClearAttemptId('KRAEPLIN');
        header("Location: index.php?page=user-dashboard&msg=" . urlencode("Tes Kraeplin tidak terbaca, hasil dianggap 0."), true, 303);
        exit;
    }

    // simpan normal (urutan param BENAR)
    saveKraeplinFromLines($conn, $user_id, $lines, $durationMinutes, $intervalSeconds, $attemptNo, $isRetake);

    [$total_items, $total_correct, $total_wrong] = kraeplinSum($lines);
    activityLog($conn, $user_id, 'test', 'complete', 'KRAEPLIN', [
        'mode' => 'submit',
        'attempt_no' => $attemptNo,
        'is_retake'  => $isRetake,
        'duration_minutes' => $durationMinutes,
        'total_items' => $total_items,
        'total_correct' => $total_correct,
        'total_wrong' => $total_wrong
    ]);

    markProgressSubmitted($conn, $user_id, 'KRAEPLIN');
    acClearAttemptId('KRAEPLIN');
    header("Location: index.php?page=user-dashboard&msg=" . urlencode("Tes Kraeplin selesai!"), true, 303);
    exit;
}


/**
 * Simpan hasil Kraeplin + attempt_no/is_retake (tanpa pakai model, supaya attempt tercatat benar).
 */
function saveKraeplinFromLines(
  mysqli $conn,
  int $user_id,
  array $lines,
  int $durationMinutes,
  int $intervalSeconds,
  int $attemptNo = 1,
  int $isRetake = 0
): void {
    $lines = kraeplinTrimLines($lines, $durationMinutes, $intervalSeconds);
    $raw_lines = json_encode($lines, JSON_UNESCAPED_UNICODE);

    $total_items = 0;
    $total_correct = 0;
    foreach ($lines as $it) {
        $total_items   += (int)($it['total_items'] ?? 0);
        $total_correct += (int)($it['correct'] ?? 0);
    }
    $accuracy = $total_items > 0 ? ($total_correct / $total_items) * 100 : 0;

    $arr = array_map(fn($it) => (int)($it['total_items'] ?? 0), $lines);
    $n = count($arr);
    $mean = $n > 0 ? array_sum($arr) / $n : 0;

    $variance = 0;
    if ($n > 0) {
        foreach ($arr as $v) $variance += pow(($v - $mean), 2);
        $variance = $variance / $n;
    }

    $first3 = array_slice($arr, 0, 3);
    $last3  = array_slice($arr, -3);

    $trend_val = 0;
    if (!empty($first3) && !empty($last3)) {
        $trend_val = (array_sum($last3) / max(count($last3), 1)) - (array_sum($first3) / max(count($first3), 1));
    }

    if ($trend_val > 0)      $trend = "meningkat";
    else if ($trend_val < 0) $trend = "menurun";
    else                     $trend = "stabil";

    $adapt = 0;
    if ($n >= 6) {
        $mid3 = array_slice($arr, 3, 3);
        $adapt = (array_sum($mid3) / 3) - (array_sum($first3) / 3);
    }

    $pattern = "zig-zag";
    $isNonDecreasing = true;
    $isNonIncreasing = true;
    for ($i = 1; $i < $n; $i++) {
        if ($arr[$i] < $arr[$i-1]) $isNonDecreasing = false;
        if ($arr[$i] > $arr[$i-1]) $isNonIncreasing = false;
    }
    if ($isNonDecreasing) $pattern = "naik";
    else if ($isNonIncreasing) $pattern = "menurun";

    // INSERT dengan attempt_no & is_retake
    $sql = "INSERT INTO kraeplin_results
            (user_id, attempt_no, is_retake, total_productivity, total_correct, accuracy_percentage, stability_score, concentration_trend, adaptation_score, work_pattern, raw_lines)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);

    $attemptNo = (int)$attemptNo;
    $isRetake  = (int)$isRetake;
    $total_items = (int)$total_items;
    $total_correct = (int)$total_correct;

    $stmt->bind_param(
        'iiiiiddsdss',
        $user_id, $attemptNo, $isRetake,
        $total_items, $total_correct,
        $accuracy, $variance,
        $trend, $adapt,
        $pattern, $raw_lines
    );
    $stmt->execute();
    $stmt->close();
}



