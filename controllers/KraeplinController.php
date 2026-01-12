<?php
require_once './config/database.php';
require_once './models/Kraeplin.php';

function adminKraeplinSettingsPage() {
    global $conn;

    // Ambil konfigurasi terbaru (kalau ada)
    $res = $conn->query("SELECT * FROM kraeplin_settings ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $settings = $res->fetch_assoc();
    } else {
        // Default kalau belum ada data
        $settings = [
            'id'       => null,
            'duration' => 15, // default 15 menit
        ];
    }

    include __DIR__ . '/../views/admin/kraeplin_settings.php';
}


/* ============================================================
   ===================== ADMIN – SETTINGS ======================
   ============================================================ */
function UserkraeplinSettings() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-login");
        exit;
    }

    $settings = Kraeplin::getSettings();
    require './views/admin/kraeplin_settings.php';
}

/* ============================================================
   ADMIN – PROCESS UPDATE DURATION
   ============================================================ */
function UserkraeplinSettingsProcess() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    $duration = intval($_POST['duration']); // 5–30 menit (300–1800 detik)

    Kraeplin::updateSettings($duration);

    echo "<script>alert('Durasi tes berhasil diperbarui!'); 
          window.location='index.php?page=admin-kraeplin-settings';</script>";
}

/* ============================================================
   ========================= USER – TEST PAGE ==================
   ============================================================ */

/* ============================================================
   USER – SUBMIT & ANALYSIS
   ============================================================ */
function kraeplinSubmit() {
    if (!isset($_SESSION['user_id'])) { exit("Unauthorized"); }

    $user_id = $_SESSION['user_id'];

    // Raw input berisi JSON:
    // [
    //   { total_items: xx, correct: yy },
    //   ...
    // ]
    $raw_json = $_POST['raw_lines'];
    $lines = json_decode($raw_json, true);

    /* ==================== ANALISIS ===================== */

    // Produktivitas total (jumlah item dikerjakan)
    $total_prod = array_sum(array_column($lines, 'total_items'));

    // Jumlah benar
    $total_correct = array_sum(array_column($lines, 'correct'));

    // Akurasi %
    $accuracy = 0;
    if ($total_prod > 0) {
        $accuracy = ($total_correct / $total_prod) * 100;
    }

    // Stabilitas / Variance
    $arr = array_column($lines, 'total_items');
    $mean = array_sum($arr) / max(count($arr), 1);
    $variance = 0;

    foreach ($arr as $v) {
        $variance += pow(($v - $mean), 2);
    }

    if (count($arr) > 0) {
        $variance /= count($arr);
    }

    // Konsentrasi (awal → akhir)
    $first3 = array_slice($arr, 0, 3);
    $last3  = array_slice($arr, -3);

    $trend_val = (array_sum($last3) / max(count($last3), 1)) -
                 (array_sum($first3) / max(count($first3), 1));

    if ($trend_val > 0)      $trend = "meningkat";
    else if ($trend_val < 0) $trend = "menurun";
    else                     $trend = "stabil";

    // Adaptasi (awal → tengah)
    $mid3 = array_slice($arr, floor(count($arr)/2) - 1, 3);

    $adapt_val = (array_sum($mid3) / max(count($mid3), 1)) -
                 (array_sum($first3) / max(count($first3), 1));

    // Pola kerja:
    $pattern = "zig-zag"; // default

    if ($arr === array_values($arr) && $arr === array_unique($arr)) {
        $pattern = "naik";
    } 
    else if ($arr === array_reverse($arr)) {
        $pattern = "menurun";
    }

    /* ==================== SIMPAN HASIL ===================== */

    Kraeplin::saveResult(
        $user_id,
        $total_prod,
        $total_correct,
        $accuracy,
        $variance,
        $trend,
        $adapt_val,
        $pattern,
        $raw_json
    );

    echo "<script>alert('Tes Kraeplin selesai!'); 
          window.location='index.php?page=user-dashboard';</script>";
}

/* ============================================================
   ==================== ADMIN – VIEW RESULTS ===================
   (opsional, halaman nanti dibuat)
   ============================================================ */
function UserkraeplinResults() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-login");
        exit;
    }

    global $conn;
    $results = $conn->query("SELECT * FROM kraeplin_results ORDER BY id DESC");

    require './views/admin/kraeplin_results.php';
}
