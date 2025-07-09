<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE music_settings SET 
    is_music_on = ?,
    volume = ?,
    current_track = ?
    WHERE user_id = ?");

$stmt->bind_param("iiii", 
    $data['is_music_on'],
    $data['volume'],
    $data['current_track'],
    $user_id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>