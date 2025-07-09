<?php
session_start();
require_once 'db.php';

// Verifikasi session dan host
if (!isset($_SESSION['user_id']) || !isset($_GET['code'])) {
    header("Location: login.php");
    exit;
}

$sessionCode = $_GET['code'];
$userId = $_SESSION['user_id'];

// Verifikasi kepemilikan game session
$stmt = $conn->prepare("SELECT session_id, session_name FROM game_sessions 
                       WHERE session_code = ? AND created_by = ?");
$stmt->bind_param("si", $sessionCode, $userId);
$stmt->execute();
$gameSession = $stmt->get_result()->fetch_assoc();

if (!$gameSession) {
    header("Location: create_matching.php?error=invalid_session");
    exit;
}

// Dapatkan jumlah ronde yang tersedia
$roundStmt = $conn->prepare("SELECT MAX(round_number) as max_round 
                            FROM session_questions 
                            WHERE session_id = ?");
$roundStmt->bind_param("s", $gameSession['session_id']);
$roundStmt->execute();
$roundResult = $roundStmt->get_result()->fetch_assoc();
$maxRound = $roundResult['max_round'] ?? 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontrol Game - <?= htmlspecialchars($gameSession['session_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            min-height: 100vh;
            color: white;
        }
        
        .control-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            padding: 2rem;
        }
        
        .btn-control {
            font-size: 1.2rem;
            padding: 1rem 2rem;
            margin: 0.5rem;
            transition: all 0.3s;
        }
        
        .round-info {
            font-size: 2rem;
            font-weight: bold;
            margin: 1rem 0;
        }
        
        .player-count {
            font-size: 1.2rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <div class="control-panel">
                    <h1 class="mb-4"><?= htmlspecialchars($gameSession['session_name']) ?></h1>
                    <h2 class="mb-4">Kode Game: <span class="badge bg-light text-dark"><?= $sessionCode ?></span></h2>
                    
                    <div class="round-info">
                        Status: <span id="game-status">Menunggu</span>
                    </div>
                    
                    <div class="player-count">
                        <i class="fas fa-users me-2"></i>
                        Pemain: <span id="player-count">0</span>
                    </div>
                    
                    <div class="my-4">
                        <button id="start-btn" class="btn btn-primary btn-control">
                            <i class="fas fa-play me-2"></i> Mulai Game
                        </button>
                        <button id="next-btn" class="btn btn-success btn-control" disabled>
                            <i class="fas fa-forward me-2"></i> Ronde Berikutnya
                        </button>
                        <button id="end-btn" class="btn btn-danger btn-control" disabled>
                            <i class="fas fa-stop me-2"></i> Akhiri Game
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <a href="game_play.php?code=<?= $sessionCode ?>" class="btn btn-light">
                            <i class="fas fa-eye me-2"></i> Lihat Game
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sessionCode = '<?= $sessionCode ?>';
        const maxRound = <?= $maxRound ?>;
        let currentRound = 0;
        
        // Fungsi update tombol berdasarkan status
        function updateControls(isActive, round) {
            document.getElementById('start-btn').disabled = isActive;
            document.getElementById('next-btn').disabled = !isActive || round >= maxRound;
            document.getElementById('end-btn').disabled = !isActive;
            document.getElementById('game-status').textContent = 
                isActive ? `Ronde ${round} (Berjalan)` : 'Menunggu';
        }
        
        // Fungsi kontrol game
        async function controlGame(action) {
            const buttons = ['start', 'next', 'end'];
            buttons.forEach(btn => {
                document.getElementById(`${btn}-btn`).disabled = true;
            });
            
            try {
                const response = await fetch('game_controller.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action, 
                        code: sessionCode 
                    })
                });
                
                const data = await response.json();
                if (!data.success) throw new Error(data.message);
                
                // Update UI setelah aksi berhasil
                if (action === 'start') {
                    currentRound = 1;
                    updateControls(true, currentRound);
                } else if (action === 'next') {
                    currentRound++;
                    updateControls(true, currentRound);
                } else if (action === 'end') {
                    currentRound = 0;
                    updateControls(false, 0);
                }
            } catch (error) {
                alert(`Gagal: ${error.message}`);
            } finally {
                updateControls(currentRound > 0, currentRound);
            }
        }
        
        // Event listeners
        document.getElementById('start-btn').addEventListener('click', () => {
            if (confirm('Mulai game sekarang?')) {
                controlGame('start');
            }
        });
        
        document.getElementById('next-btn').addEventListener('click', () => {
            if (confirm('Lanjut ke ronde berikutnya?')) {
                controlGame('next');
            }
        });
        
        document.getElementById('end-btn').addEventListener('click', () => {
            if (confirm('Akhiri game sekarang?')) {
                controlGame('end');
            }
        });
        
        // Fungsi untuk update jumlah pemain
        async function updatePlayerCount() {
            try {
                const response = await fetch(`get_players.php?code=${sessionCode}`);
                const players = await response.json();
                document.getElementById('player-count').textContent = players.length;
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        // Fungsi untuk cek status game
        async function checkGameStatus() {
            try {
                const response = await fetch(`get_current_round.php?code=${sessionCode}`);
                const data = await response.json();
                
                if (data.success) {
                    currentRound = data.round || 0;
                    updateControls(currentRound > 0, currentRound);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
        
        // Inisialisasi
        checkGameStatus();
        updatePlayerCount();
        
        // Polling setiap 3 detik
        setInterval(() => {
            checkGameStatus();
            updatePlayerCount();
        }, 3000);

// [Kode sebelumnya...]

document.getElementById('start-btn').addEventListener('click', async function() {
    const button = this;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memulai...';
    
    try {
        // 1. Kirim request ke start_game.php
        const response = await fetch('start_game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: '<?= $sessionCode ?>' })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Gagal memulai game');
        }
        
        // 2. Verifikasi perubahan di database
        let verified = false;
        for (let i = 0; i < 5; i++) { // Coba 5x dengan delay
            const checkResponse = await fetch(`check_game_status.php?code=<?= $sessionCode ?>`);
            const checkData = await checkResponse.json();
            
            if (checkData.game_active) {
                verified = true;
                break;
            }
            await new Promise(resolve => setTimeout(resolve, 1000)); // Tunggu 1 detik
        }
        
        if (!verified) {
            throw new Error('Status game tidak berubah setelah dimulai');
        }
        
        // 3. Redirect setelah verifikasi berhasil
        window.location.href = `game_play.php?code=<?= $sessionCode ?>&round=1`;
        
    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-play me-2"></i> Mulai Permainan';
    }
});
    </script>
</body>
</html>