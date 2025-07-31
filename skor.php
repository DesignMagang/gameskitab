<?php
session_start();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Sertakan file koneksi database
include 'db.php'; // Pastikan file db.php Anda sudah benar

// Ambil username dari database
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Jika username tidak ditemukan, paksa logout
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($user['username']);

// Konfigurasi poin default dari session atau nilai awal
$poin_dasar = isset($_SESSION['poin_dasar']) ? $_SESSION['poin_dasar'] : 10;
$poin_bonus = isset($_SESSION['poin_bonus']) ? $_SESSION['poin_bonus'] : 5;
$poin_penalti = isset($_SESSION['poin_penalti']) ? $_SESSION['poin_penalti'] : 2;

// Proses update pengaturan poin
if (isset($_POST['update_poin'])) {
    $_SESSION['poin_dasar'] = max(1, (int)$_POST['poin_dasar']); // Pastikan minimal 1
    $_SESSION['poin_bonus'] = max(0, (int)$_POST['poin_bonus']);
    $_SESSION['poin_penalti'] = max(0, (int)$_POST['poin_penalti']);
    
    // Redirect untuk menghindari resubmission form
    header("Location: skor.php");
    exit();
}

// Variabel untuk menyimpan info pemenang (untuk animasi modal)
$winner_info = null;

// Proses submit skor pertandingan
if (isset($_POST['submit_skor'])) {
    $team_data = [];
    // Loop untuk 4 tim (termasuk yang mungkin tersembunyi)
    for ($i = 1; $i <= 4; $i++) {
        // Ambil nilai nama dan skor, pastikan default jika kosong atau tidak ada
        $kelompok_name_post = isset($_POST['kelompok_' . $i]) ? trim($_POST['kelompok_' . $i]) : '';
        $skor_post = isset($_POST['skor_' . $i]) ? max(0, (int)$_POST['skor_' . $i]) : 0;

        // Simpan data tim, termasuk tim yang namanya kosong (untuk konsistensi database)
        $team_data[$i] = ['name' => $kelompok_name_post, 'score' => $skor_post];
    }

    // Tentukan pemenang dari tim yang memiliki nama (tidak kosong)
    $max_score = -1;
    $winning_teams = [];
    foreach ($team_data as $team_num => $team) {
        if (!empty($team['name'])) { // Hanya pertimbangkan tim yang namanya diisi
            if ($team['score'] > $max_score) {
                $max_score = $team['score'];
                $winning_teams = [$team['name']];
            } elseif ($team['score'] == $max_score && $max_score != -1) {
                $winning_teams[] = $team['name'];
            }
        }
    }

    $winner_name = (count($winning_teams) > 1) ? 'Seri' : (empty($winning_teams) ? 'N/A' : $winning_teams[0]);
    $winner_info = ['name' => $winner_name, 'score' => $max_score];

    // Persiapkan dan eksekusi pernyataan INSERT untuk semua 4 tim
    $stmt = $conn->prepare("INSERT INTO skor_pertandingan (user_id, nama_kelompok1, skor_kelompok1, nama_kelompok2, skor_kelompok2, nama_kelompok3, skor_kelompok3, nama_kelompok4, skor_kelompok4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isisiisii", 
        $_SESSION['user_id'], 
        $team_data[1]['name'], $team_data[1]['score'],
        $team_data[2]['name'], $team_data[2]['score'],
        $team_data[3]['name'], $team_data[3]['score'],
        $team_data[4]['name'], $team_data[4]['score']
    );
    
    // Eksekusi statement dan tangani error jika ada
    if (!$stmt->execute()) {
        // Anda bisa log error ini atau menampilkannya untuk debugging
        // error_log("Database Error: " . $stmt->error); 
        // Anda juga bisa mengarahkan pengguna ke halaman error atau menampilkan pesan yang lebih user-friendly
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan skor: " . $stmt->error;
        header("Location: skor.php");
        exit();
    }
    $stmt->close(); // Tutup statement setelah eksekusi
    
    // Redirect dengan parameter pemenang untuk menampilkan modal
    header("Location: skor.php?winner=" . urlencode(json_encode($winner_info)));
    exit();
}

