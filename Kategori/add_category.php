<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
        $stmt->bind_param("si", $category_name, $user_id);
        $stmt->execute();
    }
}

header("Location: dashboard.php"); // ganti sesuai nama file utama kamu
exit;
