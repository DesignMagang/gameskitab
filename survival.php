<?php
session_start();
require_once 'db.php';

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$message = '';

// Handle pembuatan sesi baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $sessionName = trim($_POST['session_name']);
    $accessCode = trim($_POST['access_code']);
    
    if (empty($sessionName) || empty($accessCode)) {
        $error = "Nama sesi dan kode akses tidak boleh kosong";
    } else {
        $stmt = $conn->prepare("INSERT INTO survival_sessions (session_name, access_code, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $sessionName, $accessCode, $userId);
        
        if ($stmt->execute()) {
            $message = "Sesi berhasil dibuat!";
            // Hilangkan pesan setelah 1 detik
            echo "<script>setTimeout(() => document.querySelector('.alert-success').remove(), 1000);</script>";
        } else {
            $error = "Gagal membuat sesi: " . $conn->error;
        }
    }
}

// Handle hapus sesi
if (isset($_GET['delete'])) {
    $sessionId = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM survival_sessions WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $sessionId, $userId);
    
    if ($stmt->execute()) {
        $message = "Sesi berhasil dihapus";
        // Hilangkan pesan setelah 1 detik
        echo "<script>setTimeout(() => document.querySelector('.alert-success').remove(), 1000);</script>";
    } else {
        $error = "Gagal menghapus sesi";
    }
}

// Handle edit sesi (modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_session'])) {
    $sessionId = $_POST['session_id'];
    $sessionName = trim($_POST['edit_session_name']);
    $accessCode = trim($_POST['edit_access_code']);
    
    $stmt = $conn->prepare("UPDATE survival_sessions SET session_name = ?, access_code = ? WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ssii", $sessionName, $accessCode, $sessionId, $userId);
    
    if ($stmt->execute()) {
        $message = "Sesi berhasil diupdate!";
        echo "<script>setTimeout(() => document.querySelector('.alert-success').remove(), 1000);</script>";
    } else {
        $error = "Gagal mengupdate sesi";
    }
}

