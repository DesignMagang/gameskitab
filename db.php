<?php
$host = '127.0.0.1:3307';
$user = 'root';
$pass = '';
$dbname = 'game_db';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Buat tabel jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS buzzer_status (
    id INT PRIMARY KEY DEFAULT 1,
    active BOOLEAN DEFAULT TRUE,
    winner INT NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("INSERT IGNORE INTO buzzer_status (id, active) VALUES (1, TRUE)");

$conn->query("CREATE TABLE IF NOT EXISTS buzzer_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team INT NOT NULL,
    buzzer_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>