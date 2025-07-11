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
$stmt_check_quiz = $conn->prepare("SELECT name FROM quiz WHERE id = ? AND user_id = ?");
$stmt_check_quiz->bind_param("ii", $quiz_id, $user_id);
$stmt_check_quiz->execute();
$result_check_quiz = $stmt_check_quiz->get_result();
if ($result_check_quiz->num_rows == 0) {
    die("Kuis tidak ditemukan atau bukan milik Anda.");
}
$quiz_name_row = $result_check_quiz->fetch_assoc();
$quiz_name = htmlspecialchars($quiz_name_row['name']);
$stmt_check_quiz->close();

// Logika untuk menambahkan pertanyaan (Self-Submission)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_question') {
    if (isset($_POST['question_text']) && !empty($_POST['question_text'])) {
        $question_text = $_POST['question_text'];
        $answer = isset($_POST['answer']) ? $_POST['answer'] : null;

        $stmt = $conn->prepare("INSERT INTO question_quiz (quiz_id, question_text, answer, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $quiz_id, $question_text, $answer, $user_id);

        if ($stmt->execute()) {
            header("Location: quiz_question.php?id=$quiz_id&success=added");
            exit();
        } else {
            header("Location: quiz_question.php?id=$quiz_id&error=add_failed&msg=" . urlencode($stmt->error));
            exit();
        }
        $stmt->close();
    } else {
        header("Location: quiz_question.php?id=$quiz_id&error=add_failed&msg=" . urlencode("Teks pertanyaan tidak boleh kosong."));
        exit();
    }
}

// Mengambil HANYA PERTANYAAN PERTAMA dari tabel 'question_quiz'
$stmt_question = $conn->prepare("SELECT id, question_text, answer FROM question_quiz WHERE quiz_id = ? AND user_id = ? ORDER BY id ASC LIMIT 1");
$stmt_question->bind_param("ii", $quiz_id, $user_id);
$stmt_question->execute();
$first_question = $stmt_question->get_result()->fetch_assoc(); // Ambil hanya satu baris
$stmt_question->close();

// Get music settings
$music_settings = $conn->query("SELECT * FROM music_settings WHERE user_id = $user_id")->fetch_assoc();
if (!$music_settings) {
    // Create default settings if not exists
    $conn->query("INSERT INTO music_settings (user_id) VALUES ($user_id)");
    $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0];
}

