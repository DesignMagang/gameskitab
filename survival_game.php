<?php
session_start();
require_once 'db.php';

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Validasi akses sesi hanya jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_name'], $_POST['access_code'])) {
    $sessionName = trim($_POST['session_name']);
    $accessCode = trim($_POST['access_code']);
    
    $stmt = $conn->prepare("SELECT id FROM survival_sessions WHERE session_name = ? AND access_code = ?");
    $stmt->bind_param("ss", $sessionName, $accessCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Akses ditolak! Nama sesi atau kode akses salah.";
        header("Location: survival.php");
        exit;
    } else {
        $session = $result->fetch_assoc();
        $_SESSION['current_survival_session'] = $session['id'];
        header("Location: survival_game.php");
        exit;
    }
}

// Jika tidak ada sesi yang aktif, redirect kembali
if (!isset($_SESSION['current_survival_session'])) {
    header("Location: survival.php");
    exit;
}

// Lanjutkan dengan kode yang ada...
$sessionId = $_SESSION['current_survival_session'];
$sessionData = $conn->query("SELECT * FROM survival_sessions WHERE id = $sessionId")->fetch_assoc();

// Get music settings
$music_settings = $conn->query("SELECT * FROM music_settings WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
if (!$music_settings) {
    $conn->query("INSERT INTO music_settings (user_id) VALUES ({$_SESSION['user_id']})");
    $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0];
}

$playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);

