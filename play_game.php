<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check session parameter
if (!isset($_GET['session'])) {
    header("Location: create_matching.php");
    exit;
}

$sessionId = $_GET['session'];
$userId = $_SESSION['user_id'];

// Verify session ownership
$stmt = $conn->prepare("SELECT session_name FROM sessions WHERE session_id = ? AND created_by = ?");
$stmt->bind_param("si", $sessionId, $userId);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    header("Location: create_matching.php?error=invalid_session");
    exit;
}

// Generate or get game session code
$stmt = $conn->prepare("SELECT * FROM game_sessions WHERE session_id = ? AND created_by = ?");
$stmt->bind_param("si", $sessionId, $userId);
$stmt->execute();
$gameSession = $stmt->get_result()->fetch_assoc();


if (!$gameSession) {
    // Generate unique 6-digit code
    do {
        $sessionCode = substr(strtoupper(bin2hex(random_bytes(3))), 0, 6);
        $checkStmt = $conn->prepare("SELECT session_code FROM game_sessions WHERE session_code = ?");
        $checkStmt->bind_param("s", $sessionCode);
        $checkStmt->execute();
    } while ($checkStmt->get_result()->num_rows > 0);

    $stmt = $conn->prepare("INSERT INTO game_sessions (session_code, session_id, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $sessionCode, $sessionId, $userId);
    $stmt->execute();
    
    $gameSession = [
        'session_code' => $sessionCode,
        'session_id' => $sessionId,
        'created_by' => $userId
    ];
}

// Get joined players with avatars
$playerStmt = $conn->prepare("SELECT player_id, player_name, joined_at FROM players WHERE session_code = ? ORDER BY joined_at");
$playerStmt->bind_param("s", $gameSession['session_code']);
$playerStmt->execute();
$players = $playerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare player data for JS
$playersData = array_map(function($player) {
    return [
        'id' => $player['player_id'],
        'name' => $player['player_name'],
        'joined' => $player['joined_at']
    ];
}, $players);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Lobby - <?= htmlspecialchars($session['session_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script> -->
     <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
            --card-bg: rgba(255, 255, 255, 0.15);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            min-height: 100vh;
            color: white;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .code-display {
            font-size: 3rem;
            letter-spacing: 0.5rem;
            font-weight: bold;
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin: 1rem 0;
        }
        
        /* Player Cards */
        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1.5rem;
            padding: 1rem;
        }
        
        .player-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .player-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(255, 255, 255, 0),
                rgba(255, 255, 255, 0.1),
                rgba(255, 255, 255, 0)
            );
            transform: rotate(30deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(30deg); }
            100% { transform: translateX(100%) rotate(30deg); }
        }
        
        .player-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .player-card:hover .player-avatar {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .player-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .player-joined {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        /* QR Code Styling */
        .qr-container {
            background: white;
            padding: 1rem;
            border-radius: 15px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .qr-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                135deg,
                rgba(106, 17, 203, 0.1),
                rgba(37, 117, 252, 0.1)
            );
            z-index: 1;
        }
        
        .qr-container canvas {
            position: relative;
            z-index: 2;
            border-radius: 8px;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .players-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .player-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="preview_game.php?session=<?= $sessionId ?>" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <h2 class="text-center mb-0"><?= htmlspecialchars($session['session_name']) ?></h2>
                        <div></div> <!-- Spacer for alignment -->
                    </div>
                    
                    <div class="glass-card p-4 text-center mb-4 pulse">
                        <h3><i class="fas fa-door-open me-2"></i>Kode Akses Game</h3>
                        <div class="code-display"><?= $gameSession['session_code'] ?></div>
                        
                        <div class="d-flex justify-content-center">
                            <div class="qr-container" id="qrcode"></div>
                        </div>
                        <small class="text-muted mt-2 d-block">Scan QR code untuk bergabung</small>
                        
                        <div class="mt-3">
                            <p class="mb-1">Atau gunakan link berikut:</p>
                            <div class="join-link bg-dark bg-opacity-25 p-2 rounded">
                                <?= "https://".$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/join_game.php?code=".$gameSession['session_code'] ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="glass-card p-4">
                        <h3 class="text-center mb-4"><i class="fas fa-users me-2"></i>Pemain yang Bergabung</h3>
                        
                        <div class="players-grid" id="players-container">
                            <?php foreach ($players as $player): ?>
                                <div class="player-card animate__animated animate__fadeInUp">
                                    <div class="player-avatar">
                                        <?= strtoupper(substr($player['player_name'], 0, 1)) ?>
                                    </div>
                                    <div class="player-name"><?= htmlspecialchars($player['player_name']) ?></div>
                                    <div class="player-joined">
                                        <?= date('H:i', strtotime($player['joined_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($players) === 0): ?>
                                <div class="col-12 text-center py-4">
                                    <i class="fas fa-users-slash me-2"></i>Belum ada pemain yang bergabung
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-center mt-4">
                            <button id="start-game-btn" class="btn btn-primary btn-lg" <?= count($players) === 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-play me-2"></i> Mulai Permainan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
    // Inisialisasi QR Code dengan error handling
    document.addEventListener('DOMContentLoaded', function() {
        try {
            if (document.getElementById('qrcode') && typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById("qrcode"), {
                    text: "<?= $gameSession['session_code'] ?>",
                    width: 180,
                    height: 180,
                    colorDark: "#6a11cb",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                console.warn('QR Code element or library not available');
                document.getElementById('qrcode').innerHTML = `
                    <div class="text-center p-3">
                        <div class="fs-2"><?= $gameSession['session_code'] ?></div>
                        <small class="text-muted">Scan kode manual</small>
                    </div>
                `;
            }
        } catch (error) {
            console.error('QR Code initialization failed:', error);
        }
    });

    // Fungsi update pemain dengan error handling
   async function updatePlayers() {
    try {
        const response = await fetch(`get_players.php?code=<?= $gameSession['session_code'] ?>`);
        
        // Cek content type sebelum parse
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Response bukan JSON');
        }
        
        const players = await response.json();
        
        // Debugging log
        console.log('Players data:', players);
        
        const container = document.getElementById('players-container');
        if (!container) return;

        if (!Array.isArray(players)) {
            throw new Error('Data pemain tidak valid');
        }

        if (players.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fas fa-users-slash me-2"></i>Belum ada pemain yang bergabung
                </div>
            `;
            document.getElementById('start-game-btn').disabled = true;
            return;
        }
        
        let html = '';
        players.forEach(player => {
            if (!player.player_name) return;
            
            html += `
                <div class="player-card animate__animated animate__fadeInUp">
                    <div class="player-avatar">
                        ${player.player_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="player-name">${player.player_name}</div>
                    <div class="player-joined">
                        ${player.joined_at ? new Date(player.joined_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        const startBtn = document.getElementById('start-game-btn');
        if (startBtn) startBtn.disabled = false;
        
    } catch (error) {
        console.error('Error updating players:', error);
        // Fallback UI
        const container = document.getElementById('players-container');
        if (container) {
            container.innerHTML = `
                <div class="col-12 text-center py-4 text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Gagal memuat data pemain
                </div>
            `;
        }
    }
}

    // Fungsi utama dengan verifikasi
    async function initializeGame() {
        // Load awal
        await updatePlayers();
        
       // GANTI BAGIAN INI:


// DENGAN INI:
let retryCount = 0;
const maxRetries = 3;

async function updatePlayersWithRetry() {
    try {
        await updatePlayers();
        retryCount = 0;
    } catch (error) {
        retryCount++;
        if (retryCount <= maxRetries) {
            console.log(`Retry ${retryCount}/${maxRetries}`);
            setTimeout(updatePlayersWithRetry, 2000);
        } else {
            console.error('Max retries reached');
            // Fallback UI ketika gagal terus
            const container = document.getElementById('players-container');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Gagal memuat data pemain setelah ${maxRetries} percobaan
                    </div>
                `;
            }
        }
    }
}

// Jalankan pertama kali
updatePlayersWithRetry();

// Set interval untuk polling berkala
setInterval(updatePlayersWithRetry, 5000);
        
        // Tombol mulai game
   document.getElementById('start-game-btn').addEventListener('click', async function() {
    const button = this;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memulai...';

    try {
        // Kirim request
        const response = await fetch('start_game.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                code: '<?= $gameSession['session_code'] ?>' 
            })
        });

        // Handle invalid JSON
        const textResponse = await response.text();
        let data;
        
        try {
            data = JSON.parse(textResponse);
        } catch {
            // Debugging tambahan
            console.error('Invalid JSON received:', textResponse);
            throw new Error('Server returned invalid response');
        }

        if (!data.success) {
            throw new Error(data.message || 'Failed to start game');
        }

        // Verifikasi status game
        const statusCheck = await fetch(`check_game_status.php?code=<?= $gameSession['session_code'] ?>`);
        const statusData = await statusCheck.json();

        if (statusData.game_active) {
            window.location.href = `game_play.php?code=<?= $gameSession['session_code'] ?>&round=1`;
        } else {
            throw new Error('Game did not activate');
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-play me-2"></i> Mulai Permainan';
    }
});
        
        // Copy link
        const joinLink = document.querySelector('.join-link');
        if (joinLink) {
            joinLink.addEventListener('click', function() {
                const text = this.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const original = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check me-2"></i> Link berhasil disalin!';
                    setTimeout(() => {
                        this.innerHTML = original;
                    }, 2000);
                }).catch(err => {
                    console.error('Copy failed:', err);
                    alert('Gagal menyalin link');
                });
            });
        }
        
        // Cleanup saat unload
        window.addEventListener('beforeunload', () => {
            clearInterval(updateInterval);
        });
    }

    // Jalankan inisialisasi
    initializeGame();
</script>
</body>
</html>