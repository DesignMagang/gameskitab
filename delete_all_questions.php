<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$category_id = intval($_POST['category_id']);

// Verify category ownership
$check = $conn->query("SELECT * FROM categories WHERE id = $category_id AND user_id = {$_SESSION['user_id']}");
if ($check->num_rows == 0) {
    http_response_code(403);
    exit;
}

// Delete all questions
$conn->query("DELETE FROM questions WHERE category_id = $category_id");

// Return success
http_response_code(200);
echo "success";
?>