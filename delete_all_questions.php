<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("DELETE FROM questions");
    $stmt->execute();
    $stmt->close();
    echo "success";
}
?>
