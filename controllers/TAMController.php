<?php
require_once './config/database.php';
require_once './models/TAM.php';

/* ============================================================
   =============== ADMIN: TAM – PAKET STIMULUS ==================
   ============================================================ */

function uploadTAMImage($fieldName, $oldPath = null) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        // tidak upload baru → pakai path lama saja
        return $oldPath;
    }

    $file = $_FILES[$fieldName];

    // Validasi dasar
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        // Di production sebaiknya dilempar error / flash message
        return $oldPath;
    }

    // Pastikan folder upload ada
    $uploadDir = __DIR__ . '/../uploads/tam';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Nama file unik
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'tam_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

    $destination = $uploadDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Simpan path relatif dari root web
        $relativePath = 'uploads/tam/' . $filename;

        // Optional: hapus file lama jika ada
        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
            @unlink(__DIR__ . '/../' . $oldPath);
        }

        return $relativePath;
    }

    return $oldPath;
}


function adminTAMPackagePage() {
    global $conn;

    // Ambil 1 paket TAM (global)
    $res = $conn->query("SELECT * FROM tam_package ORDER BY id DESC LIMIT 1");
    $package = $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;

    // Durasi default kalau belum ada data (menit)
    if (!$package) {
        $package = [
            'id'               => null,
            'image_path'       => null,
            'duration_display' => 5,   // 5 menit tampilan stimulus
            'duration_answer'  => 15,  // 15 menit menjawab
        ];
    }

    include __DIR__ . '/../views/admin/tam_package.php';
}

function adminTAMPackageSaveProcess() {
    global $conn;

    $duration_display = isset($_POST['duration_display']) ? (int)$_POST['duration_display'] : 5;
    $duration_answer  = isset($_POST['duration_answer'])  ? (int)$_POST['duration_answer']  : 15;
    $old_image_path   = $_POST['old_image_path'] ?? null;

    // Upload gambar stimulus (jika ada upload baru)
    $image_path = uploadTAMImage('image', $old_image_path);

    // Cek apakah sudah ada paket
    $res = $conn->query("SELECT id FROM tam_package ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $id  = (int)$row['id'];

        $stmt = $conn->prepare("
            UPDATE tam_package
            SET image_path = ?, duration_display = ?, duration_answer = ?
            WHERE id = ?
        ");
        $stmt->bind_param('siii', $image_path, $duration_display, $duration_answer, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO tam_package (image_path, duration_display, duration_answer)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('sii', $image_path, $duration_display, $duration_answer);
        $stmt->execute();
    }

    header("Location: index.php?page=admin-tam-package");
    exit;
}

/* ============================================================
   ADMIN – UPDATE STIMULUS & DURASI
   ============================================================ */
function tamPackageProcess() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    // Upload gambar stimulus
    $image_path = null;

    if (!empty($_FILES['stimulus_image']['name'])) {
        $filename = time() . "_" . basename($_FILES['stimulus_image']['name']);
        $target = "./uploads/tam/" . $filename;
        move_uploaded_file($_FILES['stimulus_image']['tmp_name'], $target);
        $image_path = $target;
    } else {
        $image_path = $_POST['old_image'];
    }

    $duration_display = intval($_POST['duration_display']); // ex: 300
    $duration_answer  = intval($_POST['duration_answer']);  // ex: 900

    TAM::updatePackage($image_path, $duration_display, $duration_answer);

    echo "<script>alert('Paket stimulus TAM berhasil diperbarui!'); 
          window.location='index.php?page=admin-tam-package';</script>";
}

/* ============================================================
   ================== ADMIN: LIST SOAL TAM =====================
   ============================================================ */
// LIST SOAL TAM
function adminTAMListPage() {
    global $conn;

    $result = $conn->query("SELECT * FROM tam_questions ORDER BY id ASC");
    include __DIR__ . '/../views/admin/tam_list.php';
}

// FORM TAMBAH SOAL
function adminTAMAddPage() {
    include __DIR__ . '/../views/admin/tam_add.php';
}

// PROSES TAMBAH
function adminTAMAddProcess() {
    global $conn;

    $question      = $_POST['question'] ?? '';
    $option_a      = $_POST['option_a'] ?? null;
    $option_b      = $_POST['option_b'] ?? null;
    $option_c      = $_POST['option_c'] ?? null;
    $option_d      = $_POST['option_d'] ?? null;
    $correctOption = $_POST['correct_option'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO tam_questions (question, option_a, option_b, option_c, option_d, correct_option)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'ssssss',
        $question, $option_a, $option_b, $option_c, $option_d, $correctOption
    );
    $stmt->execute();

    header("Location: index.php?page=admin-tam-list");
    exit;
}

// FORM EDIT
function adminTAMEditPage() {
    global $conn;

    $id  = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM tam_questions WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();

    if (!$row) {
        header("Location: index.php?page=admin-tam-list");
        exit;
    }

    include __DIR__ . '/../views/admin/tam_edit.php';
}

// PROSES EDIT
function adminTAMEditProcess() {
    global $conn;

    $id           = (int)$_POST['id'];
    $question     = $_POST['question'] ?? '';
    $option_a     = $_POST['option_a'] ?? null;
    $option_b     = $_POST['option_b'] ?? null;
    $option_c     = $_POST['option_c'] ?? null;
    $option_d     = $_POST['option_d'] ?? null;
    $correctOption= $_POST['correct_option'] ?? null;

    $stmt = $conn->prepare("
        UPDATE tam_questions
        SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'ssssssi',
        $question, $option_a, $option_b, $option_c, $option_d, $correctOption, $id
    );
    $stmt->execute();

    header("Location: index.php?page=admin-tam-list");
    exit;
}

// HAPUS
function adminTAMDelete() {
    global $conn;

    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM tam_questions WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    header("Location: index.php?page=admin-tam-list");
    exit;
}

/* ============================================================
   ======================= USER: STIMULUS =======================
   ============================================================ */
function tamStimulus() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?page=user-login");
        exit;
    }

    $package = TAM::getPackage();
    require './views/user/tam/stimulus.php';
}

/* ============================================================
   ========================= USER: TES TAM ======================
   ============================================================ */
function tamTest() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?page=user-login");
        exit;
    }

    $questions = TAM::getQuestions();
    $package   = TAM::getPackage();

    require './views/user/tam/test.php';
}

/* ============================================================
   =============== USER: SUBMIT TAM & AUTOSCORE ================
   ============================================================ */
function tamSubmit() {
    if (!isset($_SESSION['user_id'])) { exit("Unauthorized"); }

    $user_id = $_SESSION['user_id'];
    $answers = $_POST['answer'] ?? [];

    $score = 0;

    foreach ($answers as $qid => $ans) {
        $correct = TAM::getCorrect($qid);
        if ($ans == $correct) {
            $score++;
        }
    }

    TAM::saveResult($user_id, $score, json_encode($answers));

    echo "<script>alert('Tes TAM selesai! Skor Anda: $score');
         window.location='index.php?page=user-dashboard';</script>";
}
