<?php

session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM categories WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

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
    <title>Kategori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="icon" href="logo.png" type="image/png">

    <style>
        @keyframes gradientMove {
            0% {
                background-position: 0% 50%;
            }
            100% {
                background-position: 100% 50%;
            }
        }

        .animated-gradient-text {
            background: linear-gradient(270deg, #f5af19, #f12711, #f5af19);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientMove 4s linear infinite;
        }

        /* Music player styles */
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

        /* Rest of your existing styles... */
        .gradient-text {
            background: linear-gradient(to left, #1F1C18, #8E0E00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }

        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
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

        .glow-card {
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .glow-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(100, 255, 218, 0.3);
        }

        .gradient-border {
            position: relative;
            border-radius: 0.5rem;
        }

        .gradient-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            z-index: -1;
            background: linear-gradient(45deg, #ff00cc, #3333ff, #00ccff);
            background-size: 200% 200%;
            border-radius: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s, background-position 5s;
        }

        .gradient-border:hover::before {
            opacity: 1;
            animation: gradientMove 3s ease infinite;
        }

        .flex.items-center button {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Fullscreen styles */
        body:-webkit-full-screen {
            background-color: #0f172a; /* Or your desired fullscreen background */
        }
        body:-moz-full-screen {
            background-color: #0f172a;
        }
        body:-ms-fullscreen {
            background-color: #0f172a;
        }
        body:fullscreen {
            background-color: #0f172a;
        }
    </style>
</head>

<body class="relative h-screen bg-gray-900 overflow-hidden">
<!-- Perintah memasukan lagu ke database -->
<!-- INSERT INTO background_music (file_name, file_path, display_name) 
VALUES 
    ('Segala_Perkara.mp3', 'music/Segala_Perkara.mp3', 'Lagu Rohani'); -->

<div class="bg-particles" id="particles"></div>

    <div class="relative z-10 w-full h-full p-6">
        <div class="p-6 rounded-lg w-full h-full">
            <div class="flex justify-between items-center mb-6">
                <a href="dashboard.php" class="text-6xl font-bold animated-gradient-text hover:scale-105 transition-transform duration-300">
                    KATEGORI
                </a>
                <div class="flex items-center space-x-4">
                    <button type="button" onclick="toggleFullscreen()" id="fullscreenButton"
                            class="rounded-lg p-2 font-black bg-gradient-to-r from-purple-500 to-pink-500 text-white hover:from-purple-600 hover:to-pink-600 font-extrabold transition-all duration-300 hover:scale-110 flex items-center justify-center w-10 h-10">
                        <i class="fas fa-expand" id="fullscreenIcon"></i>
                    </button>

                    <button onclick="document.getElementById('modal').classList.remove('hidden')" 
                            class="rounded-lg p-2 font-black bg-gradient-to-r from-cyan-500 to-blue-500 text-white hover:from-cyan-600 hover:to-blue-600 font-extrabold transition-all duration-300 hover:scale-110 flex items-center justify-center w-10 h-10">
                        <svg xmlns="http://www.w3.org/2000/svg" 
                            class="h-6 w-6 font-black"  
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor" 
                            stroke-width="3">
                            <path stroke-linecap="round" 
                                stroke-linejoin="round" 
                                d="M12 4v16m8-8H4" />
                        </svg>
                    </button>

                    <a href="logout.php" title="Keluar" 
                       class="rounded-lg p-2 bg-gradient-to-r from-red-500 to-rose-500 text-white hover:from-red-600 hover:to-rose-600 font-extrabold transition-all duration-300 hover:scale-110 flex items-center justify-center w-10 h-10">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
                <div id="notif" class="mb-4 p-3 bg-gradient-to-r from-green-400 to-blue-500 text-white rounded-lg shadow-lg transform transition-all duration-500 animate-bounce">
                    Kategori berhasil dihapus.
                </div>
                <script>
                    setTimeout(() => document.getElementById('notif')?.remove(), 2000);
                </script>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
                <div id="notif" class="mb-4 p-3 bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-lg shadow-lg transform transition-all duration-500 animate-bounce">
                    Kategori berhasil diperbarui.
                </div>
                <script>
                    setTimeout(() => document.getElementById('notif')?.remove(), 2000);
                </script>
            <?php endif; ?>

            <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 backdrop-blur-sm">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-lg shadow-xl w-full max-w-sm border border-gray-700 transform transition-all duration-300 animate-fade-in">
                    <h2 class="text-xl font-semibold mb-4 text-white">Tambah Kategori</h2>
                    <form action="add_category.php" method="POST">
                        <input type="text" name="category_name" placeholder="Nama Kategori" required 
                               class="w-full px-3 py-2 border border-gray-600 rounded mb-4 bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500 transition-colors duration-300">
                                Batal
                            </button>
                            <button type="submit" 
                                    class="bg-gradient-to-r from-blue-500 to-cyan-500 text-white px-4 py-2 rounded hover:from-blue-600 hover:to-cyan-600 transition-all duration-300">
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 backdrop-blur-sm">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 p-6 rounded-lg shadow-xl w-full max-w-sm border border-gray-700 transform transition-all duration-300 animate-fade-in">
                    <h2 class="text-xl font-semibold mb-4 text-white">Edit Kategori</h2>
                    <form action="edit_category.php" method="POST">
                        <input type="hidden" name="category_id" id="editCategoryId">
                        <input type="text" name="category_name" id="editCategoryName" required 
                               class="w-full px-3 py-2 border border-gray-600 rounded mb-4 bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-500 transition-colors duration-300">
                                Batal
                            </button>
                            <button type="submit" 
                                    class="bg-gradient-to-r from-yellow-500 to-amber-500 text-white px-4 py-2 rounded hover:from-yellow-600 hover:to-amber-600 transition-all duration-300">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-6">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="gradient-border bg-gray-800 p-4 rounded-lg shadow-lg flex flex-col justify-between glow-card hover:bg-gray-700 transition-all duration-300">
                            <div class="flex justify-between items-start">
                                <a href="category.php?id=<?= $row['id'] ?>" class="text-2xl font-bold hover:no-underline break-words text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500 hover:from-cyan-300 hover:to-blue-400 transition-all duration-300">
                                    <?= htmlspecialchars($row['name']) ?>
                                </a>

                                <div class="flex items-center gap-2 mt-2">
                                    <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" 
                                        class="text-yellow-400 hover:text-yellow-300 transition-colors duration-300" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form action="delete_category.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                                        <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-300 transition-colors duration-300" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-400 col-span-full text-center py-10">Tambahkan satu untuk memulai.</p>
                <?php endif; ?>
            </div>
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
            
            // Create animated particles
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size between 2px and 10px
                const size = Math.random() * 8 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random animation duration
                particle.style.animationDuration = `${Math.random() * 20 + 10}s`;
                particle.style.animationDelay = `${Math.random() * 5}s`;
                
                particlesContainer.appendChild(particle);
            }
        });

        function openEditModal(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editModal').classList.remove('hidden');
        }

        // --- Fullscreen Functions ---
        function toggleFullscreen() {
            const element = document.documentElement; // Target the entire HTML document
            
            if (document.fullscreenElement) {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) { // Firefox
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { // IE/Edge
                    document.msExitFullscreen();
                }
            } else {
                // Enter fullscreen
                if (element.requestFullscreen) {
                    element.requestFullscreen();
                } else if (element.mozRequestFullScreen) { // Firefox
                    element.mozRequestFullScreen();
                } else if (element.webkitRequestFullscreen) { // Chrome, Safari and Opera
                    element.webkitRequestFullscreen();
                } else if (element.msRequestFullscreen) { // IE/Edge
                    element.msRequestFullscreen();
                }
            }
        }

        // Listen for fullscreen change events to update button text/icon
        document.addEventListener('fullscreenchange', updateFullscreenButton);
        document.addEventListener('mozfullscreenchange', updateFullscreenButton);
        document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
        document.addEventListener('msfullscreenchange', updateFullscreenButton);

        function updateFullscreenButton() {
            const fullscreenButton = document.getElementById('fullscreenButton');
            const fullscreenIcon = document.getElementById('fullscreenIcon');
            // Remove text from the button, only keep the icon
            fullscreenButton.textContent = ''; 
            fullscreenButton.appendChild(fullscreenIcon); // Re-add the icon if it was removed
            
            if (document.fullscreenElement || document.mozFullScreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                fullscreenIcon.classList.remove('fa-expand');
                fullscreenIcon.classList.add('fa-compress');
            } else {
                fullscreenIcon.classList.remove('fa-compress');
                fullscreenIcon.classList.add('fa-expand');
            }
        }

        // Initial call to set the correct button state when page loads
        document.addEventListener('DOMContentLoaded', updateFullscreenButton);
    </script>
</body>
</html>