<?php
require_once 'db.php';

$game_id = $_GET['game_id'] ?? 0;

$stmt = $conn->prepare("SELECT name FROM game_players WHERE game_id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['players' => $players]);
?>