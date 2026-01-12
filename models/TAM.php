<?php

class TAM {

    /* ============================================================
       Ambil stimulus global
       ============================================================ */
 // models/TAM.php
public static function getPackage() {
    global $conn;
    $sql = "SELECT * FROM tam_package ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    return $res ? $res->fetch_assoc() : null;
}

    /* ============================================================
       Update stimulus (Admin)
       ============================================================ */
    public static function updatePackage($image_path, $duration_display, $duration_answer) {
        global $conn;

        $stmt = $conn->prepare("
            UPDATE tam_package 
            SET image_path=?, duration_display=?, duration_answer=? 
            WHERE id=1
        ");

        $stmt->bind_param("sii", $image_path, $duration_display, $duration_answer);
        return $stmt->execute();
    }

    /* ============================================================
       Ambil semua soal TAM
       ============================================================ */
    public static function getQuestions() {
        global $conn;

        return $conn->query("SELECT * FROM tam_questions ORDER BY id ASC");
    }

    /* ============================================================
       Tambah soal TAM (Admin)
       ============================================================ */
    public static function createQuestion($data) {
        global $conn;

        $stmt = $conn->prepare("
            INSERT INTO tam_questions (question, option_a, option_b, option_c, option_d, correct_option)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssss",
            $data['question'], $data['option_a'], $data['option_b'],
            $data['option_c'], $data['option_d'], $data['correct_option']
        );

        return $stmt->execute();
    }

    /* ============================================================
       Ambil jawaban benar TAM
       ============================================================ */
    public static function getCorrect($id) {
        global $conn;

        $stmt = $conn->prepare("SELECT correct_option FROM tam_questions WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $data = $stmt->get_result()->fetch_assoc();
        return $data['correct_option'] ?? null;
    }

    /* ============================================================
       Simpan hasil TAM user
       ============================================================ */
  public static function saveResult($user_id, $total_correct, $total_wrong, $score)
    {
        global $conn;

        $stmt = $conn->prepare("
            INSERT INTO tam_results (user_id, total_correct, total_wrong, score)
            VALUES (?, ?, ?, ?)
        ");

        // i = int, i = int, i = int, i = int
        $stmt->bind_param('iiii', $user_id, $total_correct, $total_wrong, $score);

        return $stmt->execute();
    }
}
