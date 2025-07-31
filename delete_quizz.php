<?php
session_start();
include 'db.php'; // Pastikan file koneksi database Anda sudah di-include

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Periksa apakah 'quiz_id' ada di POST request dan metode request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_id']) && !empty($_POST['quiz_id'])) {
    $quiz_id_to_delete = intval($_POST['quiz_id']);

    // Mulai transaksi untuk memastikan konsistensi data
    $conn->begin_transaction();

    try {
        // Hapus terlebih dahulu pertanyaan-pertanyaan yang terkait dengan kuis ini
        // Ini PENTING untuk menjaga integritas referensial jika ada foreign key constraint
        $stmt_delete_questions = $conn->prepare("DELETE FROM question_quizz WHERE quiz_id = ? AND user_id = ?");
        $stmt_delete_questions->bind_param("ii", $quiz_id_to_delete, $user_id);
        $stmt_delete_questions->execute();
        $stmt_delete_questions->close();

        // Sekarang hapus kuis itu sendiri
        $stmt_delete_quiz = $conn->prepare("DELETE FROM quizz WHERE id = ? AND user_id = ?");
        $stmt_delete_quiz->bind_param("ii", $quiz_id_to_delete, $user_id);
        $stmt_delete_quiz->execute();

        if ($stmt_delete_quiz->affected_rows > 0) {
            $conn->commit(); // Commit transaksi jika berhasil
            $_SESSION['message'] = "Kuis berhasil dihapus!";
            $_SESSION['message_type'] = "success";
        } else {
            $conn->rollback(); // Rollback transaksi jika gagal
            $_SESSION['message'] = "Kuis tidak ditemukan atau bukan milik Anda.";
            $_SESSION['message_type'] = "error";
        }
        $stmt_delete_quiz->close();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback(); // Rollback jika ada error SQL
        $_SESSION['message'] = "Gagal menghapus kuis: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }

    // Tutup koneksi database
    $conn->close();

    // Redirect kembali ke halaman kuis setelah penghapusan
    header("Location: quizz.php");
    exit;
} else {
    // Jika quiz_id tidak ada atau bukan POST request, redirect dengan pesan error
    $_SESSION['message'] = "ID kuis untuk dihapus tidak valid atau metode request tidak sesuai.";
    $_SESSION['message_type'] = "error";
    header("Location: quizz.php");
    exit;
}
?>