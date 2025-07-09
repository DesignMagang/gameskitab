<?php
session_start();
require_once 'db.php';

$sessionId = $_GET['session'] ?? 0;
$roundNumber = $_GET['round'] ?? 1;

$questions = $conn->query("
    SELECT id, question_text, question_type, 
           option_a, option_b, option_c, option_d, 
           correct_answer, points, time_limit
    FROM survival_questions
    WHERE session_id = $sessionId AND round_number = $roundNumber
    ORDER BY question_order ASC
")->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($questions);
?>