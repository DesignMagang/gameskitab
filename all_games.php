<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Get user data
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

// Ambil semua sesi survival dari database
$survival_sessions_query = $conn->query("SELECT id, session_name FROM survival_sessions ORDER BY id DESC");
$survival_sessions = [];
if ($survival_sessions_query) {
    while ($row = $survival_sessions_query->fetch_assoc()) {
        $survival_sessions[] = $row;
    }
} else {
    error_log("Error fetching survival sessions: " . $conn->error);
}

// Ambil semua sesi matching game yang dibuat oleh user ini
$matchingSessions = [];
$userId = $_SESSION['user_id']; // Pastikan $userId terdefinisi di sini
$stmtMatching = $conn->prepare("SELECT session_id, session_name, created_at FROM sessions WHERE created_by = ? ORDER BY created_at DESC");
$stmtMatching->bind_param("i", $userId);
$stmtMatching->execute();
$resultMatching = $stmtMatching->get_result();

while ($row = $resultMatching->fetch_assoc()) {
    $matchingSessions[] = $row;
}
$stmtMatching->close();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Game - Petualangan Iman</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Poppins:wght@300;600&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a202c, #2d3748);
            min-height: 100vh;
        }

        .game-card {
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }

        .game-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .category-badge {
            transition: all 0.3s ease;
        }

        .category-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="text-white">
    <nav class="bg-white bg-opacity-10 backdrop-blur-md py-4 px-6 shadow-lg fixed w-full z-50">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <a href="dashboard.php" class="flex items-center space-x-2">
                    <i class="fas fa-arrow-left text-xl"></i>
                    <i class="fas fa-bible text-2xl text-yellow-400"></i>
                    <span class="font-bold text-xl">Petualangan Iman</span>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden sm:inline">Halo, <span class="font-semibold text-yellow-400"><?= $username ?></span></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full text-sm font-semibold transition">Logout</a>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-12 px-6 max-w-6xl mx-auto">
        <section class="mb-12 text-center">
            <h1 class="text-3xl md:text-4xl font-bold mb-4 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-500">
                <i class="fas fa-gamepad mr-2"></i> Semua Game
            </h1>
            <p class="max-w-2xl mx-auto text-lg opacity-90">
                Pilih dan mainkan semua game yang tersedia
            </p>

            <div class="flex flex-wrap justify-center gap-2 mt-6">
                <button class="category-badge bg-purple-500 hover:bg-purple-600 px-4 py-2 rounded-full text-sm font-semibold transition active">Semua</button>
                <button class="category-badge bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-full text-sm font-semibold transition">Kuis</button>
                <button class="category-badge bg-green-500 hover:bg-green-600 px-4 py-2 rounded-full text-sm font-semibold transition">Teka-Teki</button>
                <button class="category-badge bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full text-sm font-semibold transition">Kompetisi</button>
                <button class="category-badge bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded-full text-sm font-semibold transition">Kelompok</button>
            </div>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="quiz.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-yellow-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-puzzle-piece text-2xl text-yellow-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Puzzle Kata</h3>
                    <p class="text-sm opacity-80 mb-4">Susun potongan ayat Alkitab menjadi utuh kembali</p>
                    <div class="flex justify-center">
                        <span class="bg-yellow-400 bg-opacity-20 text-yellow-400 text-xs px-3 py-1 rounded-full">Teka-Teki</span>
                    </div>
                </div>
            </a>

            <a href="play.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-blue-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-object-group text-2xl text-blue-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Cocokkan Ayat</h3>
                    <p class="text-sm opacity-80 mb-4">Pasangkan ayat dengan kitab dan pasalnya</p>
                    <div class="flex justify-center">
                        <span class="bg-blue-400 bg-opacity-20 text-blue-400 text-xs px-3 py-1 rounded-full">Kuis</span>
                    </div>
                </div>
            </a>

            <a href="buzzer.html" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-bell text-2xl text-red-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Game Buzzer</h3>
                    <p class="text-sm opacity-80 mb-4">Lomba cepat tekan buzzer untuk menjawab</p>
                    <div class="flex justify-center">
                        <span class="bg-red-400 bg-opacity-20 text-red-400 text-xs px-3 py-1 rounded-full">Kompetisi</span>
                    </div>
                </div>
            </a>

            <a href="scratch.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-green-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-qrcode text-2xl text-green-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Scratch Alkitab</h3>
                    <p class="text-sm opacity-80 mb-4">Gores untuk menemukan ayat tersembunyi</p>
                    <div class="flex justify-center">
                        <span class="bg-green-400 bg-opacity-20 text-green-400 text-xs px-3 py-1 rounded-full">Teka-Teki</span>
                    </div>
                </div>
            </a>

            <a href="dashboard_category.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-purple-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-question-circle text-2xl text-purple-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Kuis Alkitab</h3>
                    <p class="text-sm opacity-80 mb-4">Jawab pertanyaan seputar Alkitab</p>
                    <div class="flex justify-center">
                        <span class="bg-purple-400 bg-opacity-20 text-purple-400 text-xs px-3 py-1 rounded-full">Kuis</span>
                    </div>
                </div>
            </a>

            <a href="wordsearch.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-search text-2xl text-red-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Cari Kata</h3>
                    <p class="text-sm opacity-80 mb-4">Temukan kata-kata Alkitab dalam grid</p>
                    <div class="flex justify-center">
                        <span class="bg-red-400 bg-opacity-20 text-red-400 text-xs px-3 py-1 rounded-full">Teka-Teki</span>
                    </div>
                </div>
            </a>

            <a href="memory.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-pink-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-brain text-2xl text-pink-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Game Ingatan</h3>
                    <p class="text-sm opacity-80 mb-4">Temukan pasangan yang cocok</p>
                    <div class="flex justify-center">
                        <span class="bg-pink-400 bg-opacity-20 text-pink-400 text-xs px-3 py-1 rounded-full">Teka-Teki</span>
                    </div>
                </div>
            </a>

            <a href="kelompok.php" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                <div class="p-6">
                    <div class="bg-indigo-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-users text-2xl text-indigo-400"></i>
                    </div>
                    <h3 class="font-bold text-xl mb-2">Pilih Kelompok</h3>
                    <p class="text-sm opacity-80 mb-4">Buat atau gabung kelompok permainan</p>
                    <div class="flex justify-center">
                        <span class="bg-indigo-400 bg-opacity-20 text-indigo-400 text-xs px-3 py-1 rounded-full">Kelompok</span>
                    </div>
                </div>
            </a>

            <?php if (!empty($survival_sessions)): ?>
                <?php foreach ($survival_sessions as $session): ?>
                    <a href="play_survival.php?session=<?= htmlspecialchars($session['id']) ?>" class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                        <div class="p-6">
                            <div class="bg-red-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <i class="fas fa-skull-crossbones text-2xl text-red-400"></i>
                            </div>
                            <h3 class="font-bold text-xl mb-2">Survival Quiz: <?= htmlspecialchars($session['session_name']) ?></h3>
                            <p class="text-sm opacity-80 mb-4">Bertahanlah sampai akhir dengan menjawab benar</p>
                            <div class="flex justify-center">
                                <span class="bg-red-400 bg-opacity-20 text-red-400 text-xs px-3 py-1 rounded-full">Kompetisi</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-gray-400 sm:col-span-2 lg:col-span-3 p-6">
                    <p>Tidak ada sesi Survival Quiz yang tersedia saat ini.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($matchingSessions)): ?>
                <?php foreach ($matchingSessions as $session): ?>
                    <div class="game-card bg-white bg-opacity-10 rounded-xl overflow-hidden shadow-lg backdrop-blur-sm border border-white border-opacity-20 hover:border-opacity-40">
                        <div class="p-6 flex flex-col h-full">
                            <div class="bg-blue-400 bg-opacity-20 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <i class="fas fa-object-group text-2xl text-blue-400"></i>
                            </div>
                            <h3 class="font-bold text-xl mb-2 text-white"><?= htmlspecialchars($session['session_name']) ?></h3>
                            <p class="text-sm opacity-80 mb-4 text-gray-300">Dibuat pada: <?= date('d M Y H:i', strtotime($session['created_at'])) ?></p>
                            <div class="flex justify-center mb-4">
                                <span class="bg-purple-400 bg-opacity-20 text-purple-400 text-xs px-3 py-1 rounded-full">Matching Game</span>
                            </div>
                            <div class="mt-auto flex flex-col space-y-2">
                                <a href="demo.php?session=<?= $session['session_id'] ?>" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-full text-center transition duration-300 w-full">
                                    <i class="fas fa-play-circle mr-1"></i> Mainkan Game
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-gray-400 sm:col-span-2 lg:col-span-3 p-6">
                    <div class="bg-blue-900 bg-opacity-40 border border-blue-700 text-blue-100 px-4 py-3 rounded-md flex flex-col items-center">
                        <i class="fas fa-info-circle text-2xl mb-2"></i>
                        <p class="mb-2">Anda belum membuat sesi Matching Game apa pun.</p>
                        <a href="create_matching.php" class="font-semibold text-blue-300 hover:text-blue-500 underline">Buat yang pertama sekarang!</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-black bg-opacity-30 py-6 px-6">
        <div class="max-w-6xl mx-auto text-center text-sm opacity-70">
            <p>© 2023 Petualangan Iman - Game Alkitab Interaktif</p>
            <p class="mt-2">"Tetapi carilah dahulu Kerajaan Allah dan kebenarannya, maka semuanya itu akan ditambahkan kepadamu." - Matius 6:33</p>
        </div>
    </footer>

    <script>
        // Simple category filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const categoryButtons = document.querySelectorAll('.category-badge');

            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    categoryButtons.forEach(btn => btn.classList.remove('active', 'bg-purple-600'));

                    // Add active class to clicked button
                    this.classList.add('active');
                    this.classList.add('bg-purple-600');

                    // Get category text
                    const category = this.textContent.trim();

                    // Filter games (this is a simplified version, would need more complex implementation for real filtering)
                    console.log(`Filtering by: ${category}`);
                    // In a real implementation, you would filter the game cards here
                });
            });
        });
    </script>
</body>
</html>