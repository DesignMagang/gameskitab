<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_id'])) {
    $sessionId = $_POST['session_id'];
    $userId = $_SESSION['user_id'];

    // Hapus dari tabel session_questions dulu (jika pakai FK constraint)
    $conn->query("DELETE FROM session_questions WHERE session_id = '$sessionId'");

    // Hapus dari tabel sessions
    $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ? AND created_by = ?");
    $stmt->bind_param("si", $sessionId, $userId);
    $stmt->execute();

    header("Location: create_matching.php");
    exit;
}
?>
