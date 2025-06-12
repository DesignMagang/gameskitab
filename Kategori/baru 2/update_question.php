<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $id = intval($_POST['question_id']);
    $text = trim($_POST['question_text']);
    $category = intval($_POST['category_id']); // ambil dari POST, bukan GET

    // Validasi: pastikan pertanyaan ini milik user & ada dalam kategori itu
    $cek = $conn->prepare("
        SELECT q.id 
        FROM questions q 
        JOIN categories c ON q.category_id = c.id 
        WHERE q.id = ? AND q.category_id = ? AND c.user_id = ?
    ");
    $cek->bind_param("iii", $id, $category, $user_id);
    $cek->execute();
    $result = $cek->get_result();

    if ($result->num_rows === 0) {
        // Jangan tampilkan pesan ke user, cukup redirect
        header("Location: category.php?id=$category&error=notfound");
        exit;
    }

    // Update
    $update = $conn->prepare("UPDATE questions SET question_text = ? WHERE id = ?");
    $update->bind_param("si", $text, $id);
    $update->execute();

    header("Location: category.php?id=$category");
    exit;
}

?>
