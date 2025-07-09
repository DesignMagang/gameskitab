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

// Get music settings
$music_settings = $conn->query("SELECT * FROM music_settings WHERE user_id = $userId")->fetch_assoc();
if (!$music_settings) {
    // Create default settings if not exists
    $conn->query("INSERT INTO music_settings (user_id) VALUES ($userId)");
    $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0];
}

// Get active playlist
$playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);

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
    <title>Demo Game - <?= htmlspecialchars($session['session_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .round-selector {
            max-width: 200px;
        }
        
        #start-btn {
            width: 100px;
            height: 38px;
            transition: all 0.3s;
        }
        
        #start-btn:hover {
            transform: scale(1.05);
        }
        
        #countdown {
            font-size: 5rem;
            font-weight: bold;
            text-shadow: 0 0 20px rgba(255,255,255,0.5);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .team-input {
            max-width: 300px;
            margin: 0 auto;
        }
        
        .winner-badge {
            background: linear-gradient(135deg, #f6e05e, #f59e0b);
            color: #000;
            font-weight: bold;
        }

        /* Music Player Styles */
        .music-player {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 10px 15px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .music-player:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .music-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255,255,255,0.1);
        }
        
        .music-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        
        .music-info {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .now-playing {
            font-weight: bold;
            font-size: 14px;
            color: white;
        }
        
        .track-info {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }
        
        @media (max-width: 768px) {
            .game-card {
                height: 120px;
            }
            
            .game-controls {
                flex-direction: column;
                gap: 10px;
            }
            
            .round-selector, .team-input {
                max-width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .game-card {
                height: 100px;
                font-size: 0.9rem;
            }
            
            #countdown {
                font-size: 3rem;
            }

            .music-player {
                bottom: 10px;
                right: 10px;
                padding: 8px 12px;
            }
            
            .music-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Music Player -->
    <div class="music-player" id="musicPlayer">
        <div class="music-btn" id="playPauseBtn">
            <i class="fas <?= $music_settings['is_music_on'] ? 'fa-pause' : 'fa-play' ?> text-white"></i>
        </div>
        <div class="music-info">
            <div class="now-playing" id="nowPlaying">
                <?= count($playlist) > 0 ? htmlspecialchars($playlist[$music_settings['current_track']]['display_name']) : 'No tracks' ?>
            </div>
            <div class="track-info" id="trackInfo">
                Track <?= count($playlist) > 0 ? ($music_settings['current_track'] + 1) : '0' ?> of <?= count($playlist) ?>
            </div>
        </div>
    </div>

    <audio id="backgroundMusic"></audio>
    <audio id="countdownSound" src="sounds/countdown.mp3" preload="auto"></audio>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="add_questions.php?session=<?= $sessionId ?>" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <h2 class="text-center mb-0"><?= htmlspecialchars($session['session_name']) ?></h2>
                        <a href="game_history.php?session=<?= $sessionId ?>" class="btn btn-outline-light">
                            <i class="fas fa-history me-1"></i> Riwayat
                        </a>
                    </div>
                </div>
                
                <!-- Team Input Form -->
                <div class="glass-card p-4 mb-4" id="team-form">
                    <h4 class="text-center mb-3"><i class="fas fa-users me-2"></i>Informasi Kelompok</h4>
                    <div class="team-input">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelompok</label>
                            <input type="text" id="team-name" class="form-control form-control-glass" placeholder="Masukkan nama kelompok">
                        </div>
                    </div>
                </div>
                
                <!-- Game Controls -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center game-controls">
                        <select id="round-select" class="form-select round-selector bg-dark text-white">
                            <?php foreach ($rounds as $round): ?>
                                <option value="<?= $round ?>">Ronde <?= $round ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="start-screen">
                            <button id="start-btn" class="btn btn-primary">
                                <i class="fas fa-play me-1"></i> Mulai
                            </button>
                            <div id="countdown" class="my-3 text-center" style="display: none;">3</div>
                        </div>
                        
                        <button id="reload-btn" class="btn btn-info">
                            <i class="fas fa-sync-alt me-1"></i> Acak Ulang
                        </button>
                    </div>
                </div>
                
                <!-- Game Info -->
                <div class="text-center mb-3" id="game-info" style="display: none;">
                    <span class="badge bg-primary me-2">Poin: <span id="score">0</span></span>
                    <span class="badge bg-success me-2">Percobaan: <span id="attempts">0</span></span>
                    <span class="badge bg-info">
                        <i class="fas fa-clock me-2"></i>
                        <span id="game-time">00:00</span>
                    </span>
                    <span class="badge bg-warning text-dark ms-2">
                        <i class="fas fa-users me-1"></i>
                        <span id="current-team">-</span>
                    </span>
                </div>
                
                <!-- Game Board -->
                <div class="glass-card p-4">
                    <div class="row row-cols-2 row-cols-md-4 g-3" id="game-board">
                        <!-- Kartu game akan diisi oleh JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0">
                <div class="modal-body text-center py-5">
                    <h2><i class="fas fa-trophy text-warning mb-3"></i></h2>
                    <h3 class="mb-3">Selamat!</h3>
                    <p>Kelompok <span id="winner-team" class="fw-bold"></span> telah menyelesaikan permainan dalam</p>
                    <h1 id="final-time" class="display-4 mb-4">00:00</h1>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-primary px-4 py-2" onclick="saveResult()">
                            <i class="fas fa-save me-2"></i>Simpan Hasil
                        </button>
                        <button class="btn btn-outline-light px-4 py-2" onclick="resetGame()">
                            <i class="fas fa-redo me-2"></i>Main Lagi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Data dari PHP (pertanyaan yang sudah diinput user)
        const gameQuestions = <?= json_encode($questionsData) ?>;
        const allRounds = <?= json_encode($rounds) ?>;
        const sessionId = "<?= $sessionId ?>";
        
        // Game Variables
        let currentRound = 1;
        let score = 0;
        let attempts = 0;
        let canFlip = true;
        let flippedCards = [];
        let matchedPairs = 0;
        let currentCards = [];
        let gameStarted = false;
        let gameTime = 0;
        let timerInterval;
        let countdownInterval;
        let currentTeam = "";
        
        // DOM Elements
        const startBtn = document.getElementById('start-btn');
        const countdownEl = document.getElementById('countdown');
        const gameInfo = document.getElementById('game-info');
        const gameTimeEl = document.getElementById('game-time');
        const teamNameInput = document.getElementById('team-name');
        const currentTeamEl = document.getElementById('current-team');
        const winnerTeamEl = document.getElementById('winner-team');
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        
        // Audio Elements
        const countdownSound = document.getElementById('countdownSound');
        const flipSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-quick-jump-arcade-game-239.mp3');
        const matchSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-unlock-game-notification-253.mp3');
        const winSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-winning-chimes-2015.mp3');
        
        // Music Player Functionality
        const musicPlayer = {
            audio: document.getElementById('backgroundMusic'),
            playPauseBtn: document.getElementById('playPauseBtn'),
            nowPlaying: document.getElementById('nowPlaying'),
            trackInfo: document.getElementById('trackInfo'),
            playlist: <?= json_encode($playlist) ?>,
            currentTrack: <?= $music_settings['current_track'] ?>,
            isPlaying: <?= $music_settings['is_music_on'] ?>,
            volume: <?= $music_settings['volume'] / 100 ?>,
            
            init: function() {
                if (this.playlist.length > 0) {
                    this.loadTrack();
                    if (this.isPlaying) {
                        this.play();
                    }
                }
                
                // Event listeners
                this.playPauseBtn.addEventListener('click', () => this.togglePlay());
                this.audio.addEventListener('ended', () => this.nextTrack());
            },
            
            loadTrack: function() {
                if (this.playlist.length === 0) return;
                
                const track = this.playlist[this.currentTrack];
                this.audio.src = track.file_path;
                this.audio.volume = this.volume;
                this.nowPlaying.textContent = track.display_name;
                this.trackInfo.textContent = `Track ${this.currentTrack + 1} of ${this.playlist.length}`;
                
                // Save current track to database
                this.saveSettings();
            },
            
            play: function() {
                if (this.playlist.length === 0) return;
                
                this.audio.play()
                    .then(() => {
                        this.isPlaying = true;
                        this.playPauseBtn.innerHTML = '<i class="fas fa-pause text-white"></i>';
                        this.saveSettings();
                    })
                    .catch(error => {
                        console.error('Playback failed:', error);
                    });
            },
            
            pause: function() {
                this.audio.pause();
                this.isPlaying = false;
                this.playPauseBtn.innerHTML = '<i class="fas fa-play text-white"></i>';
                this.saveSettings();
            },
            
            togglePlay: function() {
                if (this.isPlaying) {
                    this.pause();
                } else {
                    this.play();
                }
            },
            
            nextTrack: function() {
                if (this.playlist.length === 0) return;
                
                this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
                this.loadTrack();
                
                if (this.isPlaying) {
                    this.play();
                }
            },
            
            saveSettings: function() {
                fetch('update_music_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        is_music_on: this.isPlaying,
                        current_track: this.currentTrack,
                        volume: Math.round(this.volume * 100)
                    })
                });
            }
        };

        // Initialize Game
        function initGame(round = 1) {
            document.getElementById('game-board').innerHTML = '';
            score = 0;
            attempts = 0;
            matchedPairs = 0;
            gameTime = 0;
            document.getElementById('score').textContent = score;
            document.getElementById('attempts').textContent = attempts;
            updateGameTime();
            
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
        
        // Shuffle Cards
        function shuffleCards() {
            const shuffled = [...currentCards].sort(() => Math.random() - 0.5);
            renderCards(shuffled.map((card, i) => ({ ...card, realIndex: i })));
        }
        
        // Render Cards
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
        
        // Flip Card
        function flipCard(cardElement, cardId) {
            if (!gameStarted || !canFlip || cardElement.classList.contains('flipped')) return;
            
            flipSound.play();
            cardElement.classList.add('flipped');
            const index = currentCards.findIndex(c => c.id === cardId);
            flippedCards.push({ element: cardElement, index });
            
            if (flippedCards.length === 1) {
                // Jika hanya 1 kartu terbuka, balik kembali setelah 2 detik
                setTimeout(() => {
                    if (flippedCards.length === 1) {
                        flippedCards[0].element.classList.remove('flipped');
                        flippedCards = [];
                    }
                }, 2000);
            }
            else if (flippedCards.length === 2) {
                attempts++;
                document.getElementById('attempts').textContent = attempts;
                canFlip = false;
                
                setTimeout(checkMatch, 1000);
            }
        }
        
        // Check Match
        function checkMatch() {
            const [firstCard, secondCard] = flippedCards;
            const firstData = currentCards[firstCard.index];
            const secondData = currentCards[secondCard.index];
            
            // Cek apakah pertanyaan dan jawaban cocok
            const isMatch = (firstData.type === 'question' && firstData.answer === secondData.text) || 
                          (firstData.type === 'answer' && firstData.question === secondData.text);
            
            if (isMatch) {
                // Jika cocok
                matchSound.play();
                score += 50;
                document.getElementById('score').textContent = score;
                matchedPairs++;
                
                // Tandai sebagai sudah cocok
                currentCards[firstCard.index].matched = true;
                currentCards[secondCard.index].matched = true;
                
                // Hilangkan kartu yang sudah cocok
                setTimeout(() => {
                    firstCard.element.classList.add('matched');
                    secondCard.element.classList.add('matched');
                }, 500);
                
                // Cek apakah semua pasangan sudah cocok
                const totalPairs = gameQuestions.filter(q => q.round_number == currentRound).length;
                if (matchedPairs === totalPairs) {
                    endGame();
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
        
        function startGame() {
            currentTeam = teamNameInput.value.trim();
            
            if (!currentTeam) {
                alert("Silakan masukkan nama kelompok terlebih dahulu!");
                return;
            }
            
            startBtn.disabled = true;
            countdownEl.style.display = 'block';
            currentTeamEl.textContent = currentTeam;
            
            let count = 3;
            countdownEl.textContent = count;
            
            // Mainkan suara countdown SEKALI (file lengkap 3 detik + suara mulai)
            countdownSound.currentTime = 0;
            countdownSound.play();
            
            // Hitungan visual
            countdownInterval = setInterval(() => {
                count--;
                countdownEl.textContent = count;
                
                if (count <= 0) {
                    clearInterval(countdownInterval);
                    countdownEl.style.display = 'none';
                    
                    // Tunggu sampai audio selesai baru mulai game
                    countdownSound.onended = () => {
                        gameInfo.style.display = 'block';
                        gameStarted = true;
                        startTimer();
                        startBtn.disabled = false;
                    };
                }
            }, 1000);
        }
        
        // Start Timer
        function startTimer() {
            clearInterval(timerInterval);
            gameTime = 0;
            updateGameTime();
            
            timerInterval = setInterval(() => {
                gameTime++;
                updateGameTime();
            }, 1000);
        }
        
        // Update Game Time Display
        function updateGameTime() {
            const minutes = Math.floor(gameTime / 60);
            const seconds = gameTime % 60;
            gameTimeEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        // End Game
        function endGame() {
            clearInterval(timerInterval);
            gameStarted = false;
            
            winSound.play();
            document.getElementById('final-time').textContent = gameTimeEl.textContent;
            winnerTeamEl.textContent = currentTeam;
            setTimeout(() => {
                resultModal.show();
            }, 1000);
        }
        
        // Save Result to Database
        function saveResult() {
            const round = currentRound;
            const time = gameTimeEl.textContent;
            
            // Kirim data ke server untuk disimpan
            fetch('save_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    round_number: round,
                    team_name: currentTeam,
                    completion_time: time,
                    score: score,
                    attempts: attempts
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Hasil berhasil disimpan!");
                    resultModal.hide();
                    resetGame();
                } else {
                    alert("Gagal menyimpan hasil: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Terjadi kesalahan saat menyimpan hasil");
            });
        }
        
        // Reset Game
        function resetGame() {
            resultModal.hide();
            gameStarted = false;
            gameInfo.style.display = 'none';
            initGame(currentRound);
        }
        
        // Event Listeners
        startBtn.addEventListener('click', startGame);
        
        document.getElementById('round-select').addEventListener('change', function() {
            currentRound = parseInt(this.value);
            initGame(currentRound);
        });
        
        document.getElementById('reload-btn').addEventListener('click', function() {
            if (gameStarted) {
                shuffleCards();
                flippedCards.forEach(card => {
                    card.element.classList.remove('flipped');
                });
                flippedCards = [];
                canFlip = true;
            }
        });
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            initGame(currentRound);
            musicPlayer.init();
        });
    </script>
</body>
</html>