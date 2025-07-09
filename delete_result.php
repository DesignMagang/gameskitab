<?php
session_start();
require_once 'db.php';

// Cek jika tidak login
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ambil data dari POST request
$data = json_decode(file_get_contents('php://input'), true);

// Validasi data
if (empty($data['id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

// Verifikasi kepemilikan hasil (melalui session)
try {
    // Cek apakah hasil milik user yang login
    $stmt = $conn->prepare("
        DELETE gr FROM game_results gr
        JOIN sessions s ON gr.session_id = s.session_id
        WHERE gr.id = ? AND s.created_by = ?
    ");
    $stmt->bind_param("ii", $data['id'], $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan atau bukan milik Anda']);
        }
    } else {
        throw new Exception("Gagal menghapus data");
    }
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>