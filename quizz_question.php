<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quiz_id === 0) {
    die("ID kuis tidak ditemukan.");
}

// Cek kepemilikan kuis
$stmt_check_quiz = $conn->prepare("SELECT name FROM quizz WHERE id = ? AND user_id = ?");
$stmt_check_quiz->bind_param("ii", $quiz_id, $user_id);
$stmt_check_quiz->execute();
$result_check_quiz = $stmt_check_quiz->get_result();
if ($result_check_quiz->num_rows == 0) {
    die("Kuis tidak ditemukan atau bukan milik Anda.");
}
$quiz_name_row = $result_check_quiz->fetch_assoc();
$quiz_name = htmlspecialchars($quiz_name_row['name']);
$stmt_check_quiz->close();

// Mengambil HANYA PERTANYAAN PERTAMA dari tabel 'question_quizz'
// Menambahkan time_limit dalam SELECT
$stmt_question = $conn->prepare("SELECT id, question_text, answer, question_image_url, answer_image_url, time_limit FROM question_quizz WHERE quiz_id = ? AND user_id = ? ORDER BY id ASC LIMIT 1");
$stmt_question->bind_param("ii", $quiz_id, $user_id);
$stmt_question->execute();
$first_question = $stmt_question->get_result()->fetch_assoc(); // Ambil hanya satu baris
$stmt_question->close();

