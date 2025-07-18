<?php
session_start();
include 'db.php'; // Pastikan file db.php tersedia untuk koneksi database

// Redirect jika tidak login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['sessionid'])) {
    die('Sesi tidak ditemukan untuk game.');
}

$sessionid = $_GET['sessionid'];
$userId = $_SESSION['user_id'];
$session_name = '';
$all_questions_by_round = []; // Akan menyimpan semua pertanyaan yang diambil dari DB
$all_rounds = []; // Untuk dropdown ronde

// Ambil data sesi dari database
try {
    $stmt_session = $conn->prepare("SELECT session_name, created_by FROM dragdrop_sessions WHERE sessionid = ?");
    $stmt_session->bind_param("s", $sessionid);
    $stmt_session->execute();
    $result_session = $stmt_session->get_result();
    if ($result_session->num_rows === 0) {
        die('Sesi tidak valid atau tidak ditemukan.');
    }
    $session_data = $result_session->fetch_assoc();
    $session_name = htmlspecialchars($session_data['session_name']);
    $stmt_session->close();

    // Ambil semua pertanyaan untuk sesi ini, diurutkan berdasarkan ronde dan ID pertanyaan
    $stmt_questions = $conn->prepare("SELECT round_number, question_text, correct_answer, drag_options FROM dragdrop_questions WHERE session_id = ? ORDER BY round_number ASC, question_id ASC");
    $stmt_questions->bind_param("s", $sessionid);
    $stmt_questions->execute();
    $result_questions = $stmt_questions->get_result();

    while ($row = $result_questions->fetch_assoc()) {
        $round_num = (int)$row['round_number'];
        if (!isset($all_questions_by_round[$round_num])) {
            $all_questions_by_round[$round_num] = [];
            $all_rounds[] = $round_num; // Kumpulkan semua nomor ronde
        }
        // Decode JSON drag_options dan masukkan ke array
        $drag_options_array = json_decode($row['drag_options'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $drag_options_array = [];
        }

        $all_questions_by_round[$round_num][] = [
            'question_text' => htmlspecialchars($row['question_text']),
            'correct_answer' => htmlspecialchars($row['correct_answer']),
            'drag_options' => $drag_options_array // Already an array after json_decode
        ];
    }
    $stmt_questions->close();

    if (empty($all_questions_by_round)) {
        die('Belum ada pertanyaan yang dibuat untuk sesi ini. Silakan buat di halaman kelola sesi.');
    }
    
    // Urutkan kunci ronde secara numerik
    ksort($all_questions_by_round);
    sort($all_rounds); // Urutkan nomor ronde

    // Ambil pengaturan musik
    $music_settings = $conn->query("SELECT * FROM music_settings WHERE user_id = $userId")->fetch_assoc();
    if (!$music_settings) {
        // Buat pengaturan default jika tidak ada
        // Menggunakan INSERT IGNORE untuk mencegah duplikasi jika kode ini dijalankan berkali-kali
        $conn->query("INSERT IGNORE INTO music_settings (user_id, is_music_on, volume, current_track) VALUES ($userId, 1, 50, 0)"); 
        $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0]; // Default values if new entry
    }

    // Ambil playlist aktif
    $playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);
    if (!$playlist) { // Handle case where no active music in DB
        $playlist = [];
    }


} catch (Exception $e) {
    die("Error saat memuat sesi: " . $e->getMessage());
}

