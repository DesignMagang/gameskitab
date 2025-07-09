<?php
header('Content-Type: application/json');
session_start();
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$session_id = $data['session_id'] ?? '';
$entered_code = $data['entered_code'] ?? '';

$stmt = $conn->prepare("SELECT optional_code FROM dragdrop_sessions WHERE sessionid = ?");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$stmt->bind_result($optional_code);
$stmt->fetch();
$stmt->close();

if ($optional_code === null || $optional_code === '') {
    echo json_encode(['success' => true]); // Tidak butuh kode
    exit();
}

if (strcasecmp($entered_code, $optional_code) === 0) {
    echo json_encode(['success' => true]); // Kode cocok
} else {
    echo json_encode(['success' => false, 'message' => 'Kode tidak sesuai.']);
}
?>