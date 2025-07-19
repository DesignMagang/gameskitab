<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$category_id = intval($_GET['id']);

// Cek kepemilikan kategori
$cek = $conn->query("SELECT * FROM categories WHERE id = $category_id AND user_id = $user_id");
if ($cek->num_rows == 0) {
    die("Jika tidak ditemukan atau bukan milik kamu.");
}

$questions = $conn->query("SELECT * FROM questions WHERE category_id = $category_id ORDER BY question_number ASC");

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
    <title>Pertanyaan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
        <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }

        /* Cosmic Background */
        body {
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            min-height: 100vh;
            overflow-x: hidden;
            color: white;
        }

        /* Stars Animation */
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

        /* Floating Particles */
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

        /* Card Styles */
        .question-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .question-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(100, 108, 255, 0.4);
            border: 1px solid rgba(100, 108, 255, 0.4);
        }

        /* Modal Styles */
        .modal-glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Answer Display Animation */
        .answer-container {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
        }

        .answer-container.show {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        /* Button Glow Effect */
        .btn-glow {
            box-shadow: 0 0 10px rgba(100, 108, 255, 0.5);
            transition: all 0.3s ease;
        }

        .btn-glow:hover {
            box-shadow: 0 0 20px rgba(100, 108, 255, 0.8);
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

        /* Styles for the new icon buttons */
        .icon-btn {
            width: 36px; /* Smaller size for action icons */
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px; /* Make them perfectly round */
            transition: all 0.2s ease-in-out;
            border: 1px solid rgba(255,255,255,0.2); /* Subtle border */
        }

        .icon-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(255,255,255,0.3);
        }

        .icon-btn.check-btn {
            background-color: rgba(16, 185, 129, 0.7); /* Green-500 equivalent */
        }

        .icon-btn.edit-btn {
            background-color: rgba(252, 211, 38, 0.7); /* Yellow-400 equivalent */
        }

        .icon-btn.delete-btn {
            background-color: rgba(239, 68, 68, 0.7); /* Red-500 equivalent */
        }

        .icon-btn.close-btn {
            background-color: rgba(107, 114, 128, 0.7); /* Gray-500 equivalent */
        }

        /* Modern text styles for question modal */
        #questionText {
            font-size: 2.5rem; /* Lebih besar */
            color: #ecf0f1; /* Abu-abu muda modern */
            margin-bottom: 1rem;
        }

        #answerContainer .text-xl {
            font-size: 1.8rem; /* Lebih besar */
            color: #d4edda; /* Hijau muda untuk jawaban */
        }

        #answerContainer .text-lg {
            font-size: 1.6rem;
            color: #a7edba;
        }
    </style>
