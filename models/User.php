<?php
class User {

    public static function create($name, $birthdate) {
        global $conn;

        // Insert user
        $stmt = $conn->prepare("INSERT INTO users (name, birthdate) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $birthdate);
        $stmt->execute();

        $id = $stmt->insert_id;

        // Generate user_code (id-tgllahir)
        $birth = str_replace("-", "", $birthdate);
        $user_code = $id . "-" . $birth;

        // Update user_code
        $stmt2 = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ?");
        $stmt2->bind_param("si", $user_code, $id);
        $stmt2->execute();

        return $user_code;
    }

    public static function getAll() {
        global $conn;
        return $conn->query("SELECT * FROM users ORDER BY id DESC");
    }

    public static function findById($id) {
        global $conn;

        $stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    public static function findByUserCode(string $userCode): ?array
    {
        global $conn; // pakai koneksi dari config/database.php

        $stmt = $conn->prepare("
            SELECT id, user_code, name, birthdate, created_at
            FROM users
            WHERE user_code = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $userCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $user ?: null;
    }
    
    public static function update($id, $name, $birthdate) {
        global $conn;

        $stmt = $conn->prepare("UPDATE users SET name=?, birthdate=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $birthdate, $id);
        $stmt->execute();

        // regenerate user_code
        $birth = str_replace("-", "", $birthdate);
        $user_code = $id . "-" . $birth;

        $stmt2 = $conn->prepare("UPDATE users SET user_code=? WHERE id=?");
        $stmt2->bind_param("si", $user_code, $id);
        return $stmt2->execute();
    }

    public static function delete($id) {
        global $conn;

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
