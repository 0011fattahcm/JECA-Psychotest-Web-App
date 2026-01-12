<?php
require_once './config/database.php';
require_once './models/User.php';
require_once './models/TPA.php';
require_once './models/TAM.php';
require_once './models/Kraeplin.php';


// =========================
// Helper: bind params dinamis
// =========================
if (!function_exists('bindParams')) {
    function bindParams(mysqli_stmt $stmt, string $types, array $params): void {
        if ($types === '' || empty($params)) return;

        $bind = [];
        $bind[] = $types;
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('requireComposerAutoload')) {
    function requireComposerAutoload(): void {
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) return;

        $candidates = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            dirname(__DIR__) . '/vendor/autoload.php',
        ];

        foreach ($candidates as $p) {
            if (file_exists($p)) {
                require_once $p;
                return;
            }
        }

        die("Composer autoload.php tidak ditemukan. Pastikan folder vendor ada dan composer install sudah dijalankan.");
    }
}

if (!function_exists('buildDateWhere')) {
    function buildDateWhere(string $from, string $to, string $col = 'created_at', string $prefix = ' WHERE 1=1 '): array {
        $where  = $prefix;
        $types  = '';
        $params = [];

        if ($from !== '') {
            $where .= " AND {$col} >= ? ";
            $types .= "s";
            $params[] = $from . " 00:00:00";
        }
        if ($to !== '') {
            $where .= " AND {$col} <= ? ";
            $types .= "s";
            $params[] = $to . " 23:59:59";
        }

        return [$where, $types, $params];
    }
}

if (!function_exists('latestPerUserJoins')) {
    /**
     * Ambil 1 row TERBARU per user dari sebuah results table.
     * $dateWhere wajib pakai kolom "created_at" TANPA alias (untuk subquery).
     */
    function latestPerUserJoins(string $table, string $dateWhere, string $alias = 'r'): string
    {
        return "
            JOIN (
                SELECT user_id, MAX(created_at) AS last_created
                FROM {$table}
                {$dateWhere}
                GROUP BY user_id
            ) lc ON lc.user_id = {$alias}.user_id AND lc.last_created = {$alias}.created_at

            JOIN (
                SELECT user_id, created_at, MAX(id) AS last_id
                FROM {$table}
                {$dateWhere}
                GROUP BY user_id, created_at
            ) li ON li.user_id = {$alias}.user_id
               AND li.created_at = {$alias}.created_at
               AND li.last_id = {$alias}.id
        ";
    }
}

/*
 |--------------------------------------------------------------------------
 | DEVELOPMENT MODE – NO SECURITY
 |--------------------------------------------------------------------------
 | Semua pengecekan session admin_id DIHAPUS agar tidak mengganggu
 | proses pembangunan sistem. Nanti jika sistem sudah selesai, kita 
 | bisa mengaktifkan kembali security login admin.

 |--------------------------------------------------------------------------
*/


/* =============================
   ADMIN LOGIN PAGE (NO SECURITY)
   ============================= */

function adminLoginPage()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();

    if (!empty($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-dashboard", true, 303);
        exit;
    }

    $error = $_GET['error'] ?? '';
    $error = preg_replace('/[^a-z0-9_\-]/i', '', (string)$error);

    require __DIR__ . '/../views/admin/login.php';
    exit;
}

function adminLoginProcess()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: index.php?page=admin-login&error=method", true, 303);
        exit;
    }

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        header("Location: index.php?page=admin-login&error=csrf", true, 303);
        exit;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        header("Location: index.php?page=admin-login&error=empty", true, 303);
        exit;
    }

    // Throttle sederhana berbasis session (tanpa ubah DB)
    if (!isset($_SESSION['_admin_login_fail'])) $_SESSION['_admin_login_fail'] = 0;
    if (!isset($_SESSION['_admin_login_lock_until'])) $_SESSION['_admin_login_lock_until'] = 0;

    $lockUntil = (int)$_SESSION['_admin_login_lock_until'];
    if ($lockUntil > time()) {
        header("Location: index.php?page=admin-login&error=locked", true, 303);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    // jangan bocorkan apakah user ada / tidak
    if (!$admin || !password_verify($password, (string)$admin['password'])) {
        $_SESSION['_admin_login_fail'] = (int)$_SESSION['_admin_login_fail'] + 1;

        // 5x salah -> lock 10 menit
        if ((int)$_SESSION['_admin_login_fail'] >= 5) {
            $_SESSION['_admin_login_fail'] = 0;
            $_SESSION['_admin_login_lock_until'] = time() + (10 * 60);
        }

        usleep(250000); // delay kecil anti brute force
        header("Location: index.php?page=admin-login&error=invalid", true, 303);
        exit;
    }

    // sukses
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = (string)$admin['username'];

    // reset throttle
    $_SESSION['_admin_login_fail'] = 0;
    $_SESSION['_admin_login_lock_until'] = 0;

    header("Location: index.php?page=admin-dashboard", true, 303);
    exit;
}

function requireAdminLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-login&error=auth", true, 303);
        exit;
    }
}
/* =============================
   ADMIN DASHBOARD
   ============================= */