// Get active playlist
$playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pertanyaan Kuis: <?= $quiz_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
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
        
        .main-question-display {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .answer-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
        }
        
        .answer-container.show {
            max-height: 500px; /* Adjust as needed */
            transition: max-height 0.5s ease-in;
        }
        
        .btn-glow {
            box-shadow: 0 0 10px rgba(100, 108, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .btn-glow:hover {
            box-shadow: 0 0 20px rgba(100, 108, 255, 0.8);
        }
        
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
    </style>
</head>
<body class="p-6 relative overflow-hidden flex flex-col items-center justify-center min-h-screen">
    <div class="stars"></div>
    <div class="particles" id="particles"></div>
    
    <div class="w-full lg:max-w-4xl xl:max-w-6xl mx-auto modal-glass p-6 rounded-2xl mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-blue-500">
                Kuis: <span class="text-amber-300"><?= $quiz_name ?></span>
            </h1>
            <div class="flex gap-3 flex-wrap items-center">
                <a href="quiz.php" class="btn-glow bg-gray-800 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-700 transition-all">
                    ← Kembali 
                </a>
                <button onclick="document.getElementById('addQuestionModal').classList.remove('hidden')" 
                        class="btn-glow bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg font-bold hover:from-green-600 hover:to-emerald-600 transition-all">
                    + Pertanyaan
                </button>
                <?php if ($first_question): // Hanya tampilkan tombol edit jika ada pertanyaan ?>
                <button onclick="document.getElementById('editQuestionModal').classList.remove('hidden')" 
                        class="btn-glow bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-900 px-4 py-2 rounded-lg font-bold hover:from-yellow-500 hover:to-amber-600 transition-all">
                    Edit Pertanyaan
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div id="notification" class="notification">
                <?php 
                    if ($_GET['success'] === 'added') echo "Pertanyaan berhasil ditambahkan!";
                    if ($_GET['success'] === 'updated') echo "Pertanyaan berhasil diperbarui!";
                    if ($_GET['success'] === 'deleted') echo "Pertanyaan berhasil dihapus!"; // Keep for future use or manual deletion
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
         style="min-height: 40vh;"> <?php if ($first_question): ?>
            <div class="absolute top-4 right-4 flex space-x-3">
                <button id="showAnswerBtn" onclick="toggleAnswer()" title="Tampilkan Jawaban" class="text-green-400 hover:text-green-300 transition-colors">
                    <i class="fas fa-check-square fa-lg"></i>
                </button>
                </div>

            <div class="flex-grow flex flex-col justify-center text-center">
                <p id="questionText" class="text-4xl lg:text-5xl font-bold text-white break-words mb-4">
                    <?= htmlspecialchars($first_question['question_text']) ?>
                </p>
            </div>
            
            <div id="answerContainer" class="answer-container mt-6">
                <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border-l-4 border-green-500">
                    <h3 class="text-lg font-semibold text-green-400 mb-2">Jawaban:</h3>
                    <p id="answerText" class="text-white text-xl break-words">
                        <?= isset($first_question['answer']) && $first_question['answer'] ? htmlspecialchars($first_question['answer']) : 'Tidak ada jawaban tersimpan.' ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full py-10">
                <p class="text-2xl text-gray-400 mb-6 text-center">Belum ada pertanyaan untuk kuis ini.</p>
                <button onclick="document.getElementById('addQuestionModal').classList.remove('hidden')" 
                        class="btn-glow bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-6 py-3 rounded-lg font-bold text-lg hover:from-blue-600 hover:to-indigo-600 transition-all animate__animated animate__pulse animate__infinite">
                    Tambahkan Pertanyaan Pertama Anda
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div id="addQuestionModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="modal-glass p-6 rounded-xl max-w-sm w-full animate__animated animate__fadeIn">
            <h2 class="text-xl font-bold text-white mb-4">Tambah Pertanyaan Baru</h2>
            <form action="quiz_question.php?id=<?= $quiz_id ?>" method="POST" id="questionForm">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="quiz_id" value="<?= $quiz_id?>">
                <textarea name="question_text" placeholder="Tulis pertanyaan..." required 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500 h-32 resize-y"></textarea>
                
                <textarea name="answer" placeholder="Tulis jawaban (opsional)" 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-green-500 h-32 resize-y"></textarea>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('addQuestionModal').classList.add('hidden')" 
                                class="btn-glow bg-gray-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-700 transition-all">
                        Batal
                    </button>
                    <button type="submit" 
                                class="btn-glow bg-gradient-to-r from-green-500 to-emerald-500 text-white px-4 py-2 rounded-lg font-bold hover:from-green-600 hover:to-emerald-600 transition-all">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editQuestionModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="modal-glass p-6 rounded-xl max-w-sm w-full animate__animated animate__fadeIn">
            <h2 class="text-xl font-bold text-white mb-4">Edit Pertanyaan</h2>
            <form id="editForm" method="POST" action="update_question_quiz.php">
                <input type="hidden" name="question_id" value="<?= $first_question ? $first_question['id'] : '' ?>">
                <input type="hidden" name="quiz_id" value="<?= $quiz_id?>">
                <textarea name="question_text" placeholder="Tulis pertanyaan..." required 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-yellow-500 h-32 resize-y"><?= $first_question ? htmlspecialchars($first_question['question_text']) : '' ?></textarea>
                
                <textarea name="answer_text" placeholder="Tulis jawaban (opsional)" 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-green-500 h-32 resize-y"><?= $first_question && isset($first_question['answer']) ? htmlspecialchars($first_question['answer']) : '' ?></textarea>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('editQuestionModal').classList.add('hidden')" 
                                class="btn-glow bg-gray-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-700 transition-all">
                        Batal
                    </button>
                    <button type="submit" 
                                class="btn-glow bg-gradient-to-r from-yellow-500 to-amber-500 text-gray-900 px-4 py-2 rounded-lg font-bold hover:from-yellow-600 hover:to-amber-600 transition-all">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
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

            // Close addQuestionModal when clicking outside
            document.getElementById('addQuestionModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.add('hidden');
                }
            });

            // NEW: Close editQuestionModal when clicking outside
            document.getElementById('editQuestionModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.add('hidden');
                }
            });
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

        function toggleAnswer() {
            const answerContainer = document.getElementById('answerContainer');
            answerContainer.classList.toggle('show');
            
            if (answerContainer.classList.contains('show')) {
                answerContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Music player functionality (no changes needed)
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
                        console.error('Playback failed (user interaction might be needed):', error);
                        this.isPlaying = false;
                        this.playPauseBtn.innerHTML = '<i class="fas fa-play text-white"></i>';
                        this.saveSettings();
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
                        is_music_on: this.isPlaying ? 1 : 0,
                        current_track: this.currentTrack,
                        volume: Math.round(this.volume * 100)
                    })
                });
            }
        };

        musicPlayer.init();
    </script>
</body>
</html>