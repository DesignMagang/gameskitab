<?php
session_start();
require_once 'db.php'; // Pastikan path ke db.php Anda benar

header('Content-Type: application/json');

// Ambil session_id dan selected_round dari parameter GET
$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;
$selectedRound = isset($_GET['round']) ? (int)$_GET['round'] : 0;

if ($sessionId === 0 || $selectedRound === 0) {
    echo json_encode([]); // Kembalikan array kosong jika parameter tidak ada
    exit;
}

$playerResults = [];

// 1. Dapatkan semua pemain unik yang berpartisipasi di sesi dan ronde ini
// Menggunakan LEFT JOIN ke users untuk memastikan player_name ada, meskipun survival_answers juga menyimpannya
$distinctPlayersQuery = $conn->prepare("
    SELECT DISTINCT sa.user_id, COALESCE(u.username, sa.player_name) AS player_name
    FROM survival_answers sa
    LEFT JOIN users u ON sa.user_id = u.id
    WHERE sa.session_id = ? AND sa.round_number = ?
");
$distinctPlayersQuery->bind_param("ii", $sessionId, $selectedRound);
$distinctPlayersQuery->execute();
$distinctPlayersResult = $distinctPlayersQuery->get_result();

$playersData = [];
while ($player = $distinctPlayersResult->fetch_assoc()) {
    $playersData[] = $player;
}

// 2. Untuk setiap pemain, hitung total skor dan tentukan statusnya
foreach ($playersData as $player) {
    $userId = $player['user_id'];
    $playerName = htmlspecialchars($player['player_name']);
    $totalScore = 0;
    $status = 'Selesai'; // Status default

    // Dapatkan total skor untuk ronde ini
    $scoreQuery = $conn->prepare("
        SELECT SUM(points_earned) as total_score
        FROM survival_answers
        WHERE session_id = ? AND round_number = ? AND user_id = ?
    ");
    $scoreQuery->bind_param("iii", $sessionId, $selectedRound, $userId);
    $scoreQuery->execute();
    $scoreResult = $scoreQuery->get_result()->fetch_assoc();
    if ($scoreResult && $scoreResult['total_score'] !== null) {
        $totalScore = (int)$scoreResult['total_score'];
    }

    // Tentukan status ('Gugur' atau 'Selesai')
    // Seorang pemain 'Gugur' jika jawaban terakhirnya di ronde ini salah (is_correct = 0).
    $lastAnswerQuery = $conn->prepare("
        SELECT is_correct
        FROM survival_answers
        WHERE session_id = ? AND round_number = ? AND user_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $lastAnswerQuery->bind_param("iii", $sessionId, $selectedRound, $userId);
    $lastAnswerQuery->execute();
    $lastAnswerResult = $lastAnswerQuery->get_result()->fetch_assoc();

    if ($lastAnswerResult && $lastAnswerResult['is_correct'] == 0) {
        $status = 'Gugur';
    }

    $playerResults[] = [
        'player_name' => $playerName,
        'score' => $totalScore,
        'status' => $status
    ];
}

// 3. Urutkan pemain berdasarkan skor tertinggi (descending)
usort($playerResults, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

echo json_encode($playerResults);

$conn->close();
?>