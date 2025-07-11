<?php
session_start();
include 'db.php'; // Pastikan db.php berisi koneksi ke database Anda

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Pastikan request adalah POST dan ada quiz_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_id'])) {
    $quiz_id = $_POST['quiz_id'];
    $user_id = $_SESSION['user_id']; // Ambil user_id dari sesi untuk keamanan

    // Siapkan query DELETE
    // Penting: Tambahkan kondisi user_id = ? untuk memastikan hanya pemilik kuis yang bisa menghapus
    $stmt = $conn->prepare("DELETE FROM quiz WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quiz_id, $user_id);

    if ($stmt->execute()) {
        // Redirect dengan notifikasi sukses
        header("Location: quiz.php?success=deleted");
        exit();
    } else {
        // Redirect dengan notifikasi error (opsional)
        die("Error: " . $stmt->error);
    }

    $stmt->close();
} else {
    // Jika akses langsung atau data tidak lengkap
    header("Location: quiz.php"); // Arahkan kembali ke dashboard
    exit();
}

$conn->close();
?>