</head>
<body class="p-6 relative overflow-hidden">
    <div class="stars"></div>
    <div class="particles" id="particles"></div>

    <div class="max-w-xl mx-auto modal-glass p-6 rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
            <div class="flex gap-3 flex-wrap items-center" id="navButtons">
                <a href="dashboard_category.php" title="Kembali"
                   class="btn-glow bg-gray-800 text-white w-10 h-10 rounded-lg flex items-center justify-center hover:bg-gray-700 transition-all">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <button onclick="document.getElementById('modal').classList.remove('hidden')"
                        title="Tambah Pertanyaan"
                        class="btn-glow bg-gradient-to-r from-green-500 to-emerald-500 text-white w-10 h-10 rounded-lg flex items-center justify-center hover:from-green-600 hover:to-emerald-600 transition-all">
                    <i class="fas fa-plus text-xl"></i>
                </button>
                <button onclick="resetQuestions()"
                        title="Reset"
                        class="btn-glow bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-900 w-10 h-10 rounded-lg flex items-center justify-center hover:from-yellow-500 hover:to-amber-600 transition-all">
                    <i class="fas fa-redo-alt text-xl"></i>
                </button>
                <button onclick="showDeleteModal()"
                        title="Hapus Semua"
                        class="btn-glow bg-gradient-to-r from-red-600 to-pink-600 text-white w-10 h-10 rounded-lg flex items-center justify-center hover:from-red-700 hover:to-pink-700 transition-all">
                    <i class="fas fa-trash-alt text-xl"></i>
                </button>
            </div>
        </div>

        <ul id="questionList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <?php while ($q = $questions->fetch_assoc()): ?>
                <li>
                    <button id="question-<?= $q['id'] ?>"
                        onclick="showQuestion('<?= htmlspecialchars($q['question_text'], ENT_QUOTES) ?>', <?= $q['id'] ?>, '<?= isset($q['answer']) ? htmlspecialchars($q['answer'], ENT_QUOTES) : '' ?>')"
                        class="question-card w-full h-24 flex items-center justify-center text-white font-bold text-xl rounded-xl transition-all duration-300">
                        Soal <?= $q['question_number'] ?>
                    </button>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="modal-glass p-6 rounded-xl max-w-sm w-full animate__animated animate__fadeIn">
            <p class="text-xl font-bold text-white mb-6">Yakin ingin menghapus semua pertanyaan?</p>
            <div class="flex justify-center gap-4">
                <button onclick="deleteAllQuestions()"
                        class="btn-glow bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-2 rounded-lg font-bold hover:from-red-700 hover:to-pink-700 transition-all">
                    Hapus
                </button>
                <button onclick="hideDeleteModal()"
                        class="btn-glow bg-gray-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-gray-700 transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <div id="deleteSuccess" class="fixed top-5 right-5 bg-gradient-to-r from-green-500 to-teal-500 text-white px-6 py-3 rounded-lg shadow-lg hidden z-50 animate__animated animate__bounceIn">
        Semua pertanyaan berhasil dihapus.
    </div>

    <div id="modal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="modal-glass p-6 rounded-xl max-w-sm w-full animate__animated animate__fadeIn">
            <h2 class="text-xl font-bold text-white mb-4">Tambah Pertanyaan</h2>
            <form action="add_question.php" method="POST" id="questionForm">
                <input type="hidden" name="category_id" value="<?= $category_id ?>">
                <textarea name="question_text" placeholder="Tulis pertanyaan..." required
                          class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

                <textarea name="answer" placeholder="Tulis jawaban (opsional)"
                          class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('modal').classList.add('hidden')"
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

    <div id="questionModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="modal-glass p-8 rounded-xl max-w-3xl w-full relative animate__animated animate__fadeIn">
            <div class="absolute top-4 right-4 flex space-x-2"> <button id="showAnswerBtn" onclick="toggleAnswer()" title="Tampilkan Jawaban"
                        class="icon-btn check-btn text-white">
                    <i class="fas fa-check"></i>
                </button>

                <button onclick="toggleEdit()" title="Edit"
                        class="icon-btn edit-btn text-gray-900">
                    <i class="fas fa-pencil-alt"></i>
                </button>

                <a id="deleteBtn" href="#" onclick="return confirm('Yakin ingin menghapus pertanyaan ini?')"
                   title="Hapus" class="icon-btn delete-btn text-white">
                    <i class="fas fa-trash"></i>
                </a>

                <button onclick="hideModal()" title="Tutup"
                        class="icon-btn close-btn text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="questionDisplay" class="mb-6">
                <p id="questionText" class="text-3xl mt-8 font-bold text-white break-words"></p>

                <div id="answerContainer" class="answer-container mt-6">
                    <div class="bg-gray-800 bg-opacity-50 p-4 rounded-lg border-l-4 border-green-500">
                        <h3 class="text-lg font-semibold text-green-400 mb-2">Jawaban:</h3>
                        <p id="answerText" class="text-xl"></p>
                    </div>
                </div>
            </div>

            <form id="editForm" class="hidden" method="POST" action="update_question.php">
                <input type="hidden" name="question_id" id="editQuestionId">
                <input type="hidden" name="category_id" value="<?= $category_id ?>">
                <textarea name="question_text" id="editQuestionText"
                          class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-yellow-500"></textarea>

                <textarea name="answer_text" id="editAnswerText" placeholder="Tulis jawaban"
                          class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>

                <button type="submit"
                        class="btn-glow bg-gradient-to-r from-yellow-500 to-amber-500 text-gray-900 px-6 py-2 rounded-lg font-bold hover:from-yellow-600 hover:to-amber-600 transition-all w-full">
                    Simpan Perubahan
                </button>
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
        // Create animated particles
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                // Random size between 5px and 15px
                const size = Math.random() * 10 + 5;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;

                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;

                // Random animation duration and delay
                particle.style.animationDuration = `${Math.random() * 15 + 10}s`;
                particle.style.animationDelay = `${Math.random() * 5}s`;

                particlesContainer.appendChild(particle);
            }

            // Form submission handler
            document.getElementById('questionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                // Here you would typically send the form via AJAX or let it submit normally
                this.submit();
            });
        });

        let currentAnswer = '';

        function showQuestion(text, id, answer = '') {
            document.getElementById('questionModal').classList.remove('hidden');
            document.getElementById('questionText').textContent = text;
            document.getElementById('editQuestionText').value = text;
            document.getElementById('editQuestionId').value = id;
            document.getElementById('deleteBtn').href = 'delete_question.php?id=' + id + '&category=<?= $category_id ?>';

            // Store and handle answer
            currentAnswer = answer;
            document.getElementById('answerText').textContent = answer || 'Tidak ada jawaban tersimpan';
            document.getElementById('editAnswerText').value = answer;

            // Hide answer container by default
            document.getElementById('answerContainer').classList.remove('show');

            var questionItem = document.getElementById('question-' + id);
            if (questionItem) {
                questionItem.style.display = 'none';
                questionItem.classList.add('hidden-question');
            }

            document.getElementById('navButtons').style.display = 'none';
            document.getElementById('editForm').classList.add('hidden');
            document.getElementById('questionDisplay').classList.remove('hidden');
        }

        function toggleAnswer() {
            const answerContainer = document.getElementById('answerContainer');
            answerContainer.classList.toggle('show');

            // Smooth scroll to bottom if answer is long
            if (answerContainer.classList.contains('show')) {
                setTimeout(() => {
                    answerContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 300);
            }
        }

        function hideModal() {
            document.getElementById('questionModal').classList.add('hidden');
            document.getElementById('navButtons').style.display = 'flex';
        }

        function toggleEdit() {
            const editForm = document.getElementById('editForm');
            const questionDisplay = document.getElementById('questionDisplay');

            editForm.classList.toggle('hidden');
            questionDisplay.classList.toggle('hidden');

            // Hide answer when switching to edit mode
            if (!editForm.classList.contains('hidden')) {
                document.getElementById('answerContainer').classList.remove('show');
            }
        }

        function resetQuestions() {
            const hiddenItems = document.querySelectorAll('.hidden-question');
            hiddenItems.forEach(item => {
                item.style.display = 'block';
                item.classList.remove('hidden-question');
            });
        }

        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function deleteAllQuestions() {
            fetch('delete_all_questions.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'category_id': '<?= $category_id ?>'
                }),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(res => {
                if (res.ok) {
                    // Immediately reload the page after successful deletion
                    window.location.reload();
                } else {
                    console.error('Error deleting questions');
                }
            })
            .catch(error => {
                console.error('Network error:', error);
            });
        }

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

                const track = this.playlist[(this.currentTrack) % this.playlist.length];
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
        musicPlayer.init();
    </script>
</body>
</html>