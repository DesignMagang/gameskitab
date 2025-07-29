<?php
session_start();
include 'db.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Terima data
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    header("HTTP/1.1 400 Bad Request");
    exit(json_encode(['success' => false, 'message' => 'ID tidak valid']));
}

try {
    // Hapus dari database
    $stmt = $conn->prepare("DELETE FROM dragdrop_results WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    }
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>