// Handle form submission untuk menyimpan pertanyaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_text'])) {
    $roundNumber = (int)$_POST['round_number'];
    $questionText = trim($_POST['question_text']);
    $questionType = $_POST['question_type'];
    $correctAnswer = $_POST['correct_answer'];
    $answerTime = (int)$_POST['answer_time'];
    $pointSystem = $_POST['point_system'];
    $basePoints = ($pointSystem === 'double') ? 2000 : 1000;

    // Untuk pilihan ganda
    $optionA = isset($_POST['option_a']) ? trim($_POST['option_a']) : null;
    $optionB = isset($_POST['option_b']) ? trim($_POST['option_b']) : null;
    $optionC = isset($_POST['option_c']) ? trim($_POST['option_c']) : null;
    $optionD = isset($_POST['option_d']) ? trim($_POST['option_d']) : null;

    $stmt = $conn->prepare("INSERT INTO survival_questions 
    (session_id, round_number, question_order, question_text, 
    question_type, correct_answer, option_a, option_b, option_c, option_d,
    answer_time, base_points) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iiisssssssii", $sessionId, $roundNumber, $questionOrder, 
    $questionText, $questionType, $correctAnswer, 
    $optionA, $optionB, $optionC, $optionD,
    $answerTime, $basePoints);

     // Validasi input
    if (empty($questionText)) {
        $_SESSION['error'] = "Pertanyaan tidak boleh kosong";
    } elseif ($questionType === 'multiple_choice' && (empty($optionA) || empty($optionB))) {
        $_SESSION['error'] = "Pilihan A dan B harus diisi untuk pilihan ganda";
    } else {
        // Dapatkan urutan pertanyaan terakhir di ronde ini
        $lastOrder = $conn->query("SELECT MAX(question_order) as last_order FROM survival_questions 
                                  WHERE session_id = $sessionId AND round_number = $roundNumber")->fetch_assoc();
        $questionOrder = ($lastOrder['last_order'] ?? 0) + 1;
        
        // Simpan ke database
        $stmt = $conn->prepare("INSERT INTO survival_questions 
                               (session_id, round_number, question_order, question_text, 
                               question_type, correct_answer, option_a, option_b, option_c, option_d) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                               $stmt->bind_param("iiisssssss", $sessionId, $roundNumber, $questionOrder, 
                         $questionText, $questionType, $correctAnswer, 
                         $optionA, $optionB, $optionC, $optionD);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pertanyaan berhasil ditambahkan ke Ronde $roundNumber!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan pertanyaan: " . $conn->error;
        }
    }
    
    header("Location: survival_game.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survival Game: <?= htmlspecialchars($sessionData['session_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
        .music-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: rgba(255,255,255,0.1);
            transition: all 0.2s;
        }
        .music-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        .form-input {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s;
        }
        .form-input:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
        }
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-select option {
            background: #1E1B4B;
            color: white;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 to-purple-900 bg-[length:400%_400%] animate-[gradientBG_15s_ease_infinite] text-white">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <a href="survival.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition-all flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
                <h1 class="text-2xl md:text-3xl font-bold text-center">
                    <?= htmlspecialchars($sessionData['session_name']) ?>
                </h1>
            </div>
            
            <div class="flex gap-2">
                <a href="play_survival.php?session=<?= $sessionId ?>" 
                   class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg flex items-center">
                    <i class="fas fa-play mr-2"></i> Mainkan
                </a>
                <a href="preview_survival.php?session=<?= $sessionId ?>" 
                   class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview
                </a>
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-500/20 border border-green-500/50 text-green-100 px-4 py-3 rounded-lg mb-6 animate-pulse">
                <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-3 rounded-lg mb-6 animate-pulse">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Add Question Form -->
        <div class="glass-card p-6 rounded-xl shadow-lg mb-8">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-plus-circle mr-2 text-purple-300"></i> Tambah Pertanyaan Baru
            </h2>
            
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Nomor Ronde</label>
                        <select name="round_number" class="w-full form-select px-4 py-2 rounded-lg" required>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>">Ronde <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipe Pertanyaan</label>
                        <select name="question_type" id="questionType" class="w-full form-select px-4 py-2 rounded-lg" required>
                            <option value="true_false">Benar/Salah</option>
                            <option value="multiple_choice">Pilihan Ganda</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Waktu Jawab</label>
                            <select name="answer_time" class="w-full form-select px-4 py-2 rounded-lg" required>
                                <option value="20">20 Detik</option>
                                <option value="30">30 Detik</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Sistem Poin</label>
                            <select name="point_system" class="w-full form-select px-4 py-2 rounded-lg" required>
                                <option value="standard">Standar (1000 poin)</option>
                                <option value="double">Ganda (2000 poin)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-1">Pertanyaan</label>
                    <textarea name="question_text" rows="3" required
                              class="w-full form-input px-4 py-2 rounded-lg placeholder-white/50"
                              placeholder="Masukkan pertanyaan..."></textarea>
                </div>
                
                <!-- Benar/Salah Options -->
                <div id="trueFalseOptions" class="space-y-2">
                    <label class="block text-sm font-medium mb-1">Jawaban Benar</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="correct_answer" value="true" checked class="mr-2">
                            <span>Benar</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="correct_answer" value="false" class="mr-2">
                            <span>Salah</span>
                        </label>
                    </div>
                </div>
                
                <!-- Pilihan Ganda Options -->
                <div id="multipleChoiceOptions" class="space-y-2 hidden">
                    <label class="block text-sm font-medium mb-1">Pilihan Jawaban</label>
                    <div class="space-y-3">
                        <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                            <div class="flex items-center gap-2">
                                <span class="w-6 text-sm font-medium"><?= strtoupper($option) ?>.</span>
                                <input type="text" name="option_<?= $option ?>" 
                                       class="flex-1 form-input px-3 py-2 rounded-lg placeholder-white/50"
                                       placeholder="Teks pilihan <?= strtoupper($option) ?>">
                                <input type="radio" name="correct_answer" value="<?= $option ?>" 
                                       class="h-4 w-4" <?= $option === 'a' ? 'checked' : '' ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-2.5 px-4 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 rounded-lg font-medium transition-all">
                        <i class="fas fa-save mr-2"></i> Simpan Pertanyaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Music Player -->
    <div class="music-player" id="musicPlayer">
        <button id="playPauseBtn" class="music-btn">
            <i class="fas <?= $music_settings['is_music_on'] ? 'fa-pause' : 'fa-play' ?>"></i>
        </button>
        <div class="text-sm">
            <div id="nowPlaying" class="font-medium">
                <?= count($playlist) > 0 ? htmlspecialchars($playlist[$music_settings['current_track']]['display_name']) : 'No tracks' ?>
            </div>
            <div id="trackInfo" class="text-xs opacity-70">
                Track <?= count($playlist) > 0 ? ($music_settings['current_track'] + 1) : '0' ?> of <?= count($playlist) ?>
            </div>
        </div>
    </div>

    <audio id="backgroundMusic"></audio>

    <script>
        // Toggle question type
        document.getElementById('questionType').addEventListener('change', function() {
            const isMultipleChoice = this.value === 'multiple_choice';
            document.getElementById('trueFalseOptions').classList.toggle('hidden', isMultipleChoice);
            document.getElementById('multipleChoiceOptions').classList.toggle('hidden', !isMultipleChoice);
        });

        // Musik Player Functionality
        const musicPlayer = {
            audio: document.getElementById('backgroundMusic'),
            playPauseBtn: document.getElementById('playPauseBtn'),
            nowPlaying: document.getElementById('nowPlaying'),
            trackInfo: document.getElementById('trackInfo'),
            playlist: <?= json_encode($playlist) ?>,
            currentTrack: <?= $music_settings['current_track'] ?>,
            isPlaying: <?= $music_settings['is_music_on'] ?>,
            
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
                const track = this.playlist[this.currentTrack];
                this.audio.src = track.file_path;
                this.nowPlaying.textContent = track.display_name;
                this.trackInfo.textContent = `Track ${this.currentTrack + 1} of ${this.playlist.length}`;
                this.updateSettings();
            },
            
            play: function() {
                this.audio.play()
                    .then(() => {
                        this.isPlaying = true;
                        this.playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                        this.updateSettings();
                    });
            },
            
            pause: function() {
                this.audio.pause();
                this.isPlaying = false;
                this.playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                this.updateSettings();
            },
            
            togglePlay: function() {
                this.isPlaying ? this.pause() : this.play();
            },
            
            nextTrack: function() {
                this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
                this.loadTrack();
                if (this.isPlaying) this.play();
            },
            
            updateSettings: function() {
                fetch('update_music_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        is_music_on: this.isPlaying,
                        current_track: this.currentTrack,
                        volume: 50
                    })
                });
            }
        };

        // Initialize music player
        document.addEventListener('DOMContentLoaded', () => {
            musicPlayer.init();
        });
    </script>
</body>
</html>