<?php

class Kraeplin {

    /* ============================================================
       Ambil pengaturan durasi tes + interval skor
       ============================================================ */
    public static function getSettings() {
        global $conn;

        $result = $conn->query("SELECT * FROM kraeplin_settings WHERE id = 1 LIMIT 1");
        $row    = $result ? $result->fetch_assoc() : null;

        // Jika belum ada row sama sekali, kembalikan default
        if (!$row) {
            $row = [
                'id'               => 1,
                'duration'         => 20,  // menit default
                'interval_seconds' => 10,  // detik default
            ];
        } else {
            // Safety: kalau kolom interval_seconds kosong/null
            if (!isset($row['interval_seconds']) || $row['interval_seconds'] === null) {
                $row['interval_seconds'] = 10;
            }
        }

        return $row;
    }

    /* ============================================================
       Update durasi + interval (Admin)
       ============================================================ */
    public static function updateSettings($duration, $intervalSeconds) {
        global $conn;

        // Normalisasi nilai (optional, biar nggak “ngaco”)
        $duration        = (int)$duration;
        $intervalSeconds = (int)$intervalSeconds;

        if ($duration < 5)  $duration = 5;
        if ($duration > 30) $duration = 30;

        if ($intervalSeconds < 5)  $intervalSeconds = 5;
        if ($intervalSeconds > 60) $intervalSeconds = 60;

        // Pakai upsert: kalau id=1 belum ada → INSERT, kalau sudah ada → UPDATE
        $stmt = $conn->prepare("
            INSERT INTO kraeplin_settings (id, duration, interval_seconds)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE
                duration         = VALUES(duration),
                interval_seconds = VALUES(interval_seconds)
        ");

        $stmt->bind_param("ii", $duration, $intervalSeconds);
        return $stmt->execute();
    }

    /* ============================================================
       Simpan hasil tes Kraeplin user
       ============================================================ */
    public static function saveResult(
        $user_id,
        $total_items,
        $total_correct,
        $accuracy,
        $stability_score,
        $trend,
        $adapt,
        $pattern,
        $raw_json
    ) {
        global $conn;

        $stmt = $conn->prepare("
            INSERT INTO kraeplin_results
            (user_id, total_productivity, total_correct, accuracy_percentage,
             stability_score, concentration_trend, adaptation_score, work_pattern,
             raw_lines)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // i = int, d = double/float, s = string
        $stmt->bind_param(
            "iiiddsdss",
            $user_id,
            $total_items,
            $total_correct,
            $accuracy,
            $stability_score,
            $trend,
            $adapt,
            $pattern,
            $raw_json
        );

        return $stmt->execute();
    }
}
