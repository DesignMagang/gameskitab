<?php
session_start();
require_once 'db.php';

// Redirect jika tidak login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Cek parameter session
if (!isset($_GET['session'])) {
    header("Location: create_matching.php");
    exit;
}

$sessionId = $_GET['session'];
$userId = $_SESSION['user_id'];

// Verifikasi kepemilikan sesi
$stmt = $conn->prepare("SELECT session_name FROM sessions WHERE session_id = ? AND created_by = ?");
$stmt->bind_param("si", $sessionId, $userId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    header("Location: create_matching.php?error=invalid_session");
    exit;
}

// Ambil pertanyaan dari database
$questions = $conn->query("SELECT * FROM session_questions WHERE session_id = '$sessionId' ORDER BY round_number, question_id");
$questionsData = [];
$rounds = [];
while ($row = $questions->fetch_assoc()) {
    $questionsData[] = $row;
    if (!in_array($row['round_number'], $rounds)) {
        $rounds[] = $row['round_number'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Game - <?= htmlspecialchars($session['session_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
        <link rel="icon" href="logo.png" type="image/png">
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            min-height: 100vh;
            color: white;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .game-card {
    perspective: 1000px;
    height: 150px;
    cursor: pointer;
}

.game-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
    transition: transform 0.6s;
}

.game-card.flipped .game-card-inner {
    transform: rotateY(180deg);
}

.card-face {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 15px;
    text-align: center;
    border-radius: 10px;
    font-weight: bold;
    font-size: 1rem;
}

.card-front {
    background-color: rgba(52, 73, 94, 0.8);
}

.card-back {
    background: linear-gradient(135deg, rgba(106,17,203,0.85), rgba(37,117,252,0.85));
    box-shadow: 0 4px 20px rgba(106,17,203,0.15), 0 1.5px 6px rgba(37,117,252,0.10);
    border: 1.5px solid rgba(255,255,255,0.18);
    backdrop-filter: blur(8px);
    transform: rotateY(180deg);
    color: #fff;
}

.game-card.matched {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s;
}

        
        .verse-text {
            font-style: italic;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        
        .round-selector {
            max-width: 200px;
            margin: 0 auto 20px;
        }
        
        @media (max-width: 768px) {
            .game-card {
                height: 120px;
            }
        }
        
        @media (max-width: 576px) {
            .game-card {
                height: 100px;
                font-size: 0.9rem;
            }
            
            .verse-text {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="add_questions.php?session=<?= $sessionId ?>" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <h2 class="text-center mb-0"><?= htmlspecialchars($session['session_name']) ?></h2>
                        <button id="play-btn" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i> Mainkan
                        </button>
                    </div>
                    
                    <!-- Gabungan Pertanyaan dan Jawaban -->
                    <div class="glass-card p-3 mb-4">
                        <h4 class="text-center mb-3"><i class="fas fa-list me-2"></i>Daftar Pertanyaan & Jawaban</h4>
                        <div class="list-group">
                            <?php foreach ($questionsData as $q): ?>
                                <div class="list-group-item bg-dark bg-opacity-25 mb-2">
                                    <div class="fw-bold"><?= htmlspecialchars($q['question_text']) ?></div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="text-success"><?= htmlspecialchars($q['answer_text']) ?></span>
                                        <small class="text-muted"><?= $q['bible_reference'] ?: 'Tanpa referensi' ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Simulasi Game -->
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <select id="round-select" class="form-select round-selector bg-dark text-white">
                            <?php foreach ($rounds as $round): ?>
                                <option value="<?= $round ?>">Ronde <?= $round ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button id="reload-btn" class="btn btn-info ms-3">
                            <i class="fas fa-sync-alt me-1"></i> Acak Ulang
                        </button>
                    </div>
                    
                    <div class="text-center mb-3">
                        <span class="badge bg-primary me-2">Poin: <span id="score">0</span></span>
                        <span class="badge bg-success">Percobaan: <span id="attempts">0</span></span>
                    </div>
                    
                    <div class="row row-cols-2 row-cols-md-4 g-3" id="game-board">
                        <!-- Kartu game akan diisi oleh JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // Data dari PHP (pertanyaan yang sudah diinput user)
        const gameQuestions = <?= json_encode($questionsData) ?>;
        const allRounds = <?= json_encode($rounds) ?>;
        
        let currentRound = 1;
        let score = 0;
        let attempts = 0;
        let canFlip = true;
        let flippedCards = [];
        let matchedPairs = 0;
        let currentCards = [];
        
        // Inisialisasi game
        function initGame(round = 1) {
            document.getElementById('game-board').innerHTML = '';
            score = 0;
            attempts = 0;
            matchedPairs = 0;
            document.getElementById('score').textContent = score;
            document.getElementById('attempts').textContent = attempts;
            
            // Filter pertanyaan untuk ronde saat ini
            const roundQuestions = gameQuestions.filter(q => q.round_number == round);
            
            if (roundQuestions.length === 0) {
                document.getElementById('game-board').innerHTML = `
                    <div class="col-12 text-center py-4">
                        Tidak ada pertanyaan untuk Ronde ${round}
                    </div>
                `;
                return;
            }
            
            // Buat deck kartu
            currentCards = [];
            
            roundQuestions.forEach((q, index) => {
                // Tambahkan kartu pertanyaan
                currentCards.push({
                    id: index * 2,
                    type: 'question',
                    text: q.question_text,
                    answer: q.answer_text,
                    verse: q.bible_reference,
                    matched: false
                });
                
                // Tambahkan kartu jawaban
                currentCards.push({
                    id: index * 2 + 1,
                    type: 'answer',
                    text: q.answer_text,
                    question: q.question_text,
                    verse: q.bible_reference,
                    matched: false
                });
            });
            
            // Acak kartu
            shuffleCards();
        }
        
        // Fungsi untuk mengacak kartu
        function shuffleCards() {
            const shuffled = [...currentCards].sort(() => Math.random() - 0.5);
renderCards(shuffled.map((card, i) => ({ ...card, realIndex: i })));
        }
        
        // Fungsi untuk render kartu
        function renderCards(cards) {
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';

    cards.forEach((card, index) => {
        if (card.matched) return;

        const cardElement = document.createElement('div');
        cardElement.className = 'col';
        cardElement.innerHTML = `
            <div class="game-card" data-index="${index}">
                <div class="game-card-inner">
                    <div class="card-face card-front">
                        ${card.type === 'question' ? '❓ Pertanyaan' : '💡 Jawaban'}
                    </div>
                    <div class="card-face card-back">
                        ${card.text}
                    </div>
                </div>
            </div>
        `;

        // Event klik untuk flip
        cardElement.querySelector('.game-card').addEventListener('click', function () {
            flipCard(this, cards[index].id);
        });

        gameBoard.appendChild(cardElement);
    });
}

        // Fungsi untuk membalik kartu
function flipCard(cardElement, cardId) {
    const index = currentCards.findIndex(c => c.id === cardId);
                if (!canFlip || cardElement.classList.contains('flipped') || currentCards[index].matched) return;
            
            cardElement.classList.add('flipped');
            flippedCards.push({ element: cardElement, index });
            
            if (flippedCards.length === 2) {
                attempts++;
                document.getElementById('attempts').textContent = attempts;
                canFlip = false;
                
                setTimeout(checkMatch, 1000);
            }
        }
        
        // Fungsi untuk memeriksa kecocokan
        function checkMatch() {
            const [firstCard, secondCard] = flippedCards;
            const firstData = currentCards[firstCard.index];
            const secondData = currentCards[secondCard.index];
            
            // Cek apakah pertanyaan dan jawaban cocok
            const isMatch = (firstData.type === 'question' && firstData.answer === secondData.text) || 
                          (firstData.type === 'answer' && firstData.question === secondData.text);
            
            if (isMatch) {
                // Jika cocok
                score += 50;
                document.getElementById('score').textContent = score;
                matchedPairs++;
                
                // Tandai sebagai sudah cocok
                currentCards[firstCard.index].matched = true;
                currentCards[secondCard.index].matched = true;
                
                // Hilangkan kartu yang sudah cocok
                firstCard.element.classList.add('matched');
                secondCard.element.classList.add('matched');
                
                // Cek apakah semua pasangan sudah cocok
                const totalPairs = gameQuestions.filter(q => q.round_number == currentRound).length;
                if (matchedPairs === totalPairs) {
                    setTimeout(() => {
                        alert(`Selamat! Anda menyelesaikan Ronde ${currentRound} dengan ${attempts} percobaan dan ${score} poin!`);
                    }, 500);
                }
            } else {
                // Jika tidak cocok, balik kembali
                firstCard.element.classList.remove('flipped');
                secondCard.element.classList.remove('flipped');
                score = Math.max(0, score - 5);
                document.getElementById('score').textContent = score;
            }
            
            flippedCards = [];
            canFlip = true;
        }
        
        // Tombol Mainkan
        document.getElementById('play-btn').addEventListener('click', function() {
            window.location.href = `play_game.php?session=<?= $sessionId ?>`;
        });
        
        // Dropdown pilih ronde
        document.getElementById('round-select').addEventListener('change', function() {
            currentRound = parseInt(this.value);
            initGame(currentRound);
        });
        
        // Tombol acak ulang
        document.getElementById('reload-btn').addEventListener('click', function() {
            shuffleCards();
            // Reset kartu yang terbuka
            flippedCards.forEach(card => {
                card.element.classList.remove('flipped');
            });
            flippedCards = [];
            canFlip = true;
        });
        
        // Mulai game saat halaman dimuat
        document.addEventListener('DOMContentLoaded', () => {
            initGame(currentRound);
        });
    </script>
</body>
</html>
