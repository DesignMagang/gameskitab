<?php
session_start();
include 'db.php'; // Pastikan koneksi database sudah ada

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $question_id = intval($_POST['question_id']);
    $category_id = intval($_POST['category_id']); // Mungkin tidak langsung digunakan untuk update pertanyaan, tapi penting untuk redirect
    $question_text = $_POST['question_text'];
    $answer_text = $_POST['answer_text']; // <--- Ini yang penting!

    // Sanitasi input untuk mencegah SQL Injection
    $question_text = $conn->real_escape_string($question_text);
    $answer_text = $conn->real_escape_string($answer_text);

    // Periksa kepemilikan pertanyaan
    $check_owner_sql = "SELECT q.id FROM questions q JOIN categories c ON q.category_id = c.id WHERE q.id = $question_id AND c.user_id = $user_id";
    $check_owner_result = $conn->query($check_owner_sql);

    if ($check_owner_result->num_rows > 0) {
        // Query untuk UPDATE jawaban
        $sql = "UPDATE questions SET question_text = '$question_text', answer = '$answer_text' WHERE id = $question_id";

        if ($conn->query($sql) === TRUE) {
            // Berhasil disimpan, redirect kembali ke halaman kategori
            header("Location: category.php?id=" . $category_id);
            exit;
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    } else {
        die("Tidak ditemukan atau bukan pertanyaan Anda.");
    }
} else {
    die("Invalid request method.");
}

$conn->close();
?>