<?php
require 'db.php';

// Fungsi untuk mendapatkan playlist
function getPlaylist($conn) {
    $result = $conn->query("SELECT * FROM background_music WHERE is_active = TRUE ORDER BY display_name");
    $playlist = [];
    while ($row = $result->fetch_assoc()) {
        $playlist[] = $row;
    }
    return $playlist;
}

// Fungsi untuk mendapatkan pengaturan musik user
function getUserMusicSettings($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM music_settings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Buat pengaturan default jika belum ada
        $insert = $conn->prepare("INSERT INTO music_settings (user_id) VALUES (?)");
        $insert->bind_param("i", $user_id);
        $insert->execute();
        return [
            'is_music_on' => true,
            'volume' => 50,
            'current_track' => 0
        ];
    }
    
    return $result->fetch_assoc();
}

// Fungsi untuk update pengaturan musik
function updateMusicSettings($conn, $user_id, $settings) {
    $stmt = $conn->prepare("UPDATE music_settings SET 
        is_music_on = ?,
        volume = ?,
        current_track = ?
        WHERE user_id = ?");
    
    $stmt->bind_param("iiii", 
        $settings['is_music_on'],
        $settings['volume'],
        $settings['current_track'],
        $user_id
    );
    
    return $stmt->execute();
}

// API untuk mendapatkan playlist (JSON)
if (isset($_GET['action']) && $_GET['action'] == 'get_playlist') {
    header('Content-Type: application/json');
    echo json_encode(getPlaylist($conn));
    exit;
}

// API untuk update pengaturan musik
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $settings = json_decode(file_get_contents('php://input'), true);
    
    if (updateMusicSettings($conn, $user_id, $settings)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>