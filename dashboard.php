<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Ambil data user dari database
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($user['username']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Game Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="logo.png" type="image/png">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'montserrat': ['Montserrat', 'sans-serif'],
                        'poppins': ['Poppins', 'sans-serif'],
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                            '50%': { transform: 'translateY(-10px) rotate(3deg)' },
                        }
                    }
                }
            }
        }
    </script>
    <style type="text/css">
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Poppins:wght@300;600&display=swap');
        
        .game-card {
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }
        .game-card:hover {
            transform: translateY(-5px) scale(1.02);
        }
        
        /* Animasi tombol musik */
        .music-btn {
            transition: all 0.3s ease;
        }
        .music-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body class="font-poppins bg-gradient-to-br from-blue-900 to-purple-900 text-white min-h-screen">
    <audio id="backgroundMusic"></audio> 
    
    <nav class="bg-white bg-opacity-10 backdrop-blur-md py-4 px-6 shadow-lg fixed w-full z-50">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-bible text-2xl text-yellow-400"></i>
                <span class="font-montserrat font-bold text-xl">Petualangan Iman</span>
            </div>
            <div class="flex items-center space-x-4">
                <button id="musicToggle" class="music-btn bg-white bg-opacity-20 p-2 rounded-full">
                    <i class="fas fa-music text-lg"></i>
                </button>
                <span class="hidden sm:inline">Selamat datang, <span class="font-semibold text-yellow-400"><?= $username ?></span></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full text-sm font-semibold transition">Logout</a>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-12 px-6 max-w-6xl mx-auto">
        <section class="mb-12 text-center">
            <h1 class="font-montserrat text-3xl md:text-4xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-orange-500">
                Pilih Menu
            </h1>
            <p class="max-w-2xl mx-auto text-lg opacity-90">
                Temukan berbagai permainan seru dan fitur lainnya
            </p>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <a href="all_games.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-gradient-to-r from-purple-500 to-pink-500 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-gamepad text-2xl text-white"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Semua Game</h3>
                    <p class="text-sm opacity-80">Mainkan semua game yang tersedia</p>
                </div>
                <div class="bg-gradient-to-r from-purple-500 to-pink-500 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Jelajahi Semua →
                </div>
            </a>

            <a href="quiz.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-yellow-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-puzzle-piece text-2xl text-yellow-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Puzzle Kata</h3>
                    <p class="text-sm opacity-80">Susun potongan ayat Alkitab menjadi utuh kembali</p>
                </div>
                <div class="bg-yellow-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="matching.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-blue-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-object-group text-2xl text-blue-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Cocokkan Ayat</h3>
                    <p class="text-sm opacity-80">Pasangkan ayat dengan kitab dan pasalnya</p>
                </div>
                <div class="bg-blue-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="buzzer.html" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-bell text-2xl text-red-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Tombol Buzzer</h3>
                    <p class="text-sm opacity-80">Lomba cepat tekan buzzer untuk menjawab</p>
                </div>
                <div class="bg-red-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="scratch.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-green-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-qrcode text-2xl text-green-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Scratch Alkitab</h3>
                    <p class="text-sm opacity-80">Gores untuk menemukan ayat tersembunyi</p>
                </div>
                <div class="bg-green-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="dashboard_category.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-purple-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-question-circle text-2xl text-purple-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Kuis Alkitab</h3>
                    <p class="text-sm opacity-80">Jawab pertanyaan seputar Alkitab</p>
                </div>
                <div class="bg-purple-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="quiz.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-purple-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-question-circle text-2xl text-purple-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Kuis Alkitab 2</h3>
                    <p class="text-sm opacity-80">Jawab pertanyaan seputar Alkitab</p>
                </div>
                <div class="bg-purple-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="quizz.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-purple-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-question-circle text-2xl text-purple-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Kuis Alkitab 3</h3>
                    <p class="text-sm opacity-80">Jawab pertanyaan seputar Alkitab</p>
                </div>
                <div class="bg-purple-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="wordsearch.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-search text-2xl text-red-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Cari Kata</h3>
                    <p class="text-sm opacity-80">Temukan kata-kata Alkitab dalam grid</p>
                </div>
                <div class="bg-red-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="memory.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-pink-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-brain text-2xl text-pink-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Game Ingatan</h3>
                    <p class="text-sm opacity-80">Temukan pasangan yang cocok</p>
                </div>
                <div class="bg-pink-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Mainkan Sekarang →
                </div>
            </a>

            <a href="survival.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40 transition-all duration-300 hover:scale-[1.02]">
                <div class="p-6">
                    <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-skull-crossbones text-2xl text-red-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Survival Quiz</h3>
                    <p class="text-sm opacity-80">Bertahanlah sampai akhir dengan menjawab benar</p>
                </div>
                <div class="bg-red-400 bg-opacity-20 px-4 py-2 text-sm font-semibold flex items-center justify-center">
                    <span>Mainkan Sekarang</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </div>
            </a>

            <a href="dragdrop.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40 transition-all duration-300 hover:scale-[1.02]">
                <div class="p-6">
                    <div class="bg-purple-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-arrows-alt text-2xl text-purple-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Drag & Drop Quiz</h3>
                    <p class="text-sm opacity-80">Seret dan lepas jawaban ke area yang benar</p>
                </div>
                <div class="bg-purple-400 bg-opacity-20 px-4 py-2 text-sm font-semibold flex items-center justify-center">
                    <span>Mainkan Sekarang</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </div>
            </a>

            <a href="kelompok.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-indigo-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-users text-2xl text-indigo-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Pilih Kelompok</h3>
                    <p class="text-sm opacity-80">Buat atau gabung kelompok permainan</p>
                </div>
                <div class="bg-indigo-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Akses Kelompok →
                </div>
            </a>

            <a href="skor.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-amber-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-calculator text-2xl text-amber-400"></i>
                    </div>
                    <h3 class="font-montserrat font-bold text-xl mb-2">Hitung Skor</h3>
                    <p class="text-sm opacity-80">Lihat dan hitung skor permainan</p>
                </div>
                <div class="bg-amber-400 bg-opacity-20 px-4 py-2 text-sm font-semibold">
                    Lihat Skor →
                </div>
            </a>          
        </div>

    </main>

    <footer class="bg-black bg-opacity-30 py-6 px-6">
        <div class="max-w-6xl mx-auto text-center text-sm opacity-70">
            <p>© 2025 Petualangan Iman - Game Alkitab Interaktif</p>
            <p class="mt-2">"Tetapi carilah dahulu Kerajaan Allah dan kebenarannya, maka semuanya itu akan ditambahkan kepadamu." - Matius 6:33</p>
        </div>
    </footer>

    <script>
        // Sistem Musik Background
        const musicPlayer = document.getElementById('backgroundMusic');
        const musicToggle = document.getElementById('musicToggle');
        let isMusicPlaying = false;
        
        // Playlist musik
        const playlist = [
            'music/Segala_Perkara.mp3',
            'music/background2.mp3',
            'music/background3.mp3'
        ];
        let currentTrack = 0;
        
        // Fungsi untuk memutar musik
        function playMusic() {
            if (playlist.length === 0) return;
            
            musicPlayer.src = playlist[currentTrack];
            musicPlayer.load(); // Memuat ulang audio jika src berubah
            musicPlayer.play()
                .then(() => {
                    isMusicPlaying = true;
                    musicToggle.innerHTML = '<i class="fas fa-volume-up text-lg"></i>';
                })
                .catch(error => {
                    console.error("Autoplay prevented:", error);
                    // Tampilkan UI untuk interaksi user
                });
        }
        
        // Fungsi untuk mengganti track
        function nextTrack() {
            currentTrack = (currentTrack + 1) % playlist.length;
            playMusic();
        }
        
        // Event listener untuk tombol musik
        musicToggle.addEventListener('click', () => {
            if (isMusicPlaying) {
                musicPlayer.pause();
                musicToggle.innerHTML = '<i class="fas fa-volume-mute text-lg"></i>';
            } else {
                playMusic();
            }
            isMusicPlaying = !isMusicPlaying;
        });
        
        // Ketika track selesai, mainkan track berikutnya
        // Pastikan atribut 'loop' tidak ada pada tag <audio> agar event 'ended' bisa terpicu
        musicPlayer.addEventListener('ended', nextTrack);
        
        // Coba mulai musik secara otomatis (mungkin membutuhkan interaksi user)
        // Disarankan untuk memulai musik setelah ada interaksi user, seperti klik tombol.
        // Ini adalah fallback jika browser memblokir autoplay.
        document.addEventListener('DOMContentLoaded', () => {
             // Coba putar musik saat halaman dimuat, ini mungkin diblokir browser
            playMusic(); 
        });

        // Event listener untuk memastikan musik diputar setelah interaksi user pertama (jika autoplay diblokir)
        document.addEventListener('click', () => {
            if (!isMusicPlaying && playlist.length > 0) {
                playMusic();
            }
        }, { once: true }); // Hanya jalankan sekali setelah klik pertama
    </script>
</body>
</html>