<?php
session_start();
require_once 'db.php';

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Validasi akses sesi
if (!isset($_SESSION['current_survival_session'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['session_name'], $_POST['access_code'])) {
        $sessionName = trim($_POST['session_name']);
        $accessCode = trim($_POST['access_code']);
        
        $stmt = $conn->prepare("SELECT id FROM survival_sessions WHERE session_name = ? AND access_code = ?");
        $stmt->bind_param("ss", $sessionName, $accessCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error'] = "Kode akses salah!";
            header("Location: survival.php");
            exit;
        } else {
            $session = $result->fetch_assoc();
            $_SESSION['current_survival_session'] = $session['id'];
        }
    } else {
        header("Location: survival.php");
        exit;
    }
}

$sessionId = $_SESSION['current_survival_session'];
$sessionData = $conn->query("SELECT * FROM survival_sessions WHERE id = $sessionId")->fetch_assoc();

// Ambil pertanyaan kelompok per ronde
$rounds = [];
$questions = $conn->query("
    SELECT * FROM survival_questions 
    WHERE session_id = $sessionId 
    ORDER BY round_number, question_order
");

while ($question = $questions->fetch_assoc()) {
    $rounds[$question['round_number']][] = $question;
}

// Get music settings
$music_settings = $conn->query("SELECT * FROM music_settings WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
if (!$music_settings) {
    $conn->query("INSERT INTO music_settings (user_id) VALUES ({$_SESSION['user_id']})");
    $music_settings = ['is_music_on' => 1, 'volume' => 50, 'current_track' => 0];
}

$playlist = $conn->query("SELECT * FROM background_music WHERE is_active = 1 ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= htmlspecialchars($sessionData['session_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
        <link rel="icon" href="logo.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            min-height: 100vh;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 33, 0.25);
        }
        .question-card {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.07);
        }
        .question-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .correct-answer {
            position: relative;
            background: rgba(16, 185, 129, 0.15) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
        }
        .correct-answer::before {
            content: '✓';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #10b981;
            font-weight: bold;
        }
        .custom-radio {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            margin-right: 8px;
            position: relative;
            top: 3px;
        }
        .custom-radio:checked {
            background-color: #10b981;
            border-color: #10b981;
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
        }

        .modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex; align-items: center; justify-content: center;
        z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s ease;
    }
    .modal-overlay.active { opacity: 1; pointer-events: auto; }
