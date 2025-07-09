<?php
require 'db.php';

header('Content-Type: application/json');

$team = isset($_POST['team']) ? intval($_POST['team']) : 0;

if ($team < 1 || $team > 2) {
    echo json_encode(['success' => false, 'message' => 'Tim tidak valid']);
    exit;
}

// Update status buzzer
$conn->query("UPDATE buzzer_status SET active = FALSE, winner = $team WHERE id = 1");

// Simpan log
$conn->query("INSERT INTO buzzer_logs (team) VALUES ($team)");

echo json_encode(['success' => true]);
?>