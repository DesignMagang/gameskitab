<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Ambil data user
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

// Ambil riwayat peserta
$riwayat_stmt = $conn->prepare("SELECT id, nama_peserta, created_at FROM riwayat_peserta WHERE user_id = ? ORDER BY created_at DESC");
$riwayat_stmt->bind_param("i", $_SESSION['user_id']);
$riwayat_stmt->execute();
$riwayat_data = $riwayat_stmt->get_result();

// Variabel hasil
$hasil_kelompok = [];
$selected_history = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_peserta_input = '';

    // Jika dari riwayat
    if (!empty($_POST['history_id'])) {
        $history_id = intval($_POST['history_id']);
        $stmt = $conn->prepare("SELECT nama_peserta FROM riwayat_peserta WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $history_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = $result->fetch_assoc();
        if ($history) {
            $selected_history = $history['nama_peserta'];
            $nama_peserta_input = $selected_history;
        }
    } elseif (!empty($_POST['nama_peserta'])) {
        $nama_peserta_input = $_POST['nama_peserta'];
    }

    // Proses nama peserta
    if (!empty($nama_peserta_input)) {
        $nama_peserta = array_filter(array_map('trim', explode("\n", $nama_peserta_input)));

        if (!empty($nama_peserta)) {
            // Simpan ke riwayat jika input manual
            if (empty($_POST['history_id'])) {
                $nama_peserta_string = implode("\n", $nama_peserta);
                $stmt = $conn->prepare("INSERT INTO riwayat_peserta (user_id, nama_peserta) VALUES (?, ?)");
                $stmt->bind_param("is", $_SESSION['user_id'], $nama_peserta_string);
                $stmt->execute();
            }

            // Proses tipe kelompok
            $tipe_kelompok = $_POST['tipe_kelompok'] ?? 'dua_orang';
            shuffle($nama_peserta);

            if ($tipe_kelompok === 'dua_orang') {
                $hasil_kelompok = array_chunk($nama_peserta, 2);
            } else {
                $separator = ceil(count($nama_peserta) / 2);
                $hasil_kelompok[] = array_slice($nama_peserta, 0, $separator);
                $hasil_kelompok[] = array_slice($nama_peserta, $separator);
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kelompok - Game Alkitab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        function loadHistory(id) {
            document.getElementById('history_id').value = id;
            document.getElementById('main-form').submit();
        }
    </script>
</head>
<body class="font-sans bg-gradient-to-br from-blue-900 to-purple-900 text-white min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white bg-opacity-10 backdrop-blur-md py-4 px-6 shadow-lg fixed w-full z-50">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-bible text-2xl text-yellow-400"></i>
                <span class="font-bold text-xl">Pembagian Kelompok</span>
            </div>
            <div class="flex items-center space-x-4">
    <!-- Dropdown Riwayat -->
    <div class="relative">
        <button onclick="toggleDropdown()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-full flex items-center space-x-2">
            <i class="fas fa-history"></i>
            <span>Riwayat</span>
            <i class="fas fa-chevron-down text-sm"></i>
        </button>
        <div id="riwayatDropdown" class="hidden absolute left-0 mt-2 w-72 bg-white bg-opacity-10 backdrop-blur-lg rounded-lg shadow-lg z-50 max-h-80 overflow-y-auto">
            <?php if ($riwayat_data->num_rows > 0): ?>
                <?php while ($row = $riwayat_data->fetch_assoc()): ?>
                    <form method="post" class="flex items-center justify-between px-4 py-2 hover:bg-white hover:bg-opacity-10 transition">
                        <input type="hidden" id="history_id" name="history_id" value="">
                        <input type="hidden" name="nama_peserta" value="<?= htmlspecialchars($row['nama_peserta']) ?>">
                        <input type="hidden" name="tipe_kelompok" value="<?= isset($_POST['tipe_kelompok']) ? $_POST['tipe_kelompok'] : 'dua_orang' ?>">
                        <button type="submit" class="text-left w-full truncate mr-2">
                            <?= date('d M Y H:i', strtotime($row['created_at'])) ?>
                        </button>
                        <a href="hapus_riwayat.php?id=<?= $row['id'] ?>" onclick="return confirm('Hapus riwayat ini?')" class="text-red-400 hover:text-red-600">
                            <i class="fas fa-times"></i>
                        </a>
                    </form>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="px-4 py-2 text-sm text-gray-300">Tidak ada riwayat</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Username -->
    <span class="hidden sm:inline">Halo, <span class="font-semibold text-yellow-400"><?= $username ?></span></span>

    <!-- Tombol Kembali -->
    <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-full text-sm font-semibold transition">Kembali</a>
</div>

        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-24 pb-12 px-6 max-w-4xl mx-auto">
        <div class="bg-white bg-opacity-10 rounded-xl p-8 backdrop-blur-sm border border-white border-opacity-20">
            <h1 class="text-3xl font-bold mb-6 text-center">Pembagian Kelompok</h1>
            
            <form id="main-form" method="post" class="mb-8">
                <div class="mb-6">
                    <label class="block text-lg mb-2">Masukkan Nama Peserta (Pisahkan dengan baris baru):</label>
                    <textarea name="nama_peserta" rows="10" class="w-full p-3 rounded bg-white bg-opacity-10 border border-white border-opacity-20 text-white" placeholder="Contoh:
Nama 1
Nama 2
Nama 3
..."><?= 
    isset($_POST['nama_peserta']) ? htmlspecialchars($_POST['nama_peserta']) : 
    ($selected_history ? htmlspecialchars($selected_history) : '') 
?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                       <input type="radio" id="dua_orang" name="tipe_kelompok" value="dua_orang" class="hidden peer" <?= (!isset($_POST['tipe_kelompok']) || $_POST['tipe_kelompok'] == 'dua_orang') ? 'checked' : '' ?>>

                        <label for="dua_orang" class="block p-4 bg-indigo-900 bg-opacity-50 rounded-lg border-2 border-transparent peer-checked:border-indigo-400 cursor-pointer hover:bg-indigo-800 transition">
                            <div class="flex items-center">
                                <i class="fas fa-user-friends text-2xl mr-3 text-indigo-300"></i>
                                <div>
                                    <h2 class="text-xl font-semibold">Kelompok Kecil</h2>
                                    <p class="text-sm opacity-80">Bagi peserta menjadi kelompok berisi 2 orang</p>
                                </div>
                            </div>
                        </label>
                    </div>
                    
                    <div>
                        <input type="radio" id="dua_kelompok" name="tipe_kelompok" value="dua_kelompok" class="hidden peer" <?= isset($_POST['tipe_kelompok']) && $_POST['tipe_kelompok'] == 'dua_kelompok' ? 'checked' : '' ?>>
                        <label for="dua_kelompok" class="block p-4 bg-purple-900 bg-opacity-50 rounded-lg border-2 border-transparent peer-checked:border-purple-400 cursor-pointer hover:bg-purple-800 transition">
                            <div class="flex items-center">
                                <i class="fas fa-users text-2xl mr-3 text-purple-300"></i>
                                <div>
                                    <h2 class="text-xl font-semibold">2 Kelompok Besar</h2>
                                    <p class="text-sm opacity-80">Bagi peserta menjadi 2 kelompok besar</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-semibold text-lg transition">
                    <i class="fas fa-users-cog mr-2"></i> Bagi Kelompok
                </button>
            </form>
            
            <?php if (!empty($hasil_kelompok)): ?>
                <div class="mt-8">
                    <h2 class="text-2xl font-bold mb-4 text-center">Hasil Pembagian Kelompok</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-<?= count($hasil_kelompok) > 2 ? '2' : count($hasil_kelompok) ?> gap-6">
                        <?php foreach ($hasil_kelompok as $index => $kelompok): ?>
                            <div class="bg-white bg-opacity-10 rounded-lg p-4">
                                <h3 class="text-xl font-semibold mb-3 border-b border-white border-opacity-20 pb-2 flex items-center">
                                    <span class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-2"><?= $index + 1 ?></span>
                                    Kelompok <?= $index + 1 ?>
                                    <span class="ml-auto text-sm bg-white bg-opacity-20 px-2 py-1 rounded">
                                        <?= count($kelompok) ?> orang
                                    </span>
                                </h3>
                                <ul class="space-y-2">
                                    <?php foreach ($kelompok as $anggota): ?>
                                        <li class="flex items-center p-2 hover:bg-white hover:bg-opacity-10 rounded">
                                            <i class="fas fa-user-circle mr-2 opacity-70"></i>
                                            <?= htmlspecialchars($anggota) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6 flex justify-center">
                        <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-semibold transition">
                            <i class="fas fa-print mr-2"></i> Cetak Hasil
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-black bg-opacity-30 py-4 px-6 text-center text-sm opacity-70">
        © 2023 Petualangan Iman - Pembagian Kelompok
    </footer>

    <script>
        // Toggle dropdown riwayat
        document.getElementById('dropdownButton').addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.toggle('hidden');
        });

        // Tutup dropdown ketika klik di luar
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdownMenu');
            const button = document.getElementById('dropdownButton');
            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('riwayatDropdown');
        dropdown.classList.toggle('hidden');
    }

    // Tutup dropdown jika klik di luar
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('riwayatDropdown');
        const button = e.target.closest('button');
        if (!dropdown.contains(e.target) && !button) {
            dropdown.classList.add('hidden');
        }
    });
</script>

</body>
</html>