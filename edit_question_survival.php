<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// Keamanan: Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Data input tidak valid.']);
    exit;
}

$question_id = filter_var($data['question_id'] ?? 0, FILTER_VALIDATE_INT);
$question_text = trim($data['question_text'] ?? '');
$option_a = isset($data['option_a']) ? trim($data['option_a']) : null;
$option_b = isset($data['option_b']) ? trim($data['option_b']) : null;
$option_c = isset($data['option_c']) ? trim($data['option_c']) : null;
$option_d = isset($data['option_d']) ? trim($data['option_d']) : null;
$correct_answer = trim($data['correct_answer'] ?? '');

if (empty($question_id) || empty($question_text) || empty($correct_answer)) {
    echo json_encode(['success' => false, 'message' => 'Data wajib tidak boleh kosong.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE survival_questions SET 
        question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ? 
        WHERE id = ? AND session_id = ?");
    
    $stmt->bind_param("ssssssii", 
        $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, 
        $question_id, $_SESSION['current_survival_session']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}