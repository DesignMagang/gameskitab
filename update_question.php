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
    $answer = trim($_POST['answer']); // ⬅️ ambil jawaban dari form
    $category = intval($_POST['category_id']);

    // Validasi kepemilikan pertanyaan
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
        header("Location: category.php?id=$category&error=notfound");
        exit;
    }

    // Update pertanyaan & jawaban
    $update = $conn->prepare("UPDATE questions SET question_text = ?, answer = ? WHERE id = ?");
    $update->bind_param("ssi", $text, $answer, $id);
    $update->execute();

    header("Location: category.php?id=$category");
    exit;
}
