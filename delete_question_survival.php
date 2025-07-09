<?php
session_start();
require_once 'db.php'; // Make sure this path is correct

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get the POST data
$data = json_decode(file_get_contents("php://input"), true);
$questionId = $data['id'] ?? null;

if (!$questionId) {
    echo json_encode(['success' => false, 'message' => 'Question ID is missing.']);
    exit;
}

try {
    // Start a transaction to ensure atomicity
    $conn->begin_transaction();

    // 1. Delete associated answers first
    $stmtDeleteAnswers = $conn->prepare("DELETE FROM survival_answers WHERE question_id = ?");
    if (!$stmtDeleteAnswers) {
        throw new Exception("Failed to prepare statement for deleting answers: " . $conn->error);
    }
    $stmtDeleteAnswers->bind_param("i", $questionId);
    if (!$stmtDeleteAnswers->execute()) {
        throw new Exception("Failed to delete associated answers: " . $stmtDeleteAnswers->error);
    }
    $stmtDeleteAnswers->close();

    // 2. Then delete the question
    $stmtDeleteQuestion = $conn->prepare("DELETE FROM survival_questions WHERE id = ?");
    if (!$stmtDeleteQuestion) {
        throw new Exception("Failed to prepare statement for deleting question: " . $conn->error);
    }
    $stmtDeleteQuestion->bind_param("i", $questionId);
    if (!$stmtDeleteQuestion->execute()) {
        throw new Exception("Failed to delete question: " . $stmtDeleteQuestion->error);
    }
    $stmtDeleteQuestion->close();

    // Commit the transaction if both deletions were successful
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Question and associated answers deleted successfully.']);

} catch (Exception $e) {
    // Rollback the transaction in case of any error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>