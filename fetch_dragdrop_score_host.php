<?php
session_start();
include 'db.php'; // Pastikan koneksi database tersedia

header('Content-Type: application/json');

if (!isset($_GET['sessionid'])) {
    echo json_encode(['success' => false, 'message' => 'Session ID is missing.']);
    exit();
}

$sessionId = $_GET['sessionid'];
$results = [];

try {
    // Pastikan Anda menggunakan nama tabel yang benar: dragdrop_results
    $stmt = $conn->prepare("SELECT id, team_name, round_number, time_taken, submission_time FROM dragdrop_results WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    $stmt->close();
    echo json_encode(['success' => true, 'results' => $results]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>