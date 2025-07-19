<?php
session_start();
require_once 'db.php'; // Pastikan jalur ke db.php sudah benar

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Validate session access
if (!isset($_GET['session'])) {
    header("Location: dashboard.php?page=all_games");
    exit;
}

$sessionId = $_GET['session'];
$userId = $_SESSION['user_id'];

// Get survival session data
$sessionData = $conn->query("SELECT * FROM survival_sessions WHERE id = $sessionId")->fetch_assoc();
if (!$sessionData) {
    $_SESSION['error'] = "Session not found.";
    header("Location: dashboard.php?page=all_games");
    exit;
}

// Get all questions for this session, regardless of round initially, so JS can filter
$allQuestionsQuery = $conn->query("
    SELECT id, question_text, question_type,
           option_a, option_b, option_c, option_d,
           correct_answer, base_points AS points, time_limit, round_number, question_order
    FROM survival_questions
    WHERE session_id = $sessionId
    ORDER BY round_number ASC, question_order ASC
");
$allQuestions = [];
if ($allQuestionsQuery) {
    $allQuestions = $allQuestionsQuery->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching all questions: " . $conn->error);
}

// Get all rounds available in this session (for dropdown)
$roundsQuery = $conn->query("
    SELECT DISTINCT round_number
    FROM survival_questions
    WHERE session_id = $sessionId
    ORDER BY round_number ASC
");
$rounds = [];
if ($roundsQuery) {
    $rounds = $roundsQuery->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error fetching available rounds for dropdown: " . $conn->error);
}

// Get music settings (ensure it's always initialized)
$music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0]; // Default values
$music_settings_query = $conn->query("SELECT * FROM music_settings WHERE user_id = $userId");
if ($music_settings_query && $music_settings_query->num_rows > 0) {
    $fetched_settings = $music_settings_query->fetch_assoc();
    if ($fetched_settings !== null) { // Ensure fetched_settings is not null
        $music_settings = array_merge($music_settings, $fetched_settings);
    }
} else {
    // If no settings found for user, insert default
    $insert_default_settings = $conn->prepare("INSERT INTO music_settings (user_id, is_music_on, volume, current_track) VALUES (?, ?, ?, ?)");
    if ($insert_default_settings) {
        $insert_default_settings->bind_param("iiii", $userId, $music_settings['is_music_on'], $music_settings['volume'], $music_settings['current_track']);
        $insert_default_settings->execute();
        $insert_default_settings->close();
    } else {
        error_log("Error preparing default music settings insert: " . $conn->error);
    }
}

// Get active playlist
$playlist = []; // Initialize to empty array to prevent TypeError in JSON encode or count()
$playlistQuery = $conn->query("SELECT id, file_path, display_name FROM background_music WHERE is_active = 1 ORDER BY id ASC");
if ($playlistQuery) {
    $fetched_playlist = $playlistQuery->fetch_all(MYSQLI_ASSOC);
    if ($fetched_playlist !== null) { // Ensure fetched_playlist is not null
        $playlist = $fetched_playlist;
    }
} else {
    error_log("Error fetching playlist: " . $conn->error);
}

$totalTracks = is_array($playlist) ? count($playlist) : 0; // Safely count tracks

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survival Game: <?= htmlspecialchars($sessionData['session_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" href="logo.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        body {
            background: linear-gradient(to right, #2a005f, #4a007f, #7b0099, #a300b3, #c700cb);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .music-wave {
            width: 3px;
            height: 12px;
            background: white;
            margin: 0 2px;
            border-radius: 3px;
            animation: wave 1.2s infinite ease-in-out;
        }
        .music-wave:nth-child(2) { animation-delay: 0.3s; height: 16px; }
        .music-wave:nth-child(3) { animation-delay: 0.6s; height: 20px; }
        .music-wave:nth-child(4) { animation-delay: 0.9s; height: 16px; }
        .music-wave:nth-child(5) { animation-delay: 1.2s; height: 12px; }
        @keyframes wave {
            0%, 100% { transform: scaleY(0.7); }
            50% { transform: scaleY(1.3); }
            100% { transform: scaleY(0.7); }
        }
        .option-button {
            transition: all 0.2s ease-in-out;
        }
        .option-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .option-button.correct {
            background-color: #10B981 !important; /* Tailwind green-500 */
        }
        .option-button.wrong {
            background-color: #EF4444 !important; /* Tailwind red-500 */
        }
        .option-button.disabled {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Start Screen Styles */
        .start-screen-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
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

        /* Timer Progress Bar Styles */
        .timer-progress-container {
            width: 100%;
            height: 10px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .timer-progress-bar {
            height: 100%;
            width: 100%; /* Initial width */
            background-color: #4CAF50; /* Green */
            transition: width 1s linear, background-color 0.5s ease-in-out;
            border-radius: 5px;
        }
        .timer-progress-bar.warning {
            background-color: #FFC107; /* Yellow */
        }
        .timer-progress-bar.danger {
            background-color: #F44336; /* Red */
        }

        .true-false-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .true-false-btn {
            min-width: 120px;
            padding: 1rem 2rem;
            font-size: 1.2rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 to-purple-900 bg-[length:400%_400%] animate-[gradientBG_15s_ease_infinite] text-white flex flex-col items-center justify-center">

<div id="startScreenOverlay" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50">
    <div class="glass-card p-8 rounded-lg shadow-xl text-center max-w-sm w-full">
        <h2 class="text-3xl font-bold mb-6">Start Survival Game</h2>
        <div class="mb-4">
            <label for="playerNameInput" class="block text-white/80 text-sm font-bold mb-2 text-left">Player Name:</label>
            <input type="text" id="playerNameInput" class="w-full py-3 px-4 rounded-lg bg-white/80 text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter your name">
        </div>

        <div class="mb-6">
            <label for="roundSelect" class="block text-white/80 text-sm font-bold mb-2 text-left">Select Round:</label>
            <select id="roundSelect" class="w-full py-3 px-4 rounded-lg bg-white/80 text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if (!empty($rounds)): ?>
                    <?php foreach ($rounds as $round): ?>
                        <option value="<?= $round['round_number'] ?>">Round <?= $round['round_number'] ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled selected>No rounds available</option>
                <?php endif; ?>
            </select>
        </div>

        <button id="startButton" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-all w-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" disabled>
            <i class="fas fa-play mr-2"></i> Start Game
        </button>
        <div id="countdown" class="my-6 text-white text-5xl font-bold hidden">3</div>
    </div>
</div>

<div id="gameContent" class="container mx-auto px-4 py-8 max-w-2xl hidden">
    <div class="text-center mb-6">
        <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-300">
            Survival Challenge
        </h1>
        <p class="text-lg text-white/80 mt-2">Round <span id="currentRoundDisplay">1</span> - <?= htmlspecialchars($sessionData['session_name']) ?></p>
    </div>

    <div class="glass-card p-6 rounded-xl shadow-lg mb-6 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex flex-col items-center">
            <div class="text-white/80 text-sm">Player:</div>
            <div id="playerNameDisplay" class="text-xl font-bold"></div>
        </div>
        <div class="flex flex-col items-center">
            <div class="text-white/80 text-sm">Question:</div>
            <div id="questionCounter" class="text-xl font-bold">1/0</div>
        </div>
        <div class="flex flex-col items-center">
            <div class="text-white/80 text-sm">Time Left:</div>
            <div id="timeLeft" class="text-xl font-bold">00:00</div>
        </div>
        <div class="flex flex-col items-center">
            <div class="text-white/80 text-sm">Score:</div>
            <div id="currentScore" class="text-xl font-bold">0</div>
        </div>
    </div>

    <div class="timer-progress-container">
        <div id="timerProgressBar" class="timer-progress-bar"></div>
    </div>

    <div class="glass-card p-8 rounded-xl shadow-lg mb-6">
        <h2 id="questionText" class="text-2xl font-semibold mb-6 text-center"></h2>
        <div id="optionsContainer" class="grid grid-cols-1 gap-4">
            </div>
        <div id="trueFalseContainer" class="true-false-container hidden">
            <button id="trueButton" class="true-false-btn bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                True
            </button>
            <button id="falseButton" class="true-false-btn bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition-all">
                False
            </button>
        </div>
    </div>

    <div class="text-center mt-6">
        <p id="feedbackMessage" class="text-lg font-semibold mb-4 hidden"></p>
    </div>
</div>

<div class="fixed bottom-6 right-6 z-50">
    <div id="musicPlayer" class="glass-card rounded-full px-4 py-2 flex items-center gap-3 shadow-xl hover:shadow-2xl transition-all cursor-pointer group">
        <button id="playPauseBtn" class="w-12 h-12 rounded-full flex items-center justify-center bg-purple-500/20 hover:bg-purple-500/30 transition-all relative overflow-hidden focus:outline-none">
            <div id="musicWave" class="flex items-end h-6 absolute" style="display: none;">
                <div class="music-wave"></div>
                <div class="music-wave"></div>
                <div class="music-wave"></div>
                <div class="music-wave"></div>
                <div class="music-wave"></div>
            </div>
            <i id="playIcon" class="fas fa-play absolute"></i>
            <i id="pauseIcon" class="fas fa-pause absolute" style="display: none;"></i>
        </button>
        <div class="text-sm pr-2 max-w-xs overflow-hidden">
            <div id="nowPlaying" class="font-medium truncate">
                <?php
                // FIX: Ensure playlist is an array before using it
                $currentTrackIndex = $music_settings['current_track'] ?? 0;
                if (!empty($playlist) && isset($playlist[$currentTrackIndex])) {
                    echo htmlspecialchars($playlist[$currentTrackIndex]['display_name']);
                } else {
                    echo 'No track selected';
                }
                ?>
            </div>
            <div id="trackInfo" class="text-xs opacity-70 truncate">
                <?php
                // FIX: Ensure playlist is an array before using count()
                $totalTracks = is_array($playlist) ? count($playlist) : 0;
                $trackPosition = ($totalTracks > 0 && isset($music_settings['current_track'])) ? ($music_settings['current_track'] + 1) : 0;
                echo htmlspecialchars("$trackPosition/$totalTracks • ");
                ?>
                <span id="musicTime">0:00</span>
            </div>
        </div>
    </div>
</div>

<audio id="backgroundMusic"></audio>
<audio id="countdownSound" src="sounds/countdown.mp3" preload="auto"></audio>

<script>
    // Game state variables
    const allQuestionsData = <?= json_encode($allQuestions) ?>; // All questions from PHP
    let questions = []; // Questions for the selected round
    const sessionId = <?= $sessionId ?>;
    const userId = <?= $userId ?>;
    let currentQuestionIndex = 0;
    let score = 0;
    let timeLeft = 0;
    let timerInterval;
    let isGameOver = false;
    let playerName = '';
    let selectedRound = null; // Will be set from dropdown
    let questionStartTime; // To track time for points calculation
    let currentQuestion;

    // DOM elements
    const startScreenOverlay = document.getElementById('startScreenOverlay');
    const playerNameInput = document.getElementById('playerNameInput');
    const startButton = document.getElementById('startButton');
    const countdownEl = document.getElementById('countdown');
    const gameContent = document.getElementById('gameContent');
    const playerNameDisplay = document.getElementById('playerNameDisplay');
    const questionCounterEl = document.getElementById('questionCounter');
    const currentRoundDisplay = document.getElementById('currentRoundDisplay');
    const timeLeftEl = document.getElementById('timeLeft');
    const currentScoreEl = document.getElementById('currentScore');
    const questionTextEl = document.getElementById('questionText');
    const optionsContainer = document.getElementById('optionsContainer');
    const trueFalseContainer = document.getElementById('trueFalseContainer');
    const trueButton = document.getElementById('trueButton');
    const falseButton = document.getElementById('falseButton');
    const feedbackMessageEl = document.getElementById('feedbackMessage');
    const timerProgressBar = document.getElementById('timerProgressBar');
    const roundSelect = document.getElementById('roundSelect');

    // Audio elements (only countdownSound remains)
    const countdownSound = document.getElementById('countdownSound');

    // Music Player Functionality
    const musicPlayer = {
        audio: document.getElementById('backgroundMusic'),
        playPauseBtn: document.getElementById('playPauseBtn'),
        nowPlaying: document.getElementById('nowPlaying'),
        trackInfo: document.getElementById('trackInfo'),
        musicWave: document.getElementById('musicWave'),
        playIcon: document.getElementById('playIcon'),
        pauseIcon: document.getElementById('pauseIcon'),
        playlist: <?= json_encode($playlist) ?>,
        currentTrack: <?= $music_settings['current_track'] ?? 0 ?>,
        isMusicOn: <?= $music_settings['is_music_on'] ? 1 : 0 ?>,

        init() {
            if (this.playlist.length > 0) {
                this.loadTrack();
                if (this.isMusicOn) {
                    this.playIcon.style.display = 'none';
                    this.pauseIcon.style.display = 'block';
                    this.musicWave.style.display = 'flex';
                } else {
                    this.playIcon.style.display = 'block';
                    this.pauseIcon.style.display = 'none';
                    this.musicWave.style.display = 'none';
                }
            } else {
                this.playPauseBtn.disabled = true;
                this.nowPlaying.textContent = 'No track available';
                this.trackInfo.textContent = '0/0 • 0:00';
            }

            this.playPauseBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.togglePlay();
            });

            this.audio.addEventListener('ended', () => this.nextTrack());
            this.audio.addEventListener('timeupdate', () => this.updateMusicTime());
        },

        loadTrack() {
            if (this.playlist.length === 0) return;
            const track = this.playlist[this.currentTrack];
            this.audio.src = track.file_path;
            this.nowPlaying.textContent = track.display_name;
            this.trackInfo.innerHTML = `${this.currentTrack + 1}/${this.playlist.length} • <span id="musicTime">0:00</span>`;
            this.audio.load();
        },

        play() {
            if (this.playlist.length === 0) return;
            this.audio.play()
                .then(() => {
                    this.isMusicOn = true;
                    this.playIcon.style.display = 'none';
                    this.pauseIcon.style.display = 'block';
                    this.musicWave.style.display = 'flex';
                    this.updateSettings();
                })
                .catch(error => {
                    console.warn("Autoplay was prevented:", error);
                    this.isMusicOn = false; // Reset to false if autoplay prevented
                    this.playIcon.style.display = 'block';
                    this.pauseIcon.style.display = 'none';
                    this.musicWave.style.display = 'none';
                });
        },

        pause() {
            this.audio.pause();
            this.isMusicOn = false;
            this.playIcon.style.display = 'block';
            this.pauseIcon.style.display = 'none';
            this.musicWave.style.display = 'none';
            this.updateSettings();
        },

        togglePlay() {
            this.isMusicOn ? this.pause() : this.play();
        },

        nextTrack() {
            this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
            this.loadTrack();
            if (this.isMusicOn) this.play();
        },

        stop() {
            this.audio.pause();
            this.audio.currentTime = 0;
            this.isMusicOn = false;
            this.playIcon.style.display = 'block';
            this.pauseIcon.style.display = 'none';
            this.musicWave.style.display = 'none';
            this.updateSettings();
        },

        updateMusicTime() {
            const currentTime = this.audio.currentTime;
            const duration = this.audio.duration;
            if (!isNaN(duration)) {
                const minutes = Math.floor(currentTime / 60);
                const seconds = Math.floor(currentTime % 60);
                document.getElementById('musicTime').textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            }
        },

        updateSettings: function() {
            fetch('update_music_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    is_music_on: this.isMusicOn ? 1 : 0,
                    current_track: this.currentTrack,
                    volume: 50 // Assuming volume is fixed at 50 for now
                })
            })
            .then(response => {
                if (!response.ok) {
                    console.error('Failed to update music settings on server.');
                }
            })
            .catch(error => {
                console.error('Error updating music settings:', error);
            });
        }
    };


    // --- Game Logic ---

    // Initial check for start button state
    updateStartButton();

    roundSelect.addEventListener('change', function() {
        selectedRound = parseInt(this.value);
        // Filter questions based on selected round
        questions = allQuestionsData.filter(q => parseInt(q.round_number) === selectedRound);
        // Sort questions by question_order
        questions.sort((a, b) => parseInt(a.question_order) - parseInt(b.question_order));
        updateStartButton();
        // Update question counter display for the selected round's total questions
        questionCounterEl.textContent = `1/${questions.length}`;
    });

    playerNameInput.addEventListener('input', updateStartButton);

    function updateStartButton() {
        // Ensure a round is selected (value is not empty string and is a number)
        const isRoundSelected = roundSelect.value !== "" && !isNaN(parseInt(roundSelect.value));
        // Check if there are questions for the currently selected round
        const hasQuestionsForRound = questions.length > 0;

        startButton.disabled = playerNameInput.value.trim() === '' || !isRoundSelected || !hasQuestionsForRound;

        // If no rounds are available in the dropdown, disable the button and show a message
        if (roundSelect.options.length === 0 || roundSelect.options[0].value === "") { // Check if 'No rounds available' is the only option
            startButton.disabled = true;
            // Optionally, you could show a Swal.fire here on page load if no rounds exist
            // but for now, the `disabled` state should suffice.
        }
    }


    startButton.addEventListener('click', () => {
        // Store player name immediately for survival_results.php
        playerName = playerNameInput.value.trim();
        sessionStorage.setItem('player_name', playerName);
        sessionStorage.setItem('selected_round', selectedRound); // Store the selected round for results page

        if (questions.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Questions Found!',
                text: `There are no questions available for Round ${selectedRound}. Please select another round or add questions.`,
                confirmButtonText: 'OK'
            });
            startButton.disabled = false; // Re-enable button
            return;
        }

        startGameCountdown();
    });

    // Event listeners for True/False buttons
    trueButton.addEventListener('click', () => checkAnswer('true'));
    falseButton.addEventListener('click', () => checkAnswer('false'));

    function startGameCountdown() {
        playerNameDisplay.textContent = playerName;
        currentRoundDisplay.textContent = selectedRound;

        startButton.disabled = true;
        countdownEl.style.display = 'block';

        let count = 3;
        countdownEl.textContent = count;
        countdownSound.currentTime = 0; // Ensure sound starts from beginning
        countdownSound.play();

        const countdownInterval = setInterval(() => {
            count--;
            countdownEl.textContent = count;

            if (count > 0) {
                countdownSound.currentTime = 0; // Play sound for each count
                countdownSound.play();
            } else {
                clearInterval(countdownInterval); // Stop the interval first
                countdownSound.pause(); // Stop the sound immediately
                countdownSound.currentTime = 0; // Reset sound to beginning
                countdownEl.style.display = 'none'; // Hide countdown element

                startScreenOverlay.classList.add('hidden'); // Hide start screen
                gameContent.classList.remove('hidden'); // Show game content

                // Start background music if enabled
                if (musicPlayer.isMusicOn) {
                    musicPlayer.play();
                }

                // Load the first question
                questionStartTime = Date.now(); // Record start time for first question
                loadQuestion();
            }
        }, 1000);
    }

    function loadQuestion() {
        if (isGameOver) return; // Prevent loading if game is over

        if (currentQuestionIndex >= questions.length) {
            endGame(true); // All questions for the selected round are completed
            return;
        }

        currentQuestion = questions[currentQuestionIndex];
        questionTextEl.textContent = currentQuestion.question_text;
        questionCounterEl.textContent = `${currentQuestionIndex + 1}/${questions.length}`;
        currentRoundDisplay.textContent = currentQuestion.round_number; // Ensure round display updates if rounds change

        // Reset timer
        timeLeft = currentQuestion.time_limit;
        updateTimeDisplay();
        updateProgressBar(timeLeft, currentQuestion.time_limit);
        timeLeftEl.classList.remove('text-red-500', 'animate-pulse', 'text-yellow-500'); // Reset colors
        timerProgressBar.classList.remove('warning', 'danger'); // Reset progress bar colors
        timerProgressBar.style.backgroundColor = ''; // Reset to default green

        clearInterval(timerInterval);
        questionStartTime = Date.now(); // Record start time for this question
        timerInterval = setInterval(updateTimer, 1000);

        // Clear previous options and hide true/false container
        optionsContainer.innerHTML = '';
        trueFalseContainer.classList.add('hidden');
        enableOptions(); // Ensure options are enabled for the new question

        if (currentQuestion.question_type === 'true_false') {
            trueFalseContainer.classList.remove('hidden');
        } else { // Multiple choice questions
            const options = [
                { label: 'A', value: currentQuestion.option_a },
                { label: 'B', value: currentQuestion.option_b },
                { label: 'C', value: currentQuestion.option_c },
                { label: 'D', value: currentQuestion.option_d }
            ].filter(opt => opt.value !== null && opt.value.trim() !== ''); // Filter out empty options

            // NO SHUFFLE for options A, B, C, D as requested, they maintain order based on opt.label
            options.forEach(opt => {
                const button = document.createElement('button');
                button.textContent = `${opt.label}. ${opt.value}`;
                button.className = 'option-button bg-white/10 hover:bg-white/20 text-white font-semibold py-3 px-4 rounded-lg transition-all focus:outline-none text-left';
                button.setAttribute('data-value', opt.value); // Store the actual value
                button.addEventListener('click', () => checkAnswer(opt.value));
                optionsContainer.appendChild(button);
            });
        }

        feedbackMessageEl.classList.add('hidden');
    }

    function updateTimer() {
        timeLeft--;
        updateTimeDisplay();
        updateProgressBar(timeLeft, currentQuestion.time_limit);

        if (timeLeft <= 5 && timeLeft > 0) {
            timeLeftEl.classList.add('text-red-500', 'animate-pulse');
            timerProgressBar.classList.add('danger');
        } else if (timeLeft <= 10 && timeLeft > 5) {
            timeLeftEl.classList.add('text-yellow-500');
            timerProgressBar.classList.add('warning');
        } else {
            timeLeftEl.classList.remove('text-red-500', 'animate-pulse', 'text-yellow-500'); // Remove if time passes thresholds
            timerProgressBar.classList.remove('warning', 'danger');
            timerProgressBar.style.backgroundColor = ''; // Reset to default green
        }

        if (timeLeft <= 0) {
            handleTimeExpired();
        }
    }

    function handleTimeExpired() {
        clearInterval(timerInterval);
        disableOptions();
        const timeTakenMs = currentQuestion.time_limit * 1000; // Full time limit used in ms

        recordAnswer('', false, 0, timeTakenMs, currentQuestion.round_number); // Empty answer, 0 points

        // No wrongSound.play() here as per request

        markCorrectAnswer(currentQuestion.correct_answer); // Show correct answer after timeout

        Swal.fire({
            icon: 'error',
            title: 'WAKTU HABIS!',
            text: 'Anda tidak menjawab dan gugur!',
            showConfirmButton: false,
            timer: 3000, // Show for 3 seconds
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading();
            },
            willClose: () => {
                isGameOver = true;
                window.location.href = `survival_results.php?session=${sessionId}&round=${selectedRound}`;
            }
        });
    }


    function updateTimeDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timeLeftEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    function updateProgressBar(currentTime, totalTime) {
        const progress = (currentTime / totalTime) * 100;
        timerProgressBar.style.width = `${progress}%`;
    }

    function calculatePoints(basePoints, timeLimit, timeTakenMs) {
        const timeTakenSec = timeTakenMs / 1000;
        if (timeTakenSec <= 0) return basePoints; // Answered instantly
        if (timeTakenSec >= timeLimit) return 0; // Took too long or timed out

        // Kahoot-like point calculation: faster answers get more points
        // Scale factor: 0.5 (min points) + 0.5 * (1 - time_taken / time_limit)
        const timeFactor = 1 - (timeTakenSec / timeLimit);
        let calculatedPoints = basePoints * (0.5 + 0.5 * timeFactor); // Ensures min 50% points for correct answer
        return Math.max(0, Math.round(calculatedPoints)); // Ensure non-negative and round
    }

    function checkAnswer(selectedOptionValue) {
        clearInterval(timerInterval);
        disableOptions();

        const responseTimeMs = Date.now() - questionStartTime;
        const isCorrect = (selectedOptionValue === currentQuestion.correct_answer);
        let pointsEarned = 0;

        if (isCorrect) {
            pointsEarned = calculatePoints(parseInt(currentQuestion.points), currentQuestion.time_limit, responseTimeMs);
            score += pointsEarned;
            currentScoreEl.textContent = score;

            feedbackMessageEl.textContent = `BENAR! + ${pointsEarned} poin.`;
            feedbackMessageEl.classList.remove('hidden');
            // No correctSound.play() here as per request
            markCorrectAnswer(currentQuestion.correct_answer);

            recordAnswer(selectedOptionValue, true, pointsEarned, responseTimeMs, currentQuestion.round_number);

            if (currentQuestionIndex < questions.length - 1) {
                Swal.fire({
                    icon: 'success',
                    title: 'BENAR!',
                    html: `Jawaban Anda benar! Anda mendapatkan <strong>${pointsEarned}</strong> poin.<br>Lanjut ke soal berikutnya...`,
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    willClose: () => {
                        currentQuestionIndex++;
                        loadQuestion();
                    }
                });
            } else {
                endGame(true); // All questions for the selected round answered correctly
            }
        } else {
            feedbackMessageEl.textContent = `SALAH! Jawaban yang benar adalah: ${currentQuestion.correct_answer}`;
            feedbackMessageEl.classList.remove('hidden');
            // No wrongSound.play() here as per request
            markCorrectAnswer(currentQuestion.correct_answer); // Mark correct one
            markWrongAnswer(selectedOptionValue); // Mark user's wrong choice

            recordAnswer(selectedOptionValue, false, 0, responseTimeMs, currentQuestion.round_number); // 0 points for wrong answer

            Swal.fire({
                icon: 'error',
                title: 'JAWABAN ANDA SALAH!',
                text: 'Anda gugur!',
                showConfirmButton: false,
                timer: 3000, // Show for 3 seconds
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                },
                willClose: () => {
                    isGameOver = true;
                    window.location.href = `survival_results.php?session=${sessionId}&round=${selectedRound}`;
                }
            });
        }
    }

    function markCorrectAnswer(correctAnswerValue) {
        if (currentQuestion.question_type === 'true_false') {
            if (correctAnswerValue === 'true') {
                trueButton.classList.add('correct');
            } else {
                falseButton.classList.add('correct');
            }
        } else {
            Array.from(optionsContainer.children).forEach(button => {
                if (button.getAttribute('data-value') === correctAnswerValue) {
                    button.classList.add('correct');
                }
            });
        }
    }

    function markWrongAnswer(selectedOptionValue) {
        if (currentQuestion.question_type === 'true_false') {
            if (selectedOptionValue === 'true') {
                trueButton.classList.add('wrong');
            } else {
                falseButton.classList.add('wrong');
            }
        } else {
            Array.from(optionsContainer.children).forEach(button => {
                if (button.getAttribute('data-value') === selectedOptionValue) {
                    button.classList.add('wrong');
                }
            });
        }
    }

    function disableOptions() {
        if (currentQuestion.question_type === 'true_false') {
            trueButton.disabled = true;
            falseButton.disabled = true;
            trueButton.classList.add('disabled');
            falseButton.classList.add('disabled');
        } else {
            Array.from(optionsContainer.children).forEach(button => {
                button.disabled = true;
                button.classList.add('disabled');
            });
        }
    }

    function enableOptions() {
        if (currentQuestion.question_type === 'true_false') {
            trueButton.disabled = false;
            falseButton.disabled = false;
            trueButton.classList.remove('disabled', 'correct', 'wrong');
            falseButton.classList.remove('disabled', 'correct', 'wrong');
        } else {
            Array.from(optionsContainer.children).forEach(button => {
                button.disabled = false;
                button.classList.remove('disabled', 'correct', 'wrong');
            });
        }
    }

    function recordAnswer(userAnswer, isCorrect, pointsEarned, timeTakenMs, roundNumber) {
        fetch('save_survival_answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: sessionId,
                question_id: currentQuestion.id,
                user_id: userId,
                player_name: playerName,
                user_answer: userAnswer,
                is_correct: isCorrect,
                points_earned: pointsEarned,
                time_taken: timeTakenMs / 1000, // Convert ms to seconds
                round_number: roundNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error("Failed to save answer:", data.message);
            }
        })
        .catch(error => {
            console.error('Error saving answer:', error);
        });
    }

    function endGame(isCompleted) {
        if (isGameOver) return; // Prevent multiple calls
        isGameOver = true;
        clearInterval(timerInterval);
        musicPlayer.stop(); // Stop background music immediately
        // No gameOverSound.play() here as per request

        sessionStorage.setItem('final_score', score);
        sessionStorage.setItem('is_completed_game', isCompleted);

        Swal.fire({
            icon: isCompleted ? 'success' : 'info',
            title: isCompleted ? 'GAME SELESAI!' : 'GAME OVER!',
            text: isCompleted
                ? `Selamat, ${playerName}! Anda berhasil menyelesaikan semua soal di Ronde ${selectedRound} dengan skor akhir: ${score}!`
                : `Maaf, ${playerName}! Game berakhir. Skor akhir Anda: ${score}.`,
            showConfirmButton: true,
            confirmButtonText: 'Lihat Hasil',
            allowOutsideClick: false,
            didClose: () => {
                window.location.href = `survival_results.php?session=${sessionId}&round=${selectedRound}`;
            }
        });
    }

    // Initialize music player
    musicPlayer.init();

    // Set initial selected round and load questions for it
    if (roundSelect.options.length > 0 && roundSelect.options[0].value !== "") {
        selectedRound = parseInt(roundSelect.value);
        questions = allQuestionsData.filter(q => parseInt(q.round_number) === selectedRound);
        questions.sort((a, b) => parseInt(a.question_order) - parseInt(b.question_order));
        questionCounterEl.textContent = `1/${questions.length}`;
    } else {
        startButton.disabled = true;
        Swal.fire({
            icon: 'info',
            title: 'No Rounds Available',
            text: 'There are no quiz rounds configured for this session yet. Please add questions to start a game.',
            allowOutsideClick: false,
            showConfirmButton: true,
            confirmButtonText: 'OK'
        });
    }
</script>
</body>
</html>