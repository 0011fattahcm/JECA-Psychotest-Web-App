<?php
require_once __DIR__ . '/../config/database.php';

$username = 'godblessingmycode'; // ganti!
$plain    = 'OfficialAdmin2025'; // ganti!

$hash = password_hash($plain, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE password = VALUES(password)");
$stmt->bind_param("ss", $username, $hash);
$stmt->execute();

echo "OK. Admin password hashed & saved for: {$username}\n";
