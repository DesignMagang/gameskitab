<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$question_id = intval($_GET['id']);
$category_id = intval($_GET['category']);

// Cek apakah pertanyaan memang milik user
$cek = $conn->query("
    SELECT q.id FROM questions q
    JOIN categories c ON q.category_id = c.id
    WHERE q.id = $question_id AND c.user_id = $user_id
");

if ($cek->num_rows > 0) {
    $conn->query("DELETE FROM questions WHERE id = $question_id");
}

header("Location: category.php?id=$category_id");
exit;
