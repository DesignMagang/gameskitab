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

// Get game history
function getGameHistory($conn, $sessionId) {
    $history = [];
    $rounds = $conn->query("
        SELECT round_number, COUNT(*) as team_count, 
               MIN(completion_time) as best_time, 
               MAX(score) as max_score
        FROM game_results 
        WHERE session_id = '$sessionId'
        GROUP BY round_number
        ORDER BY round_number
    ");

    while ($row = $rounds->fetch_assoc()) {
        $roundNumber = $row['round_number'];
        $teams = $conn->query("
            SELECT id, team_name, completion_time, score, attempts 
            FROM game_results 
            WHERE session_id = '$sessionId' AND round_number = $roundNumber
            ORDER BY score DESC, completion_time ASC
        ");
        
        $teamData = [];
        while ($team = $teams->fetch_assoc()) {
            $teamData[] = $team;
        }
        
        $history[] = [
            'round_number' => $roundNumber,
            'team_count' => $row['team_count'],
            'best_time' => $row['best_time'],
            'max_score' => $row['max_score'],
            'teams' => $teamData
        ];
    }
    return $history;
}

// Get podium winners
function getPodiumWinners($conn, $sessionId) {
    $winners = $conn->query("
        SELECT team_name, COUNT(*) as win_count 
        FROM game_results 
        WHERE session_id = '$sessionId' AND score = (
            SELECT MAX(score) 
            FROM game_results gr 
            WHERE gr.session_id = game_results.session_id 
            AND gr.round_number = game_results.round_number
        )
        GROUP BY team_name
        ORDER BY win_count DESC
        LIMIT 3
    ");

    $podium = [];
    while ($row = $winners->fetch_assoc()) {
        $podium[] = $row;
    }
    return $podium;
}

$history = getGameHistory($conn, $sessionId);
$podium = getPodiumWinners($conn, $sessionId);
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Permainan - <?= htmlspecialchars($session['session_name']) ?></title>
        <link rel="icon" href="logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- <link rel="icon" type="image/png" href="logo.png"> -->

    <style>
        /* Custom animation for podium steps */
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(60px) scale(0.9); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .podium-step-animation {
            animation: fadeInUp 0.5s forwards;
        }
        /* Custom glass effect as Tailwind plugin might not support it out of the box */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .refresh-icon.spinning {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-800 to-blue-600 min-h-screen text-white font-sans p-4 sm:p-6">
    <div class="container mx-auto max-w-4xl py-5">
        <div id="main-content">
            <div class="glass-card rounded-2xl p-4 mb-4 shadow-lg">
                <div class="flex justify-between items-center">
                    <a href="demo.php?session=<?= $sessionId ?>" class="btn btn-outline btn-light">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Kembali
                    </a>
                    <h2 class="text-2xl font-bold text-center">Riwayat Permainan</h2>
                    <div class="w-24"></div> </div>
                <h3 class="text-center mt-3 text-xl font-semibold text-gray-200"><?= htmlspecialchars($session['session_name']) ?></h3>
            </div>
            
            <?php if (!empty($podium)): ?>
            <div id="podium-section" class="glass-card rounded-2xl p-5 mb-4 shadow-lg">
                <div class="text-center mb-6">
                    <h4 class="text-2xl font-bold inline-block relative pb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block mr-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.25 2.25a.75.75 0 00-1.5 0v1.135A4.001 4.001 0 005.135 7.5H4a.75.75 0 000 1.5h1.135a4.001 4.001 0 003.365 3.865v1.135a.75.75 0 001.5 0v-1.135a4.001 4.001 0 003.365-3.865H16a.75.75 0 000-1.5h-1.135A4.001 4.001 0 0011.25 3.385V2.25zM8.5 7.5a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0z" clip-rule="evenodd" /></svg>
                        Podium Pemenang
                    </h4>
                    <div class="mt-2 text-sm text-gray-400">
                        Terakhir diperbarui: <span id="last-updated"><?= date('H:i:s') ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2 inline-block refresh-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5" /><path d="M4 9a9 9 0 0114.13-4.57" /><path d="M20 15a9 9 0 01-14.13 4.57" /></svg>
                    </div>
                </div>
                
                <div class="flex justify-center items-end gap-2 md:gap-4 flex-col md:flex-row">
                    <div class="podium-step-animation w-11/12 md:w-1/3 order-2 md:order-1" style="animation-delay: 0.2s;">
                        <div class="bg-gray-400 text-center font-bold text-lg p-3 rounded-t-lg flex justify-center items-center shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 00-1.223.992l-.767 5.37-4.02-4.02a1 1 0 00-1.414 1.414l4.02 4.02-5.37.767A1 1 0 003 12.25v5.5a1 1 0 001 1h5.5a1 1 0 00.758-1.66l-5.37-.767 4.02-4.02 4.02 4.02-.767 5.37A1 1 0 0012.25 19h5.5a1 1 0 001-1v-5.5a1 1 0 00-1.66-.758l-.767-5.37 4.02-4.02a1 1 0 10-1.414-1.414l-4.02 4.02.767-5.37A1 1 0 0013.75 3h-3.5A1 1 0 0010 3z" clip-rule="evenodd" /></svg>
                            #2
                        </div>
                        <div class="bg-white text-gray-800 p-4 rounded-b-lg flex flex-col items-center shadow-lg">
                            <div class="w-16 h-16 rounded-full bg-gray-400 flex items-center justify-center text-2xl font-bold text-white mb-3 shadow-inner">
                                <?= isset($podium[1]) ? substr($podium[1]['team_name'], 0, 1) : '?' ?>
                            </div>
                            <h5 class="font-bold text-md truncate w-full text-center"><?= isset($podium[1]) ? htmlspecialchars($podium[1]['team_name']) : '-' ?></h5>
                            <div class="font-bold text-lg"><?= isset($podium[1]) ? $podium[1]['win_count'] . 'x <span class="text-sm">menang</span>' : '0x' ?></div>
                        </div>
                    </div>
                    
                    <div class="podium-step-animation w-11/12 md:w-1/3 order-1 md:order-2" style="animation-delay: 0.4s;">
                        <div class="bg-yellow-500 text-center font-bold text-xl p-4 rounded-t-lg flex justify-center items-center shadow-lg transform md:scale-110">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 20 20" fill="currentColor"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                            #1
                        </div>
                        <div class="bg-white text-gray-800 p-6 rounded-b-lg flex flex-col items-center shadow-xl transform md:scale-110">
                             <div class="w-20 h-20 rounded-full bg-yellow-500 flex items-center justify-center text-3xl font-bold text-white mb-4 shadow-inner">
                                <?= isset($podium[0]) ? substr($podium[0]['team_name'], 0, 1) : '?' ?>
                            </div>
                            <h5 class="font-bold text-lg truncate w-full text-center"><?= isset($podium[0]) ? htmlspecialchars($podium[0]['team_name']) : '-' ?></h5>
                            <div class="font-bold text-xl"><?= isset($podium[0]) ? $podium[0]['win_count'] . 'x <span class="text-base">menang</span>' : '0x' ?></div>
                        </div>
                    </div>

                    <div class="podium-step-animation w-11/12 md:w-1/3 order-3 md:order-3" style="animation-delay: 0.6s;">
                        <div class="bg-yellow-700 text-center font-bold text-lg p-3 rounded-t-lg flex justify-center items-center shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 00-1.223.992l-.767 5.37-4.02-4.02a1 1 0 00-1.414 1.414l4.02 4.02-5.37.767A1 1 0 003 12.25v5.5a1 1 0 001 1h5.5a1 1 0 00.758-1.66l-5.37-.767 4.02-4.02 4.02 4.02-.767 5.37A1 1 0 0012.25 19h5.5a1 1 0 001-1v-5.5a1 1 0 00-1.66-.758l-.767-5.37 4.02-4.02a1 1 0 10-1.414-1.414l-4.02 4.02.767-5.37A1 1 0 0013.75 3h-3.5A1 1 0 0010 3z" clip-rule="evenodd" /></svg>
                            #3
                        </div>
                        <div class="bg-white text-gray-800 p-4 rounded-b-lg flex flex-col items-center shadow-lg">
                            <div class="w-16 h-16 rounded-full bg-yellow-700 flex items-center justify-center text-2xl font-bold text-white mb-3 shadow-inner">
                                <?= isset($podium[2]) ? substr($podium[2]['team_name'], 0, 1) : '?' ?>
                            </div>
                            <h5 class="font-bold text-md truncate w-full text-center"><?= isset($podium[2]) ? htmlspecialchars($podium[2]['team_name']) : '-' ?></h5>
                            <div class="font-bold text-lg"><?= isset($podium[2]) ? $podium[2]['win_count'] . 'x <span class="text-sm">menang</span>' : '0x' ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="history-section">
            <?php if (empty($history)): ?>
                <div class="glass-card rounded-2xl p-8 text-center shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <h4 class="text-2xl font-bold">Belum ada riwayat permainan</h4>
                    <p class="text-gray-300 mt-2">Mulai permainan untuk melihat riwayatnya di sini</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($history as $round): ?>
                    <div class="collapse collapse-plus glass-card rounded-2xl shadow-lg">
                        <input type="checkbox" checked /> 
                        <div class="collapse-title text-xl font-medium p-4">
                            <div class="flex justify-between items-center w-full">
                                <h4 class="text-lg font-bold">Ronde <?= $round['round_number'] ?></h4>
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-primary"><?= $round['team_count'] ?> Kelompok</span>
                                    <span class="badge badge-success">Rekor: <?= $round['max_score'] ?> Poin</span>
                                </div>
                            </div>
                        </div>
                        <div class="collapse-content px-4 pb-4">
                            <div class="space-y-2">
                            <?php foreach ($round['teams'] as $index => $team): ?>
                                <div class="bg-gray-800/50 p-3 rounded-lg flex justify-between items-center transition hover:bg-gray-800/70">
                                    <div>
                                        <h5 class="font-bold text-base mb-1">
                                            <?= htmlspecialchars($team['team_name']) ?>
                                            <?php if ($index === 0): ?>
                                                <span class="badge badge-warning font-bold ml-2">Pemenang</span>
                                            <?php endif; ?>
                                        </h5>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-400">
                                            <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><?= $team['completion_time'] ?></span>
                                            <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg><?= $team['score'] ?> Poin</span>
                                            <span class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0114.13-4.57M20 15a9 9 0 01-14.13 4.57" /></svg><?= $team['attempts'] ?> Percobaan</span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-circle btn-outline btn-error" onclick="confirmDelete(<?= $team['id'] ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval = setInterval(refreshData, 5000);
        let isRefreshing = false;
        const refreshIcon = document.querySelector('.refresh-icon');

        function refreshData() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            refreshIcon.classList.add('spinning');
            
            // Use fetch API to get fresh content without full page reload
            fetch(window.location.href + '&refresh=' + new Date().getTime())
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    
                    const newContent = newDoc.getElementById('main-content');
                    if (newContent) {
                        document.getElementById('main-content').innerHTML = newContent.innerHTML;
                        // Re-initialize any dynamic elements if necessary, e.g., animations
                        document.querySelectorAll('.podium-step-animation').forEach(step => {
                            step.style.animation = 'none';
                            void step.offsetHeight; // Reflow
                            step.style.animation = ''; // Re-apply animation from CSS
                        });
                    }
                    updateLastUpdatedTime();
                })
                .catch(error => {
                    console.error('Refresh error:', error);
                })
                .finally(() => {
                    isRefreshing = false;
                    // Check if icon exists before trying to remove class
                    const currentRefreshIcon = document.querySelector('.refresh-icon');
                    if (currentRefreshIcon) {
                        currentRefreshIcon.classList.remove('spinning');
                    }
                });
        }
        
        function updateLastUpdatedTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            const lastUpdatedEl = document.getElementById('last-updated');
            if(lastUpdatedEl) {
                lastUpdatedEl.textContent = timeString;
            }
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else {
                refreshData(); // Refresh immediately on return
                refreshInterval = setInterval(refreshData, 5000);
            }
        });

        function confirmDelete(resultId) {
            if (confirm("Apakah Anda yakin ingin menghapus hasil ini?")) {
                fetch('delete_result.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: resultId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        refreshData(); // Refresh content after successful delete
                    } else {
                        alert("Gagal menghapus: " + (data.message || 'Terjadi kesalahan'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("Terjadi kesalahan saat menghapus");
                });
            }
        }
    </script>
</body>
</html>