<?php
session_start();
include 'db.php';

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("DELETE FROM riwayat_peserta WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_GET['id'], $_SESSION['user_id']);
    $stmt->execute();
}

header("Location: kelompok.php"); // Ganti dengan nama file utamamu
exit();
