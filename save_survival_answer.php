<?php
error_reporting(E_ALL); // Aktifkan pelaporan semua error PHP
ini_set('display_errors', 1); // Tampilkan error langsung di browser (untuk debugging)

session_start();
require_once 'db.php'; // Pastikan jalur ke db.php sudah benar

header('Content-Type: application/json');

// Pastikan request method adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Dapatkan data JSON dari body request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validasi data yang diterima
if (!isset($data['session_id'], $data['question_id'], $data['is_correct'], $data['points_earned'], $data['player_name'], $data['round_number'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

$sessionId = $data['session_id'];
$questionId = $data['question_id'];
$isCorrect = $data['is_correct'];
$pointsEarned = $data['points_earned'];
$playerName = $data['player_name']; // Ambil nama pemain
$roundNumber = $data['round_number']; // Ambil nomor ronde

$userId = $_SESSION['user_id'] ?? null; // Ambil user_id dari sesi, jika ada. Bisa null jika pemain tidak login.

try {
    // Siapkan statement SQL untuk memasukkan data jawaban
    $stmt = $conn->prepare("INSERT INTO survival_answers (user_id, question_id, player_name, session_id, is_correct, points_earned, round_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    // Bind parameter. Tipe data: i=integer, s=string.
    // Sesuaikan tipe data 'user_id' jika memang bisa NULL di database Anda.
    // Jika user_id selalu INT dan tidak NULL, pastikan $_SESSION['user_id'] selalu ada.
    $stmt->bind_param("iisiiis", $userId, $questionId, $playerName, $sessionId, $isCorrect, $pointsEarned, $roundNumber);

    // Eksekusi statement
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Answer saved successfully.']);
    } else {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error saving answer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>