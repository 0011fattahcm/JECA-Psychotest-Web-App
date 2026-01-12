<?php

class TPA
{
    /* ============================================================
       ADMIN – Ambil soal berdasarkan kategori + sesi (urutan ID)
       ============================================================ */
    public static function getByCategorySession($category, $session)
    {
        global $conn;

        $stmt = $conn->prepare("SELECT * FROM tpa_questions WHERE category=? AND session=? ORDER BY id ASC");
        $stmt->bind_param("ss", $category, $session);
        $stmt->execute();

        return $stmt->get_result();
    }

    /* ============================================================
       ADMIN – Ambil soal berdasarkan kategori (tanpa sesi, urutan ID)
       ============================================================ */
    public static function getByCategory($category)
    {
        global $conn;

        $stmt = $conn->prepare("SELECT * FROM tpa_questions WHERE category=? ORDER BY id ASC");
        $stmt->bind_param("s", $category);
        $stmt->execute();

        return $stmt->get_result();
    }

    /* ============================================================
       USER – Ambil soal random per kategori + sesi (LIMIT)
       Digunakan untuk membangun 60 soal TPA:
       - Verbal sesi 1–3 @5 soal
       - Kuantitatif @20 soal
       - Logika @10 soal
       - Spasial @15 soal
       ============================================================ */
    public static function getRandomByCategorySession($category, $session, $limit)
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT * FROM tpa_questions 
            WHERE category=? AND session=? 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->bind_param("ssi", $category, $session, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public static function getRandomByCategory($category, $limit)
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT * FROM tpa_questions 
            WHERE category=? 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->bind_param("si", $category, $limit);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /* ============================================================
       USER – Helper: bangun struktur $sections 60 soal sekali tes
       ============================================================ */
    public static function buildSectionsForFullTest()
    {
        $sections = [];

        // Verbal – Sesi 1–3 (masing-masing 5 soal)
        for ($s = 1; $s <= 3; $s++) {
            $sections[] = [
                'title'     => "Verbal – Sesi {$s}",
                'subtitle'  => 'Kemampuan bahasa (sinonim, antonim, pemahaman kata)',
                'questions' => self::getRandomByCategorySession('verbal', (string)$s, 5),
            ];
        }

        // Kuantitatif – 20 soal
        $sections[] = [
            'title'     => 'Kuantitatif',
            'subtitle'  => 'Kemampuan numerik dan berhitung',
            'questions' => self::getRandomByCategory('kuantitatif', 20),
        ];

        // Logika – 10 soal
        $sections[] = [
            'title'     => 'Logika',
            'subtitle'  => 'Penalaran logis dan pola berpikir',
            'questions' => self::getRandomByCategory('logika', 10),
        ];

        // Spasial – 15 soal
        $sections[] = [
            'title'     => 'Spasial',
            'subtitle'  => 'Kemampuan membayangkan bentuk dan posisi ruang',
            'questions' => self::getRandomByCategory('spasial', 15),
        ];

        return $sections;
    }

    /* ============================================================
       Get correct answer untuk autoscore (1 soal)
       ============================================================ */
    public static function getCorrectAnswer($question_id)
    {
        global $conn;

        $stmt = $conn->prepare("SELECT correct_option FROM tpa_questions WHERE id=?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();

        $data = $stmt->get_result()->fetch_assoc();
        return $data['correct_option'] ?? null;
    }

    /* ============================================================
       USER – Ambil meta beberapa soal sekaligus (id, category, session, correct_option)
       ============================================================ */
    public static function getQuestionsMeta(array $ids)
    {
        global $conn;

        if (empty($ids)) {
            return [];
        }

        // pastikan bersih, hanya integer
        $ids = array_map('intval', $ids);
        $in  = implode(',', $ids);

        $sql = "
            SELECT id, category, session, correct_option 
            FROM tpa_questions 
            WHERE id IN ($in)
        ";

        $result = $conn->query($sql);
        $data   = [];

        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['id']] = $row;
        }

        return $data;
    }

    /* ============================================================
       Simpan hasil TPA user (1 baris per tes)
       Catatan:
       - Field category/session di DB sekarang enum, jadi untuk sementara
         kita isi 'verbal' dan '1' sebagai placeholder.
       - Kalau nanti mau lebih rapi, kamu bisa ubah enum jadi ada nilai
         khusus misalnya 'tpa_all' dan '0', lalu ganti di sini.
       ============================================================ */
    public static function saveResult($user_id, $category, $session, $score, $answers_json)
    {
        global $conn;

        $stmt = $conn->prepare("
            INSERT INTO tpa_results 
            (user_id, category, session, score, answers) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("issis", $user_id, $category, $session, $score, $answers_json);
        return $stmt->execute();
    }

    /* ============================================================
       Ambil semua soal (untuk Admin)
       ============================================================ */
    public static function getAll()
    {
        global $conn;
        return $conn->query("SELECT * FROM tpa_questions ORDER BY id DESC");
    }

    /* ============================================================
       Tambah soal TPA (Admin)
       ============================================================ */
    public static function create($data)
    {
        global $conn;

        $stmt = $conn->prepare("
            INSERT INTO tpa_questions
            (category, session, type, question_text, question_image,
             option_a_text, option_a_image,
             option_b_text, option_b_image,
             option_c_text, option_c_image,
             option_d_text, option_d_image,
             correct_option)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssssssss",
            $data['category'], $data['session'], $data['type'],
            $data['question_text'], $data['question_image'],
            $data['option_a_text'], $data['option_a_image'],
            $data['option_b_text'], $data['option_b_image'],
            $data['option_c_text'], $data['option_c_image'],
            $data['option_d_text'], $data['option_d_image'],
            $data['correct_option']
        );

        return $stmt->execute();
    }
}
