<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['game_id'])) {
    header('Location: matching.php');
    exit;
}

$game_id = $_SESSION['game_id'];
$is_host = $_SESSION['is_host'] ?? false;

// Get game info
$stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$game = $stmt->get_result()->fetch_assoc();

// Get current round info
$stmt = $conn->prepare("SELECT * FROM game_rounds WHERE game_id = ? AND round_number = ?");
$stmt->bind_param("ii", $game_id, $game['current_round']);
$stmt->execute();
$round = $stmt->get_result()->fetch_assoc();

// Get current question
$stmt = $conn->prepare("SELECT q.* FROM game_questions gq 
                        JOIN bible_questions q ON gq.question_id = q.id 
                        WHERE gq.game_id = ? AND gq.round_id = ? AND gq.status = 'asked'");
$stmt->bind_param("ii", $game_id, $round['id']);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Session | Matching Alkitab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Same styles as matching.php */
        
        .answer-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .answer-card:hover {
            transform: scale(1.03);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }
        
        .timer-circle {
            width: 80px;
            height: 80px;
            border: 5px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .score-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="timer-circle me-3">
                        <span id="timer">30</span>
                    </div>
                    <div>
                        <h5 class="mb-0">Ronde <?= $game['current_round'] ?></h5>
                        <small>Pertanyaan <span id="question-number">1</span>/5</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="score-badge d-inline-block">
                    <i class="fas fa-star me-2 text-warning"></i>
                    <span id="player-score">0</span> Poin
                </div>
            </div>
        </div>
        
        <div class="row justify-content-center mb-4">
            <div class="col-md-10">
                <div class="card-glass p-4 text-center animate__animated animate__fadeIn">
                    <h3 id="question-text"><?= htmlspecialchars($question['question']) ?></h3>
                    <small class="text-muted">Referensi: <?= htmlspecialchars($question['reference']) ?></small>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <?php 
            $answers = [
                $question['correct_answer'],
                $question['wrong_answer1'],
                $question['wrong_answer2'],
                $question['wrong_answer3']
            ];
            shuffle($answers);
            
            foreach ($answers as $index => $answer): 
            ?>
            <div class="col-md-6">
                <div class="card-glass p-4 answer-card animate__animated animate__fadeInUp" 
                     style="animation-delay: <?= $index * 0.1 ?>s"
                     data-answer="<?= htmlspecialchars($answer) ?>">
                    <h5 class="mb-0"><?= htmlspecialchars($answer) ?></h5>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($is_host): ?>
        <div class="row mt-4">
            <div class="col-12 text-center">
                <button id="next-question-btn" class="btn btn-primary btn-lg" disabled>
                    <i class="fas fa-arrow-right me-2"></i>Pertanyaan Berikutnya
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Answer Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-glass">
                <div class="modal-body text-center p-5">
                    <div id="correct-feedback" style="display:none;">
                        <h1 class="text-success mb-4"><i class="fas fa-check-circle"></i></h1>
                        <h3>Jawaban Benar!</h3>
                        <p class="lead">+10 Poin</p>
                    </div>
                    <div id="wrong-feedback" style="display:none;">
                        <h1 class="text-danger mb-4"><i class="fas fa-times-circle"></i></h1>
                        <h3>Jawaban Salah</h3>
                        <p>Jawaban benar: <span id="correct-answer-text"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Game logic will be implemented here
        // Including timer, answer selection, scoring, etc.
    </script>
</body>
</html>