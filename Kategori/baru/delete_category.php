<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $category_id = intval($_POST['category_id']);

    // Cek apakah kategori milik user
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Kategori tidak ditemukan atau bukan milik user
        header("Location: dashboard.php?error=notfound");
        exit;
    }

    // Hapus kategori
    $del = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $del->bind_param("i", $category_id);
    $del->execute();

    header("Location: dashboard.php?success=deleted");
    exit;
}
?>