</style>
</head>
<body class="text-gray-100 antialiased">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="glass-panel sticky top-0 z-10">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between">
                    <a href="survival.php" class="flex items-center gap-2 hover:text-white transition-colors">
                        <i class="fas fa-chevron-left"></i>
                        <span class="hidden sm:inline">Kembali</span>
                    </a>
                    
                    <div class="text-center">
                        <h1 class="text-xl md:text-2xl font-bold">
                            <i class="fas fa-eye mr-2 text-purple-200"></i>
                            <?= htmlspecialchars($sessionData['session_name']) ?>
                        </h1>
                        <div class="flex gap-2 justify-center mt-1">
                            <span class="text-xs bg-white/10 px-2 py-0.5 rounded-full">
                                <i class="fas fa-layer-group mr-1"></i>
                                <?= count($rounds) ?> Rounds
                            </span>
                            <span class="text-xs bg-white/10 px-2 py-0.5 rounded-full">
                                <i class="fas fa-question mr-1"></i>
                                <?= array_sum(array_map('count', $rounds)) ?> Questions
                            </span>
                        </div>
                    </div>
                    
                    <div class="w-6"></div> <!-- Spacer -->
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8 max-w-6xl">
            <div class="space-y-8 animate-fadeIn">
                <?php foreach ($rounds as $roundNumber => $questions): ?>
                    <div class="glass-panel rounded-xl overflow-hidden animate-fadeIn" style="animation-delay: <?= $roundNumber * 0.1 ?>s">
                        <div class="bg-white/10 px-6 py-4 border-b border-white/10 flex items-center">
                            <div class="w-10 h-10 rounded-full bg-purple-500/20 flex items-center justify-center mr-3">
                                <span class="font-bold"><?= $roundNumber ?></span>
                            </div>
                            <h2 class="font-bold text-lg">
                                Round <?= $roundNumber ?>
                            </h2>
                            <span class="ml-auto text-sm bg-white/10 px-3 py-1 rounded-full">
                                <?= count($questions) ?> Questions
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 p-6">
                            <?php foreach ($questions as $index => $q): ?>
                                <div class="question-card rounded-lg p-5 border border-white/10 hover:border-white/20" id="question-card-<?= $q['id'] ?>">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex items-start flex-1">
                                            <span class="text-white/60 mr-2 font-mono"><?= $index + 1 ?>.</span>
                                            <p class="font-medium flex-1"><?= htmlspecialchars($q['question_text']) ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4 flex-shrink-0">
                                            <button class="edit-btn text-blue-300 hover:text-white transition-colors"
                                                data-id="<?= $q['id'] ?>"
                                                data-text="<?= htmlspecialchars($q['question_text']) ?>"
                                                data-type="<?= $q['question_type'] ?>"
                                                data-option-a="<?= htmlspecialchars($q['option_a']) ?>"
                                                data-option-b="<?= htmlspecialchars($q['option_b']) ?>"
                                                data-option-c="<?= htmlspecialchars($q['option_c']) ?>"
                                                data-option-d="<?= htmlspecialchars($q['option_d']) ?>"
                                                data-correct="<?= $q['correct_answer'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="delete-btn text-red-300 hover:text-white transition-colors" data-id="<?= $q['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
    
                                    <div class="space-y-3 ml-6">
                                        <div class="space-y-3 ml-6">
                                        <?php if ($q['question_type'] === 'true_false'): ?>
                                            <div class="<?= $q['correct_answer'] === 'true' ? 'correct-answer' : '' ?> px-3 py-2 rounded border border-white/10">
                                                <input type="radio" disabled <?= $q['correct_answer'] === 'true' ? 'checked' : '' ?> 
                                                       class="custom-radio">
                                                <label>True</label>
                                            </div>
                                            <div class="<?= $q['correct_answer'] === 'false' ? 'correct-answer' : '' ?> px-3 py-2 rounded border border-white/10">
                                                <input type="radio" disabled <?= $q['correct_answer'] === 'false' ? 'checked' : '' ?> 
                                                       class="custom-radio">
                                                <label>False</label>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach (['a', 'b', 'c', 'd'] as $option): ?>
                                                <?php if (!empty($q['option_'.$option])): ?>
                                                    <div class="<?= $q['correct_answer'] === $option ? 'correct-answer' : '' ?> px-3 py-2 rounded border border-white/10">
                                                        <input type="radio" disabled 
                                                               <?= $q['correct_answer'] === $option ? 'checked' : '' ?>
                                                               class="custom-radio">
                                                        <label><?= strtoupper($option) ?>. <?= htmlspecialchars($q['option_'.$option]) ?></label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Di bagian card pertanyaan: -->
                                    <div class="flex justify-between items-center mt-3 text-xs">
                                        <span class="bg-white/10 px-2 py-1 rounded">
                                            <i class="far fa-clock mr-1"></i> <?= $q['answer_time'] ?> detik
                                        </span>
                                        <span class="bg-white/10 px-2 py-1 rounded">
                                            <i class="fas fa-star mr-1"></i> <?= $q['base_points'] ?> poin
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Music Player -->
    <div class="fixed bottom-6 right-6 z-50">
        <div id="musicPlayer" class="glass-panel rounded-full px-4 py-2 flex items-center gap-3 shadow-xl hover:shadow-2xl transition-all cursor-pointer group">
            <button id="playPauseBtn" class="w-12 h-12 rounded-full flex items-center justify-center bg-purple-500/20 hover:bg-purple-500/30 transition-all relative overflow-hidden">
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
                    <?= $playlist[$music_settings['current_track']]['display_name'] ?? 'No track selected' ?>
                </div>
                <div id="trackInfo" class="text-xs opacity-70 truncate">
                    <?= ($music_settings['current_track'] + 1) ?>/<?= count($playlist) ?> • 
                    <span id="musicTime">0:00</span>
                </div>
            </div>
        </div>
    </div>

    <audio id="backgroundMusic"></audio>

    <div id="editModal" class="modal-overlay">
    <div class="glass-panel rounded-xl w-full max-w-2xl mx-4" onclick="event.stopPropagation();">
        <form id="editQuestionForm" class="p-6">
            <h3 class="font-bold text-xl mb-4">Edit Pertanyaan</h3>
            <input type="hidden" id="editQuestionId" name="question_id">
            
            <div class="mb-4">
                <label for="editQuestionText" class="block text-sm font-medium mb-1">Teks Pertanyaan</label>
                <textarea id="editQuestionText" name="question_text" rows="3" class="w-full bg-white/10 rounded-md p-2 border border-white/20 focus:ring-2 focus:ring-purple-400 focus:outline-none"></textarea>
            </div>

            <div id="editOptionsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="editOptionA" class="block text-sm font-medium mb-1">Pilihan A</label>
                    <input type="text" id="editOptionA" name="option_a" class="w-full bg-white/10 rounded-md p-2 border border-white/20">
                </div>
                <div>
                    <label for="editOptionB" class="block text-sm font-medium mb-1">Pilihan B</label>
                    <input type="text" id="editOptionB" name="option_b" class="w-full bg-white/10 rounded-md p-2 border border-white/20">
                </div>
                <div>
                    <label for="editOptionC" class="block text-sm font-medium mb-1">Pilihan C</label>
                    <input type="text" id="editOptionC" name="option_c" class="w-full bg-white/10 rounded-md p-2 border border-white/20">
                </div>
                <div>
                    <label for="editOptionD" class="block text-sm font-medium mb-1">Pilihan D</label>
                    <input type="text" id="editOptionD" name="option_d" class="w-full bg-white/10 rounded-md p-2 border border-white/20">
                </div>
            </div>

            <div class="mb-4">
                 <label for="editCorrectAnswer" class="block text-sm font-medium mb-1">Jawaban Benar</label>
                 <select id="editCorrectAnswer" name="correct_answer" class="w-full bg-white/10 rounded-md p-2 border border-white/20">
                    </select>
            </div>
            
            <div class="flex justify-end gap-4 mt-6">
                <button type="button" id="cancelEditBtn" class="px-4 py-2 rounded-md bg-white/10 hover:bg-white/20 transition-colors">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-md bg-purple-500 hover:bg-purple-600 transition-colors font-semibold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div> 

    <script>
        // Enhanced Music Player
        const musicPlayer = {
            audio: document.getElementById('backgroundMusic'),
            playPauseBtn: document.getElementById('playPauseBtn'),
            nowPlaying: document.getElementById('nowPlaying'),
            trackInfo: document.getElementById('trackInfo'),
            musicWave: document.getElementById('musicWave'),
            playIcon: document.getElementById('playIcon'),
            pauseIcon: document.getElementById('pauseIcon'),
            musicTime: document.getElementById('musicTime'),
            playlist: <?= json_encode($playlist) ?>,
            currentTrack: <?= $music_settings['current_track'] ?? 0 ?>,
            isPlaying: <?= $music_settings['is_music_on'] ? 1 : 0 ?>,
            
            init() {
                if (this.playlist.length > 0) {
                    this.loadTrack();
                    if (this.isPlaying) {
                        this.play();
                    }
                }
                
                // Event listeners
                this.playPauseBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.togglePlay();
                });
                
                this.audio.addEventListener('ended', () => this.nextTrack());
                this.audio.addEventListener('timeupdate', () => this.updateTime());
                
                document.getElementById('musicPlayer').addEventListener('click', () => {
                    this.nextTrack();
                    if (!this.isPlaying) this.play();
                });
            },
            
            loadTrack() {
                if (this.playlist.length === 0) return;
                
                const track = this.playlist[this.currentTrack];
                this.audio.src = track.file_path;
                this.nowPlaying.textContent = track.display_name;
                this.trackInfo.innerHTML = `${this.currentTrack + 1}/${this.playlist.length} • <span id="musicTime">0:00</span>`;
                
                // Update settings in background
                this.updateSettings();
            },
            
            play() {
                this.audio.play()
                    .then(() => {
                        this.isPlaying = true;
                        this.playIcon.style.display = 'none';
                        this.pauseIcon.style.display = 'block';
                        this.musicWave.style.display = 'flex';
                        this.updateSettings();
                    })
                    .catch(error => {
                        console.error("Playback failed:", error);
                    });
            },
            
            pause() {
                this.audio.pause();
                this.isPlaying = false;
                this.playIcon.style.display = 'block';
                this.pauseIcon.style.display = 'none';
                this.musicWave.style.display = 'none';
                this.updateSettings();
            },
            
            togglePlay() {
                this.isPlaying ? this.pause() : this.play();
            },
            
            nextTrack() {
                if (this.playlist.length === 0) return;
                
                this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
                this.loadTrack();
                if (this.isPlaying) this.play();
                
                // Small celebration for new track
                if (this.playlist.length > 1) {
                    confetti({
                        particleCount: 30,
                        spread: 60,
                        origin: { y: 0.6 }
                    });
                }
            },
            
            updateTime() {
                const minutes = Math.floor(this.audio.currentTime / 60);
                const seconds = Math.floor(this.audio.currentTime % 60);
                this.musicTime.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            },
            
            updateSettings() {
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

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            musicPlayer.init();
            
            // Animate cards on load
            const cards = document.querySelectorAll('.question-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.05}s`;
            });

            // ▼▼▼ TAMBAHKAN KODE INI DI DALAM document.addEventListener('DOMContentLoaded', ...) ▼▼▼

