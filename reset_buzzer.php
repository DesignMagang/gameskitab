<?php
require 'db.php';

header('Content-Type: application/json');

$conn->query("UPDATE buzzer_status SET active = TRUE, winner = NULL WHERE id = 1");

echo json_encode(['success' => true]);
?>