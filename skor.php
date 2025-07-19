<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

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

// Konfigurasi poin default
$poin_dasar = isset($_SESSION['poin_dasar']) ? $_SESSION['poin_dasar'] : 10;
$poin_bonus = isset($_SESSION['poin_bonus']) ? $_SESSION['poin_bonus'] : 5;
$poin_penalti = isset($_SESSION['poin_penalti']) ? $_SESSION['poin_penalti'] : 2;

if (isset($_POST['update_poin'])) {
    $_SESSION['poin_dasar'] = max(1, (int)$_POST['poin_dasar']); // Ensure minimum 1
    $_SESSION['poin_bonus'] = max(0, (int)$_POST['poin_bonus']);
    $_SESSION['poin_penalti'] = max(0, (int)$_POST['poin_penalti']);
    
    // No success message needed for point configuration update based on your request.
    header("Location: skor.php");
    exit();
}

// Variable to store winner info for the animation
$winner_info = null;

if (isset($_POST['submit_skor'])) {
    $kelompok1 = trim($_POST['kelompok1']);
    $skor1 = max(0, (int)$_POST['skor1']);
    $kelompok2 = trim($_POST['kelompok2']);
    $skor2 = max(0, (int)$_POST['skor2']);
    
    // Basic validation
    if (empty($kelompok1) || empty($kelompok2)) {
        $_SESSION['error'] = "Nama kelompok tidak boleh kosong.";
        header("Location: skor.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO skor_pertandingan (user_id, nama_kelompok1, skor_kelompok1, nama_kelompok2, skor_kelompok2) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isisi", $_SESSION['user_id'], $kelompok1, $skor1, $kelompok2, $skor2);
    $stmt->execute();
    
    // Determine winner for animation
    if ($skor1 > $skor2) {
        $winner_info = ['name' => $kelompok1, 'score' => $skor1];
    } elseif ($skor2 > $skor1) {
        $winner_info = ['name' => $kelompok2, 'score' => $skor2];
    } else {
        $winner_info = ['name' => 'Seri', 'score' => max($skor1, $skor2)]; // For a draw
    }

    // Instead of redirecting with session success, we'll pass winner info via query param
    // This allows the JS to pick it up and trigger the animation on page load.
    // We encode it to handle special characters in team names
    header("Location: skor.php?winner=" . urlencode(json_encode($winner_info)));
    exit();
}

// Check for winner info from URL
if (isset($_GET['winner'])) {
    $winner_info = json_decode(urldecode($_GET['winner']), true);
    // Remove the query parameter after processing to prevent re-triggering
    echo "<script>
            window.onload = function() {
                if (history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('winner');
                    history.replaceState(null, null, url.toString());
                }
                showWinnerModal(" . json_encode($winner_info['name']) . ");
            };
          </script>";
}


$riwayat_skor = $conn->prepare("SELECT * FROM skor_pertandingan WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$riwayat_skor->bind_param("i", $_SESSION['user_id']);
$riwayat_skor->execute();
$riwayat_data = $riwayat_skor->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Board - Game Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a;
        }
        
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        
        .score-card {
            background: linear-gradient(145deg, rgba(30,41,59,0.8) 0%, rgba(15,23,42,0.9) 100%);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .team-card {
            transition: all 0.3s ease;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .score-input {
            -moz-appearance: textfield;
            text-align: center; /* Center the score for better readability */
            font-size: 1.5rem; /* Larger font for scores */
            font-weight: bold;
        }
        
        .score-input::-webkit-outer-spin-button,
        .score-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        /* Winner Animation Modal */
        .winner-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .winner-modal.show {
            opacity: 1;
            visibility: visible;
        }
        .winner-modal-content {
            background: linear-gradient(135deg, #1A202C, #2D3748);
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            transform: scale(0.8);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            border: 2px solid #ecc94b; /* Yellow border */
        }
        .winner-modal.show .winner-modal-content {
            transform: scale(1);
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f0f; /* Example color */
            animation: confetti-fall 3s forwards;
            opacity: 0;
        }

        @keyframes confetti-fall {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        .sparkle {
            position: absolute;
            background-color: white;
            border-radius: 50%;
            opacity: 0;
            animation: sparkle-fade 1s forwards;
        }
        @keyframes sparkle-fade {
            0% { transform: scale(0); opacity: 1; }
            50% { transform: scale(1); opacity: 1; }
            100% { transform: scale(0); opacity: 0; }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 to-indigo-900 text-white">
    <header class="bg-gradient-to-r from-blue-800 to-indigo-800 py-6 shadow-xl">
        <div class="container mx-auto px-6 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fas fa-bible text-3xl text-yellow-400"></i>
                <h1 class="title-font text-2xl md:text-3xl font-bold">Score Board Game Alkitab</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden md:inline text-yellow-300">Welcome, <?= $username ?></span>
                <a href="dashboard.php" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded-lg font-medium transition flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12 max-w-6xl">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-600 text-white p-4 rounded-lg mb-8 flex items-center justify-between">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="text-white hover:text-red-200" onclick="this.parentElement.style.display='none';">&times;</button>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="score-card rounded-xl p-6 mb-8 backdrop-blur-sm">
            <h2 class="text-2xl font-bold mb-6 flex items-center text-yellow-400">
                <i class="fas fa-cog mr-3"></i>
                Game Point Settings
            </h2>
            
            <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block mb-2 font-medium">Base Points</label>
                    <div class="relative">
                        <input type="number" name="poin_dasar" value="<?= $poin_dasar ?>" min="1" required
                               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-500/50">
                        <div class="absolute right-3 top-3 text-gray-400">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium">Bonus Points</label>
                    <div class="relative">
                        <input type="number" name="poin_bonus" value="<?= $poin_bonus ?>" min="0"
                               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-500/50">
                        <div class="absolute right-3 top-3 text-gray-400">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block mb-2 font-medium">Penalty Points</label>
                    <div class="relative">
                        <input type="number" name="poin_penalti" value="<?= $poin_penalti ?>" min="0"
                               class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-500/50">
                        <div class="absolute right-3 top-3 text-gray-400">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="md:col-span-3">
                    <button type="submit" name="update_poin" 
                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
        
        <div class="score-card rounded-xl p-8">
            <h1 class="text-3xl font-bold mb-8 text-center title-font text-yellow-400">
                <i class="fas fa-trophy mr-3"></i>
                Game Score Board
            </h1>
            
            <form method="post">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="team-card bg-gradient-to-br from-blue-800/70 to-blue-900/80 p-6 rounded-xl">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-blue-300"></i>
                            Team 1
                        </h2>
                        
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Team Name:</label>
                            <input type="text" name="kelompok1" required 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/50"
                                   placeholder="Enter team name">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor1', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor1" id="skor1" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor1', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor1', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-blue-500 hover:bg-blue-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor1', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                         <div class="text-center">
                            <button type="button" onclick="resetScore('skor1')" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Score
                            </button>
                        </div>
                    </div>
                    
                    <div class="team-card bg-gradient-to-br from-indigo-800/70 to-indigo-900/80 p-6 rounded-xl">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-indigo-300"></i>
                            Team 2
                        </h2>
                        
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Team Name:</label>
                            <input type="text" name="kelompok2" required 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/50"
                                   placeholder="Enter team name">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor2', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor2" id="skor2" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor2', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor2', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-indigo-500 hover:bg-indigo-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor2', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                         <div class="text-center">
                            <button type="button" onclick="resetScore('skor2')" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Score
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="submit_skor" 
                            class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white px-8 py-4 rounded-lg font-bold text-lg transition shadow-lg hover:shadow-xl">
                        <i class="fas fa-save mr-2"></i> SAVE SCORES
                    </button>
                </div>
            </form>
            
            <div class="mt-12">
                <h2 class="text-2xl font-bold mb-6 text-center title-font text-yellow-400">
                    <i class="fas fa-history mr-3"></i>
                    Recent Games
                </h2>
                
                <?php if ($riwayat_data->num_rows > 0): ?>
                    <div class="overflow-x-auto rounded-xl bg-gray-800/30 backdrop-blur-sm">
                        <table class="w-full">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="p-4 text-left">Date</th>
                                    <th class="p-4 text-left">Team 1</th>
                                    <th class="p-4 text-center">Score</th>
                                    <th class="p-4 text-left">Team 2</th>
                                    <th class="p-4 text-center">Score</th>
                                    <th class="p-4 text-center">Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $riwayat_data->fetch_assoc()): 
                                    $winner_team = '';
                                    $winner_class = '';
                                    if ($row['skor_kelompok1'] > $row['skor_kelompok2']) {
                                        $winner_team = htmlspecialchars($row['nama_kelompok1']) . ' Wins!';
                                        $winner_class = 'bg-green-600/30 text-green-300 winner-badge';
                                    } elseif ($row['skor_kelompok2'] > $row['skor_kelompok1']) {
                                        $winner_team = htmlspecialchars($row['nama_kelompok2']) . ' Wins!';
                                        $winner_class = 'bg-green-600/30 text-green-300 winner-badge';
                                    } else {
                                        $winner_team = 'Draw';
                                        $winner_class = 'bg-yellow-600/30 text-yellow-300';
                                    }
                                ?>
                                    <tr class="border-b border-gray-700/50 hover:bg-gray-700/20">
                                        <td class="p-4"><?= date('d M H:i', strtotime($row['created_at'])) ?></td>
                                        <td class="p-4"><?= htmlspecialchars($row['nama_kelompok1']) ?></td>
                                        <td class="p-4 text-center font-bold <?= $row['skor_kelompok1'] > $row['skor_kelompok2'] ? 'text-green-400' : '' ?>">
                                            <?= $row['skor_kelompok1'] ?>
                                        </td>
                                        <td class="p-4"><?= htmlspecialchars($row['nama_kelompok2']) ?></td>
                                        <td class="p-4 text-center font-bold <?= $row['skor_kelompok2'] > $row['skor_kelompok1'] ? 'text-green-400' : '' ?>">
                                            <?= $row['skor_kelompok2'] ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="<?= $winner_class ?> px-3 py-1 rounded-full text-sm font-bold">
                                                <?= $winner_team ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 bg-gray-800/30 rounded-xl">
                        <i class="fas fa-info-circle text-2xl text-blue-400 mb-2"></i>
                        <p class="text-gray-400">No game history yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="bg-black/30 py-6 mt-12 text-center text-sm text-gray-400">
        <div class="container mx-auto px-6">
            <p>© 2025 Bible Game Adventure | Created with <i class="fas fa-heart text-red-400"></i> for Faith Education</p>
        </div>
    </footer>

    <div id="winnerModal" class="winner-modal">
        <div class="winner-modal-content">
            <h2 class="title-font text-4xl font-bold text-yellow-400 mb-4">
                <i class="fas fa-crown text-5xl mb-3 animate-bounce"></i><br>
                Selamat!
            </h2>
            <p id="winnerMessage" class="text-2xl text-white font-semibold mb-6">
                </p>
            <button onclick="closeWinnerModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition shadow-md">
                Tutup
            </button>
        </div>
    </div>

    <script>
        function updateScore(id, change) {
            const input = document.getElementById(id);
            let newValue = parseInt(input.value) + change;
            newValue = Math.max(newValue, 0); // Ensure score doesn't go below zero
            input.value = newValue;
            
            // Add visual feedback (brief highlight)
            input.classList.add('transition', 'duration-100', 'ease-in-out', 'bg-blue-500/10');
            setTimeout(() => {
                input.classList.remove('bg-blue-500/10');
            }, 300);
        }

        function resetScore(id) {
            document.getElementById(id).value = 0;
            // Optional: Add a visual reset effect
            const input = document.getElementById(id);
            input.classList.add('transition', 'duration-100', 'ease-in-out', 'bg-red-500/10');
            setTimeout(() => {
                input.classList.remove('bg-red-500/10');
            }, 300);
        }

        // --- Winner Modal Functions ---
        function showWinnerModal(winnerName) {
            const modal = document.getElementById('winnerModal');
            const winnerMessage = document.getElementById('winnerMessage');
            
            if (winnerName === 'Seri') {
                winnerMessage.innerHTML = `Pertandingan berakhir <span class="text-yellow-300">SERI!</span><br>Hebat sekali!`;
            } else {
                winnerMessage.innerHTML = `Selamat kepada <span class="text-yellow-300">${winnerName}</span> yang telah berhasil memenangkan game ini!`;
            }

            modal.classList.add('show');
            playConfetti(); // Trigger confetti animation
        }

        function closeWinnerModal() {
            const modal = document.getElementById('winnerModal');
            modal.classList.remove('show');
            clearConfetti(); // Clear confetti when modal closes
        }

        // --- Confetti Animation Functions ---
        const confettiColors = ['#FFC107', '#FF5722', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39'];
        const confettiElements = [];

        function createConfetti() {
            const confetti = document.createElement('div');
            confetti.classList.add('confetti');
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = confettiColors[Math.floor(Math.random() * confettiColors.length)];
            confetti.style.width = Math.random() * 8 + 5 + 'px';
            confetti.style.height = confetti.style.width;
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's'; // 2-4 seconds
            confetti.style.animationDelay = (Math.random() * 0.5) + 's'; // Slight delay for spread
            confetti.style.transform = `translateY(-10vh) rotate(${Math.random() * 360}deg)`; // Initial random position and rotation
            document.body.appendChild(confetti);
            confettiElements.push(confetti);

            // Remove confetti after animation to prevent DOM bloat
            confetti.addEventListener('animationend', () => {
                confetti.remove();
                confettiElements.splice(confettiElements.indexOf(confetti), 1);
            });
        }

        function playConfetti() {
            for (let i = 0; i < 100; i++) { // Generate 100 pieces of confetti
                createConfetti();
            }
        }

        function clearConfetti() {
            confettiElements.forEach(confetti => confetti.remove());
            confettiElements.length = 0; // Clear the array
        }

        // Check if there's a winner to show modal on page load
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('winner')) {
                const winnerInfoJson = urlParams.get('winner');
                try {
                    const winnerInfo = JSON.parse(decodeURIComponent(winnerInfoJson));
                    showWinnerModal(winnerInfo.name);
                } catch (e) {
                    console.error("Error parsing winner info:", e);
                }
            }
        });
    </script>
</body>
</html>