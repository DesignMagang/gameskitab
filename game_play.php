<?php
session_start();
require_once 'db.php';

// Ambil kode dan ronde
$sessionCode = $_GET['code'] ?? '';
$currentRound = intval($_GET['round'] ?? 1);

// Validasi kode sesi
$stmt = $conn->prepare("SELECT session_id, session_name FROM game_sessions 
                        JOIN sessions ON game_sessions.session_id = sessions.session_id 
                        WHERE session_code = ?");
$stmt->bind_param("s", $sessionCode);
$stmt->execute();
$result = $stmt->get_result();
$sessionData = $result->fetch_assoc();

if (!$sessionData) {
    die("Sesi game tidak ditemukan.");
}

$sessionId = $sessionData['session_id'];
$sessionName = $sessionData['session_name'];

// Ambil pertanyaan berdasarkan ronde
$questionStmt = $conn->prepare("SELECT * FROM session_questions 
                                WHERE session_id = ? AND round_number = ?
                                ORDER BY question_id ASC");
$questionStmt->bind_param("ii", $sessionId, $currentRound);
$questionStmt->execute();
$questions = $questionStmt->get_result();

$questionList = [];
while ($q = $questions->fetch_assoc()) {
    $questionList[] = $q;
}

// Cek apakah ini ronde terakhir
$maxRoundResult = $conn->query("SELECT MAX(round_number) as max_round FROM session_questions WHERE session_id = $sessionId");
$maxRound = $maxRoundResult->fetch_assoc()['max_round'];

$isLastRound = $currentRound >= $maxRound;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Game - Ronde <?= $currentRound ?></title>
        <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(45deg, #6a11cb, #2575fc);
            color: white;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        .glass {
            background: rgba(255,255,255,0.08);
            padding: 2rem;
            border-radius: 15px;
            margin-top: 3rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .question-box {
            background: rgba(0,0,0,0.2);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="glass">
        <h2 class="text-center mb-4"><?= htmlspecialchars($sessionName) ?> - Ronde <?= $currentRound ?></h2>

        <?php if (count($questionList) === 0): ?>
            <div class="alert alert-warning text-center">
                Tidak ada pertanyaan untuk ronde ini.
            </div>
        <?php else: ?>
            <form method="post" action="submit_answers.php">
                <?php foreach ($questionList as $index => $q): ?>
                    <div class="question-box">
                        <p><strong>Pertanyaan <?= $index + 1 ?>:</strong> <?= htmlspecialchars($q['question_text']) ?></p>
                        <input type="hidden" name="question_id[]" value="<?= $q['question_id'] ?>">
                        <input type="text" name="answers[]" class="form-control" placeholder="Jawaban kamu..." required>
                    </div>
                <?php endforeach; ?>
                
                <input type="hidden" name="session_code" value="<?= htmlspecialchars($sessionCode) ?>">
                <input type="hidden" name="round" value="<?= $currentRound ?>">

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">Kirim Jawaban</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
