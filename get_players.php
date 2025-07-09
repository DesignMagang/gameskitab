<?php
// Pastikan tidak ada output sebelum ini
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Error reporting untuk development (matikan di production)
error_reporting(0);
ini_set('display_errors', 0);

require __DIR__ . '/db.php';

try {
    // Validasi input
    if (!isset($_GET['code']) || strlen($_GET['code']) !== 6) {
        throw new Exception('Invalid game code format');
    }

    $code = $_GET['code'];

    // Gunakan prepared statement
    $stmt = $conn->prepare("SELECT 
                          player_id, 
                          player_name, 
                          DATE_FORMAT(joined_at, '%H:%i') as join_time
                          FROM players 
                          WHERE session_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $players = $result->fetch_all(MYSQLI_ASSOC);

    // Pastikan tidak ada output lain
    ob_clean();
    echo json_encode($players ?: ['status' => 'empty']);
    exit;

} catch (Exception $e) {
    // Log error secara internal
    file_put_contents(__DIR__ . '/logs/php_errors.log', date('[Y-m-d H:i:s]') . $e->getMessage() . "\n", FILE_APPEND);
    
    // Response error clean
    ob_clean();
    echo json_encode([
        'error' => true,
        'message' => 'Internal server error'
    ]);
    exit;
}