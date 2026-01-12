<?php
require_once './config/database.php';
require_once './models/TPA.php';

/* ============================================================
   ADMIN – LIST SOAL TPA PER KATEGORI
   ============================================================ */
function tpaList() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-login");
        exit;
    }

    $category = $_GET['cat'] ?? 'verbal';
    $session  = $_GET['session'] ?? null;

    if ($category === 'verbal' && $session) {
        $questions = TPA::getByCategorySession($category, $session);
    } else if ($category !== 'verbal') {
        $questions = TPA::getByCategory($category);
    }

    require './views/admin/tpa_list.php';
}

/* ============================================================
   ADMIN – PAGE ADD SOAL
   ============================================================ */
function tpaAdd() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: index.php?page=admin-login");
        exit;
    }

    require './views/admin/tpa_add.php';
}

/* ============================================================
   ADMIN – PROCESS ADD SOAL (UPLOAD + INSERT)
   ============================================================ */
function tpaAddProcess() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    // Ambil data input
    $category = $_POST['category'];
    $session  = $_POST['session'] ?? null;
    $type     = $_POST['type'];
    $correct  = $_POST['correct_option'];

    // Upload helper
    function uploadOrNull($name) {
        if (!empty($_FILES[$name]['name'])) {
            $filename = time() . '_' . basename($_FILES[$name]['name']);
            $target = "./uploads/tpa/" . $filename;
            move_uploaded_file($_FILES[$name]['tmp_name'], $target);
            return $target;
        }
        return null;
    }

    // Question
    $question_text  = $_POST['question_text'];
    $question_image = uploadOrNull("question_image");

    // Options
    $option_a_text  = $_POST['option_a_text'];
    $option_a_img   = uploadOrNull("option_a_image");

    $option_b_text  = $_POST['option_b_text'];
    $option_b_img   = uploadOrNull("option_b_image");

    $option_c_text  = $_POST['option_c_text'];
    $option_c_img   = uploadOrNull("option_c_image");

    $option_d_text  = $_POST['option_d_text'];
    $option_d_img   = uploadOrNull("option_d_image");

    // Prepare data
    $data = [
        "category" => $category,
        "session" => $session,
        "type" => $type,
        "question_text" => $question_text,
        "question_image" => $question_image,
        "option_a_text" => $option_a_text,
        "option_a_image" => $option_a_img,
        "option_b_text" => $option_b_text,
        "option_b_image" => $option_b_img,
        "option_c_text" => $option_c_text,
        "option_c_image" => $option_c_img,
        "option_d_text" => $option_d_text,
        "option_d_image" => $option_d_img,
        "correct_option" => $correct
    ];

    // Save to DB
    TPA::create($data);

    echo "<script>alert('Soal TPA berhasil ditambahkan!'); window.location='index.php?page=admin-tpa-list&cat=$category';</script>";
}

/* ============================================================
   ADMIN – EDIT SOAL PAGE
   ============================================================ */
function tpaEdit() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    $id = $_GET['id'];
    global $conn;

    $q = $conn->query("SELECT * FROM tpa_questions WHERE id=$id")->fetch_assoc();

    require './views/admin/tpa_edit.php';
}

/* ============================================================
   ADMIN – PROCESS EDIT SOAL
   ============================================================ */
function tpaEditProcess() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    global $conn;

    $id       = $_POST['id'];
    $category = $_POST['category'];
    $session  = $_POST['session'];
    $type     = $_POST['type'];
    $correct  = $_POST['correct_option'];

    // Upload helper
    function uploadOrKeep($name, $old) {
        if (!empty($_FILES[$name]['name'])) {
            $filename = time() . '_' . basename($_FILES[$name]['name']);
            $target   = "./uploads/tpa/" . $filename;
            move_uploaded_file($_FILES[$name]['tmp_name'], $target);
            return $target;
        }
        return $old;
    }

    // ====== question ======
    $question_text  = $_POST['question_text'];
    $question_image_old = $_POST['old_question_image'];
    $question_image = uploadOrKeep("question_image", $question_image_old);

    // ====== options ======
    $option_a_text = $_POST['option_a_text'];
    $option_a_image = uploadOrKeep("option_a_image", $_POST['old_option_a_image']);

    $option_b_text = $_POST['option_b_text'];
    $option_b_image = uploadOrKeep("option_b_image", $_POST['old_option_b_image']);

    $option_c_text = $_POST['option_c_text'];
    $option_c_image = uploadOrKeep("option_c_image", $_POST['old_option_c_image']);

    $option_d_text = $_POST['option_d_text'];
    $option_d_image = uploadOrKeep("option_d_image", $_POST['old_option_d_image']);

    // Update
    $stmt = $conn->prepare("
        UPDATE tpa_questions SET
            category=?, session=?, type=?,
            question_text=?, question_image=?,
            option_a_text=?, option_a_image=?,
            option_b_text=?, option_b_image=?,
            option_c_text=?, option_c_image=?,
            option_d_text=?, option_d_image=?,
            correct_option=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssssssssssssi",
        $category, $session, $type,
        $question_text, $question_image,
        $option_a_text, $option_a_image,
        $option_b_text, $option_b_image,
        $option_c_text, $option_c_image,
        $option_d_text, $option_d_image,
        $correct, $id
    );

    $stmt->execute();

    echo "<script>alert('Soal berhasil diperbarui!'); window.location='index.php?page=admin-tpa-list&cat=$category';</script>";
}

/* ============================================================
   ADMIN – DELETE SOAL
   ============================================================ */
function tpaDelete() {
    if (!isset($_SESSION['admin_id'])) { exit("Unauthorized"); }

    $id = $_GET['id'];
    global $conn;

    $conn->query("DELETE FROM tpa_questions WHERE id=$id");

    echo "<script>alert('Soal berhasil dihapus!'); history.back();</script>";
}

/* ============================================================
   USER TPA ENDPOINT (Sudah ditangani UserController)
   ============================================================ */
function userTPA() {
    echo "This is handled inside UserController.";
}

