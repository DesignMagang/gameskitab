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
        $conn->query("INSERT INTO music_settings (user_id) VALUES ($userId)");
        $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0];
    }

    // Ambil playlist aktif
    $playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);


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
        .drop-target-slot > .dropped-item {
            padding: 0.2rem 0.6rem;
            background-color: #4f46e5; /* Indigo-600 */
            color: white;
            border-radius: 0.3rem;
            font-size: 0.875rem; /* Smaller font for dropped items */
            white-space: nowrap; /* Prevent wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
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
        .end-game-btn {
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
                <?= count($playlist) > 0 ? htmlspecialchars($playlist[$music_settings['current_track']]['display_name']) : 'No tracks' ?>
            </div>
            <div class="track-info" id="trackInfo">
                Track <?= count($playlist) > 0 ? ($music_settings['current_track'] + 1) : '0' ?> of <?= count($playlist) ?>
            </div>
        </div>
    </div>
    <audio id="backgroundMusic"></audio>
    <audio id="countdownSound" src="sounds/countdown.mp3" preload="auto"></audio>
    <audio id="correctAnswerSound" src="sounds/correct.mp3" preload="auto"></audio> <audio id="incorrectAnswerSound" src="sounds/incorrect.mp3" preload="auto"></audio> <audio id="winRoundSound" src="sounds/win.mp3" preload="auto"></audio> <div class="w-full max-w-2xl mx-auto mb-8"> 
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

                <div id="drag-options-container" class="flex flex-wrap justify-center gap-2 p-4 bg-slate-800 rounded-lg shadow-inner mb-6 min-h-[5rem]">
                    </div>

                <div id="game-message" class="action-message hidden"></div>
            </div>

            <div id="end-round-controls" class="flex justify-center mt-8 hidden">
                <button id="end-game-btn" class="w-full sm:w-auto py-3 px-6 end-game-btn text-white font-semibold rounded-lg">
                    <i class="fas fa-flag-checkered mr-2"></i> Selesai Ronde Ini
                </button>
            </div>
        </div>

        <div id="endGameModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-70 flex justify-center items-center z-50 hidden">
            <div class="bg-slate-800 p-8 rounded-lg shadow-lg text-center border border-slate-700 max-w-md w-full">
                <h3 class="text-3xl font-bold text-white mb-4 title-font">Permainan Selesai!</h3>
                <p class="text-slate-300 text-lg mb-6">Selamat, <span id="finalTeamName" class="font-bold text-blue-300"></span> telah menyelesaikan semua ronde!</p>
                <div class="text-slate-400 text-md mb-2">Total Waktu Anda:</div>
                <div id="finalTimeDisplay" class="final-score mb-8"></div>
                <button id="playAgainBtn" class="py-3 px-6 play-btn text-white font-semibold rounded-lg">
                    <i class="fas fa-redo mr-2"></i> Main Lagi
                </button>
                <button id="viewResultsBtn" class="py-3 px-6 check-btn text-white font-semibold rounded-lg mt-3">
                    <i class="fas fa-list-ol mr-2"></i> Lihat Hasil Peringkat
                </button>
            </div>
        </div>
    </div>

    <script>
        const allQuestionsByRound = <?= $questions_json ?>;
        const allRounds = <?= json_encode($all_rounds) ?>;
        const sessionId = "<?= $sessionid ?>";

        let currentRound = allRounds[0]; // Mulai dari ronde pertama yang tersedia
        let currentTeamName = '';
        let gameStarted = false;
        let gameTime = 0;
        let timerInterval;
        let countdownInterval;
        let canDragDrop = false; // Kontrol apakah item bisa di-drag/drop
        let currentDragItem = null; // To store the currently dragged item

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
        const dragOptionsContainer = document.getElementById('drag-options-container');
        const gameMessage = document.getElementById('game-message');
        const endRoundControls = document.getElementById('end-round-controls');
        const endGameBtn = document.getElementById('end-game-btn');
        const endGameModal = document.getElementById('endGameModal');
        const finalTimeDisplay = document.getElementById('finalTimeDisplay');
        const finalTeamName = document.getElementById('finalTeamName');
        const playAgainBtn = document.getElementById('playAgainBtn');
        const viewResultsBtn = document.getElementById('viewResultsBtn');

        // Audio Elements
        const countdownSound = document.getElementById('countdownSound');
        const correctAnswerSound = document.getElementById('correctAnswerSound');
        const incorrectAnswerSound = document.getElementById('incorrectAnswerSound');
        const winRoundSound = document.getElementById('winRoundSound'); // Suara saat 1 pertanyaan benar

        // Music Player Functionality
        const musicPlayer = {
            audio: document.getElementById('backgroundMusic'),
            playPauseBtn: document.getElementById('playPauseBtn'),
            nowPlaying: document.getElementById('nowPlaying'),
            trackInfo: document.getElementById('trackInfo'),
            playlist: <?= $playlist_json ?>,
            currentTrack: <?= $music_settings['current_track'] ?>,
            isPlaying: <?= $music_settings['is_music_on'] ?>,
            volume: <?= $music_settings['volume'] / 100 ?>,
            
            init: function() {
                if (this.playlist.length > 0) {
                    this.loadTrack();
                    if (this.isPlaying) {
                        // Playback might be blocked by browser, try playing on user interaction
                        this.audio.play().catch(e => console.log("Music auto-play blocked, will play on user interaction."));
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
                        // Inform user that playback might need interaction
                        if (error.name === "NotAllowedError") {
                            console.warn("Autoplay was prevented. User interaction required to play music.");
                            // You could show a small icon/message to prompt user to click play
                        }
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
                        is_music_on: this.isPlaying ? 1 : 0, // Convert boolean to 1/0
                        current_track: this.currentTrack,
                        volume: Math.round(this.volume * 100)
                    })
                });
            }
        };

        // --- Game Logic ---

        function initRound(roundNum) {
            gameStarted = false;
            canDragDrop = false; // Disable drag/drop initially
            clearInterval(timerInterval); // Stop any running timer
            gameTime = 0; // Reset time for the new round
            updateGameTimeDisplay();
            gameMessage.classList.add('hidden');
            endRoundControls.classList.add('hidden');
            
            const questions = allQuestionsByRound[roundNum];
            if (!questions || questions.length === 0) {
                questionsContainer.innerHTML = `<div class="text-center text-slate-400">Tidak ada pertanyaan untuk Ronde ${roundNum}.</div>`;
                dragOptionsContainer.innerHTML = '';
                return;
            }

            roundTitle.textContent = `Ronde ${roundNum}`;
            questionsContainer.innerHTML = '';
            dragOptionsContainer.innerHTML = '';

            let allDragOptionsForThisRound = [];
            
            // Loop melalui pertanyaan untuk ronde saat ini dan render
            questions.forEach((q, qIndex) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'bg-slate-700 p-4 rounded-lg border border-slate-600';
                questionDiv.dataset.questionIndex = qIndex; // Add index for easy reference
                questionDiv.innerHTML = `
                    <p class="text-white font-semibold mb-3">Q${qIndex + 1}: ${q.question_text}</p>
                    <div class="drop-targets-wrapper flex flex-wrap gap-2 justify-center" data-question-index="${qIndex}">
                        </div>
                    <div class="question-feedback hidden mt-3 text-center font-bold"></div>
                `;
                const dropTargetsWrapper = questionDiv.querySelector('.drop-targets-wrapper');
                questionsContainer.appendChild(questionDiv);

                // Split correct answer by comma or period and create dynamic drop targets
                const correctParts = q.correct_answer.split(/([.,\s]+)/).filter(part => part.trim() !== '');
                
                correctParts.forEach((part, partIndex) => {
                    const dropTargetSlot = document.createElement('div');
                    dropTargetSlot.className = 'drop-target-slot';
                    dropTargetSlot.dataset.correctPart = part.toLowerCase().trim();
                    // Set a unique ID for each slot to track its content
                    dropTargetSlot.id = `q${qIndex}-slot${partIndex}`; 
                    dropTargetSlot.textContent = (part.trim() === ',' || part.trim() === '.') ? part.trim() : '_____';
                    dropTargetsWrapper.appendChild(dropTargetSlot);
                });

                // Add correct answer parts to the drag options for this specific round
                correctParts.forEach(part => {
                    if (part.trim() !== '' && part.trim() !== ',' && part.trim() !== '.') {
                         allDragOptionsForThisRound.push(part.trim());
                    }
                });
                // Add other drag options for this specific question
                allDragOptionsForThisRound = allDragOptionsForThisRound.concat(q.drag_options);
            });
            
            // Filter unique, non-empty options and shuffle
            const uniqueDragOptions = Array.from(new Set(allDragOptionsForThisRound.filter(opt => opt.trim() !== '')));
            shuffleArray(uniqueDragOptions);

            uniqueDragOptions.forEach(optionText => {
                const dragItem = document.createElement('div');
                dragItem.className = 'drag-item';
                dragItem.textContent = optionText;
                dragItem.setAttribute('draggable', true);
                dragOptionsContainer.appendChild(dragItem);
            });

            addDragAndDropListeners();
            hideGameContent(); // Hide content until game starts
        }

        function addDragAndDropListeners() {
            document.querySelectorAll('.drag-item').forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    if (!canDragDrop) {
                        e.preventDefault(); // Prevent dragging if game not started
                        return;
                    }
                    currentDragItem = item;
                    item.classList.add('opacity-50');
                    e.dataTransfer.setData('text/plain', item.textContent);
                    e.dataTransfer.effectAllowed = 'move';
                });

                item.addEventListener('dragend', () => {
                    if (currentDragItem) {
                        currentDragItem.classList.remove('opacity-50');
                    }
                    currentDragItem = null;
                });
            });

            document.querySelectorAll('.drop-target-slot').forEach(target => {
                target.addEventListener('dragover', (e) => {
                    if (!canDragDrop) return;
                    e.preventDefault();
                    target.classList.add('drag-over');
                    e.dataTransfer.dropEffect = 'move';
                });

                target.addEventListener('dragleave', () => {
                    if (!canDragDrop) return;
                    target.classList.remove('drag-over');
                });

                target.addEventListener('drop', (e) => {
                    if (!canDragDrop) return;
                    e.preventDefault();
                    target.classList.remove('drag-over');

                    if (currentDragItem && !target.querySelector('.dropped-item')) {
                        // If there's an item already in this slot, return it to the options container
                        const existingDroppedItem = target.querySelector('.dropped-item');
                        if (existingDroppedItem) {
                            // Re-enable draggable and reset style
                            existingDroppedItem.classList.remove('dropped-item');
                            existingDroppedItem.classList.add('drag-item');
                            existingDroppedItem.setAttribute('draggable', true);
                            dragOptionsContainer.appendChild(existingDroppedItem);
                        }

                        // Remove the current drag item from its original parent
                        if (currentDragItem.parentNode) {
                            currentDragItem.parentNode.removeChild(currentDragItem);
                        }

                        currentDragItem.classList.remove('drag-item', 'opacity-50');
                        currentDragItem.classList.add('dropped-item');
                        currentDragItem.removeAttribute('draggable');

                        target.innerHTML = '';
                        target.appendChild(currentDragItem);
                        
                        // After a drop, check the specific question
                        checkQuestionCompletion(target.closest('[data-question-index]'));
                    }
                });
            });
        }

        function checkQuestionCompletion(questionDivElement) {
            const questionIndex = questionDivElement.dataset.questionIndex;
            const dropTargetsWrapper = questionDivElement.querySelector('.drop-targets-wrapper');
            const questionFeedback = questionDivElement.querySelector('.question-feedback');
            const currentQuestionData = allQuestionsByRound[currentRound][questionIndex];
            const correctPartsForQuestion = currentQuestionData.correct_answer.split(/([.,\s]+)/).filter(part => part.trim() !== '');

            let allPartsCorrect = true;
            let allSlotsFilled = true;

            dropTargetsWrapper.querySelectorAll('.drop-target-slot').forEach((slot, slotIndex) => {
                slot.classList.remove('correct', 'incorrect'); // Reset colors
                const expectedPart = correctPartsForQuestion[slotIndex].toLowerCase().trim();
                const droppedItem = slot.querySelector('.dropped-item');

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
                    allPartsCorrect = false; // If not filled, it's not correct
                }
            });

            if (allSlotsFilled) { // Only provide feedback if all slots are filled for this question
                if (allPartsCorrect) {
                    questionFeedback.textContent = 'Jawaban Anda Benar!';
                    questionFeedback.className = 'question-feedback mt-3 text-center font-bold message-success';
                    correctAnswerSound.play();
                } else {
                    questionFeedback.textContent = 'Jawaban Salah. Coba lagi.';
                    questionFeedback.className = 'question-feedback mt-3 text-center font-bold message-error';
                    incorrectAnswerSound.play();
                }
                questionFeedback.classList.remove('hidden');
            } else {
                questionFeedback.classList.add('hidden'); // Hide feedback if not all filled
            }

            // After checking a question, check if all questions in the round are correct
            checkRoundCompletion();
        }

        function checkRoundCompletion() {
            const currentRoundQuestionsCount = allQuestionsByRound[currentRound].length;
            let correctlyAnsweredQuestions = 0;

            document.querySelectorAll('#questions-container > div').forEach(questionDiv => {
                const questionFeedback = questionDiv.querySelector('.question-feedback');
                if (questionFeedback && questionFeedback.classList.contains('message-success')) {
                    correctlyAnsweredQuestions++;
                }
            });

            if (correctlyAnsweredQuestions === currentRoundQuestionsCount && currentRoundQuestionsCount > 0) {
                // All questions in the current round are correct
                gameStarted = false; // Stop game for this round
                canDragDrop = false; // Disable drag/drop
                clearInterval(timerInterval); // Stop timer
                winRoundSound.play(); // Play win sound for the round

                gameMessage.textContent = 'Selamat! Semua jawaban di ronde ini benar!';
                gameMessage.className = 'action-message message-success';
                gameMessage.classList.remove('hidden');
                
                endRoundControls.classList.remove('hidden'); // Show "Selesai Ronde Ini" button
            }
        }

        function startGameCountdown() {
            currentTeamName = teamNameInput.value.trim();
            if (!currentTeamName) {
                alert("Silakan masukkan nama tim terlebih dahulu!");
                return;
            }

            teamInputSection.classList.add('hidden'); // Hide team input
            playBtn.classList.add('hidden'); // Hide play button
            countdownEl.classList.remove('hidden'); // Show countdown
            roundSelect.disabled = true; // Disable round select during game

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
                    // Ensure countdown sound finishes before game starts
                    countdownSound.onended = () => {
                        startGame();
                    };
                    // Fallback if onended doesn't fire immediately (e.g., if sound is very short)
                    if (countdownSound.duration === 0 || countdownSound.ended) {
                        startGame();
                    }
                }
            }, 1000);
        }

        function startGame() {
            gameContent.classList.remove('hidden'); // Show game content
            gameStarted = true;
            canDragDrop = true; // Enable drag/drop
            startTimer(); // Start game timer
            musicPlayer.play(); // Attempt to play background music on user interaction
        }

        function startTimer() {
            clearInterval(timerInterval);
            gameTime = 0;
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
            teamInputSection.classList.remove('hidden'); // Show team input again
            playBtn.classList.remove('hidden'); // Show play button
            roundSelect.disabled = false; // Enable round select
            teamNameInput.value = ''; // Clear team name
            currentTeamName = ''; // Reset current team name
            gameStarted = false;
            canDragDrop = false;
            clearInterval(timerInterval);
            gameTime = 0;
            updateGameTimeDisplay();
            initRound(allRounds[0]); // Re-initialize with the first round
        }
        
        // Event Listeners
        playBtn.addEventListener('click', startGameCountdown);
        endGameBtn.addEventListener('click', () => {
            // Logic for "Selesai Ronde Ini" button
            // If there are more rounds, prompt for next round
            const currentRoundIndex = allRounds.indexOf(currentRound);
            if (currentRoundIndex < allRrounds.length - 1) {
                // Go to next round
                currentRound = allRounds[currentRoundIndex + 1];
                roundSelect.value = currentRound; // Update dropdown
                initRound(currentRound); // Load next round
                // Auto start countdown for next round
                startGameCountdown();
            } else {
                // All rounds completed
                showEndGameModal();
            }
        });
        roundSelect.addEventListener('change', function() {
            currentRound = parseInt(this.value);
            initRound(currentRound);
        });
        playAgainBtn.addEventListener('click', resetGame);
        viewResultsBtn.addEventListener('click', () => {
            // Redirect to dragdrop_result.php
            window.location.href = `dragdrop_result.php?sessionid=${sessionId}`;
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            // Populate round select dropdown
            roundSelect.innerHTML = ''; // Clear existing options
            allRounds.forEach(round => {
                const option = document.createElement('option');
                option.value = round;
                option.textContent = `Ronde ${round}`;
                roundSelect.appendChild(option);
            });
            // Set initial selected round
            if (allRounds.length > 0) {
                 roundSelect.value = allRounds[0];
                 currentRound = allRounds[0];
            }

            initRound(currentRound);
            musicPlayer.init();
        });

        function showEndGameModal() {
            finalTimeDisplay.textContent = gameTimeEl.textContent; // Display the total time for the last round or overall
            finalTeamName.textContent = currentTeamName;
            endGameModal.classList.remove('hidden');
        }

        function hideGameContent() {
            gameContent.classList.add('hidden'); // Sembunyikan bagian game
            dragOptionsContainer.innerHTML = ''; // Kosongkan drag options
            questionsContainer.innerHTML = ''; // Kosongkan pertanyaan
            gameMessage.classList.add('hidden'); // Sembunyikan pesan
            endRoundControls.classList.add('hidden'); // Sembunyikan tombol selesai ronde
        }
    </script>
</body>
</html>