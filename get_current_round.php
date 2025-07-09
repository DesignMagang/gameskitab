<?php
require_once 'db.php';

header('Content-Type: application/json');

$sessionCode = $_GET['code'] ?? '';
$stmt = $conn->prepare("SELECT current_round FROM game_sessions WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();
echo json_encode([
    'success' => true,
    'round' => $result['current_round'] ?? 0
]);