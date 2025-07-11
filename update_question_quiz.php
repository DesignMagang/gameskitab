<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Pastikan kita mendapatkan id pertanyaan, teks pertanyaan, dan quiz_id
// Form dari quiz_question.php mengirimkan 'quiz_id' melalui POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['question_id']) && isset($_POST['question_text']) && isset($_POST['quiz_id'])) { // <-- UBAH DARI 'category_id' ke 'quiz_id'
    $question_id = intval($_POST['question_id']);
    $question_text = $_POST['question_text'];
    $answer_text = isset($_POST['answer_text']) ? $_POST['answer_text'] : null;
    $quiz_id = intval($_POST['quiz_id']); // <-- UBAH DARI $category_id
    $user_id = $_SESSION['user_id'];

    // Update pertanyaan di tabel question_quiz
    // Pastikan kolom di database Anda adalah 'quiz_id', bukan 'category_id'
    $stmt = $conn->prepare("UPDATE question_quiz SET question_text = ?, answer = ? WHERE id = ? AND quiz_id = ? AND user_id = ?"); // <-- UBAH DARI 'category_id' ke 'quiz_id'
    $stmt->bind_param("ssiii", $question_text, $answer_text, $question_id, $quiz_id, $user_id); // <-- UBAH DARI $category_id ke $quiz_id

    if ($stmt->execute()) {
        header("Location: quiz_question.php?id=$quiz_id&success=updated"); // <-- UBAH DARI $category_id ke $quiz_id
        exit();
    } else {
        header("Location: quiz_question.php?id=$quiz_id&error=update_failed&msg=" . urlencode($stmt->error)); // <-- UBAH DARI $category_id ke $quiz_id
        exit();
    }
    $stmt->close();
} else {
    // Jika parameter tidak lengkap, kita harus pastikan $quiz_id memiliki nilai
    // untuk redirect agar tidak muncul error "ID kuis tidak ditemukan."
    $redirect_quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0; // Ambil quiz_id jika ada
    header("Location: quiz_question.php?id=$redirect_quiz_id&error=update_failed&msg=" . urlencode("Data update tidak lengkap."));
    exit();
}
$conn->close();
?>