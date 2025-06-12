<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_id'])) {
    $category_id = intval($_POST['category_id']);

    // Hapus semua pertanyaan berdasarkan kategori
    $stmt = $conn->prepare("DELETE FROM questions WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);

    if ($stmt->execute()) {
        header("Location: category.php?id=" . $category_id . "&deleted=1");
        exit();
    } else {
        echo "Gagal menghapus pertanyaan.";
    }

    $stmt->close();
}
?>
