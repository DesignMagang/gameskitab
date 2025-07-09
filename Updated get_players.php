<?php
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_GET['code'])) {
    echo json_encode([]);
    exit;
}

$code = $_GET['code'];
$stmt = $conn->prepare("SELECT player_id, player_name, avatar, joined_at FROM players WHERE session_code = ? ORDER BY joined_at");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = [
        'player_id' => $row['player_id'],
        'player_name' => $row['player_name'],
        'avatar' => $row['avatar'],
        'joined_at' => $row['joined_at']
    ];
}

echo json_encode($players);
?>