function adminDashboard()
{
    requireAdminLogin();
    global $conn;
      require_once './controllers/TestWindowHelper.php';
    $testWindow = getTestWindowSetting($conn);
    $isOpenNow  = isTestWindowOpenNow($conn);


    // ---------- Helper ----------
    $scalar = function ($sql) use ($conn) {
        $res = $conn->query($sql);
        if (!$res) return 0;
        $row = $res->fetch_row();
        return (int)($row[0] ?? 0);
    };

    $mapCountByDate = function ($sql) use ($conn) {
        // return associative: ['YYYY-MM-DD' => count]
        $out = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[$r['d']] = (int)$r['c'];
            }
        }
        return $out;
    };

    $mapCountByMonth = function ($sql) use ($conn) {
        // return associative: ['YYYY-MM' => count]
        $out = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[$r['m']] = (int)$r['c'];
            }
        }
        return $out;
    };
    

    // ---------- KPI ----------
    $total_user          = $scalar("SELECT COUNT(*) FROM users");
    $total_tpa_questions = $scalar("SELECT COUNT(*) FROM tpa_questions");
    $total_tam_questions = $scalar("SELECT COUNT(*) FROM tam_questions");
    $total_tpa_results   = $scalar("SELECT COUNT(*) FROM tpa_results");
    $total_tam_results   = $scalar("SELECT COUNT(*) FROM tam_results");
    $total_kraep_results = $scalar("SELECT COUNT(*) FROM kraeplin_results");

    // ---------- Funnel (unique user yang sudah pernah submit) ----------
    $users_done_tpa      = $scalar("SELECT COUNT(DISTINCT user_id) FROM tpa_results");
    $users_done_tam      = $scalar("SELECT COUNT(DISTINCT user_id) FROM tam_results");
    $users_done_kraeplin = $scalar("SELECT COUNT(DISTINCT user_id) FROM kraeplin_results");

    // ---------- Chart 7 hari terakhir (TPA/TAM/Kraeplin) ----------
    $tpa7 = $mapCountByDate("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM tpa_results
        WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ");

    $tam7 = $mapCountByDate("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM tam_results
        WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ");

    $kr7 = $mapCountByDate("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM kraeplin_results
        WHERE created_at >= (CURDATE() - INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ");

    $days = [];
    $chartTPA = [];
    $chartTAM = [];
    $chartKR  = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} day"));
        $days[] = date('d M', strtotime($d));
        $chartTPA[] = (int)($tpa7[$d] ?? 0);
        $chartTAM[] = (int)($tam7[$d] ?? 0);
        $chartKR[]  = (int)($kr7[$d]  ?? 0);
    }

    // ---------- Chart bulanan (6 bulan terakhir) ----------
    $userMonth = $mapCountByMonth("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
        FROM users
        WHERE created_at >= (DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01'))
        GROUP BY m
        ORDER BY m
    ");

    $tpaMonth = $mapCountByMonth("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
        FROM tpa_results
        WHERE created_at >= (DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01'))
        GROUP BY m
        ORDER BY m
    ");

    $tamMonth = $mapCountByMonth("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
        FROM tam_results
        WHERE created_at >= (DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01'))
        GROUP BY m
        ORDER BY m
    ");

    $krMonth = $mapCountByMonth("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
        FROM kraeplin_results
        WHERE created_at >= (DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01'))
        GROUP BY m
        ORDER BY m
    ");

    $monthLabels = [];
    $monthUsers  = [];
    $monthTests  = []; // total attempt semua tes
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime(date('Y-m-01') . " -{$i} month"));
        $monthLabels[] = date('M Y', strtotime($m . '-01'));
        $monthUsers[]  = (int)($userMonth[$m] ?? 0);
        $monthTests[]  = (int)($tpaMonth[$m] ?? 0) + (int)($tamMonth[$m] ?? 0) + (int)($krMonth[$m] ?? 0);
    }

    // ---------- Aktivitas terbaru (gabungan) ----------
    $recentActivities = [];
    $sqlRecent = "
        (SELECT 'TPA' AS test_type, tr.id AS test_id, tr.user_id, u.name AS user_name,
                CONCAT(tr.score, ' / 60') AS score_text, tr.created_at
         FROM tpa_results tr
         LEFT JOIN users u ON tr.user_id = u.id)
        UNION ALL
        (SELECT 'TAM' AS test_type, r.id AS test_id, r.user_id, u.name AS user_name,
                CONCAT(r.score, ' pts') AS score_text, r.created_at
         FROM tam_results r
         LEFT JOIN users u ON r.user_id = u.id)
        UNION ALL
        (SELECT 'Kraeplin' AS test_type, kr.id AS test_id, kr.user_id, u.name AS user_name,
                CONCAT(kr.total_productivity, ' item') AS score_text, kr.created_at
         FROM kraeplin_results kr
         LEFT JOIN users u ON kr.user_id = u.id)
        ORDER BY created_at DESC
        LIMIT 10
    ";
    $resRecent = $conn->query($sqlRecent);
    if ($resRecent) {
        while ($r = $resRecent->fetch_assoc()) {
            $recentActivities[] = $r;
        }
    }

    // ---------- Leaderboard Kraeplin ----------
    $topKraeplin = [];
    $resTop = $conn->query("
        SELECT kr.id, kr.user_id, u.name AS user_name, u.user_code,
               kr.total_productivity, kr.total_correct, kr.accuracy_percentage, kr.created_at
        FROM kraeplin_results kr
        LEFT JOIN users u ON kr.user_id = u.id
        ORDER BY kr.total_productivity DESC
        LIMIT 5
    ");
    if ($resTop) {
        while ($r = $resTop->fetch_assoc()) $topKraeplin[] = $r;
    }

    // Kirim ke view
    require './views/admin/dashboard.php';
}

function adminSaveTestWindow() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    $mode = $_POST['mode'] ?? 'closed';
    $is_open = ($mode === 'open') ? 1 : 0;

    $open_date  = trim($_POST['open_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '');

    if ($is_open === 1) {
        if ($open_date === '' || $start_time === '' || $end_time === '') {
            $_SESSION['flash_err'] = "Tanggal dan jam wajib diisi saat membuka tes.";
            header("Location: index.php?page=admin-dashboard");
            exit;
        }

        $s = strtotime($open_date . ' ' . $start_time);
        $e = strtotime($open_date . ' ' . $end_time);
        if (!$s || !$e || $e <= $s) {
            $_SESSION['flash_err'] = "Rentang jam tidak valid (end_time harus > start_time).";
            header("Location: index.php?page=admin-dashboard");
            exit;
        }
    } else {
        $open_date = null;
        $start_time = null;
        $end_time = null;
    }

    $stmt = $conn->prepare("
        INSERT INTO test_window_settings (id, is_open, open_date, start_time, end_time)
        VALUES (1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          is_open = VALUES(is_open),
          open_date = VALUES(open_date),
          start_time = VALUES(start_time),
          end_time = VALUES(end_time)
    ");
    $stmt->bind_param("isss", $is_open, $open_date, $start_time, $end_time);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_ok'] = "Pengaturan tes berhasil disimpan.";
    header("Location: index.php?page=admin-dashboard");
    exit;
}


/* =============================
   USER MANAGEMENT (NO SECURITY)
   ============================= */
function adminUsers()
{
    requireAdminLogin();
    global $conn;

    $q        = trim($_GET['q'] ?? '');
    $from     = trim($_GET['from'] ?? '');   // YYYY-MM-DD
    $to       = trim($_GET['to'] ?? '');     // YYYY-MM-DD
    $pageNow  = max(1, (int)($_GET['p'] ?? 1));
    $perPage  = (int)($_GET['per_page'] ?? 20);
    $perPage  = max(5, min(100, $perPage));
    $offset   = ($pageNow - 1) * $perPage;

    $where  = " WHERE 1=1 ";
    $types  = "";
    $params = [];

    if ($q !== '') {
        $where .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $types .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    if ($from !== '') {
        $where .= " AND u.created_at >= ? ";
        $types .= "s";
        $params[] = $from . " 00:00:00";
    }

    if ($to !== '') {
        $where .= " AND u.created_at <= ? ";
        $types .= "s";
        $params[] = $to . " 23:59:59";
    }

    // TOTAL COUNT
    $sqlCount = "SELECT COUNT(*) AS total FROM users u {$where}";
    $stmt = $conn->prepare($sqlCount);
    if (!$stmt) { die("Prepare failed (count): " . $conn->error); }
    bindParams($stmt, $types, $params);
    $stmt->execute();
    $totalUsers = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($totalUsers / $perPage));
    if ($pageNow > $totalPages) {
        $pageNow = $totalPages;
        $offset  = ($pageNow - 1) * $perPage;
    }

    // LIST DATA
    $sql = "SELECT u.* FROM users u {$where} ORDER BY u.id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { die("Prepare failed (list): " . $conn->error); }

    $types2  = $types . "ii";
    $params2 = $params;
    $params2[] = $perPage;
    $params2[] = $offset;

    bindParams($stmt, $types2, $params2);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // untuk view
    $state = [
        'q' => $q,
        'from' => $from,
        'to' => $to,
        'p' => $pageNow,
        'per_page' => $perPage,
        'total' => $totalUsers,
        'total_pages' => $totalPages,
    ];

    require './views/admin/manage_user.php';
}

function adminToggleUserActive(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    $targetId = (int)($_REQUEST['id'] ?? 0);
    $desired  = isset($_REQUEST['state']) ? (int)$_REQUEST['state'] : -1;

    $returnUrl = (string)($_REQUEST['return_url'] ?? 'index.php?page=admin-users');
    if (strpos($returnUrl, 'index.php') !== 0) $returnUrl = 'index.php?page=admin-users';

    if ($targetId <= 0 || !in_array($desired, [0,1], true)) {
        header("Location: {$returnUrl}&msg=" . urlencode("Status invalid."));
        exit;
    }

    if ($desired === 0) {
        $reason = trim($_REQUEST['reason'] ?? '');
        if ($reason === '') $reason = 'Disabled by admin';

        $stmt = $conn->prepare("
            UPDATE users 
            SET is_active=0, deactivated_at=NOW(), deactivated_reason=? 
            WHERE id=? LIMIT 1
        ");
        $stmt->bind_param("si", $reason, $targetId);
        $stmt->execute();
        $stmt->close();

        require_once './controllers/ForceSubmitHelper.php';
        $submitted = force_submit_all_open_progress($conn, $targetId);

        $suffix = !empty($submitted) ? (" | Force submit: " . implode(', ', $submitted)) : "";
        header("Location: {$returnUrl}&msg=" . urlencode("User berhasil dinonaktifkan{$suffix}"));
        exit;
    }

    // enable kembali → clear metadata nonaktif
    $stmt = $conn->prepare("
        UPDATE users 
        SET is_active=1, deactivated_at=NULL, deactivated_reason=NULL 
        WHERE id=? LIMIT 1
    ");
    $stmt->bind_param("i", $targetId);
    $stmt->execute();
    $stmt->close();

    header("Location: {$returnUrl}&msg=" . urlencode("User berhasil diaktifkan kembali."));
    exit;
}

function adminGrantRetake(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    global $conn;

    $userId = (int)($_POST['user_id'] ?? 0);

    $tpa = !empty($_POST['tpa']);
    $tam = !empty($_POST['tam']);
    $kr  = !empty($_POST['kraeplin']);

    $amount = max(1, (int)($_POST['amount'] ?? 1)); // default 1

    $returnUrl = (string)($_POST['return_url'] ?? 'index.php?page=admin-users');
    if (strpos($returnUrl, 'index.php') !== 0) $returnUrl = 'index.php?page=admin-users';

    if ($userId <= 0 || (!$tpa && !$tam && !$kr)) {
        $_SESSION['flash_err'] = "Pilih user dan minimal 1 tes.";
        header("Location: {$returnUrl}");
        exit;
    }

    // Pastikan user exists
    $chk = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
    $chk->bind_param("i", $userId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$exists) {
        $_SESSION['flash_err'] = "User tidak ditemukan.";
        header("Location: {$returnUrl}");
        exit;
    }

    $a = $tpa ? $amount : 0;
    $b = $tam ? $amount : 0;
    $c = $kr  ? $amount : 0;

    $stmt = $conn->prepare("
        UPDATE users SET
          tpa_retake_quota      = tpa_retake_quota + ?,
          tam_retake_quota      = tam_retake_quota + ?,
          kraeplin_retake_quota = kraeplin_retake_quota + ?
        WHERE id=? LIMIT 1
    ");
    $stmt->bind_param("iiii", $a, $b, $c, $userId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_ok'] = "Akses ulang tes berhasil diberikan.";
    header("Location: {$returnUrl}");
    exit;
}

// Halaman form tambah user
function adminAddUserPage()
{
    requireAdminLogin();
    // View: form tambah user
    require './views/admin/add_user.php';
}

// Proses simpan user baru
function adminAddUserProcess()
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php?page=admin-users');
        exit;
    }

    $name      = trim($_POST['name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');

    if ($name === '' || $birthdate === '') {
        // Bisa ditambah flash message kalau mau
        header('Location: index.php?page=admin-add-user');
        exit;
    }

    // Insert user dulu (user_code masih kosong)
    $stmt = $conn->prepare("INSERT INTO users (name, birthdate) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $birthdate);
    $stmt->execute();

    $newId = $stmt->insert_id;
    $stmt->close();

    // Generate user_code: ID-YYYYMMDD (sesuai penjelasanmu)
    $datePart = date('Ymd', strtotime($birthdate));
    $userCode = $newId . '-' . $datePart;

    $stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $stmt->bind_param('si', $userCode, $newId);
    $stmt->execute();
    $stmt->close();

    header('Location: index.php?page=admin-users');
    exit;
}

// Halaman edit user
function adminUserEditPage()
{
    requireAdminLogin();
    global $conn;

    if (!isset($_GET['id'])) {
        header('Location: index.php?page=admin-users');
        exit;
    }

    $id = (int) $_GET['id'];

    $stmt = $conn->prepare("SELECT id, user_code, name, birthdate FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        header('Location: index.php?page=admin-users');
        exit;
    }

    // View: form edit user
    require './views/admin/edit_user.php';
}

// Proses update user
function adminUserUpdateProcess()
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php?page=admin-users');
        exit;
    }

    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');

    if ($id <= 0 || $name === '' || $birthdate === '') {
        header("Location: index.php?page=admin-user-edit&id={$id}");
        exit;
    }

    // Update name & birthdate
    $stmt = $conn->prepare("UPDATE users SET name = ?, birthdate = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $birthdate, $id);
    $stmt->execute();
    $stmt->close();

    // Update user_code juga, karena tergantung birthdate
    $datePart = date('Ymd', strtotime($birthdate));
    $userCode = $id . '-' . $datePart;

    $stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    $stmt->bind_param('si', $userCode, $id);
    $stmt->execute();
    $stmt->close();

    header('Location: index.php?page=admin-users');
    exit;
}
// ======================================================================
// PROSES: Hapus User
// ======================================================================
function adminUserDeleteProcess()
{
    global $conn;

    $id = $_GET['id'] ?? null;

    if (!$id) {
        header('Location: index.php?page=admin-users');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // Langsung balik ke halaman Kelola User
    header('Location: index.php?page=admin-users');
    exit;
}

function adminUsersExportExcel()
{
    requireAdminLogin();
    global $conn;

    // =========================
    // 1) Pastikan autoload vendor PhpSpreadsheet terbaca
    // =========================
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $candidates = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/vendor/autoload.php',
            dirname(__DIR__) . '/vendor/autoload.php',
        ];
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                break;
            }
        }
    }
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        die("PhpSpreadsheet belum ter-load. Pastikan vendor/autoload.php ada dan composer install sudah benar.");
    }

    // =========================
    // 2) Ambil filter
    // =========================
    $q    = trim($_GET['q'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    // Validasi format tanggal (YYYY-MM-DD)
    $isValidDate = function ($s) {
        if ($s === '') return false;
        $dt = DateTime::createFromFormat('Y-m-d', $s);
        return $dt && $dt->format('Y-m-d') === $s;
    };

    if (!$isValidDate($from)) $from = '';
    if (!$isValidDate($to))   $to   = '';

    // Jika from > to, swap biar aman
    if ($from !== '' && $to !== '' && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    // =========================
    // 3) Query data sesuai filter
    // =========================
    $where  = " WHERE 1=1 ";
    $types  = "";
    $params = [];

    if ($q !== '') {
        $where .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $types  .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    if ($from !== '') {
        $where .= " AND u.created_at >= ? ";
        $types  .= "s";
        $params[] = $from . " 00:00:00";
    }

    if ($to !== '') {
        $where .= " AND u.created_at <= ? ";
        $types  .= "s";
        $params[] = $to . " 23:59:59";
    }

    $sql  = "SELECT u.id, u.user_code, u.name, u.birthdate, u.created_at
             FROM users u {$where}
             ORDER BY u.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { die("Prepare failed (export users): " . $conn->error); }

    // bindParams helper Anda tetap dipakai (pastikan helper ini ada)
    if ($types !== "" && !empty($params)) {
        bindParams($stmt, $types, $params);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // =========================
    // 4) Buat Excel .xlsx yang rapi (styled)
    // =========================
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('JECA Psychotest')
        ->setTitle('Export Users')
        ->setSubject('Users')
        ->setDescription('Export data users JECA Psychotest');

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Users');

    // Layout
    $titleRow   = 1;
    $infoRow    = 2;
    $headerRow  = 4;
    $startRow   = 5;

    // Title (merge)
    $sheet->mergeCells("A{$titleRow}:E{$titleRow}");
    $sheet->setCellValue("A{$titleRow}", "EXPORT DATA USERS");

    // Info (range/filter)
    $rangeText = "Rentang: " . ($from ?: 'Semua') . " s/d " . ($to ?: 'Semua');
    $searchText = "Pencarian: " . ($q ?: '-');
    $generatedText = "Generated: " . date('Y-m-d H:i:s');
    $sheet->mergeCells("A{$infoRow}:E{$infoRow}");
    $sheet->setCellValue("A{$infoRow}", "{$rangeText}  |  {$searchText}  |  {$generatedText}");

    // Header kolom
    $headers = ['No', 'User Code', 'Nama', 'Tanggal Lahir', 'Dibuat'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $headerRow, $h);
        $col++;
    }

    // Isi data
    $r = $startRow;
    $no = 1;
    foreach ($rows as $u) {
        $birth = (!empty($u['birthdate'])) ? date('Y-m-d', strtotime($u['birthdate'])) : '-';
        $created = (!empty($u['created_at'])) ? date('Y-m-d H:i:s', strtotime($u['created_at'])) : '-';

        $sheet->setCellValue("A{$r}", $no++);
        $sheet->setCellValue("B{$r}", $u['user_code'] ?? '-');
        $sheet->setCellValue("C{$r}", $u['name'] ?? '-');
        $sheet->setCellValue("D{$r}", $birth);
        $sheet->setCellValue("E{$r}", $created);
        $r++;
    }

    $lastDataRow = max($startRow, $r - 1);
    $lastCol = 'E';

    // =========================
    // 5) Styling biar profesional
    // =========================
    // Column widths (rapi, tidak nabrak)
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(32);
    $sheet->getColumnDimension('D')->setWidth(16);
    $sheet->getColumnDimension('E')->setWidth(22);

    // Title style
    $sheet->getStyle("A{$titleRow}:E{$titleRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0F172A'], // slate-900
        ],
    ]);
    $sheet->getRowDimension($titleRow)->setRowHeight(28);

    // Info style
    $sheet->getStyle("A{$infoRow}:E{$infoRow}")->applyFromArray([
        'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E2E8F0'], // slate-200
        ],
    ]);
    $sheet->getRowDimension($infoRow)->setRowHeight(18);

    // Header style
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F1F5F9'], // slate-100
        ],
        'borders' => [
            'bottom' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'],
            ],
        ],
    ]);
    $sheet->getRowDimension($headerRow)->setRowHeight(20);

    // Body borders + vertical align
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray([
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0'],
            ],
        ],
    ]);

    // Align per column
    $sheet->getStyle("A{$startRow}:A{$lastDataRow}")
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B{$startRow}:B{$lastDataRow}")
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("C{$startRow}:C{$lastDataRow}")
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("D{$startRow}:E{$lastDataRow}")
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Freeze header (biar enak scroll)
    $sheet->freezePane("A{$startRow}");

    // AutoFilter
    $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");

    // =========================
    // 6) Output file .xlsx (tanpa warning format Excel)
    // =========================
    $safeFrom = $from ?: 'all';
    $safeTo   = $to   ?: 'all';
    $safeFrom = preg_replace('/[^0-9a-zA-Z_-]/', '', $safeFrom);
    $safeTo   = preg_replace('/[^0-9a-zA-Z_-]/', '', $safeTo);

    $filename = "users_{$safeFrom}_{$safeTo}.xlsx";

    // Bersihkan buffer agar file tidak corrupt
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
/* =============================
   TPA HANDLING (NO SECURITY)
   ============================= */
/* ======================================================
   TPA - LIST
   ====================================================== */

// ===========================================
// HELPER UPLOAD GAMBAR TPA
// ===========================================
function uploadTPAImage($fieldName, $oldPath = null) {
    
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        // tidak ada file baru, pakai path lama (untuk edit)
        return $oldPath;
    }

    $tmpName  = $_FILES[$fieldName]['tmp_name'];
    $origName = basename($_FILES[$fieldName]['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
        // ekstensi tidak valid → abaikan, pakai path lama
        return $oldPath;
    }

    $uploadDirFs = __DIR__ . '/../uploads/tpa/';
    if (!is_dir($uploadDirFs)) {
        mkdir($uploadDirFs, 0777, true);
    }

    $newName = $fieldName . '_' . time() . '_' . uniqid() . '.' . $ext;
    $fullPath = $uploadDirFs . $newName;

    if (move_uploaded_file($tmpName, $fullPath)) {
        // path yang disimpan di DB (relative ke root web)
        return 'uploads/tpa/' . $newName;
    }

    return $oldPath;
}

function adminTPAList() {
    requireAdminLogin();
    global $conn;

    $category = $_GET['category'] ?? 'verbal';
    $session  = (int)($_GET['session'] ?? 1);

    $stmt = $conn->prepare("
        SELECT * FROM tpa_questions
        WHERE category = ? AND session = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("si", $category, $session);
    $stmt->execute();
    $result = $stmt->get_result();

    require './views/admin/tpa_list.php';
}

/* ======================================================
   TPA - ADD FORM PAGE
   ====================================================== */
function adminTPAAddPage() {
    requireAdminLogin();
    $category = $_GET['category'] ?? 'verbal';
    $session  = (int)($_GET['session'] ?? 1);

    require './views/admin/tpa_add.php';
}

/* ======================================================
   TPA - INSERT
   ====================================================== */
function adminTPAAddProcess() {
    global $conn;

    $category = $_POST['category'];   // verbal / kuantitatif / ...
    $session  = $_POST['session'];    // '1' / '2' / '3' (enum string)

    $question_text = $_POST['question_text'] ?? '';

    $option_a_text = $_POST['option_a_text'] ?? '';
    $option_b_text = $_POST['option_b_text'] ?? '';
    $option_c_text = $_POST['option_c_text'] ?? '';
    $option_d_text = $_POST['option_d_text'] ?? '';

    $correct_option = $_POST['correct_option'];   // 'A' / 'B' / 'C' / 'D'

    // upload gambar (boleh kosong)
    $question_image = uploadTPAImage('question_image');
    $option_a_image = uploadTPAImage('option_a_image');
    $option_b_image = uploadTPAImage('option_b_image');
    $option_c_image = uploadTPAImage('option_c_image');
    $option_d_image = uploadTPAImage('option_d_image');

    // tentukan type otomatis (text / image / mixed)
    $hasText  = trim($question_text.$option_a_text.$option_b_text.$option_c_text.$option_d_text) !== '';
    $hasImage = $question_image || $option_a_image || $option_b_image || $option_c_image || $option_d_image;

    if ($hasText && $hasImage) {
        $type = 'mixed';
    } elseif ($hasImage) {
        $type = 'image';
    } else {
        $type = 'text';
    }

    $sql = "
        INSERT INTO tpa_questions
        (
            category, session, type,
            question_text, question_image,
            option_a_text, option_a_image,
            option_b_text, option_b_image,
            option_c_text, option_c_image,
            option_d_text, option_d_image,
            correct_option
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssssss",
        $category, $session, $type,
        $question_text, $question_image,
        $option_a_text, $option_a_image,
        $option_b_text, $option_b_image,
        $option_c_text, $option_c_image,
        $option_d_text, $option_d_image,
        $correct_option
    );
    $stmt->execute();

    header("Location: index.php?page=admin-tpa-list&category=$category&session=$session");
    exit;
}


/* ======================================================
   TPA - EDIT PAGE
   ====================================================== */
function adminTPAEditPage() {
    requireAdminLogin();
    global $conn;

    $id = (int)$_GET['id'];
    $row = $conn->query("SELECT * FROM tpa_questions WHERE id = $id")->fetch_assoc();

    require './views/admin/tpa_edit.php';
}

/* ======================================================
   TPA - UPDATE
   ====================================================== */
function adminTPAEditProcess() {
    global $conn;

    $id       = (int)$_POST['id'];
    $category = $_POST['category'];   // verbal / kuantitatif / logika / spasial
    $session  = $_POST['session'];    // '1' / '2' / '3' (enum string)

    // TEXT
    $question_text = $_POST['question_text'] ?? '';

    $option_a_text = $_POST['option_a_text'] ?? '';
    $option_b_text = $_POST['option_b_text'] ?? '';
    $option_c_text = $_POST['option_c_text'] ?? '';
    $option_d_text = $_POST['option_d_text'] ?? '';

    $correct_option = $_POST['correct_option'];   // 'A' / 'B' / 'C' / 'D'

    // PATH LAMA GAMBAR
    $old_question_image = $_POST['old_question_image'] ?? null;
    $old_option_a_image = $_POST['old_option_a_image'] ?? null;
    $old_option_b_image = $_POST['old_option_b_image'] ?? null;
    $old_option_c_image = $_POST['old_option_c_image'] ?? null;
    $old_option_d_image = $_POST['old_option_d_image'] ?? null;

    // UPLOAD GAMBAR BARU (jika ada), kalau tidak → pakai path lama
    $question_image = uploadTPAImage('question_image', $old_question_image);
    $option_a_image = uploadTPAImage('option_a_image', $old_option_a_image);
    $option_b_image = uploadTPAImage('option_b_image', $old_option_b_image);
    $option_c_image = uploadTPAImage('option_c_image', $old_option_c_image);
    $option_d_image = uploadTPAImage('option_d_image', $old_option_d_image);

    // HITUNG TYPE (text / image / mixed)
    $hasText  = trim($question_text.$option_a_text.$option_b_text.$option_c_text.$option_d_text) !== '';
    $hasImage = $question_image || $option_a_image || $option_b_image || $option_c_image || $option_d_image;

    if ($hasText && $hasImage) {
        $type = 'mixed';
    } elseif ($hasImage) {
        $type = 'image';
    } else {
        $type = 'text';
    }

    $sql = "
        UPDATE tpa_questions
        SET
            category        = ?,
            session         = ?,
            type            = ?,
            question_text   = ?,
            question_image  = ?,
            option_a_text   = ?, option_a_image = ?,
            option_b_text   = ?, option_b_image = ?,
            option_c_text   = ?, option_c_image = ?,
            option_d_text   = ?, option_d_image = ?,
            correct_option  = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssssssssi',
        $category, $session, $type,
        $question_text, $question_image,
        $option_a_text, $option_a_image,
        $option_b_text, $option_b_image,
        $option_c_text, $option_c_image,
        $option_d_text, $option_d_image,
        $correct_option,
        $id
    );
    $stmt->execute();

    header("Location: index.php?page=admin-tpa-list&category=$category&session=$session");
    exit;
}


/* ======================================================
   TPA - DELETE
   ====================================================== */
function adminTPADelete() {
    global $conn;

    $id       = (int)$_GET['id'];
    $category = $_GET['category'];
    $session  = (int)$_GET['session'];

    $conn->query("DELETE FROM tpa_questions WHERE id = $id");

    header("Location: index.php?page=admin-tpa-list&category=$category&session=$session");
    exit;
}

// LIST HASIL TPA
function adminTPAResultsPage() {
    requireAdminLogin();
    global $conn;

    // Filter sederhana via query string (optional)
    $category = $_GET['category'] ?? 'all';
    $session  = $_GET['session'] ?? 'all';

    $where  = [];
    $params = [];
    $types  = '';

    if ($category !== 'all') {
        $where[]  = 'category = ?';
        $types   .= 's';
        $params[] = $category;
    }

    if ($session !== 'all') {
        $where[]  = 'session = ?';
        $types   .= 's';
        $params[] = $session;
    }

    $sql = "SELECT * FROM tpa_results";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC';

    if (!empty($where)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    include __DIR__ . '/../views/admin/tpa_results.php';
}

// DETAIL SATU HASIL TPA

function adminTPAResultDetailPage() {
    requireAdminLogin();
    global $conn;

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        exit("ID tidak valid.");
    }

    // Ambil 1 row hasil + data user (JOIN)
    $stmt = $conn->prepare("
        SELECT r.*, u.name AS user_name, u.user_code
        FROM tpa_results r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    if (!$data) {
        exit("Data hasil tes tidak ditemukan.");
    }

    // Konstanta jumlah soal per kategori (sesuai requirement Anda)
    $maxMap = [
        'verbal'      => 15,
        'kuantitatif' => 20,
        'logika'      => 10,
        'spasial'     => 15,
    ];
    $labelMap = [
        'verbal'      => 'Verbal',
        'kuantitatif' => 'Kuantitatif',
        'logika'      => 'Logika',
        'spasial'     => 'Spasial',
    ];

    // Inisialisasi breakdown
    $breakdown = [];
    foreach ($maxMap as $k => $max) {
        $breakdown[$k] = [
            'label'   => $labelMap[$k],
            'correct' => 0,
            'max'     => $max,
        ];
    }

    // Decode answers JSON
    $answersArr = [];
    if (!empty($data['answers'])) {
        $decoded = json_decode($data['answers'], true);
        if (is_array($decoded)) {
            $answersArr = $decoded;
        }
    }

    // Hitung benar per kategori dari answers[*].is_correct
    foreach ($answersArr as $a) {
        $cat = $a['category'] ?? null;
        $isCorrect = !empty($a['is_correct']); // true/1

        if ($cat && isset($breakdown[$cat]) && $isCorrect) {
            $breakdown[$cat]['correct']++;
        }
    }

    // Cap agar tidak melewati max (antisipasi data dobel)
    foreach ($breakdown as $k => $bd) {
        if ($bd['correct'] > $bd['max']) {
            $breakdown[$k]['correct'] = $bd['max'];
        }
    }

    // Total
    $totalQuestions  = array_sum($maxMap); // 60
    $totalCorrect    = 0;
    foreach ($breakdown as $bd) {
        $totalCorrect += (int)$bd['correct'];
    }
    if ($totalCorrect > $totalQuestions) {
        $totalCorrect = $totalQuestions;
    }
    $totalPercentage = $totalQuestions > 0 ? ($totalCorrect / $totalQuestions) * 100 : 0.0;

    // Kirim ke view
    include __DIR__ . '/../views/admin/tpa_result_detail.php';
}

/* =============================
   TAM ADMIN (NO SECURITY)
   ============================= */
function adminTAMPackage() {
    requireAdminLogin();
    global $conn;
    $data = $conn->query("SELECT * FROM tam_package LIMIT 1")->fetch_assoc();
    require './views/admin/tam_package.php';
}

function adminKraeplinSettings()
{
    requireAdminLogin();
    $settings = Kraeplin::getSettings();
    require './views/admin/kraeplin_settings.php';
}

function adminKraeplinSettingsSave()
{
requireAdminLogin();
$duration  = (int)($_POST['duration'] ?? 20);
$interval  = (int)($_POST['interval_seconds'] ?? 10);

Kraeplin::updateSettings($duration, $interval);

    echo "<script>alert('Pengaturan Kraeplin berhasil disimpan');window.location='index.php?page=admin-kraeplin-settings';</script>";
    exit;
}



/* =============================
   KRAEPLIN ADMIN (NO SECURITY)
   ============================= */

// LIST HASIL KRAEPLIN
function adminKraeplinResults()
{
    requireAdminLogin();
    global $conn;

    $q    = trim($_GET['q'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    $perPage = 10;
    $page    = max(1, (int)($_GET['k_page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    // date filter (langsung ke kr.created_at)
    [$dateWhere, $dateTypes, $dateParams] = buildDateWhere($from, $to, 'kr.created_at', '');

    // =========================
    // COUNT total (SEMUA ROW, bukan DISTINCT user)
    // =========================
    $countSql = "
        SELECT COUNT(*) AS cnt
        FROM kraeplin_results kr
        LEFT JOIN users u ON u.id = kr.user_id
        WHERE 1=1
        {$dateWhere}
    ";

    $countTypes  = $dateTypes;
    $countParams = $dateParams;

    if ($q !== '') {
        $countSql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $countTypes  .= "ss";
        $countParams[] = $like;
        $countParams[] = $like;
    }

    $stmt = $conn->prepare($countSql);
    if (!$stmt) die("Prepare failed (count kraeplin): " . $conn->error);
    bindParams($stmt, $countTypes, $countParams);
    $stmt->execute();
    $total = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // =========================
    // DATA (SEMUA attempt)
    // =========================
    $sql = "
        SELECT
            kr.*,
            u.name      AS user_name,
            u.user_code AS user_code
        FROM kraeplin_results kr
        LEFT JOIN users u ON u.id = kr.user_id
        WHERE 1=1
        {$dateWhere}
    ";

    $types  = $dateTypes;
    $params = $dateParams;

    if ($q !== '') {
        $sql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $types  .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    // urutan "ranking-like" tapi semua attempt tetap ikut
    $sql .= "
        ORDER BY kr.total_correct DESC, kr.total_productivity DESC, kr.created_at DESC, kr.id DESC
        LIMIT ? OFFSET ?
    ";
    $types  .= "ii";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed (list kraeplin all): " . $conn->error);
    bindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $filters = ['q' => $q, 'from' => $from, 'to' => $to];

    include __DIR__ . '/../views/admin/kraeplin_results.php';
}

// DETAIL SATU HASIL KRAEPLIN
/* ============================================================
   ADMIN – DETAIL HASIL TES KRAEPLIN
   ============================================================ */
function adminKraeplinResultDetail()
{
    requireAdminLogin();
    // pakai koneksi global
    global $conn;

    // Ambil ID hasil dari query string
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        exit("ID hasil tes tidak valid.");
    }

    // Ambil 1 row hasil + data user
    $stmt = $conn->prepare("
        SELECT kr.*, u.name AS user_name, u.user_code 
        FROM kraeplin_results kr
        LEFT JOIN users u ON kr.user_id = u.id
        WHERE kr.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    if (!$data) {
        exit("Data hasil tes tidak ditemukan.");
    }

    // Decode raw_lines untuk tabel & grafik
    $lines = [];
    if (!empty($data['raw_lines'])) {
        $decoded = json_decode($data['raw_lines'], true);
        if (is_array($decoded)) {
            $lines = $decoded;
        }
    }

    // Kirim ke view
    require './views/admin/kraeplin_results_detail.php';
}

function adminKraeplinExportExcel()
{
    requireAdminLogin();
    global $conn;

    // pastikan autoload vendor tersedia
    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    $q    = trim($_GET['q'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    // date filter langsung ke kr.created_at
    [$dateWhere, $dateTypes, $dateParams] = buildDateWhere($from, $to, 'kr.created_at', '');

    $sql = "
        SELECT
            kr.*,
            u.name      AS user_name,
            u.user_code AS user_code
        FROM kraeplin_results kr
        LEFT JOIN users u ON u.id = kr.user_id
        WHERE 1=1
        {$dateWhere}
    ";

    $types  = $dateTypes;
    $params = $dateParams;

    if ($q !== '') {
        $sql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $types  .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY kr.total_correct DESC, kr.total_productivity DESC, kr.created_at DESC, kr.id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed (export kraeplin all): " . $conn->error);

    bindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $safeFrom = $from ?: 'all';
    $safeTo   = $to   ?: 'all';
    $filename = "kraeplin_results_{$safeFrom}_{$safeTo}.xlsx";

    if (ob_get_length()) { ob_end_clean(); }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Kraeplin Results');

    $headers = [
        'No',
        'ID Tes',
        'User ID',
        'User Code',
        'Nama',
        'Retake',
        'Attempt No',
        'Produktivitas',
        'Jawaban Benar',
        'Akurasi (%)',
        'Stabilitas',
        'Konsentrasi',
        'Adaptasi',
        'Pola Kerja',
        'Waktu Tes',
    ];

    $sheet->fromArray($headers, null, 'A1');

    $sheet->getStyle('A1:O1')->getFont()->setBold(true);
    $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1:O1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(20);

    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:O1');

    $rowNum = 2;
    $no = 1;

    foreach ($rows as $r) {
        $attemptNo = (int)($r['attempt_no'] ?? 1);
        $isRetake  = (!empty($r['is_retake']) || $attemptNo > 1) ? 'RETAKE' : '-';

        $sheet->setCellValue("A{$rowNum}", $no++);
        $sheet->setCellValue("B{$rowNum}", (int)($r['id'] ?? 0));
        $sheet->setCellValue("C{$rowNum}", (int)($r['user_id'] ?? 0));

        $sheet->setCellValueExplicit(
            "D{$rowNum}",
            (string)($r['user_code'] ?? '-'),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );

        $sheet->setCellValue("E{$rowNum}", (string)($r['user_name'] ?? '-'));
        $sheet->setCellValue("F{$rowNum}", $isRetake);
        $sheet->setCellValue("G{$rowNum}", $attemptNo);

        $sheet->setCellValue("H{$rowNum}", (int)($r['total_productivity'] ?? 0));
        $sheet->setCellValue("I{$rowNum}", (int)($r['total_correct'] ?? 0));
        $sheet->setCellValue("J{$rowNum}", (float)($r['accuracy_percentage'] ?? 0));
        $sheet->setCellValue("K{$rowNum}", (float)($r['stability_score'] ?? 0));
        $sheet->setCellValue("L{$rowNum}", (string)($r['concentration_trend'] ?? '-'));
        $sheet->setCellValue("M{$rowNum}", (float)($r['adaptation_score'] ?? 0));
        $sheet->setCellValue("N{$rowNum}", (string)($r['work_pattern'] ?? '-'));
        $sheet->setCellValue("O{$rowNum}", (string)($r['created_at'] ?? '-'));

        $rowNum++;
    }

    $lastRow = max(2, $rowNum - 1);

    $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('0.0"%"');
    $sheet->getStyle("K2:K{$lastRow}")->getNumberFormat()->setFormatCode('0.0');
    $sheet->getStyle("M2:M{$lastRow}")->getNumberFormat()->setFormatCode('0.0');

    $sheet->getStyle("A2:C{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F2:J{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("O2:O{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    foreach (range('A','O') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $sheet->getStyle("A1:O{$lastRow}")->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}


/* =============================
   ADMIN LOGOUT (OPTIONAL)
   ============================= */
function adminLogout()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();

    // Jika logout via POST, wajib CSRF valid
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_validate($_POST['csrf'] ?? '')) {
            header("Location: index.php?page=admin-dashboard&error=csrf", true, 303);
            exit;
        }
    }

    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['_admin_login_fail'],
        $_SESSION['_admin_login_lock_until'],
        $_SESSION['_csrf_admin']
    );

    header("Location: index.php?page=admin-login", true, 303);
    exit;
}


function adminResultsExportExcel() {
    requireAdminLogin();
    global $conn;

    requireComposerAutoload();

    $type = strtolower(trim($_GET['type'] ?? 'tpa')); // 'tpa' | 'tam'
    $q    = trim($_GET['q'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    // date where untuk subquery latest-created
    [$dateWhere, $dateTypes, $dateParams] = buildDateWhere($from, $to, 'created_at', ' WHERE 1=1 ');

    // =========================
    // TAM EXPORT (latest attempt per user)
    // =========================
    if ($type === 'tam') {
    $sql = "
    SELECT r.id, r.user_id, u.user_code, u.name AS user_name,
           r.total_correct, r.total_wrong, r.score, r.created_at,
           COALESCE(ac.attempts, 1) AS attempts
    FROM tam_results r
    JOIN users u ON u.id = r.user_id

    LEFT JOIN (
        SELECT user_id, COUNT(*) AS attempts
        FROM tam_results
        GROUP BY user_id
    ) ac ON ac.user_id = r.user_id

    " . latestPerUserJoins('tam_results', $dateWhere, 'r') . "

    WHERE 1=1
";

$types  = $dateTypes . $dateTypes;
$params = array_merge($dateParams, $dateParams);

if ($q !== '') {
    $sql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
    $like = "%{$q}%";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY r.score DESC, r.created_at DESC, r.id DESC ";


        $stmt = $conn->prepare($sql);
        if (!$stmt) die("Prepare failed (export TAM): " . $conn->error);
        bindParams($stmt, $types, $params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ===== Build Excel =====
        $totalQuestions = 24; // TAM: 24 soal

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TAM Ranking');

        $headers = ['Rank', 'User Code', 'Nama', 'Benar', 'Salah', 'Skor', 'Persentase', 'Waktu Tes', 'Retake', 'ID Tes'];
        $sheet->fromArray($headers, null, 'A1');

        $r = 2;
        $rank = 1;
        foreach ($rows as $row) {
            $score = (int)($row['score'] ?? 0);
            $pct = $totalQuestions > 0 ? ($score / $totalQuestions) * 100 : 0;

            $isRetake = (!empty($row['is_retake']) || ((int)($row['attempt_no'] ?? 1) > 1)) ? 'RETAKE' : '-';

            $sheet->fromArray([
                $rank++,
                $row['user_code'] ?? '-',
                $row['user_name'] ?? '-',
                (int)($row['total_correct'] ?? 0),
                (int)($row['total_wrong'] ?? 0),
                $score,
                round($pct, 1) . '%',
                $row['created_at'] ?? '-',
                $isRetake,
                (int)($row['id'] ?? 0),
            ], null, "A{$r}");
            $r++;
        }

        styleExcelSheet($sheet, count($headers), $r - 1);

        $safeFrom = $from ?: 'all';
        $safeTo   = $to   ?: 'all';
        $filename = "tam_results_{$safeFrom}_{$safeTo}.xlsx";

        outputXlsx($spreadsheet, $filename);
        exit;
    }

    // =========================
    // TPA EXPORT (latest attempt per user)
    // =========================
   $sql = "
    SELECT r.id, r.user_id, u.user_code, u.name AS user_name,
           r.score, r.created_at,
           COALESCE(ac.attempts, 1) AS attempts
    FROM tpa_results r
    JOIN users u ON u.id = r.user_id

    LEFT JOIN (
        SELECT user_id, COUNT(*) AS attempts
        FROM tpa_results
        GROUP BY user_id
    ) ac ON ac.user_id = r.user_id

    " . latestPerUserJoins('tpa_results', $dateWhere, 'r') . "

    WHERE 1=1
";

$types  = $dateTypes . $dateTypes;
$params = array_merge($dateParams, $dateParams);

if ($q !== '') {
    $sql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
    $like = "%{$q}%";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY r.score DESC, r.created_at DESC, r.id DESC ";


    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed (export TPA): " . $conn->error);
    bindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $totalQuestions = 60; // TPA total 60 soal

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('TPA Ranking');

    $headers = ['Rank', 'User Code', 'Nama', 'Skor', 'Total Soal', 'Persentase', 'Waktu Tes', 'Retake', 'ID Tes'];
    $sheet->fromArray($headers, null, 'A1');

    $r = 2;
    $rank = 1;
    foreach ($rows as $row) {
        $score = (int)($row['score'] ?? 0);
        $pct = $totalQuestions > 0 ? ($score / $totalQuestions) * 100 : 0;

        $isRetake = (!empty($row['is_retake']) || ((int)($row['attempt_no'] ?? 1) > 1)) ? 'RETAKE' : '-';

        $sheet->fromArray([
            $rank++,
            $row['user_code'] ?? '-',
            $row['user_name'] ?? '-',
            $score,
            $totalQuestions,
            round($pct, 1) . '%',
            $row['created_at'] ?? '-',
            $isRetake,
            (int)($row['id'] ?? 0),
        ], null, "A{$r}");
        $r++;
    }

    styleExcelSheet($sheet, count($headers), $r - 1);

    $safeFrom = $from ?: 'all';
    $safeTo   = $to   ?: 'all';
    $filename = "tpa_results_{$safeFrom}_{$safeTo}.xlsx";

    outputXlsx($spreadsheet, $filename);
    exit;
}

function adminResultsHistoryPage()
{
    requireAdminLogin();
    global $conn;

    $type   = strtolower(trim($_GET['type'] ?? 'tam')); // tpa|tam|kraeplin
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) exit("user_id tidak valid.");

    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    // Map table + kolom output minimal
    $map = [
        'tpa' => [
            'table'  => 'tpa_results',
            'select' => 'r.id, r.user_id, r.score, r.created_at'
        ],
        'tam' => [
            'table'  => 'tam_results',
            'select' => 'r.id, r.user_id, r.total_correct, r.total_wrong, r.score, r.created_at'
        ],
        'kraeplin' => [
            'table'  => 'kraeplin_results',
            'select' => 'r.id, r.user_id, r.total_productivity, r.total_correct, r.accuracy_percentage, r.created_at'
        ],
    ];

    if (!isset($map[$type])) $type = 'tam';
    $table  = $map[$type]['table'];
    $select = $map[$type]['select'];

    // date filter untuk outer query pakai alias r.created_at
    [$where, $types, $params] = buildDateWhere($from, $to, 'r.created_at', ' WHERE 1=1 ');

    $sql = "
        SELECT {$select}, u.user_code, u.name AS user_name
        FROM {$table} r
        JOIN users u ON u.id = r.user_id
        {$where} AND r.user_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ";

    $types  .= "i";
    $params[] = $userId;

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed (history {$type}): " . $conn->error);

    bindParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Buat penomoran attempt di PHP (biar tidak tergantung kolom attempt_no)
    $asc = array_reverse($rows);
    $i = 1;
    foreach ($asc as &$r) {
        $r['attempt_no'] = $i++;
        $r['is_retake']  = ($r['attempt_no'] > 1) ? 1 : 0;
    }
    unset($r);
    $rows = array_reverse($asc);

    // Siapkan untuk view (nanti kita garap di frontend/view)
    $historyType = $type;
    require __DIR__ . '/../views/admin/results_history.php';
}


// ===== Styling Excel (rapi, kolom tidak nabrak, header bagus) =====
function styleExcelSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colCount, int $lastRow): void {
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
    $rangeHeader = "A1:{$lastCol}1";
    $rangeAll    = "A1:{$lastCol}{$lastRow}";

    // Header style
    $sheet->getStyle($rangeHeader)->getFont()->setBold(true);
    $sheet->getStyle($rangeHeader)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($rangeHeader)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $sheet->getStyle($rangeHeader)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFEFF6FF'); // biru muda halus

    // Borders
    $sheet->getStyle($rangeAll)->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
        ->getColor()->setARGB('FFE5E7EB');

    // Freeze + autofilter
    $sheet->freezePane('A2');
    $sheet->setAutoFilter($rangeHeader);

    // Auto width (pakai perkiraan aman)
    for ($i = 1; $i <= $colCount; $i++) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Row height
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Align data
    if ($lastRow >= 2) {
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }
}

// ===== Output Xlsx =====
function outputXlsx(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename): void {
    // pastikan tidak ada output lain yang merusak file
    if (ob_get_length()) { ob_end_clean(); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function adminResultsPage() {
    requireAdminLogin();
    global $conn;

    $q    = trim($_GET['q'] ?? '');
    $from = trim($_GET['from'] ?? '');
    $to   = trim($_GET['to'] ?? '');

    // pagination terpisah
    $perPage  = 10;
    $tpaPage  = max(1, (int)($_GET['tpa_page'] ?? 1));
    $tamPage  = max(1, (int)($_GET['tam_page'] ?? 1));
    $tpaOff   = ($tpaPage - 1) * $perPage;
    $tamOff   = ($tamPage - 1) * $perPage;

    // =========================
    // COUNT (untuk pagination) -> HITUNG SEMUA ATTEMPT
    // =========================
    // ---------- COUNT TPA ----------
    $countTpaSql = "SELECT COUNT(*) AS total
                    FROM tpa_results r
                    JOIN users u ON u.id = r.user_id
                    WHERE 1=1 ";
    $countTpaTypes  = "";
    $countTpaParams = [];

    if ($q !== '') {
        $countTpaSql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $countTpaTypes .= "ss";
        $countTpaParams[] = $like;
        $countTpaParams[] = $like;
    }
    if ($from !== '') {
        $countTpaSql .= " AND r.created_at >= ? ";
        $countTpaTypes .= "s";
        $countTpaParams[] = $from . " 00:00:00";
    }
    if ($to !== '') {
        $countTpaSql .= " AND r.created_at <= ? ";
        $countTpaTypes .= "s";
        $countTpaParams[] = $to . " 23:59:59";
    }

    $stmt = $conn->prepare($countTpaSql);
    if (!$stmt) die("Prepare failed (count TPA): " . $conn->error);
    bindParams($stmt, $countTpaTypes, $countTpaParams);
    $stmt->execute();
    $tpaTotal = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // ---------- COUNT TAM ----------
    $countTamSql = "SELECT COUNT(*) AS total
                    FROM tam_results r
                    JOIN users u ON u.id = r.user_id
                    WHERE 1=1 ";
    $countTamTypes  = "";
    $countTamParams = [];

    if ($q !== '') {
        $countTamSql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $countTamTypes .= "ss";
        $countTamParams[] = $like;
        $countTamParams[] = $like;
    }
    if ($from !== '') {
        $countTamSql .= " AND r.created_at >= ? ";
        $countTamTypes .= "s";
        $countTamParams[] = $from . " 00:00:00";
    }
    if ($to !== '') {
        $countTamSql .= " AND r.created_at <= ? ";
        $countTamTypes .= "s";
        $countTamParams[] = $to . " 23:59:59";
    }

    $stmt = $conn->prepare($countTamSql);
    if (!$stmt) die("Prepare failed (count TAM): " . $conn->error);
    bindParams($stmt, $countTamTypes, $countTamParams);
    $stmt->execute();
    $tamTotal = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $tpaTotalPages = max(1, (int)ceil($tpaTotal / $perPage));
    $tamTotalPages = max(1, (int)ceil($tamTotal / $perPage));

    $tpaPage = min($tpaPage, $tpaTotalPages);
    $tamPage = min($tamPage, $tamTotalPages);

    $tpaOff  = ($tpaPage - 1) * $perPage;
    $tamOff  = ($tamPage - 1) * $perPage;

    // =========================
    // LIST: TAMPILKAN SEMUA ATTEMPT (RETake dan NON-retake)
    // =========================

    // ---------- TPA rows ----------
    $tpaSql = "
        SELECT
            r.id, r.user_id, u.user_code, u.name AS user_name,
            r.score, r.created_at,
            r.attempt_no, r.is_retake
        FROM tpa_results r
        JOIN users u ON u.id = r.user_id
        WHERE 1=1
    ";

    $tpaTypes  = "";
    $tpaParams = [];

    if ($q !== '') {
        $tpaSql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $tpaTypes .= "ss";
        $tpaParams[] = $like;
        $tpaParams[] = $like;
    }
    if ($from !== '') {
        $tpaSql .= " AND r.created_at >= ? ";
        $tpaTypes .= "s";
        $tpaParams[] = $from . " 00:00:00";
    }
    if ($to !== '') {
        $tpaSql .= " AND r.created_at <= ? ";
        $tpaTypes .= "s";
        $tpaParams[] = $to . " 23:59:59";
    }

    $tpaSql .= " ORDER BY r.score DESC, r.created_at DESC, r.id DESC
                LIMIT ? OFFSET ? ";
    $tpaTypes .= "ii";
    $tpaParams[] = $perPage;
    $tpaParams[] = $tpaOff;

    $stmt = $conn->prepare($tpaSql);
    if (!$stmt) die("Prepare failed (TPA list all): " . $conn->error);
    bindParams($stmt, $tpaTypes, $tpaParams);
    $stmt->execute();
    $tpaRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ---------- TAM rows ----------
    $tamSql = "
        SELECT
            r.id, r.user_id, u.user_code, u.name AS user_name,
            r.total_correct, r.total_wrong, r.score, r.created_at,
            r.attempt_no, r.is_retake
        FROM tam_results r
        JOIN users u ON u.id = r.user_id
        WHERE 1=1
    ";

    $tamTypes  = "";
    $tamParams = [];

    if ($q !== '') {
        $tamSql .= " AND (u.name LIKE ? OR u.user_code LIKE ?) ";
        $like = "%{$q}%";
        $tamTypes .= "ss";
        $tamParams[] = $like;
        $tamParams[] = $like;
    }
    if ($from !== '') {
        $tamSql .= " AND r.created_at >= ? ";
        $tamTypes .= "s";
        $tamParams[] = $from . " 00:00:00";
    }
    if ($to !== '') {
        $tamSql .= " AND r.created_at <= ? ";
        $tamTypes .= "s";
        $tamParams[] = $to . " 23:59:59";
    }

    $tamSql .= " ORDER BY r.score DESC, r.created_at DESC, r.id DESC
                LIMIT ? OFFSET ? ";
    $tamTypes .= "ii";
    $tamParams[] = $perPage;
    $tamParams[] = $tamOff;

    $stmt = $conn->prepare($tamSql);
    if (!$stmt) die("Prepare failed (TAM list all): " . $conn->error);
    bindParams($stmt, $tamTypes, $tamParams);
    $stmt->execute();
    $tamRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // variabel untuk view
    $filters    = compact('q', 'from', 'to');
    $tpaPerPage = $perPage;
    $tamPerPage = $perPage;

    require __DIR__ . '/../views/admin/results.php';
}


function adminActivityListPage() {
    requireAdminLogin();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();

    global $conn;

    $limit = 50;
    $page  = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $off   = ($page - 1) * $limit;

    $q         = trim((string)($_GET['q'] ?? ''));            // cari user_code/nama/event
    $eventType = trim((string)($_GET['event_type'] ?? ''));   // auth|test
    $eventName = trim((string)($_GET['event_name'] ?? ''));   // login_success|enter|...
    $testCode  = trim((string)($_GET['test_code'] ?? ''));    // TPA|TAM|KRAEPLIN
    $dateFrom  = trim((string)($_GET['from'] ?? ''));         // YYYY-MM-DD
    $dateTo    = trim((string)($_GET['to'] ?? ''));           // YYYY-MM-DD

    $where  = [];
    $types  = "";
    $params = [];

    if ($q !== '') {
        // EMAIL DIHAPUS → pakai user_code + name
        $where[] = "(u.user_code LIKE ? OR u.name LIKE ? OR l.event_name LIKE ? OR l.test_code LIKE ?)";
        $like = "%{$q}%";
        $types .= "ssss";
        array_push($params, $like, $like, $like, $like);
    }
    if ($eventType !== '') {
        $where[] = "l.event_type = ?";
        $types .= "s";
        $params[] = $eventType;
    }
    if ($eventName !== '') {
        $where[] = "l.event_name = ?";
        $types .= "s";
        $params[] = $eventName;
    }
    if ($testCode !== '') {
        $where[] = "l.test_code = ?";
        $types .= "s";
        $params[] = $testCode;
    }
    if ($dateFrom !== '') {
        $where[] = "DATE(l.event_time) >= ?";
        $types .= "s";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = "DATE(l.event_time) <= ?";
        $types .= "s";
        $params[] = $dateTo;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // COUNT
    $countSql = "
        SELECT COUNT(*) AS cnt
        FROM user_activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        {$whereSql}
    ";
    $total = 0;
    $stmt = $conn->prepare($countSql);
    if (!$stmt) { die("Prepare failed (count activity): " . $conn->error); }
    bindParams($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total = (int)($row['cnt'] ?? 0);
    $stmt->close();

    $pages = max(1, (int)ceil($total / $limit));

    // DATA
    $dataSql = "
        SELECT
            l.id, l.event_time, l.event_type, l.event_name, l.test_code,
            l.ip, l.user_agent, l.detail_json,
            u.user_code AS user_code,
            u.name      AS user_name
        FROM user_activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        {$whereSql}
        ORDER BY l.event_time DESC
        LIMIT ? OFFSET ?
    ";

    $rows = [];
    $stmt = $conn->prepare($dataSql);
    if (!$stmt) { die("Prepare failed (list activity): " . $conn->error); }

    $types2  = $types . "ii";
    $params2 = $params;
    $params2[] = $limit;
    $params2[] = $off;

    bindParams($stmt, $types2, $params2);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    require './views/admin/activity_list.php';
}


function adminActivityDetail() {
    requireAdminLogin();
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    sendNoCacheHeaders();

    header('Content-Type: application/json; charset=utf-8');
    global $conn;

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { echo json_encode(['ok'=>false]); exit; }

    $sql = "
        SELECT l.*,
               u.user_code AS user_code,
               u.name      AS user_name
        FROM user_activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE l.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(['ok'=>false,'err'=>$conn->error]); exit; }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['ok'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
}
