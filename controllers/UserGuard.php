<?php
function ensureUserSession() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['user_id'])) {
    header("Location: index.php?page=user-login");
    exit;
  }
}

function isUserActiveNow($conn, $userId) {
  $stmt = $conn->prepare("SELECT is_active FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->bind_result($active);
  $stmt->fetch();
  $stmt->close();
  return ((int)$active === 1);
}