// Ambil daftar sesi yang dibuat user
$sessions = [];
$stmt = $conn->prepare("SELECT id, session_name, access_code, created_at FROM survival_sessions WHERE created_by = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survival Game Session</title>
    <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" href="logo.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        /* Animasi untuk modal */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-animation {
            animation: modalFadeIn 0.3s ease-out;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 to-purple-900 bg-[length:400%_400%] animate-[gradientBG_15s_ease_infinite]">
    <!-- Tombol Kembali -->
    <a href="dashboard.php" class="fixed top-4 left-4 z-50 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full backdrop-blur-md transition-all">
        <i class="fas fa-arrow-left"></i>
    </a>

    <!-- Audio untuk musik -->
    <audio id="bgMusic" loop>
        <source src="assets/music.mp3" type="audio/mpeg">
    </audio>
    <button id="musicToggle" class="fixed top-4 right-4 z-50 bg-white/20 hover:bg-white/30 text-white p-3 rounded-full backdrop-blur-md transition-all">
        <i class="fas fa-music"></i>
    </button>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8 pt-8">
            <h1 class="text-4xl font-bold text-white mb-2">Survival Game</h1>
            <p class="text-white/80">Buat sesi permainan baru</p>
        </div>

        <!-- Notifikasi -->
        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-100 p-4 rounded-lg mb-6 border border-red-500/50 animate-pulse">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert-success bg-green-500/20 text-green-100 p-4 rounded-lg mb-6 border border-green-500/50 animate-pulse">
                <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Form Buat Sesi Baru -->
        <div class="glass-card rounded-xl p-6 mb-8 shadow-2xl max-w-md mx-auto">
            <h2 class="text-2xl font-bold text-white mb-4 flex items-center justify-center">
                <i class="fas fa-plus-circle mr-3 text-purple-300"></i> Buat Sesi Baru
            </h2>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-white/80 text-sm mb-2">Nama Sesi</label>
                    <input type="text" name="session_name" required 
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-400"
                           placeholder="Contoh: Kuis Alkitab Survival">
                </div>
                
                <div class="mb-6">
                    <label class="block text-white/80 text-sm mb-2">Kode Akses</label>
                    <input type="text" name="access_code" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-400"
                           placeholder="Buat kode akses">
                </div>
                
                <button type="submit" name="create_session"
                        class="w-full py-3 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-bold rounded-lg transition-all duration-300 flex items-center justify-center">
                    <i class="fas fa-save mr-2"></i> Simpan Sesi
                </button>
            </form>
        </div>

        <!-- Riwayat Sesi -->
        <div class="glass-card rounded-xl p-6 shadow-2xl max-w-3xl mx-auto">
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center justify-center">
                <i class="fas fa-history mr-3 text-yellow-300"></i> Riwayat Sesi Anda
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($sessions as $session): ?>
                    <div class="bg-white/5 hover:bg-white/10 transition-all duration-300 rounded-lg p-4 border border-white/10">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-white truncate"><?= htmlspecialchars($session['session_name']) ?></h3>
                                <p class="text-sm text-white/60 mt-1">
                                    <i class="far fa-calendar-alt mr-1"></i> 
                                    <?= date('d M Y H:i', strtotime($session['created_at'])) ?>
                                </p>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="showEditModal(<?= $session['id'] ?>, '<?= htmlspecialchars($session['session_name']) ?>', '<?= htmlspecialchars($session['access_code']) ?>')"
                                   class="w-10 h-10 flex items-center justify-center bg-yellow-500/20 hover:bg-yellow-500/30 rounded-lg text-yellow-300 transition-all"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $session['id'] ?>" 
                                   class="w-10 h-10 flex items-center justify-center bg-red-500/20 hover:bg-red-500/30 rounded-lg text-red-300 transition-all"
                                   title="Hapus"
                                   onclick="return confirm('Hapus sesi ini?')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                        <button onclick="showAccessModal('<?= htmlspecialchars($session['session_name']) ?>')"
                           class="w-full mt-3 py-2 bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white rounded-lg transition-all">
                            <i class="fas fa-play mr-2"></i> Mulai Game
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal Kode Akses -->
    <div id="accessModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="glass-card rounded-xl p-6 w-full max-w-md modal-animation">
            <h3 class="text-xl font-bold text-white mb-4">Masukkan Kode Akses</h3>
            <form method="POST" action="survival_game.php" class="space-y-4">
                <input type="hidden" id="modalSessionName" name="session_name">
                <div>
                    <label class="block text-white/80 text-sm mb-2">Kode Akses</label>
                    <input type="text" name="access_code" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-400"
                           placeholder="Masukkan kode akses">
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="hideModal('accessModal')"
                            class="flex-1 py-2 bg-gray-500/50 hover:bg-gray-500/70 text-white rounded-lg transition-all">
                        Batal
                    </button>
                    <button type="submit"
                            class="flex-1 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white rounded-lg transition-all">
                        Masuk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Sesi -->
    <div id="editModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="glass-card rounded-xl p-6 w-full max-w-md modal-animation">
            <h3 class="text-xl font-bold text-white mb-4">Edit Sesi</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" id="editSessionId" name="session_id">
                <div>
                    <label class="block text-white/80 text-sm mb-2">Nama Sesi</label>
                    <input type="text" id="editSessionName" name="edit_session_name" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-400">
                </div>
                <div>
                    <label class="block text-white/80 text-sm mb-2">Kode Akses</label>
                    <input type="text" id="editAccessCode" name="edit_access_code" required
                           class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-purple-400">
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="hideModal('editModal')"
                            class="flex-1 py-2 bg-gray-500/50 hover:bg-gray-500/70 text-white rounded-lg transition-all">
                        Batal
                    </button>
                    <button type="submit" name="edit_session"
                            class="flex-1 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg transition-all">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Musik
        const music = document.getElementById('bgMusic');
        const musicToggle = document.getElementById('musicToggle');
        let isMusicPlaying = false;

        musicToggle.addEventListener('click', () => {
            if (isMusicPlaying) {
                music.pause();
                musicToggle.innerHTML = '<i class="fas fa-music"></i>';
            } else {
                music.play();
                musicToggle.innerHTML = '<i class="fas fa-pause"></i>';
            }
            isMusicPlaying = !isMusicPlaying;
        });

        // Modal functions
        function showAccessModal(sessionName) {
            document.getElementById('modalSessionName').value = sessionName;
            document.getElementById('accessModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function showEditModal(id, name, code) {
            document.getElementById('editSessionId').value = id;
            document.getElementById('editSessionName').value = name;
            document.getElementById('editAccessCode').value = code;
            document.getElementById('editModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.id === 'accessModal') hideModal('accessModal');
            if (e.target.id === 'editModal') hideModal('editModal');
        });
    </script>
</body>
</html>