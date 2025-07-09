<?php
session_start();
require_once 'db.php';

// Check session and permissions
if (!isset($_GET['session']) || !isset($_SESSION['user_id'])) {
    header("Location: create_matching.php");
    exit;
}

$sessionId = $_GET['session'];
$userId = $_SESSION['user_id'];

// Verify session ownership
$stmt = $conn->prepare("SELECT session_name FROM sessions WHERE session_id = ? AND created_by = ?");
$stmt->bind_param("si", $sessionId, $userId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    header("Location: create_matching.php");
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new question
    if (isset($_POST['question_text'])) {
        $roundNumber = (int)$_POST['round_number'];
        $questionText = $_POST['question_text'];
        $answerText = $_POST['answer_text'];
        $bibleRef = $_POST['bible_reference'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO session_questions (session_id, round_number, question_text, answer_text, bible_reference) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $sessionId, $roundNumber, $questionText, $answerText, $bibleRef);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pertanyaan berhasil ditambahkan ke Ronde $roundNumber!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan pertanyaan: " . $conn->error;
        }
    }
    // Update existing question
    elseif (isset($_POST['edit_id'])) {
        $questionId = (int)$_POST['edit_id'];
        $roundNumber = (int)$_POST['edit_round'];
        $questionText = $_POST['edit_question'];
        $answerText = $_POST['edit_answer'];
        $bibleRef = $_POST['edit_reference'] ?? '';
        
        $stmt = $conn->prepare("UPDATE session_questions SET round_number = ?, question_text = ?, answer_text = ?, bible_reference = ? WHERE question_id = ?");
        $stmt->bind_param("isssi", $roundNumber, $questionText, $answerText, $bibleRef, $questionId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pertanyaan berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui pertanyaan: " . $conn->error;
        }
    }
    
    header("Location: add_questions.php?session=$sessionId");
    exit;
}

// Handle question deletion
if (isset($_GET['delete'])) {
    $questionId = (int)$_GET['delete'];
    $conn->query("DELETE FROM session_questions WHERE question_id = $questionId AND session_id = '$sessionId'");
    $_SESSION['success'] = "Pertanyaan berhasil dihapus!";
    header("Location: add_questions.php?session=$sessionId");
    exit;
}

// Get existing questions
$questions = $conn->query("SELECT * FROM session_questions WHERE session_id = '$sessionId' ORDER BY round_number, question_id");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Pertanyaan | <?= htmlspecialchars($session['session_name']) ?></title>
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
            overflow-x: hidden;
        }
        
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255,255,255,0.1) 0%, transparent 20%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,0.1) 0%, transparent 20%);
            animation: gradientPulse 15s infinite alternate;
        }
        
        @keyframes gradientPulse {
            0% { background-size: 100% 100%; }
            50% { background-size: 150% 150%; }
            100% { background-size: 100% 100%; }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }
        
        .select-white {
            background-color: rgba(255,255,255,0.9) !important;
            color: #333 !important;
            border: 1px solid #ddd !important;
        }
        
        .alert-auto-hide {
            animation: fadeOut 5s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
        
        .action-btns {
            white-space: nowrap;
        }
        
        .action-btns .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 2px;
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        /* Edit modal styles */
        .modal-content {
            background: rgba(40, 40, 80, 0.95);
            backdrop-filter: blur(10px);
            color: white;
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
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header with Back Button -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="create_matching.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                    <h2 class="text-center mb-0 floating">
                        <?= htmlspecialchars($session['session_name']) ?>
                    </h2>
                   <a href="demo.php?session=<?= $sessionId ?>" class="btn btn-success">
                        <i class="fas fa-play me-1"></i> Mainkan
                    </a>
                    <a href="preview_game.php?session=<?= $sessionId ?>" class="btn btn-info">
                        <i class="fas fa-eye me-1"></i> Preview
                    </a>
                </div>
                
                <!-- Auto-hiding alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-auto-hide animate__animated animate__bounceIn">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-auto-hide animate__animated animate__shakeX">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add Question Form -->
                <div class="glass-card p-4 mb-4 animate__animated animate__fadeIn">
                    <h4 class="text-center mb-4"><i class="fas fa-plus-circle me-2"></i>Tambah Pertanyaan Baru</h4>
                    
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Nomor Ronde</label>
                                <select name="round_number" class="form-select select-white" required>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?= $i ?>">Ronde <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-9">
                                <label class="form-label">Referensi Alkitab</label>
                                <input type="text" name="bible_reference" class="form-control bg-transparent text-white" 
                                       placeholder="Contoh: Kejadian 1:1">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Pertanyaan</label>
                                <textarea name="question_text" class="form-control bg-transparent text-white" 
                                          rows="2" required placeholder="Tulis pertanyaan..."></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Jawaban</label>
                                <input type="text" name="answer_text" class="form-control bg-transparent text-white" 
                                       required placeholder="Tulis jawaban benar">
                            </div>
                            
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary w-100 py-2">
                                    <i class="fas fa-save me-2"></i> Simpan Pertanyaan
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Questions Table -->
                <div class="glass-card p-4 animate__animated animate__fadeInUp">
                    <h4 class="text-center mb-4"><i class="fas fa-list me-2"></i>Daftar Pertanyaan</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                                <tr>
                                    <th width="80">Ronde</th>
                                    <th>Pertanyaan</th>
                                    <th>Jawaban</th>
                                    <th>Referensi</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($q = $questions->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $q['round_number'] ?></td>
                                    <td><?= htmlspecialchars($q['question_text']) ?></td>
                                    <td><?= htmlspecialchars($q['answer_text']) ?></td>
                                    <td><?= $q['bible_reference'] ?: '-' ?></td>
                                    <td class="action-btns">
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                data-id="<?= $q['question_id'] ?>"
                                                data-round="<?= $q['round_number'] ?>"
                                                data-question="<?= htmlspecialchars($q['question_text']) ?>"
                                                data-answer="<?= htmlspecialchars($q['answer_text']) ?>"
                                                data-reference="<?= $q['bible_reference'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="add_questions.php?session=<?= $sessionId ?>&delete=<?= $q['question_id'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Hapus pertanyaan ini?')">
                                           <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Edit Question Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pertanyaan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="editId">
                        <div class="mb-3">
                            <label class="form-label">Nomor Ronde</label>
                            <select name="edit_round" class="form-select select-white" id="editRound" required>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>">Ronde <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referensi Alkitab</label>
                            <input type="text" name="edit_reference" class="form-control bg-transparent text-white" id="editReference">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pertanyaan</label>
                            <textarea name="edit_question" class="form-control bg-transparent text-white" id="editQuestion" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jawaban</label>
                            <input type="text" name="edit_answer" class="form-control bg-transparent text-white" id="editAnswer" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-auto-hide').forEach(el => {
                el.style.display = 'none';
            });
        }, 3000);
        
        // Edit modal handler
        const editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#editId').value = button.getAttribute('data-id');
            this.querySelector('#editRound').value = button.getAttribute('data-round');
            this.querySelector('#editQuestion').value = button.getAttribute('data-question');
            this.querySelector('#editAnswer').value = button.getAttribute('data-answer');
            this.querySelector('#editReference').value = button.getAttribute('data-reference') || '';
        });

        // Music player functionality
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

        // Initialize music player
        document.addEventListener('DOMContentLoaded', () => {
            musicPlayer.init();
        });
    </script>
</body>
</html>