<?php
session_start();
require_once 'db.php';

// Initialize variables with default values
$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;
$roundNumber = isset($_GET['round']) ? (int)$_GET['round'] : 0;
$sessionName = "Unknown Session";
$results = [];
$maxPossibleScore = 0;
$availableRounds = [];

// Handle AJAX delete request
if (isset($_POST['action']) && $_POST['action'] === 'delete_participant') {
    header('Content-Type: application/json');
    
    $deleteSessionId = filter_var($_POST['session_id'], FILTER_VALIDATE_INT);
    $deleteRoundNumber = filter_var($_POST['round_number'], FILTER_VALIDATE_INT);
    $playerName = $conn->real_escape_string($_POST['player_name']);
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

    if (!$deleteSessionId || !$deleteRoundNumber || !$userId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        exit;
    }

    $deleteQuery = $conn->prepare("DELETE FROM survival_answers WHERE session_id = ? AND round_number = ? AND player_name = ? AND user_id = ?");
    if ($deleteQuery && $deleteQuery->bind_param("iisi", $deleteSessionId, $deleteRoundNumber, $playerName, $userId) && $deleteQuery->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Participant deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $conn->error]);
    }
    exit;
}

// Fetch session data and available rounds if valid session ID
if ($sessionId > 0) {
    // Get session name
    $sessionQuery = $conn->prepare("SELECT session_name FROM survival_sessions WHERE id = ?");
    if ($sessionQuery) {
        $sessionQuery->bind_param("i", $sessionId);
        $sessionQuery->execute();
        $sessionResult = $sessionQuery->get_result();
        if ($sessionResult->num_rows > 0) {
            $sessionName = htmlspecialchars($sessionResult->fetch_assoc()['session_name']);
        }
        $sessionQuery->close();
    }

    // Get available rounds for this session
    $roundsQuery = $conn->prepare("SELECT DISTINCT round_number FROM survival_answers WHERE session_id = ? ORDER BY round_number ASC");
    if ($roundsQuery) {
        $roundsQuery->bind_param("i", $sessionId);
        $roundsQuery->execute();
        $roundsResult = $roundsQuery->get_result();
        while ($row = $roundsResult->fetch_assoc()) {
            $availableRounds[] = $row['round_number'];
        }
        $roundsQuery->close();
    }

    // If no specific round is selected, default to the latest round
    if ($roundNumber === 0 && !empty($availableRounds)) {
        $roundNumber = max($availableRounds);
    } elseif ($roundNumber === 0 && empty($availableRounds)) {
        // No rounds exist yet, set a default of 1 for display purposes
        $roundNumber = 1;
    }


    // Get leaderboard data with elimination status for the selected round
    $leaderboardQuery = $conn->prepare("
        SELECT 
            sa.player_name, 
            sa.user_id, 
            SUM(sa.points_earned) AS total_points,
            MAX(CASE WHEN sa.is_correct = 0 THEN 1 ELSE 0 END) AS is_eliminated
        FROM survival_answers sa
        WHERE sa.session_id = ? AND sa.round_number = ?
        GROUP BY sa.player_name, sa.user_id
        ORDER BY total_points DESC, RAND()
    ");
    
    if ($leaderboardQuery) {
        $leaderboardQuery->bind_param("ii", $sessionId, $roundNumber);
        $leaderboardQuery->execute();
        $leaderboardResult = $leaderboardQuery->get_result();
        while ($row = $leaderboardResult->fetch_assoc()) {
            $results[] = [
                'player_name' => $row['player_name'],
                'user_id' => $row['user_id'],
                'total_points' => $row['total_points'],
                'is_eliminated' => (bool)$row['is_eliminated']
            ];
        }
        $leaderboardQuery->close();
    }
}

// Get current player info (not directly used for display, but kept for consistency)
$currentPlayerName = $_SESSION['player_name'] ?? 'Guest';
$finalScore = $_SESSION['final_score'] ?? 0;
unset($_SESSION['player_name'], $_SESSION['final_score']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?= $sessionName ?> (Round <?= $roundNumber ?>)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #1a202c, #2d3748);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .result-container {
            background: rgba(26, 32, 44, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .table-row {
            transition: all 0.3s ease;
            animation: fadeIn 0.5s forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .eliminated-text {
            color: #ef4444;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.7; text-shadow: 0 0 5px rgba(239, 68, 68, 0.5); }
            50% { opacity: 1; text-shadow: 0 0 10px rgba(239, 68, 68, 0.8); }
        }
        .top-player {
            background: linear-gradient(90deg, rgba(234, 179, 8, 0.1), rgba(234, 179, 8, 0.05));
        }
        .second-player {
            background: linear-gradient(90deg, rgba(156, 163, 175, 0.1), rgba(156, 163, 175, 0.05));
        }
        .third-player {
            background: linear-gradient(90deg, rgba(180, 83, 9, 0.1), rgba(180, 83, 9, 0.05));
        }
    </style>
</head>
<body class="text-gray-100 flex items-center justify-center p-4">
    <div class="result-container w-full max-w-4xl rounded-xl overflow-hidden p-6">
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-300">
                    <i class="fas fa-trophy mr-2"></i>HASIL SURVIVAL
                </h1>
                <div class="flex flex-wrap gap-4 mt-2">
                    <span class="bg-blue-900/50 px-3 py-1 rounded-full text-sm">
                        <?= htmlspecialchars($sessionName) ?>
                    </span>
                    <div class="relative inline-block text-left">
                        <select id="roundSelector" onchange="window.location.href = '?session=<?= $sessionId ?>&round=' + this.value"
                            class="bg-purple-900/50 px-3 py-1 rounded-full text-sm appearance-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-purple-500 pr-8">
                            <?php if (empty($availableRounds)): ?>
                                <option value="<?= $roundNumber ?>" selected>Ronde <?= $roundNumber ?> (Tidak ada data)</option>
                            <?php else: ?>
                                <?php foreach ($availableRounds as $round): ?>
                                    <option value="<?= $round ?>" <?= ($round == $roundNumber) ? 'selected' : '' ?>>
                                        Ronde <?= $round ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-100">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 6.096 6.924 4.682 8.338z"/></svg>
                        </div>
                    </div>
                </div>
            </div>
            <button id="refreshBtn" class="mt-4 md:mt-0 bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg flex items-center transition-all">
                <i class="fas fa-sync-alt mr-2"></i>
                <span id="refreshText">Auto-Refresh ON</span>
            </button>
        </div>

        <?php if (!empty($results)): ?>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="w-full">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="py-3 px-4 text-left rounded-tl-lg">Peringkat</th>
                            <th class="py-3 px-4 text-left">Nama Pemain</th>
                            <th class="py-3 px-4 text-right rounded-tr-lg">Skor</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $index => $row): ?>
                            <tr class="table-row <?= $index === 0 ? 'top-player' : '' ?> <?= $index === 1 ? 'second-player' : '' ?> <?= $index === 2 ? 'third-player' : '' ?> hover:bg-gray-800/50" 
                                style="animation-delay: <?= $index * 0.05 ?>s">
                                <td class="py-3 px-4 font-bold">
                                    <?= $index + 1 ?>
                                    <?php if ($index < 3): ?>
                                        <span class="ml-2">
                                            <?php if ($index === 0): ?>🏆<?php elseif ($index === 1): ?>🥈<?php else: ?>🥉<?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['player_name']) ?></td>
                                <td class="py-3 px-4 text-right font-mono">
                                    <span class="<?= $row['is_eliminated'] ? 'eliminated-text' : 'text-green-400' ?>">
                                        <?= $row['total_points'] ?>
                                        <?= $row['is_eliminated'] ? ' (GUGUR)' : '' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <button class="delete-btn bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm transition-all"
                                        data-session="<?= $sessionId ?>"
                                        data-round="<?= $roundNumber ?>"
                                        data-player="<?= htmlspecialchars($row['player_name']) ?>"
                                        data-user="<?= $row['user_id'] ?>">
                                        <i class="fas fa-trash mr-1"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12 bg-gray-800/50 rounded-lg">
                <i class="fas fa-exclamation-triangle text-3xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-medium">Belum Ada Hasil</h3>
                <p class="text-gray-400 mt-2">Tidak ada pemain yang menyelesaikan ronde ini.</p>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="survival.php" class="inline-block bg-gray-700 hover:bg-gray-600 px-6 py-2 rounded-lg transition-all">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Sesi
            </a>
        </div>
    </div>

    <script>
        // Auto-refresh functionality
        let refreshInterval = null;
        const refreshBtn = document.getElementById('refreshBtn');
        const refreshText = document.getElementById('refreshText');
        
        function toggleRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
                refreshText.textContent = "Auto-Refresh OFF";
                refreshBtn.classList.remove('bg-green-600');
                refreshBtn.classList.add('bg-indigo-600');
            } else {
                refreshInterval = setInterval(() => location.reload(), 5000);
                refreshText.textContent = "Auto-Refresh ON";
                refreshBtn.classList.remove('bg-indigo-600');
                refreshBtn.classList.add('bg-green-600');
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Start with auto-refresh on
            toggleRefresh();
            
            // Button click handler
            refreshBtn.addEventListener('click', toggleRefresh);
            
            // Delete button handlers
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    Swal.fire({
                        title: 'Hapus Peserta?',
                        text: `Yakin ingin menghapus ${this.dataset.player} dari hasil?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal',
                        background: '#1a202c',
                        color: 'white'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch(window.location.href, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'delete_participant',
                                    session_id: this.dataset.session,
                                    round_number: this.dataset.round,
                                    player_name: this.dataset.player,
                                    user_id: this.dataset.user
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({
                                        title: 'Terhapus!',
                                        text: data.message,
                                        icon: 'success',
                                        background: '#1a202c',
                                        color: 'white'
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: data.message,
                                        icon: 'error',
                                        background: '#1a202c',
                                        color: 'white'
                                    });
                                }
                            });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>