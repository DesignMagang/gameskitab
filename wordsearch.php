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

// Daftar kata Alkitab untuk game
$kata_alkitab = [
    "KASIH", "IMAN", "DOA", "YESUS", "ALLAH", "ROH", "SUCI", 
    "INJIL", "SALIB", "ANUGERAH", "PENGAMPUNAN", "KESELAMATAN",
    "PERPULUHAN", "NABI", "MURKA", "MUKJIZAT", "ZAKAT", "PUASA",
    "BAPTIS", "PERJANJIAN", "GEREJA", "PENCIPTAAN", "KEJADIAN",
    "KELUARAN", "IMAMAT", "BILANGAN", "ULANGAN", "YEHEZKIEL",
    "PENGKOTBAH", "WAHYU", "MATIUS", "MARKUS", "LUKAS", "YOHANES"
];

// Ambil 8 kata secara acak
shuffle($kata_alkitab);
$kata_terpilih = array_slice($kata_alkitab, 0, 8);

// Buat grid word search 15x15
$grid_size = 15;
$grid = array_fill(0, $grid_size, array_fill(0, $grid_size, ''));

// Fungsi untuk menempatkan kata di grid
function tempatkan_kata($kata, &$grid) {
    $panjang = strlen($kata);
    $grid_size = count($grid);
    
    $arah = [
        ['x' => 1, 'y' => 0],   // Horizontal
        ['x' => 0, 'y' => 1],    // Vertikal
        ['x' => 1, 'y' => 1]     // Diagonal
    ];
    
    shuffle($arah);
    
    foreach ($arah as $dir) {
        $max_x = $grid_size - ($panjang * $dir['x']);
        $max_y = $grid_size - ($panjang * $dir['y']);
        
        if ($max_x < 0 || $max_y < 0) continue;
        
        $x = rand(0, $max_x);
        $y = rand(0, $max_y);
        
        $bisa_ditempatkan = true;
        for ($i = 0; $i < $panjang; $i++) {
            $current_x = $x + ($i * $dir['x']);
            $current_y = $y + ($i * $dir['y']);
            
            if ($grid[$current_y][$current_x] !== '' && 
                $grid[$current_y][$current_x] !== $kata[$i]) {
                $bisa_ditempatkan = false;
                break;
            }
        }
        
        if ($bisa_ditempatkan) {
            for ($i = 0; $i < $panjang; $i++) {
                $current_x = $x + ($i * $dir['x']);
                $current_y = $y + ($i * $dir['y']);
                $grid[$current_y][$current_x] = $kata[$i];
            }
            return true;
        }
    }
    
    return false;
}

// Tempatkan kata-kata di grid
foreach ($kata_terpilih as $kata) {
    $berhasil = tempatkan_kata($kata, $grid);
    if (!$berhasil) {
        // Jika tidak bisa ditempatkan, coba kata lain
        continue;
    }
}

// Isi sel kosong dengan huruf acak
$huruf_acak = range('A', 'Z');
for ($y = 0; $y < $grid_size; $y++) {
    for ($x = 0; $x < $grid_size; $x++) {
        if ($grid[$y][$x] === '') {
            $grid[$y][$x] = $huruf_acak[array_rand($huruf_acak)];
        }
    }
}

// Simpan kata yang harus dicari di session
$_SESSION['kata_yang_dicari'] = $kata_terpilih;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Kata Alkitab - Game Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .wordsearch-grid {
            display: grid;
            grid-template-columns: repeat(15, 1fr);
            gap: 2px;
            margin: 0 auto;
            max-width: 600px;
        }
        .wordsearch-cell {
            width: 100%;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s;
        }
        .wordsearch-cell:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .wordsearch-cell.selected {
            background-color: rgba(59, 130, 246, 0.5);
        }
        .wordsearch-cell.found {
            background-color: rgba(16, 185, 129, 0.5);
        }
        .word-list {
            columns: 2;
            column-gap: 1rem;
        }
        @media (max-width: 640px) {
            .word-list {
                columns: 1;
            }
        }
    </style>
