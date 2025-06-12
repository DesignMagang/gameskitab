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
    $question_text = trim($_POST['question_text']);

    // Cek kepemilikan kategori
    $cek = $conn->query("SELECT * FROM categories WHERE id = $category_id AND user_id = $user_id");
    if ($cek->num_rows == 0) {
        die("Kategori tidak ditemukan.");
    }

    if (!empty($question_text)) {
        $stmt = $conn->prepare("INSERT INTO questions (category_id, question_text) VALUES (?, ?)");
        $stmt->bind_param("is", $category_id, $question_text);
        $stmt->execute();
    }
}

header("Location: category.php?id=$category_id");
exit;