// Encode semua data pertanyaan ke JSON untuk JavaScript
$questions_json = json_encode($all_questions_by_round);
$playlist_json = json_encode($playlist);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Game Drag & Drop: <?= $session_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a; /* Dark slate background */
            color: #e2e8f0; /* Light slate text */
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        .game-container {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2); /* Blue glowing effect */
        }
        /* Adjusted .drag-item to always be draggable */
        .drag-item {
            background-color: #3b82f6; /* Blue-500 */
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 9999px; /* Full rounded */
            cursor: grab;
            display: inline-block;
            margin: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            position: relative; /* For z-index when dragging */
            z-index: 10;
        }
        .drag-item:active {
            cursor: grabbing;
            transform: scale(1.05);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .drag-item.disabled { /* New style for disabled items in correct questions */
            opacity: 0.7;
            cursor: default;
            background-color: #6B7280; /* Gray out */
        }

        .drop-target-slot { /* New class for individual slots */
            background-color: rgba(30, 41, 59, 0.7); /* Slate-800 semi-transparent */
            border: 2px dashed #475569; /* Slate-600 */
            border-radius: 0.375rem; /* Smaller radius */
            min-width: 5rem; /* Ensure minimum width */
            min-height: 2.5rem; /* Ensure it's visible */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem; /* Smaller padding */
            transition: background-color 0.2s ease, border-color 0.2s ease;
            color: #94a3b8; /* Slate-400 */
            font-style: italic;
            flex-grow: 1; /* Allow slots to grow */
            flex-shrink: 0; /* Prevent shrinking too much */
        }
        .drop-target-slot.drag-over {
            background-color: rgba(59, 130, 246, 0.2); /* Blue-500 light */
            border-color: #3b82f6; /* Blue-500 */
        }
        .drop-target-slot.correct {
            background-color: rgba(16, 185, 129, 0.2); /* Emerald-500 light */
            border-color: #10b981; /* Emerald-500 */
        }
        .drop-target-slot.incorrect {
            background-color: rgba(239, 68, 68, 0.2); /* Red-500 light */
            border-color: #ef4444; /* Red-500 */
        }
        /* Style for dropped item inside slot */
        .drop-target-slot > .drag-item { /* Changed from .dropped-item to .drag-item */
            padding: 0.2rem 0.6rem;
            background-color: #4f46e5; /* Indigo-600 */
            color: white;
            border-radius: 0.3rem;
            font-size: 0.875rem; /* Smaller font for dropped items */
            white-space: nowrap; /* Prevent wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            /* Ensure it can still be dragged */
            cursor: grab;
        }
        .drop-target-slot.correct > .drag-item {
            cursor: default !important; /* Force no dragging */
        }

        .check-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); /* Green gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .check-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(5, 150, 105, 0.4);
        }
        .play-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .play-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4);
        }
        .end-game-btn { /* This style is no longer directly used for a visible button */
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); /* Orange gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .end-game-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(234, 88, 12, 0.4);
        }

        .action-message {
            text-align: center;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }
        .message-success {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .message-error {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .message-info {
             background-color: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        .final-score {
            font-size: 2.5rem;
            font-weight: bold;
            color: #10b981;
            text-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
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
        .team-input-section {
            max-width: 400px;
            margin: 0 auto;
        }
        .form-control-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
        }
        .form-control-glass::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-control-glass:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
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
        @media (max-width: 576px) {
            .music-player {
                bottom: 10px;
                right: 10px;
                padding: 8px 12px;
            }
            .music-info {
                display: none;
            }
        }
        /* Style for the check button per question */
        .question-check-btn {
            background: #4a90e2; /* A nice blue */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 1rem;
            width: 100%; /* Full width */
        }
        .question-check-btn:hover {
            background: #357ABD;
        }
        .question-check-btn:disabled {
            background-color: #6B7280; /* Gray out when disabled */
            cursor: not-allowed;
        }
        /* New style for correctly answered questions */
        .question-correct .drop-target-slot {
            pointer-events: none; /* Disable interaction */
            background-color: rgba(16, 185, 129, 0.3); /* Slightly darker green when correct */
            border-color: #059669;
        }
        .question-correct .drop-target-slot > .drag-item {
            cursor: default !important; /* Force no dragging */
        }
        .question-correct .question-check-btn {
            display: none; /* Hide button when correct */
        }
    </style>