// Music settings and playlist fetching removed
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pertanyaan Quizz: <?= $quiz_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }
        
        body {
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: white;
        }
        
        .stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .stars::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(2px 2px at 20px 30px, #eee, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 40px 70px, #fff, rgba(0,0,0,0)),
                radial-gradient(2px 2px at 90px 40px, #ddd, rgba(0,0,0,0));
            background-size: 200px 200px;
            animation: twinkle 5s infinite;
        }
        
        @keyframes twinkle {
            0% { opacity: 0.2; }
            50% { opacity: 1; }
            100% { opacity: 0.2; }
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            animation: float 15s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Updated Styles for "Keren" Question and Answer Display */
        .main-question-display {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 15px 50px rgba(0, 0, 0, 0.5), 
                0 0 80px rgba(100, 108, 255, 0.3);
            transition: all 0.5s ease;
        }

        .main-question-display:hover {
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.6), 
                0 0 100px rgba(100, 108, 255, 0.5);
        }
        
        /* General button style for icons */
        .icon-btn-nav {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 1.25rem;
        }

        .icon-btn-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 15px rgba(255,255,255,0.4);
        }

        .btn-glow {
            box-shadow: 0 0 10px rgba(100, 108, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .btn-glow:hover {
            box-shadow: 0 0 20px rgba(100, 108, 255, 0.8);
        }
        
        /* Music player styles removed */
        
        /* Notifikasi */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(to right, #4CAF50, #8BC34A); /* Default green */
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
            opacity: 1;
            transform: translateY(0);
        }
        .notification.error {
            background: linear-gradient(to right, #EF4444, #DC2626); /* Red */
        }
        .notification.hidden {
            opacity: 0;
            transform: translateY(-20px);
        }

        /* Style for clickable timer */
        .cursor-pointer {
            cursor: pointer;
        }

        /* Original Styles for images within the question display */
        .question-image, .answer-image {
            max-width: 100%;
            height: auto;
            border-radius: 0.75rem;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .question-image {
            border: 2px solid rgba(130, 200, 255, 0.5); /* Blueish border for question images */
        }
        .answer-image {
            border: 2px solid rgba(132, 204, 22, 0.5); /* Greenish border for answer images */
        }

        /* Style for the large 'X' mark and new images */
        .overlay-image {
            transition: opacity 0.2s ease-out; /* For fade in/out */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 50;
            pointer-events: none; /* Allows clicks to pass through */
            opacity: 0; /* Initially hidden */
            
            /* New sizing as requested */
            width: 700px;
            height: 700px;
            object-fit: contain;
            /* Ensure responsiveness */
            max-width: 100%; 
            max-height: 100%;
        }
        .overlay-text {
            text-shadow: 0 0 20px rgba(255,0,0,0.8);
            font-size: 15rem; /* For 'X' text */
            font-weight: black;
            color: #EF4444; /* Red color for 'X' */
        }
        .overlay-image.animate__fadeIn {
            opacity: 1;
        }
        .overlay-image.animate__fadeOut {
            opacity: 0;
        }

        /* Info Modal Styling */
        .info-modal-content {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.6), 0 0 90px rgba(255, 255, 255, 0.3);
            text-align: center;
            color: white;
        }

        /* New: Blur effect for content */
        .blurred-content {
            filter: blur(10px); /* Adjust blur strength as needed */
            transition: filter 0.3s ease-in-out;
        }

    </style>
</head>
<body class="p-6 relative overflow-y-auto flex flex-col items-center justify-center min-h-screen">
    <div class="stars"></div>
    <div class="particles" id="particles"></div>
    
    <div class="w-full lg:max-w-4xl xl:max-w-6xl mx-auto modal-glass p-6 rounded-2xl mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-blue-500">
                Quizz: <span class="text-amber-300"><?= $quiz_name ?></span>
            </h1>
            <div class="flex gap-3 flex-wrap items-center">
                <a href="quizz.php" title="Kembali"
                   class="btn-glow bg-gray-800 text-white icon-btn-nav hover:bg-gray-700">
                    <i class="fas fa-arrow-left"></i>
                </a>
                
                <?php if (!$first_question): // Hanya tampilkan jika belum ada pertanyaan ?>
                <a href="edit_quizz_question.php?quiz_id=<?= $quiz_id ?>" 
                        title="Tambah Pertanyaan"
                        class="btn-glow bg-gradient-to-r from-green-500 to-emerald-500 text-white icon-btn-nav hover:from-green-600 hover:to-emerald-600">
                    <i class="fas fa-plus"></i>
                </a>
                <?php endif; ?>

                <?php if ($first_question): // Hanya tampilkan tombol edit dan timer jika ada pertanyaan ?>
                <a href="edit_quizz_question.php?quiz_id=<?= $quiz_id ?>&question_id=<?= $first_question['id'] ?>"
                   title="Edit Pertanyaan"
                   class="btn-glow bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-900 icon-btn-nav hover:from-yellow-500 hover:to-amber-600">
                    <i class="fas fa-pencil-alt"></i>
                </a>
                <span id="quizTimer" class="text-white text-2xl font-bold bg-gray-900 px-4 py-2 rounded-lg shadow-md border border-gray-700 cursor-pointer">00:00</span>
                <button id="fullscreenBtn" class="btn-glow bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-4 py-2 rounded-lg font-bold hover:from-purple-600 hover:to-indigo-600 transition-all" title="Perbesar Layar">
                    <i class="fas fa-expand"></i>
                </button>
                <button id="infoButton" title="Petunjuk Keyboard" class="btn-glow bg-gray-800 text-white icon-btn-nav hover:bg-gray-700">
                    <i class="fas fa-info-circle"></i>
                </button>
                <?php endif; ?>
                </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div id="notification" class="notification">
                <?php 
                    if ($_GET['success'] === 'added') echo "Pertanyaan berhasil ditambahkan!";
                    if ($_GET['success'] === 'updated') echo "Pertanyaan berhasil diperbarui!";
                    if ($_GET['success'] === 'deleted') echo "Pertanyaan berhasil dihapus!";
                ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div id="notification" class="notification error">
                Terjadi kesalahan: <?= htmlspecialchars($_GET['msg'] ?? 'Tidak diketahui.') ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="mainQuestionDisplay" 
          class="main-question-display p-8 rounded-xl w-full max-w-4xl relative animate__animated animate__fadeIn flex flex-col justify-between"
          style="min-height: 40vh;"> 
        <?php if ($first_question): ?>
            <div class="flex-grow flex flex-col justify-center text-center">
                <?php if ($first_question['question_text']): ?>
                    <p id="questionText" class="text-5xl lg:text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-300 to-purple-500 break-words mb-4">
                        <?= htmlspecialchars($first_question['question_text']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($first_question['question_image_url']): ?>
                    <img src="<?= htmlspecialchars($first_question['question_image_url']) ?>" alt="Gambar Pertanyaan" class="question-image mx-auto cursor-pointer" onclick="this.classList.add('hidden');">
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full py-10">
                <p class="text-2xl text-gray-400 mb-6 text-center">Belum ada pertanyaan untuk kuis ini.</p>
                <a href="edit_quizz_question.php?quiz_id=<?= $quiz_id ?>" 
                        class="btn-glow bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-6 py-3 rounded-lg font-bold text-lg hover:from-blue-600 hover:to-indigo-600 transition-all animate__animated animate__pulse animate__infinite">
                    Tambahkan Pertanyaan Pertama Anda
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div id="wrongMark" class="overlay-image overlay-text animate__animated opacity-0">X</div>
    
    <img id="tenangImage" src="sounds/tenang.jpg" alt="Tenang" class="overlay-image animate__animated hidden">
    <img id="sabarImage" src="sounds/sabar.png" alt="Sabar" class="overlay-image animate__animated hidden">

    <div id="answerModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center p-4 z-[1002] hidden animate__animated animate__fadeIn">
        <div class="main-question-display p-8 rounded-xl w-full max-w-2xl relative animate__animated animate__zoomIn">
            <button id="closeAnswerModal" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-400 focus:outline-none">&times;</button>
            <?php if (!empty($first_question['answer']) || !empty($first_question['answer_image_url'])): ?>
                <div class="mt-4 p-4 rounded-lg bg-gray-900 bg-opacity-40 border border-gray-700 text-center">
                    <h3 class="text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-green-300 to-teal-500 mb-2">Jawaban:</h3>
                    <?php if ($first_question['answer']): ?>
                        <p class="text-2xl text-white break-words"><?= htmlspecialchars($first_question['answer']) ?></p>
                    <?php endif; ?>
                    <?php if ($first_question['answer_image_url']): ?>
                        <img src="<?= htmlspecialchars($first_question['answer_image_url']) ?>" alt="Gambar Jawaban" class="answer-image mx-auto cursor-pointer" onclick="this.classList.add('hidden');">
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-xl text-gray-400 text-center">Jawaban tidak tersedia.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="infoModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center p-4 z-[1003] hidden animate__animated animate__fadeIn">
        <div class="info-modal-content p-8 rounded-xl w-full max-w-md relative animate__animated animate__zoomIn">
            <button id="closeInfoModal" class="absolute top-4 right-4 text-white text-3xl hover:text-gray-400 focus:outline-none">&times;</button>
            <h3 class="text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-300 to-blue-500 mb-4">Petunjuk Keyboard</h3>
            <p class="text-xl text-white mb-2">Tekan 'S' untuk menampilkan tanda SALAH (X)</p>
            <p class="text-xl text-white mb-2">Tekan 'B' untuk menampilkan JAWABAN BENAR</p>
            <p class="text-xl text-white mb-2">Tekan 'C' untuk menampilkan gambar TENANG</p>
            <p class="text-xl text-white mb-2">Tekan 'P' untuk menampilkan gambar SABAR</p>
            <p class="text-xl text-white">Tekan 'L' untuk memutar audio lucu.mp4</p>
        </div>
    </div>

    <audio id="correctSound" src="sounds/correct.mp3"></audio>
    <audio id="wrongSound" src="sounds/wrong.mp3"></audio>
    <audio id="timerMusic" src="sounds/timer-music.mp3" loop></audio>
    <audio id="timeUpSound" src="sounds/timeup.mp3"></audio>
    <audio id="lucuSound" src="sounds/lucu.mp4"></audio>

    <script>
        // Notification handler
        document.addEventListener('DOMContentLoaded', () => {
            const notification = document.getElementById('notification');
            if (notification) {
                setTimeout(() => {
                    notification.classList.add('hidden');
                    setTimeout(() => notification.remove(), 500); // Remove after transition
                }, 3000); // Hide after 3 seconds
            }
        });

        // Create animated particles
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                const size = Math.random() * 10 + 5;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                particle.style.animationDuration = `${Math.random() * 15 + 10}s`;
                particle.style.animationDelay = `${Math.random() * 5}s`;
                
                particlesContainer.appendChild(particle);
            }
        });

        // Music player functionality removed

        // LOGIC FOR THE TOGGLE TIMER
        let timeLeft; // Initialized dynamically
        let timerInterval;
        let isTimerRunning = false;
        const quizTimerDisplay = document.getElementById('quizTimer');
        const mainQuestionDisplay = document.getElementById('mainQuestionDisplay'); // Get the main question container

        // New audio elements for timer
        const timerMusic = document.getElementById('timerMusic');
        const timeUpSound = document.getElementById('timeUpSound');
        
        function formatTime(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function updateTimerDisplay() {
            quizTimerDisplay.textContent = formatTime(timeLeft);

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                isTimerRunning = false;
                quizTimerDisplay.textContent = "Waktu Habis!";
                quizTimerDisplay.classList.add('text-red-500');
                timerMusic.pause(); // Stop timer music
                timeUpSound.currentTime = 0; // Reset and play time up sound
                timeUpSound.play().catch(e => console.error("Error playing time up sound:", e));
                if (mainQuestionDisplay) {
                    mainQuestionDisplay.classList.remove('blurred-content'); // Remove blur if time is up
                }
            } else if (!isTimerRunning) {
                 quizTimerDisplay.classList.add('text-yellow-500'); // Keep color indication
                 quizTimerDisplay.classList.remove('text-red-500');
            } else {
                quizTimerDisplay.classList.remove('text-red-500', 'text-yellow-500');
            }
            timeLeft--;
        }

        function pauseQuestionTimer() {
            clearInterval(timerInterval);
            isTimerRunning = false;
            quizTimerDisplay.classList.add('text-yellow-500');
            quizTimerDisplay.classList.remove('text-red-500');
            timerMusic.pause(); // Pause timer music
            
            // Add blur
            if (mainQuestionDisplay) {
                mainQuestionDisplay.classList.add('blurred-content');
            }
        }

        function startQuestionTimer() {
            if (timeLeft <= 0) return; // Prevent starting if time is already zero or less
            isTimerRunning = true;
            quizTimerDisplay.classList.remove('text-yellow-500', 'text-red-500'); // Reset color when starting
            timerInterval = setInterval(updateTimerDisplay, 1000);
            timerMusic.play().catch(e => console.error("Error playing timer music:", e));

            // Remove blur
            if (mainQuestionDisplay) {
                mainQuestionDisplay.classList.remove('blurred-content');
            }
        }

        function toggleTimer() {
            // No direct PHP variable access here, use the JS variable `hasQuestion`
            if (!hasQuestion) return;

            // Prevent timer toggle if timeLeft is 0 (N/A mode)
            if (timeLeft <= 0) {
                return;
            }

            if (isTimerRunning) {
                pauseQuestionTimer();
            } else {
                startQuestionTimer();
            }
        }

        // Define these variables safely from PHP
        const hasQuestion = <?= json_encode($first_question !== null) ?>;
        const initialTimeLimit = <?= json_encode($first_question['time_limit'] ?? 30) ?>; // Will be 30 if $first_question is null or time_limit is not set

        document.addEventListener('DOMContentLoaded', () => {
            if (hasQuestion) {
                timeLeft = initialTimeLimit; // Use the safely defined initialTimeLimit
                // Hanya mulai timer secara otomatis jika time_limit BUKAN 0 (Tanpa Waktu)
                if (timeLeft > 0) {
                    startQuestionTimer(); 
                } else {
                    quizTimerDisplay.textContent = "N/A"; // Jika tanpa waktu
                    // JANGAN tambahkan blur jika tanpa waktu
                }
                quizTimerDisplay.addEventListener('click', toggleTimer);
            } else {
                quizTimerDisplay.textContent = "N/A"; // Display N/A if no question
                quizTimerDisplay.style.cursor = "default"; // No click action if no question
            }
            
            // Initial state: ensure modals are hidden on load
            const answerModal = document.getElementById('answerModal');
            if (answerModal) {
                answerModal.classList.add('hidden');
            }
            const infoModal = document.getElementById('infoModal');
            if (infoModal) {
                infoModal.classList.add('hidden');
            }
        });

        // LOGIC FOR FULLSCREEN BUTTON
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const fullscreenIcon = fullscreenBtn ? fullscreenBtn.querySelector('i') : null;

        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', toggleFullscreen);

            document.addEventListener('fullscreenchange', () => {
                if (document.fullscreenElement) {
                    fullscreenIcon.classList.remove('fa-expand');
                    fullscreenIcon.classList.add('fa-compress');
                    fullscreenBtn.title = 'Kecilkan Layar';
                } else {
                    fullscreenIcon.classList.remove('fa-compress');
                    fullscreenIcon.classList.add('fa-expand');
                    fullscreenBtn.title = 'Perbesar Layar';
                }
            });
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else {
                document.exitFullscreen();
            }
        }

        // --- Efek Suara Benar/Salah ---
        const correctSound = document.getElementById('correctSound');
        const wrongSound = document.getElementById('wrongSound');
        const wrongMark = document.getElementById('wrongMark'); // Get the X mark
        const tenangImage = document.getElementById('tenangImage');
        const sabarImage = document.getElementById('sabarImage');

        function playCorrectSound() {
            correctSound.currentTime = 0;
            correctSound.play().catch(e => console.error("Error playing correct sound:", e));
        }

        function playWrongSound() {
            wrongSound.currentTime = 0;
            wrongSound.play().catch(e => console.error("Error playing wrong sound:", e));
        }

        // Modified: showOverlayImage now only shows, hideAllOverlayImages handles hiding
        function showOverlayImage(element) {
            if (!element) return;
            element.classList.remove('hidden', 'animate__fadeOut');
            element.classList.add('animate__animated', 'animate__fadeIn');
            element.style.opacity = 1;
        }

        // New function: hide all visible overlay images
        function hideAllOverlayImages() {
            const overlayImages = document.querySelectorAll('.overlay-image');
            overlayImages.forEach(element => {
                // Check if the element is currently visible (not hidden and has opacity 1)
                if (!element.classList.contains('hidden') && element.style.opacity == 1) {
                    element.classList.remove('animate__fadeIn'); // Remove entrance animation
                    element.classList.add('animate__animated', 'animate__fadeOut'); // Add exit animation
                    element.style.opacity = 0; // Set opacity to 0 immediately for fadeOut

                    // After the fadeOut animation completes, add 'hidden' class and clean up
                    setTimeout(() => {
                        element.classList.add('hidden');
                        element.classList.remove('animate__animated', 'animate__fadeOut'); // Clean up animation classes
                    }, 500); // Matches the duration of animate__fadeOut
                }
            });
        }

        // New: Global click listener to hide overlay images
        document.addEventListener('click', (event) => {
            // Check if the click occurred inside the answer or info modal
            const clickedInsideAnswerModal = answerModal && answerModal.contains(event.target) && !answerModal.classList.contains('hidden');
            const clickedInsideInfoModal = infoModal && infoModal.contains(event.target) && !infoModal.classList.contains('hidden');

            // Only hide overlay images if the click is not inside an open modal
            if (!clickedInsideAnswerModal && !clickedInsideInfoModal) {
                hideAllOverlayImages();
            }
        });

        // New elements for the answer modal and info modal
        const answerModal = document.getElementById('answerModal');
        const closeAnswerModal = document.getElementById('closeAnswerModal');
        const infoModal = document.getElementById('infoModal');
        const infoButton = document.getElementById('infoButton');
        const closeInfoModal = document.getElementById('closeInfoModal');

        // --- Keyboard Event Listener ---
        document.addEventListener('keydown', (event) => {
            // Use the JavaScript variable `hasQuestion`
            if (!hasQuestion) return; // Only react if there's a question

            if (event.key === 's' || event.key === 'S') {
                playWrongSound();
                showOverlayImage(wrongMark);
            } else if (event.key === 'b' || event.key === 'B') {
                playCorrectSound();
                // Hanya pause timer jika sedang berjalan (punya time_limit)
                if (timeLeft > 0 && isTimerRunning) {
                     pauseQuestionTimer();
                }
                if (answerModal) {
                    answerModal.classList.remove('hidden');
                    // Reset and add entrance animation for the modal content
                    const modalContent = answerModal.querySelector('.main-question-display');
                    modalContent.classList.remove('animate__zoomOut'); // Remove exit animation if present
                    modalContent.classList.add('animate__animated', 'animate__zoomIn');
                }
            } else if (event.key === 'c' || event.key === 'C') {
                showOverlayImage(tenangImage);
            } else if (event.key === 'p' || event.key === 'P') {
                showOverlayImage(sabarImage);
            } else if (event.key === 'l' || event.key === 'L') { // New: 'L' key for lucu.mp4
                const lucuSound = document.getElementById('lucuSound');
                if (lucuSound) {
                    lucuSound.currentTime = 0;
                    lucuSound.play().catch(e => console.error("Error playing lucu sound:", e));
                }
            }
        });

        // Add close button functionality for the answer modal
        if (closeAnswerModal) {
            closeAnswerModal.addEventListener('click', () => {
                if (answerModal) {
                    // Add exit animation before hiding
                    const modalContent = answerModal.querySelector('.main-question-display');
                    modalContent.classList.remove('animate__zoomIn');
                    modalContent.classList.add('animate__animated', 'animate__zoomOut');

                    // Hide after animation (adjust time if needed)
                    setTimeout(() => {
                        answerModal.classList.add('hidden');
                        modalContent.classList.remove('animate__animated', 'animate__zoomOut'); // Clean up
                    }, 500); // Duration of animate__zoomOut
                }
            });
        }

        // Add Escape key to close any open modal
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (answerModal && !answerModal.classList.contains('hidden')) {
                    if (closeAnswerModal) {
                        closeAnswerModal.click();
                    }
                } else if (infoModal && !infoModal.classList.contains('hidden')) {
                    if (closeInfoModal) {
                        closeInfoModal.click();
                    }
                }
            }
        });

        // Info Button functionality
        if (infoButton) {
            infoButton.addEventListener('click', () => {
                if (infoModal) {
                    infoModal.classList.remove('hidden');
                    const modalContent = infoModal.querySelector('.info-modal-content');
                    modalContent.classList.remove('animate__zoomOut');
                    modalContent.classList.add('animate__animated', 'animate__zoomIn');
                }
            });
        }

        if (closeInfoModal) {
            closeInfoModal.addEventListener('click', () => {
                if (infoModal) {
                    const modalContent = infoModal.querySelector('.info-modal-content');
                    modalContent.classList.remove('animate__zoomIn');
                    modalContent.classList.add('animate__animated', 'animate__zoomOut');
                    setTimeout(() => {
                        infoModal.classList.add('hidden');
                        modalContent.classList.remove('animate__animated', 'animate__zoomOut');
                    }, 500);
                }
            });
        }

    </script>
</body>
</html>