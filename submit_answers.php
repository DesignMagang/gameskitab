<?php
require_once 'db.php';

$sessionCode = $_POST['session_code'] ?? '';
$round = intval($_POST['round']);
$questionIds = $_POST['question_id'] ?? [];
$answers = $_POST['answers'] ?? [];

// Simpan jawaban (opsional - kamu bisa buat tabel `player_answers`)

for ($i = 0; $i < count($questionIds); $i++) {
    // simpan $questionIds[$i] dan $answers[$i] ke database jika perlu
    // contoh:
    // INSERT INTO player_answers (session_code, question_id, answer, round) ...
}

// Redirect ke ronde berikutnya
$nextRound = $round + 1;
header("Location: game_play.php?code=$sessionCode&round=$nextRound");
exit;
