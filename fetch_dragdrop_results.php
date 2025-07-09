<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$sessionId = $_GET['sessionid'] ?? null;

if (!$sessionId) {
    echo json_encode(['success' => false, 'message' => 'Session ID is missing.']);
    exit;
}

try {
    // Ambil semua hasil untuk sesi ini, KECUALI 'Final_Score'
    // Urutkan berdasarkan time_taken (terkecil duluan), lalu submission_time (terlama duluan)
    $stmt = $conn->prepare("
        SELECT team_name, round_number, time_taken, submission_time
        FROM dragdrop_results
        WHERE session_id = ? AND round_number != 'Final_Score' -- HANYA AMBIL HASIL RONDE BIASA
        ORDER BY time_taken ASC, submission_time ASC
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    echo json_encode(['success' => true, 'results' => $results]);

    $stmt->close();
} catch (Exception $e) {
    error_log("Error in fetch_dragdrop_results.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please check server logs.']);
}

$conn->close();
// Pastikan tidak ada karakter lain setelah ini jika tidak ada tag penutup ?>