</head>
<body class="font-sans bg-gradient-to-br from-blue-900 to-purple-900 text-white min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white bg-opacity-10 backdrop-blur-md py-4 px-6 shadow-lg fixed w-full z-50">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-bible text-2xl text-yellow-400"></i>
                <span class="font-bold text-xl">Cari Kata Alkitab</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="hidden sm:inline">Halo, <span class="font-semibold text-yellow-400"><?= $username ?></span></span>
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-full text-sm font-semibold transition">Kembali</a>
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-12 px-6 max-w-4xl mx-auto">
        <div class="bg-white bg-opacity-10 rounded-xl p-8 backdrop-blur-sm border border-white border-opacity-20">
            <h1 class="text-3xl font-bold mb-6 text-center">Cari Kata Alkitab</h1>
            
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-2">Kata yang harus dicari:</h2>
                <div class="word-list">
                    <?php foreach ($kata_terpilih as $kata): ?>
                        <div class="mb-2">
                            <span class="word-item inline-block px-3 py-1 bg-blue-900 bg-opacity-50 rounded-lg" data-word="<?= $kata ?>">
                                <?= $kata ?>
                            </span>
                            <i class="fas fa-check text-green-400 ml-2 hidden"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="wordsearch-grid mb-8" id="wordsearchGrid">
                <?php for ($y = 0; $y < $grid_size; $y++): ?>
                    <?php for ($x = 0; $x < $grid_size; $x++): ?>
                        <div class="wordsearch-cell" data-x="<?= $x ?>" data-y="<?= $y ?>">
                            <?= $grid[$y][$x] ?>
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
            
            <div class="flex justify-between items-center">
                <button id="checkBtn" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-semibold transition">
                    <i class="fas fa-check-circle mr-2"></i> Periksa
                </button>
                <button id="newGameBtn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg font-semibold transition">
                    <i class="fas fa-redo mr-2"></i> Game Baru
                </button>
            </div>
        </div>
    </main>

    <footer class="bg-black bg-opacity-30 py-4 px-6 text-center text-sm opacity-70">
        © 2023 Petualangan Iman - Cari Kata Alkitab
    </footer>

    <script>
        let selectedCells = [];
        let foundWords = [];
        const wordItems = document.querySelectorAll('.word-item');
        
        // Inisialisasi game
        function initGame() {
            // Reset seleksi
            selectedCells = [];
            foundWords = [];
            
            // Reset tampilan sel
            document.querySelectorAll('.wordsearch-cell').forEach(cell => {
                cell.classList.remove('selected', 'found');
            });
            
            // Reset daftar kata
            wordItems.forEach(item => {
                item.nextElementSibling.classList.add('hidden');
            });
        }
        
        // Handle klik sel
        document.getElementById('wordsearchGrid').addEventListener('click', function(e) {
            const cell = e.target.closest('.wordsearch-cell');
            if (!cell) return;
            
            // Jika sel sudah ditemukan, abaikan
            if (cell.classList.contains('found')) return;
            
            // Toggle seleksi
            cell.classList.toggle('selected');
            
            const x = parseInt(cell.dataset.x);
            const y = parseInt(cell.dataset.y);
            
            const index = selectedCells.findIndex(c => c.x === x && c.y === y);
            if (index === -1) {
                selectedCells.push({ x, y, letter: cell.textContent });
            } else {
                selectedCells.splice(index, 1);
            }
        });
        
        // Periksa kata yang dipilih
        document.getElementById('checkBtn').addEventListener('click', function() {
            if (selectedCells.length < 2) {
                alert('Pilih minimal 2 huruf untuk membentuk kata!');
                return;
            }
            
            // Urutkan sel berdasarkan posisi
            selectedCells.sort((a, b) => {
                if (a.y !== b.y) return a.y - b.y;
                return a.x - b.x;
            });
            
            // Cek apakah membentuk garis lurus
            const isHorizontal = selectedCells.every((cell, i) => 
                i === 0 || (cell.y === selectedCells[0].y && cell.x === selectedCells[i-1].x + 1));
            
            const isVertical = selectedCells.every((cell, i) => 
                i === 0 || (cell.x === selectedCells[0].x && cell.y === selectedCells[i-1].y + 1));
            
            const isDiagonal = selectedCells.every((cell, i) => 
                i === 0 || (cell.x === selectedCells[i-1].x + 1 && cell.y === selectedCells[i-1].y + 1));
            
            if (!isHorizontal && !isVertical && !isDiagonal) {
                alert('Pilih huruf yang berurutan dalam garis lurus!');
                return;
            }
            
            // Ambil kata yang dibentuk
            const word = selectedCells.map(cell => cell.letter).join('');
            
            // Cek apakah kata ada di daftar
            const wordItem = Array.from(wordItems).find(item => 
                item.dataset.word === word && !foundWords.includes(word));
            
            if (wordItem) {
                // Tandai sebagai ditemukan
                foundWords.push(word);
                wordItem.nextElementSibling.classList.remove('hidden');
                
                // Tandai sel sebagai ditemukan
                selectedCells.forEach(cell => {
                    const cellEl = document.querySelector(`.wordsearch-cell[data-x="${cell.x}"][data-y="${cell.y}"]`);
                    cellEl.classList.remove('selected');
                    cellEl.classList.add('found');
                });
                
                // Cek jika semua kata sudah ditemukan
                if (foundWords.length === wordItems.length) {
                    setTimeout(() => {
                        alert('Selamat! Anda telah menemukan semua kata!');
                    }, 300);
                }
            } else {
                alert('Kata tidak ditemukan dalam daftar atau sudah ditemukan sebelumnya!');
            }
            
            // Reset seleksi
            selectedCells = [];
            document.querySelectorAll('.wordsearch-cell.selected').forEach(cell => {
                cell.classList.remove('selected');
            });
        });
        
        // Game baru
        document.getElementById('newGameBtn').addEventListener('click', function() {
            if (confirm('Mulai game baru? Game saat ini akan direset.')) {
                location.reload();
            }
        });
        
        // Inisialisasi awal
        initGame();
    </script>
</body>
</html>