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
if (empty($data['session_id']) || empty($data['round_number']) || empty($data['team_name']) || 
    empty($data['completion_time']) || !isset($data['score']) || !isset($data['attempts'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Simpan ke database
try {
    $stmt = $conn->prepare("INSERT INTO game_results 
                          (session_id, round_number, team_name, completion_time, score, attempts) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissii", 
        $data['session_id'],
        $data['round_number'],
        $data['team_name'],
        $data['completion_time'],
        $data['score'],
        $data['attempts']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to save result");
    }
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>