<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $file = $_FILES['audio'];

    $targetDir = "uploads/";
    $fileName = time() . "_" . basename($file["name"]);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        $stmt = $conn->prepare("INSERT INTO songs (title, filename) VALUES (?, ?)");
        $stmt->bind_param("ss", $title, $fileName);
        $stmt->execute();
        header("Location: dashboard.php");
    } else {
        echo "Gagal upload lagu.";
    }
}
?>