// Cek parameter pemenang dari URL untuk menampilkan modal
if (isset($_GET['winner'])) {
    $winner_info = json_decode(urldecode($_GET['winner']), true);
    echo "<script>
            window.onload = function() {
                // Hapus parameter winner dari URL setelah modal ditampilkan
                if (history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('winner');
                    history.replaceState(null, null, url.toString());
                }
                showWinnerModal(" . json_encode($winner_info['name']) . ");
                // Pastikan status tim dimuat setelah logika modal
                loadTeamVisibility();
            };
          </script>";
} else {
    // Jika tidak ada parameter winner, muat visibilitas tim saat halaman dimuat
    echo "<script>
            window.onload = function() {
                loadTeamVisibility();
            };
          </script>";
}

// **CATATAN**: Bagian untuk mengambil riwayat skor telah DIHAPUS.
// $riwayat_skor = $conn->prepare("SELECT ...");
// ...
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
            text-align: center;
            font-size: 1.5rem;
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
                <div id="teamsContainer" class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="team-card bg-gradient-to-br from-blue-800/70 to-blue-900/80 p-6 rounded-xl">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-blue-300"></i>
                            Team 1
                        </h2>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Team Name:</label>
                            <input type="text" name="kelompok_1" required 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/50"
                                   placeholder="Enter team name">
                        </div>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor_1', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor_1" id="skor_1" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor_1', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor_1', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-blue-500 hover:bg-blue-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor_1', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                        <div class="text-center">
                            <button type="button" onclick="resetScore('skor_1')" 
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
                            <input type="text" name="kelompok_2" required 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/50"
                                   placeholder="Enter team name">
                        </div>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor_2', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor_2" id="skor_2" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor_2', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor_2', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-indigo-500 hover:bg-indigo-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor_2', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                        <div class="text-center">
                            <button type="button" onclick="resetScore('skor_2')" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Score
                            </button>
                        </div>
                    </div>

                    <div id="team3Container" class="team-card bg-gradient-to-br from-purple-800/70 to-purple-900/80 p-6 rounded-xl hidden relative">
                        <button type="button" onclick="hideTeam(3)" class="absolute top-3 right-3 text-white text-lg bg-red-600 hover:bg-red-700 rounded-full w-8 h-8 flex items-center justify-center">
                            <i class="fas fa-times"></i>
                        </button>
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-purple-300"></i>
                            Team 3
                        </h2>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Team Name:</label>
                            <input type="text" name="kelompok_3" 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-purple-400 focus:ring-2 focus:ring-purple-400/50"
                                   placeholder="Enter team name">
                        </div>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor_3', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor_3" id="skor_3" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor_3', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor_3', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-purple-500 hover:bg-purple-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor_3', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                        <div class="text-center">
                            <button type="button" onclick="resetScore('skor_3')" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Score
                            </button>
                        </div>
                    </div>

                    <div id="team4Container" class="team-card bg-gradient-to-br from-teal-800/70 to-teal-900/80 p-6 rounded-xl hidden relative">
                        <button type="button" onclick="hideTeam(4)" class="absolute top-3 right-3 text-white text-lg bg-red-600 hover:bg-red-700 rounded-full w-8 h-8 flex items-center justify-center">
                            <i class="fas fa-times"></i>
                        </button>
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-teal-300"></i>
                            Team 4
                        </h2>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Team Name:</label>
                            <input type="text" name="kelompok_4" 
                                   class="w-full p-3 rounded-lg bg-gray-800/50 border border-gray-700 focus:border-teal-400 focus:ring-2 focus:ring-teal-400/50"
                                   placeholder="Enter team name">
                        </div>
                        <div class="mb-4">
                            <label class="block mb-2 font-medium">Score:</label>
                            <div class="flex items-center mb-3">
                                <button type="button" onclick="updateScore('skor_4', -<?= $poin_dasar ?>)" 
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-l-lg font-bold transition">
                                    -<?= $poin_dasar ?>
                                </button>
                                <input type="number" name="skor_4" id="skor_4" value="0" min="0" readonly
                                       class="score-input w-full p-3 bg-gray-800/70 border-t border-b border-gray-700 text-center text-xl font-bold">
                                <button type="button" onclick="updateScore('skor_4', <?= $poin_dasar ?>)" 
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-r-lg font-bold transition">
                                    +<?= $poin_dasar ?>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-between space-x-2 mb-4">
                            <button type="button" onclick="updateScore('skor_4', <?= $poin_bonus ?>)" 
                                    class="w-1/2 bg-teal-500 hover:bg-teal-600 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-plus-circle mr-2"></i> Bonus (+<?= $poin_bonus ?>)
                            </button>
                            <button type="button" onclick="updateScore('skor_4', -<?= $poin_penalti ?>)" 
                                    class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-lg font-semibold transition flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i> Penalty (-<?= $poin_penalti ?>)
                            </button>
                        </div>
                        <div class="text-center">
                            <button type="button" onclick="resetScore('skor_4')" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm transition">
                                <i class="fas fa-redo-alt mr-1"></i> Reset Score
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mb-8" id="toggleButtonContainer">
                    <button type="button" id="toggleTeamButton" onclick="showNextTeam()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center justify-center mx-auto shadow-md hover:shadow-xl">
                        <i class="fas fa-plus-circle mr-2"></i> Add More Teams (Max 4)
                    </button>
                </div>

                <div class="text-center">
                    <button type="submit" name="submit_skor" 
                            class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white px-8 py-4 rounded-lg font-bold text-lg transition shadow-lg hover:shadow-xl">
                        <i class="fas fa-save mr-2"></i> SAVE SCORES
                    </button>
                </div>
            </form>
            
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
            newValue = Math.max(newValue, 0); // Pastikan skor tidak kurang dari nol
            input.value = newValue;
            
            // Tambahkan umpan balik visual (sorotan singkat)
            input.classList.add('transition', 'duration-100', 'ease-in-out', 'bg-blue-500/10');
            setTimeout(() => {
                input.classList.remove('bg-blue-500/10');
            }, 300);
        }

        function resetScore(id) {
            document.getElementById(id).value = 0;
            // Opsi: Tambahkan efek reset visual
            const input = document.getElementById(id);
            input.classList.add('transition', 'duration-100', 'ease-in-out', 'bg-red-500/10');
            setTimeout(() => {
                input.classList.remove('bg-red-500/10');
            }, 300);
        }

        // Muat status visibilitas untuk setiap tim dari localStorage
        let team3Visible = localStorage.getItem('team3Visible') === 'true';
        let team4Visible = localStorage.getItem('team4Visible') === 'true';

        function updateToggleButtonState() {
            const toggleButton = document.getElementById('toggleTeamButton');
            const toggleButtonContainer = document.getElementById('toggleButtonContainer');

            if (team3Visible && team4Visible) {
                // Semua tim terlihat, sembunyikan tombol "Add More Teams" global
                toggleButtonContainer.classList.add('hidden');
            } else if (team3Visible) {
                // Tim 3 terlihat, Tim 4 tersembunyi
                toggleButton.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> Add Team 4';
                toggleButtonContainer.classList.remove('hidden');
            } else {
                // Hanya Tim 1 & 2 terlihat (atau tidak ada yang terlihat, status default)
                toggleButton.innerHTML = '<i class="fas fa-plus-circle mr-2"></i> Add More Teams (Max 4)';
                toggleButtonContainer.classList.remove('hidden');
            }
        }

        function showNextTeam() {
            const team3 = document.getElementById('team3Container');
            const team4 = document.getElementById('team4Container');

            if (!team3Visible) {
                team3.classList.remove('hidden');
                team3Visible = true;
                localStorage.setItem('team3Visible', 'true');
            } else if (!team4Visible) {
                team4.classList.remove('hidden');
                team4Visible = true;
                localStorage.setItem('team4Visible', 'true');
            }
            updateToggleButtonState();
        }

        function hideTeam(teamNumber) {
            const teamElement = document.getElementById('team' + teamNumber + 'Container');
            const teamNameInput = document.querySelector('input[name="kelompok_' + teamNumber + '"]');
            const teamScoreInput = document.getElementById('skor_' + teamNumber);

            if (teamElement) {
                teamElement.classList.add('hidden');
                // Reset nilai input untuk mencegah pengiriman data tim yang tersembunyi
                if (teamNameInput) teamNameInput.value = '';
                if (teamScoreInput) teamScoreInput.value = 0;

                if (teamNumber === 3) {
                    team3Visible = false;
                    localStorage.setItem('team3Visible', 'false');
                } else if (teamNumber === 4) {
                    team4Visible = false;
                    localStorage.setItem('team4Visible', 'false');
                }
                updateToggleButtonState(); // Perbarui status tombol global
            }
        }

        function loadTeamVisibility() {
            const team3 = document.getElementById('team3Container');
            const team4 = document.getElementById('team4Container');

            if (team3Visible) {
                team3.classList.remove('hidden');
            } else {
                team3.classList.add('hidden');
            }

            if (team4Visible) {
                team4.classList.remove('hidden');
            } else {
                team4.classList.add('hidden');
            }
            updateToggleButtonState(); // Atur status tombol awal saat dimuat
        }


        // --- Fungsi Modal Pemenang ---
        function showWinnerModal(winnerName) {
            const modal = document.getElementById('winnerModal');
            const winnerMessage = document.getElementById('winnerMessage');
            
            if (winnerName === 'Seri') {
                winnerMessage.innerHTML = `Pertandingan berakhir <span class="text-yellow-300">SERI!</span><br>Hebat sekali!`;
            } else if (winnerName === 'N/A') { // Tangani kasus di mana tidak ada tim yang diisi
                 winnerMessage.innerHTML = `Tidak ada tim yang diisi untuk menentukan pemenang.`;
            } else {
                winnerMessage.innerHTML = `Selamat kepada <span class="text-yellow-300">${winnerName}</span> yang telah berhasil memenangkan game ini!`;
            }

            modal.classList.add('show');
            playConfetti(); // Picu animasi confetti
        }

        function closeWinnerModal() {
            const modal = document.getElementById('winnerModal');
            modal.classList.remove('show');
            clearConfetti(); // Bersihkan confetti saat modal ditutup
        }

        // --- Fungsi Animasi Confetti ---
        const confettiColors = ['#FFC107', '#FF5722', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#03A9F4', '#00BCD4', '#009688', '#4CAF50', '#8BC34A', '#CDDC39'];
        const confettiElements = [];

        function createConfetti() {
            const confetti = document.createElement('div');
            confetti.classList.add('confetti');
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = confettiColors[Math.floor(Math.random() * confettiColors.length)];
            confetti.style.width = Math.random() * 8 + 5 + 'px';
            confetti.style.height = confetti.style.width;
            confetti.style.animationDuration = (Math.random() * 2 + 2) + 's'; // 2-4 detik
            confetti.style.animationDelay = (Math.random() * 0.5) + 's'; // Sedikit delay untuk penyebaran
            confetti.style.transform = `translateY(-10vh) rotate(${Math.random() * 360}deg)`; // Posisi dan rotasi awal acak
            document.body.appendChild(confetti);
            confettiElements.push(confetti);

            // Hapus confetti setelah animasi selesai untuk mencegah bloat DOM
            confetti.addEventListener('animationend', () => {
                confetti.remove();
                confettiElements.splice(confettiElements.indexOf(confetti), 1);
            });
        }

        function playConfetti() {
            for (let i = 0; i < 100; i++) { // Hasilkan 100 buah confetti
                createConfetti();
            }
        }

        function clearConfetti() {
            confettiElements.forEach(confetti => confetti.remove());
            confettiElements.length = 0; // Bersihkan array
        }

        // Panggil loadTeamVisibility saat halaman dimuat (setelah modal pemenang atau langsung)
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('winner')) {
                // Logika pemenang sudah memanggil loadTeamVisibility di window.onload (di PHP)
                // Jadi tidak perlu dipanggil di sini lagi jika ada parameter winner
            } else {
                loadTeamVisibility(); // Hanya panggil di sini jika tidak ditangani oleh modal pemenang
            }
        });
    </script>
</body>
</html>