</head>
<body class="relative flex flex-col items-center justify-center min-h-screen px-4 bg-cover bg-center pb-12">
    <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
    </div>

    <div class="music-player" id="musicPlayer">
        <div class="music-btn" id="playPauseBtn">
            <i class="fas <?= $music_settings['is_music_on'] ? 'fa-pause' : 'fa-play' ?> text-white"></i>
        </div>
        <div class="music-info">
            <div class="now-playing" id="nowPlaying">
                <?= count($playlist) > 0 ? htmlspecialchars($playlist[$music_settings['current_track'] ?? 0]['display_name'] ?? 'No track selected') : 'No tracks' ?>
            </div>
            <div class="track-info" id="trackInfo">
                Track <?= count($playlist) > 0 ? (($music_settings['current_track'] ?? 0) + 1) : '0' ?> of <?= count($playlist) ?>
            </div>
        </div>
    </div>
    <audio id="backgroundMusic" loop></audio> <audio id="countdownSound" src="sounds/countdown.mp3" preload="auto"></audio>
    <audio id="correctAnswerSound" src="sounds/correct.mp3" preload="auto"></audio> 
    <audio id="incorrectAnswerSound" src="sounds/incorrect.mp3" preload="auto"></audio> 
    <audio id="winRoundSound" src="sounds/win.mp3" preload="auto"></audio> 

    <div class="w-full max-w-2xl mx-auto mb-8"> 
        <div class="text-center mb-10">
            <h1 class="title-font text-4xl font-bold text-white mb-2">Bible Drag & Drop</h1>
            <p class="text-slate-300">Sesi: <span class="font-semibold text-blue-300"><?= $session_name ?></span></p>
        </div>

        <div class="game-container p-6 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
            <div id="team-input-section" class="mb-6 p-4 bg-slate-800 rounded-lg shadow-inner team-input-section">
                <h4 class="text-center font-bold text-xl text-white mb-3">Masukkan Nama Tim</h4>
                <input type="text" id="team-name-input" class="form-control-glass w-full mb-4" placeholder="Contoh: Tim Alpha">
            </div>

            <div class="flex flex-col sm:flex-row justify-center sm:justify-between items-center gap-4 mb-6">
                <div class="flex items-center gap-4">
                     <select id="round-select" class="bg-slate-700 text-white p-2 rounded-lg border border-slate-600 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($all_rounds as $round): ?>
                            <option value="<?= $round ?>">Ronde <?= $round ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="game-time" class="text-lg font-semibold text-white bg-slate-700 py-2 px-4 rounded-lg">
                        00:00
                    </div>
                </div>
                <div id="countdown" class="hidden text-center text-blue-400">3</div>
                <button id="play-game-btn" class="w-full sm:w-auto py-3 px-6 play-btn text-white font-semibold rounded-lg">
                    <i class="fas fa-play mr-2"></i> Mulai Main
                </button>
            </div>

            <div id="game-content" class="hidden">
                <h2 id="round-title" class="font-bold text-2xl text-white mb-6 text-center">Ronde 1</h2>
                
                <div id="questions-container" class="space-y-6 mb-6">
                    </div>
                
                <div id="game-message" class="action-message hidden"></div>
            </div>

            </div>

        <div id="endGameModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-70 flex justify-center items-center z-50 hidden">
            <div class="bg-slate-800 p-8 rounded-lg shadow-lg text-center border border-slate-700 max-w-md w-full">
                <h3 class="text-3xl font-bold text-white mb-4 title-font">Ronde Selesai!</h3>
                <p class="text-slate-300 text-lg mb-6">Selamat, <span id="finalTeamName" class="font-bold text-blue-300"></span> telah menyelesaikan ronde ini!</p>
                <div class="text-slate-400 text-md mb-2">Waktu penyelesaian ronde Anda:</div>
                <div id="finalTimeDisplay" class="final-score mb-8"></div>
                <button id="playAgainBtn" class="py-3 px-6 play-btn text-white font-semibold rounded-lg">
                    <i class="fas fa-redo mr-2"></i> Main Ronde Baru
                </button>
            </div>
        </div>
    </div>

    <script>
        const allQuestionsByRound = <?= $questions_json ?>;
        const allRounds = <?= json_encode($all_rounds) ?>;
        const sessionId = "<?= $sessionid ?>";

        let currentRound = allRounds[0]; 
        let currentTeamName = '';
        let gameStarted = false;
        let gameTime = 0; // Total game time in seconds
        let timerInterval;
        let countdownInterval;
        let canDragDrop = false; 
        let currentDragItem = null; // Ini adalah deklarasi yang benar dan tunggal
        let roundStartTime = 0; // To store the start time of the current round for per-round time calculation

        // DOM Elements
        const teamNameInput = document.getElementById('team-name-input');
        const teamInputSection = document.getElementById('team-input-section');
        const playBtn = document.getElementById('play-game-btn');
        const countdownEl = document.getElementById('countdown');
        const gameTimeEl = document.getElementById('game-time');
        const roundSelect = document.getElementById('round-select');
        const gameContent = document.getElementById('game-content');
        const roundTitle = document.getElementById('round-title');
        const questionsContainer = document.getElementById('questions-container');
        const gameMessage = document.getElementById('game-message');
        const endGameModal = document.getElementById('endGameModal');
        const finalTimeDisplay = document.getElementById('finalTimeDisplay');
        const finalTeamName = document.getElementById('finalTeamName');
        const playAgainBtn = document.getElementById('playAgainBtn');

        const correctAnswerModal = document.getElementById('correctAnswerModal');
        const correctSentenceDisplay = document.getElementById('correctSentenceDisplay');
        const closeCorrectModalBtn = document.getElementById('closeCorrectModalBtn');
        
        // Audio Elements
        const countdownSound = document.getElementById('countdownSound');
        const correctAnswerSound = document.getElementById('correctAnswerSound');
        const incorrectAnswerSound = document.getElementById('incorrectAnswerSound');
        const winRoundSound = document.getElementById('winRoundSound'); 

        // Listener for the modal close button
    closeCorrectModalBtn.addEventListener('click', () => {
        correctAnswerModal.classList.add('hidden');
        nextQuestion(); // Proceed to the next question after closing modal
    });

        // Music Player Functionality
        const musicPlayer = {
            audio: document.getElementById('backgroundMusic'),
            playPauseBtn: document.getElementById('playPauseBtn'),
            nowPlaying: document.getElementById('nowPlaying'),
            trackInfo: document.getElementById('trackInfo'),
            playlist: <?= $playlist_json ?>,
            currentTrack: <?= $music_settings['current_track'] ?? 0 ?>, // Added null coalescing for safety
            isPlaying: <?= $music_settings['is_music_on'] ?? 0 ?>, // Added null coalescing for safety
            volume: <?= ($music_settings['volume'] ?? 50) / 100 ?>, // Added null coalescing for safety
            
            init: function() {
                console.log("Music Player Init - Playlist length:", this.playlist.length, "Is Playing:", this.isPlaying);
                if (this.playlist.length > 0) {
                    this.loadTrack();
                    if (this.isPlaying) {
                        this.audio.play().then(() => {
                            console.log("Music autostarted successfully (after possible initial block).");
                        }).catch(e => console.log("Music auto-play blocked or failed initially:", e.message));
                    }
                }
                this.playPauseBtn.addEventListener('click', () => this.togglePlay());
                this.audio.addEventListener('ended', () => this.nextTrack());
                this.audio.addEventListener('error', (e) => console.error("Audio error:", e.message, this.audio.error));
            },
            loadTrack: function() {
                if (this.playlist.length === 0) {
                    console.warn("No tracks in playlist to load.");
                    return;
                }
                // Ensure currentTrack is a valid index
                if (this.currentTrack < 0 || this.currentTrack >= this.playlist.length) {
                    console.warn(`currentTrack index (${this.currentTrack}) out of bounds. Resetting to 0.`);
                    this.currentTrack = 0;
                }

                const track = this.playlist[this.currentTrack];
                if (!track || !track.file_path) {
                    console.error("Invalid track or file_path:", track);
                    return;
                }
                this.audio.src = track.file_path;
                this.audio.volume = this.volume;
                this.nowPlaying.textContent = track.display_name;
                this.trackInfo.textContent = `Track ${this.currentTrack + 1} of ${this.playlist.length}`;
                this.saveSettings();
                console.log("Track loaded:", track.display_name, "Source:", track.file_path);
            },
            play: function() {
                if (this.playlist.length === 0) {
                    console.warn("Cannot play, playlist is empty.");
                    return;
                }
                this.audio.play().then(() => {
                    this.isPlaying = true;
                    this.playPauseBtn.innerHTML = '<i class="fas fa-pause text-white"></i>';
                    this.saveSettings();
                    console.log("Music started playing.");
                }).catch(error => console.error('Playback failed after user interaction:', error));
            },
            pause: function() {
                this.audio.pause();
                this.isPlaying = false;
                this.playPauseBtn.innerHTML = '<i class="fas fa-play text-white"></i>';
                this.saveSettings();
                console.log("Music paused.");
            },
            togglePlay: function() {
                console.log("Toggle Play clicked. Current state isPlaying:", this.isPlaying);
                if (this.isPlaying) { this.pause(); } else { this.play(); }
            },
            nextTrack: function() {
                console.log("Next track.");
                if (this.playlist.length === 0) return;
                this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
                this.loadTrack();
                if (this.isPlaying) { this.play(); }
            },
            saveSettings: function() {
                fetch('update_music_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_music_on: this.isPlaying ? 1 : 0, current_track: this.currentTrack, volume: Math.round(this.volume * 100) })
                });
            }
        };


        // --- Game Logic ---
        // Variabel currentDragItem sudah dideklarasikan di atas. JANGAN DEKLARASIKAN LAGI DI SINI!
        // let currentDragItem = null; // <-- Baris ini yang dihapus/dihilangkan.

        function initRound(roundNum) {
            gameMessage.classList.add('hidden');
            
            const questions = allQuestionsByRound[roundNum];
            if (!questions || questions.length === 0) {
                questionsContainer.innerHTML = `<div class="text-center text-slate-400">Tidak ada pertanyaan untuk Ronde ${roundNum}.</div>`;
                return;
            }

            roundTitle.textContent = `Ronde ${roundNum}`;
            questionsContainer.innerHTML = ''; 

            questions.forEach((q, qIndex) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-block bg-slate-700 p-4 rounded-lg border border-slate-600';
                questionDiv.dataset.questionIndex = qIndex; 
                questionDiv.dataset.isCorrect = 'false'; 

                // Membuat ID unik untuk container opsi drag dari setiap pertanyaan
                const dragOptionsContainerId = `q${qIndex}-drag-options-container`;

                questionDiv.innerHTML = `
                    <p class="text-white font-semibold mb-3">Q${qIndex + 1}: ${q.question_text}</p>
                    <div class="drop-targets-wrapper flex flex-wrap gap-2 justify-center" data-question-index="${qIndex}">
                        </div>
                    <div id="${dragOptionsContainerId}" class="drag-options-container flex flex-wrap justify-center gap-2 p-4 bg-slate-800 rounded-lg shadow-inner mt-4 min-h-[5rem]">
                        </div>
                    <button class="question-check-btn mt-4 hidden">Periksa Jawaban</button>
                    <div class="question-feedback hidden mt-3 text-center font-bold"></div>
                `;
                questionsContainer.appendChild(questionDiv);

                const dropTargetsWrapper = questionDiv.querySelector('.drop-targets-wrapper');
                const dragOptionsContainerForQuestion = questionDiv.querySelector('.drag-options-container');
                const checkButton = questionDiv.querySelector('.question-check-btn');

                const correctParts = q.correct_answer.split(/(\s+|[,.]\s*)/).filter(part => part.trim() !== '');
                
                correctParts.forEach((part, partIndex) => {
                    const dropTargetSlot = document.createElement('div');
                    dropTargetSlot.className = 'drop-target-slot';
                    dropTargetSlot.dataset.correctPart = part.toLowerCase().trim();
                    dropTargetSlot.id = `q${qIndex}-slot${partIndex}`; 
                    dropTargetSlot.textContent = (part.trim() === ',' || part.trim() === '.') ? part.trim() : '_____';
                    dropTargetsWrapper.appendChild(dropTargetSlot);
                });

                let allDragOptionsForThisQuestion = [];
                correctParts.forEach(part => {
                    if (part.trim() !== '' && !['.', ',', ';', ':', '!', '?'].includes(part.trim())) { 
                         allDragOptionsForThisQuestion.push(part.trim());
                    }
                });
                allDragOptionsForThisQuestion = allDragOptionsForThisQuestion.concat(q.drag_options);
                
                const uniqueDragOptions = Array.from(new Set(allDragOptionsForThisQuestion.filter(opt => opt.trim() !== '')));
                shuffleArray(uniqueDragOptions); 

                uniqueDragOptions.forEach(optionText => {
                    const dragItem = document.createElement('div');
                    dragItem.className = 'drag-item'; // Item stays as 'drag-item'
                    dragItem.textContent = optionText;
                    dragItem.setAttribute('draggable', true);
                    // Menyimpan ID container asal untuk item yang bisa ditarik kembali
                    dragItem.dataset.originalContainerId = dragOptionsContainerId; 
                    dragOptionsContainerForQuestion.appendChild(dragItem);
                });

                checkButton.addEventListener('click', () => checkQuestionCompletion(questionDiv));

                // No need to attach drop listener here, it's done globally by addDragAndDropListeners
            }); 

            addDragAndDropListeners(); 
        }

        function addDragAndDropListeners() {
            // Remove previous listeners to prevent duplicates
            document.querySelectorAll('[data-drag-listener="true"]').forEach(el => {
                el.removeEventListener('dragstart', handleDragStart);
                el.removeEventListener('dragend', handleDragEnd);
                el.removeAttribute('data-drag-listener');
            });
            document.querySelectorAll('[data-drop-listener="true"]').forEach(el => {
                el.removeEventListener('dragover', handleDragOver);
                el.removeEventListener('dragleave', handleDragLeave);
                el.removeEventListener('drop', handleDrop);
                el.removeAttribute('data-drop-listener');
            });


            document.querySelectorAll('.drag-item').forEach(item => {
                item.setAttribute('data-drag-listener', 'true');
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd); // Ensure dragend is handled
            });

            document.querySelectorAll('.drop-target-slot, .drag-options-container').forEach(target => {
                target.setAttribute('data-drop-listener', 'true');
                target.addEventListener('dragover', handleDragOver);
                target.addEventListener('dragleave', handleDragLeave);
                target.addEventListener('drop', handleDrop);
            });
        }

        // let currentDragItem = null; // BARIS INI TELAH DIHAPUS/DIKOMENTARI KARENA SUDAH ADA DI ATAS

        function handleDragStart(e) {
            if (!canDragDrop || e.target.closest('.question-block')?.dataset.isCorrect === 'true') {
                e.preventDefault(); 
                return;
            }
            currentDragItem = e.target;
            currentDragItem.classList.add('opacity-50');
            e.dataTransfer.setData('text/plain', currentDragItem.textContent);
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragEnd() {
            if (currentDragItem) {
                currentDragItem.classList.remove('opacity-50');
            }
            currentDragItem = null;
        }

        function handleDragOver(e) {
            if (!canDragDrop) return;
            const targetSlot = e.target.closest('.drop-target-slot');
            const targetContainer = e.target.closest('.drag-options-container');

            // Prevent drag over on correct questions' slots
            if (targetSlot && targetSlot.closest('.question-block')?.dataset.isCorrect === 'true') {
                e.preventDefault();
                return;
            }

            // Allow dropping back into original container or any valid target slot
            if (targetSlot || (targetContainer && targetContainer.classList.contains('drag-options-container'))) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (targetSlot) {
                    targetSlot.classList.add('drag-over');
                } else if (targetContainer) {
                    targetContainer.classList.add('drag-over');
                }
            }
        }

        function handleDragLeave(e) {
            const targetSlot = e.target.closest('.drop-target-slot');
            const targetContainer = e.target.closest('.drag-options-container');
            if (targetSlot) {
                targetSlot.classList.remove('drag-over');
            }
            if (targetContainer) {
                targetContainer.classList.remove('drag-over');
            }
        }

        async function handleDrop(e) {
            e.preventDefault();
            const dropTarget = e.target.closest('.drop-target-slot') || e.target.closest('.drag-options-container');
            
            if (dropTarget) {
                dropTarget.classList.remove('drag-over');
            }

            if (!canDragDrop || !currentDragItem || !dropTarget) return;

            // Jika item dijatuhkan kembali ke container asal atau container opsi drag lainnya
            if (dropTarget.classList.contains('drag-options-container')) {
                // Pindahkan item yang sedang ditarik ke dalam container drag-options
                dropTarget.appendChild(currentDragItem);
                currentDragItem = null; // Reset
                // Check if any question associated with this item needs re-evaluation
                document.querySelectorAll('.question-block').forEach(qDiv => {
                    checkIfQuestionReady(qDiv);
                });
                return;
            }

            // Jika target adalah drop-target-slot
            if (dropTarget.classList.contains('drop-target-slot')) {
                 if (dropTarget.closest('.question-block')?.dataset.isCorrect === 'true') {
                    // Cannot drop on a slot of a correctly answered question
                    currentDragItem.classList.remove('opacity-50');
                    currentDragItem = null;
                    return;
                }

                const existingItemInSlot = dropTarget.querySelector('.drag-item');
                if (existingItemInSlot && existingItemInSlot !== currentDragItem) {
                    // Pindahkan item yang sudah ada kembali ke container asalnya
                    const originalContainerId = existingItemInSlot.dataset.originalContainerId;
                    const originalContainer = document.getElementById(originalContainerId);
                    if (originalContainer) {
                        originalContainer.appendChild(existingItemInSlot);
                    }
                }
                
                // --- Perbaikan di sini: Hapus konten lama sebelum menambahkan yang baru ---
                // Bersihkan slot sebelum menambahkan item baru
                dropTarget.textContent = ''; // Hapus teks placeholder (misalnya '_____')

                // Letakkan item yang sedang ditarik ke dalam slot
                dropTarget.appendChild(currentDragItem);
                checkIfQuestionReady(dropTarget.closest('.question-block'));
            }
            currentDragItem = null; // Reset
        }


        function checkIfQuestionReady(questionDivElement) {
            const dropTargetsWrapper = questionDivElement.querySelector('.drop-targets-wrapper');
            const checkButton = questionDivElement.querySelector('.question-check-btn');
            
            let allSlotsFilled = true;
            dropTargetsWrapper.querySelectorAll('.drop-target-slot').forEach(slot => {
                if (!slot.querySelector('.drag-item')) { // Check for 'drag-item'
                    allSlotsFilled = false;
                }
            });

            if (allSlotsFilled) {
                checkButton.classList.remove('hidden');
            } else {
                checkButton.classList.add('hidden');
            }
        }

        async function checkQuestionCompletion(questionDivElement) {
            const questionIndex = questionDivElement.dataset.questionIndex;
            const dropTargetsWrapper = questionDivElement.querySelector('.drop-targets-wrapper');
            const questionFeedback = questionDivElement.querySelector('.question-feedback');
            const checkButton = questionDivElement.querySelector('.question-check-btn');

            const currentQuestionData = allQuestionsByRound[currentRound][questionIndex];
            const correctPartsForQuestion = currentQuestionData.correct_answer.split(/(\s+|[,.]\s*)/).filter(part => part.trim() !== '');

            let allPartsCorrect = true;
            let allSlotsFilled = true; // Ensure all slots are filled before checking correctness

            dropTargetsWrapper.querySelectorAll('.drop-target-slot').forEach((slot, slotIndex) => {
                slot.classList.remove('correct', 'incorrect'); 
                const expectedPart = correctPartsForQuestion[slotIndex].toLowerCase().trim();
                const droppedItem = slot.querySelector('.drag-item'); // Changed to drag-item

                if (droppedItem) {
                    const actualPart = droppedItem.textContent.toLowerCase().trim();
                    if (actualPart === expectedPart) {
                        slot.classList.add('correct');
                    } else {
                        slot.classList.add('incorrect');
                        allPartsCorrect = false;
                    }
                } else {
                    allSlotsFilled = false; 
                    allPartsCorrect = false; 
                }
            });

            if (!allSlotsFilled) {
                 questionFeedback.textContent = 'Silakan isi semua kotak kosong terlebih dahulu.';
                 questionFeedback.className = 'question-feedback mt-3 text-center font-bold message-info';
                 questionFeedback.classList.remove('hidden');
                 incorrectAnswerSound.play(); // Or a different sound for incomplete
                 return;
            }


            if (allPartsCorrect) {
                questionFeedback.textContent = 'Jawaban Anda Benar!';
                questionFeedback.className = 'question-feedback mt-3 text-center font-bold message-success';
                correctAnswerSound.play();
                
                questionDivElement.dataset.isCorrect = 'true'; 
                questionDivElement.classList.add('question-correct'); 
                checkButton.style.display = 'none'; 
                
                // Menonaktifkan drag/drop untuk slot yang sudah benar
                dropTargetsWrapper.querySelectorAll('.drop-target-slot').forEach(slot => {
                    slot.style.pointerEvents = 'none'; 
                    const itemInSlot = slot.querySelector('.drag-item');
                    if(itemInSlot) {
                        itemInSlot.setAttribute('draggable', 'false'); // Item di dalam slot tidak bisa ditarik lagi
                        itemInSlot.classList.add('disabled');
                    }
                });
                // Menonaktifkan item yang tersisa di container opsi drag untuk pertanyaan ini
                questionDivElement.querySelector('.drag-options-container').querySelectorAll('.drag-item').forEach(item => {
                    item.setAttribute('draggable', 'false');
                    item.classList.add('disabled');
                });


                // Setelah satu soal benar, cek apakah semua soal di ronde ini sudah benar
                await checkRoundCompletion(); // Menggunakan await karena checkRoundCompletion bisa async
            } else {
                questionFeedback.textContent = 'Jawaban Salah. Silakan coba lagi.';
                questionFeedback.className = 'question-feedback mt-3 text-center font-bold message-error';
                incorrectAnswerSound.play();
            }
            questionFeedback.classList.remove('hidden');
        }

        async function checkRoundCompletion() {
            const currentRoundQuestionsCount = allQuestionsByRound[currentRound].length;
            let correctlyAnsweredQuestions = 0;

            document.querySelectorAll('#questions-container > .question-block').forEach(questionDiv => {
                if (questionDiv.dataset.isCorrect === 'true') {
                    correctlyAnsweredQuestions++;
                }
            });

            if (correctlyAnsweredQuestions === currentRoundQuestionsCount && currentRoundQuestionsCount > 0) {
                // All questions in the current round are correct
                gameStarted = false; 
                canDragDrop = false; 
                clearInterval(timerInterval); 
                winRoundSound.play(); 

                gameMessage.textContent = 'Selamat! Semua jawaban di ronde ini benar!';
                gameMessage.className = 'action-message message-success';
                gameMessage.classList.remove('hidden');
                
                const roundCompletionTime = gameTime - roundStartTime;
                // Save round result (per-round time)
                await saveGameResult(currentTeamName, currentRound, roundCompletionTime);

                // Show modal temporarily then redirect after a short delay
                showEndGameModal(roundCompletionTime);
                setTimeout(() => {
                    // Redirect to the results page
                    window.location.href = `dragdrop_result.php?sessionid=${sessionId}`;
                }, 3000); // Redirect after 3 seconds
            }
        }

        function startGameCountdown() {
            currentTeamName = teamNameInput.value.trim();
            if (!currentTeamName) {
                alert("Silakan masukkan nama tim terlebih dahulu!");
                // Tidak menyembunyikan tombol 'Play' di sini agar pengguna bisa mencoba lagi
                return; 
            }
            teamNameInput.disabled = true;
            teamInputSection.classList.add('hidden'); 
            playBtn.classList.add('hidden'); // Tombol 'Play' disembunyikan setelah validasi nama tim berhasil
            countdownEl.classList.remove('hidden'); 
            roundSelect.disabled = true; 
            
            gameContent.classList.remove('hidden'); 

            let count = 3;
            countdownEl.textContent = count;
            countdownSound.currentTime = 0;
            countdownSound.play();

            countdownInterval = setInterval(() => {
                count--;
                countdownEl.textContent = count;
                if (count <= 0) {
                    clearInterval(countdownInterval);
                    countdownEl.classList.add('hidden');
                    setTimeout(() => {
                        startGame();
                    }, 500); 
                }
            }, 1000);
        }

        function startGame() {
            gameStarted = true;
            canDragDrop = true; 
            roundStartTime = gameTime; // Reset round start time
            startTimer(); 
            musicPlayer.play(); 
            
            initRound(currentRound); // (Re)initialize the current round
        }

        function startTimer() {
            clearInterval(timerInterval);
            updateGameTimeDisplay();
            timerInterval = setInterval(() => {
                gameTime++;
                updateGameTimeDisplay();
            }, 1000);
        }

        function updateGameTimeDisplay() {
            const minutes = Math.floor(gameTime / 60);
            const seconds = gameTime % 60;
            gameTimeEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
        }

        function resetGame() {
            endGameModal.classList.add('hidden');
            teamInputSection.classList.remove('hidden'); 
            teamNameInput.disabled = false; 
            playBtn.classList.remove('hidden'); 
            roundSelect.disabled = false; 
            teamNameInput.value = ''; 
            currentTeamName = ''; 
            gameStarted = false;
            canDragDrop = false;
            clearInterval(timerInterval);
            gameTime = 0; 
            roundStartTime = 0; 
            updateGameTimeDisplay();
            hideGameContent(); 
            
            if (allRounds.length > 0) {
                currentRound = allRounds[0]; // Reset to the first round available
                roundSelect.value = allRounds[0];
                initRound(currentRound); 
            }
        }

        // Function to save game results
        async function saveGameResult(teamName, roundNumber, timeTaken) {
            try {
                const response = await fetch('save_dragdrop_result.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        team_name: teamName,
                        round_number: roundNumber, 
                        time_taken: timeTaken 
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to save game result:', result.message);
                } else {
                    console.log('Game result saved:', result.message);
                }
            } catch (error) {
                console.error('Error saving game result:', error);
            }
        }
        
        // Event Listeners
        playBtn.addEventListener('click', startGameCountdown);

        roundSelect.addEventListener('change', function() {
            if (gameStarted) {
                // If game is active, don't allow changing round
                this.value = currentRound;
                alert('Tidak bisa mengubah ronde saat game sedang berjalan. Harap selesaikan ronde atau reset game.');
                return;
            }
            currentRound = parseInt(this.value);
            hideGameContent(); 
            initRound(currentRound); 
        });
        playAgainBtn.addEventListener('click', resetGame);
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            roundSelect.innerHTML = ''; 
            allRounds.forEach(round => {
                const option = document.createElement('option');
                option.value = round;
                option.textContent = `Ronde ${round}`;
                roundSelect.appendChild(option);
            });
            if (allRounds.length > 0) {
                 roundSelect.value = allRounds[0];
                 currentRound = allRounds[0];
            }

            initRound(currentRound); 
            hideGameContent(); 
            musicPlayer.init();
        });

        function showEndGameModal(completionTime) {
            const minutes = Math.floor(completionTime / 60);
            const seconds = completionTime % 60;
            finalTimeDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            finalTeamName.textContent = currentTeamName;
            endGameModal.classList.remove('hidden');
        }

        function hideGameContent() {
            gameContent.classList.add('hidden'); 
            questionsContainer.innerHTML = ''; 
            gameMessage.classList.add('hidden'); 
        }
    </script>

</div>
</body>
</html>