const modal = document.getElementById('editModal');
const mainContent = document.querySelector('main'); // Target area yang lebih luas untuk event

mainContent.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-btn');
    const deleteBtn = e.target.closest('.delete-btn');

    if (editBtn) {
        handleEdit(editBtn);
    }
    if (deleteBtn) {
        handleDelete(deleteBtn);
    }
});

function handleEdit(button) {
    const data = button.dataset;
    document.getElementById('editQuestionId').value = data.id;
    document.getElementById('editQuestionText').value = data.text;
    
    const questionType = data.type;
    const optionsContainer = document.getElementById('editOptionsContainer');
    const correctAnswerSelect = document.getElementById('editCorrectAnswer');
    
    correctAnswerSelect.innerHTML = ''; // Kosongkan pilihan sebelumnya

    if (questionType === 'multiple_choice') {
        optionsContainer.style.display = 'grid';
        document.getElementById('editOptionA').value = data.optionA;
        document.getElementById('editOptionB').value = data.optionB;
        document.getElementById('editOptionC').value = data.optionC;
        document.getElementById('editOptionD').value = data.optionD;

        ['a', 'b', 'c', 'd'].forEach(opt => {
            const optionEl = document.createElement('option');
            optionEl.value = opt;
            optionEl.textContent = `Pilihan ${opt.toUpperCase()}`;
            if (data.correct === opt) {
                optionEl.selected = true;
            }
            correctAnswerSelect.appendChild(optionEl);
        });
    } else { // true_false
        optionsContainer.style.display = 'none';
        ['true', 'false'].forEach(opt => {
             const optionEl = document.createElement('option');
             optionEl.value = opt;
             optionEl.textContent = opt.charAt(0).toUpperCase() + opt.slice(1);
             if (data.correct === opt) {
                optionEl.selected = true;
             }
             correctAnswerSelect.appendChild(optionEl);
        });
    }
    
    modal.classList.add('active');
}

function handleDelete(button) {
    const questionId = button.dataset.id;
    if (confirm('Apakah Anda yakin ingin menghapus pertanyaan ini? Aksi ini tidak dapat dibatalkan.')) {
        fetch('delete_question_survival.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: questionId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pertanyaan berhasil dihapus!');
                const cardToRemove = document.getElementById(`question-card-${questionId}`);
                if (cardToRemove) {
                    cardToRemove.remove();
                }
            } else {
                alert('Gagal menghapus pertanyaan: ' + (data.message || 'Error tidak diketahui'));
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

// Logika untuk menutup modal
modal.addEventListener('click', () => modal.classList.remove('active'));
document.getElementById('cancelEditBtn').addEventListener('click', () => modal.classList.remove('active'));

// Menangani submit form edit
document.getElementById('editQuestionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    fetch('edit_question_survival.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            alert('Pertanyaan berhasil diperbarui!');
            location.reload(); // Muat ulang halaman untuk melihat perubahan
        } else {
            alert('Gagal memperbarui: ' + (result.message || 'Error tidak diketahui'));
        }
    })
    .catch(error => console.error('Error:', error));
});
        });
    </script>
</body>
</html>