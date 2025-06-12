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
    $category_name = trim($_POST['category_name']);

    // Cek kategori milik user
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: dashboard.php?error=notfound");
        exit;
    }

    // Update kategori
    $update = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $update->bind_param("si", $category_name, $category_id);
    $update->execute();

    header("Location: dashboard.php?success=updated");
    exit;
}
?>
