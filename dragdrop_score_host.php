<?php
session_start();
include 'db.php'; // Pastikan koneksi database tersedia

// Redirect jika tidak login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['sessionid'])) {
    die('Session ID is missing.');
}

$sessionId = $_GET['sessionid'];
$session_name = '';
$available_rounds = [];

try {
    // Ambil nama sesi
    $stmt_session = $conn->prepare("SELECT session_name FROM dragdrop_sessions WHERE sessionid = ?");
    $stmt_session->bind_param("s", $sessionId);
    $stmt_session->execute();
    $result_session = $stmt_session->get_result();
    if ($result_session->num_rows === 0) {
        die('Invalid session ID.');
    }
    $session_data = $result_session->fetch_assoc();
    $session_name = htmlspecialchars($session_data['session_name']);
    $stmt_session->close();

    // Ambil daftar ronde yang tersedia untuk sesi ini, termasuk 'Final_Score'
    // Menggunakan UNION untuk memastikan 'Final_Score' selalu ada jika entri lain ada
    $stmt_rounds = $conn->prepare("
        SELECT DISTINCT round_number FROM dragdrop_results WHERE session_id = ?
        ORDER BY 
            CASE 
                WHEN round_number = 'Final_Score' THEN 0 
                ELSE 1 
            END,
            CAST(round_number AS UNSIGNED), -- Sort numeric rounds properly
            round_number
    ");
    $stmt_rounds->bind_param("s", $sessionId);
    $stmt_rounds->execute();
    $result_rounds = $stmt_rounds->get_result();
    while ($row = $result_rounds->fetch_assoc()) {
        $available_rounds[] = $row['round_number'];
    }
    $stmt_rounds->close();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Tentukan ronde yang akan ditampilkan secara default
// Jika ada ronde "Final_Score", itu akan menjadi default. Jika tidak, ronde pertama yang tersedia.
$default_round_to_display = '';
if (in_array('Final_Score', $available_rounds)) {
    $default_round_to_display = 'Final_Score';
} elseif (!empty($available_rounds)) {
    $default_round_to_display = $available_rounds[0];
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Peringkat: <?= $session_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a; /* Dark slate background */
            color: #e2e8f0; /* Light slate text */
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        .container-results {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2); /* Blue glowing effect */
        }
        .refresh-btn {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); /* Orange gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(234, 88, 12, 0.4);
        }
        .back-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4);
        }
        .trophy-icon {
            color: #FFD700; /* Gold */
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }
        .trophy-icon.silver {
            color: #C0C0C0; /* Silver */
            text-shadow: 0 0 10px rgba(192, 192, 192, 0.5);
        }
        .trophy-icon.bronze {
            color: #CD7F32; /* Bronze */
            text-shadow: 0 0 10px rgba(205, 127, 50, 0.5);
        }
        .loading-spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .delete-btn {
            background-color: #ef4444; /* Red-500 */
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        .delete-btn:hover {
            background-color: #dc2626; /* Red-600 */
        }
        /* Styles for the dropdown */
        .round-selector-container {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .round-selector {
            background-color: rgba(30, 41, 59, 0.7); /* Darker, semi-transparent */
            border: 1px solid #475569; /* slate-600 */
            color: #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            cursor: pointer;
            outline: none;
            appearance: none; /* Remove default select arrow */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23e2e8f0%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13%205.1L146.2%20202.7%2018.5%2074.5a17.6%2017.6%200%200%200-25.3%2024.7l130.8%20129.8c3.4%203.4%207.8%205.1%2012.3%205.1s8.9-1.7%2012.3-5.1L287%2094.1a17.6%2017.6%200%200%200%200-24.7z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 0.7em top 50%, 0 0;
            background-size: 0.65em auto, 100%;
        }
    </style>
</head>
<body class="relative flex flex-col items-center justify-center min-h-screen px-4 pb-12">
    <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
    </div>

    <div class="w-full max-w-3xl mx-auto mb-8">
        <div class="text-center mb-10">
            <h1 class="title-font text-4xl font-bold text-white mb-2">Hasil Peringkat</h1>
            <p class="text-slate-300">Sesi: <span class="font-semibold text-blue-300"><?= $session_name ?></span></p>
        </div>

        <div class="container-results p-6 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h2 class="text-2xl font-bold text-white" id="mainTableTitle"></h2>
                
                <div class="round-selector-container">
                    <!-- <label for="roundSelect" class="text-slate-300 font-medium">Pilih Ronde:</label> -->
                    <select id="roundSelect" class="round-selector">
                        <?php if (empty($available_rounds)): ?>
                            <option value="">Tidak ada ronde tersedia</option>
                        <?php else: ?>
                            <?php foreach ($available_rounds as $round): ?>
                                <option value="<?= htmlspecialchars($round) ?>" <?= ($round == $default_round_to_display) ? 'selected' : '' ?>>
                                    <?php 
                                        if ($round == 'Final_Score') {
                                            echo 'Peringkat Akhir';
                                        } else {
                                            echo 'Ronde ' . htmlspecialchars($round);
                                        }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="flex space-x-3">
                    <button id="refreshResultsBtn" class="py-2 px-4 refresh-btn text-white font-semibold rounded-lg flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <button onclick="window.location.href='index.php'" class="py-2 px-4 back-btn text-white font-semibold rounded-lg">
                        <i class="fas fa-home mr-2"></i> 
                    </button>
                </div>
            </div>

            <div id="loadingSpinner" class="flex justify-center items-center py-8 hidden">
                <div class="loading-spinner"></div>
                <p class="text-slate-400 ml-3">Memuat peringkat...</p>
            </div>

            <div id="resultsDisplay" class="min-h-[200px]">
                </div>
            
            <div id="noResultsMessage" class="hidden text-center text-slate-400 py-8">
                Belum ada hasil untuk ronde ini.
            </div>
        </div>
    </div>

    <script>
        const sessionId = "<?= $sessionId ?>";
        const roundSelect = document.getElementById('roundSelect');
        const resultsDisplay = document.getElementById('resultsDisplay');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const refreshResultsBtn = document.getElementById('refreshResultsBtn');
        const mainTableTitle = document.getElementById('mainTableTitle');

        let refreshIntervalId; // To store the interval ID for auto-refresh
        let isAutoRefreshActive = false; // Default: mati

        // Function to format time from seconds to MM:SS
        function formatTime(seconds) {
            if (isNaN(seconds) || seconds === null) {
                return 'N/A';
            }
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        async function fetchAndDisplayResults(selectedRound, shuffle = false) {
            loadingSpinner.classList.remove('hidden');
            resultsDisplay.innerHTML = '';
            noResultsMessage.classList.add('hidden');

            try {
                const response = await fetch(`fetch_dragdrop_score_host.php?sessionid=${sessionId}`);
                const data = await response.json();

                loadingSpinner.classList.add('hidden');

                if (data.success && data.results.length > 0) {
                    let filteredResults = data.results.filter(result => result.round_number == selectedRound);

                    // Set table title based on selected round
                    if (selectedRound === 'Final_Score') {
                        mainTableTitle.textContent = 'Peringkat Akhir Tim';
                    } else {
                        mainTableTitle.textContent = `Ronde ${selectedRound}`;
                    }

                    if (filteredResults.length > 0) {
                        if (shuffle) {
                            shuffleArray(filteredResults);
                        } else {
                            // Sort results by time_taken then submission_time
                            filteredResults.sort((a, b) => {
                                if (a.time_taken !== b.time_taken) {
                                    return a.time_taken - b.time_taken;
                                }
                                return new Date(a.submission_time) - new Date(b.submission_time);
                            });
                        }

                        let tableHtml = `
                            <table class="min-w-full bg-slate-800 rounded-lg overflow-hidden shadow-lg">
                                <thead class="bg-slate-700">
                                    <tr>
                                        <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Peringkat</th>
                                        <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Nama Tim</th>
                                        <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Waktu</th>
                                        <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Waktu Submit</th>
                                        <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700">
                        `;

                        filteredResults.forEach((result, index) => {
                            const rank = index + 1;
                            let trophyIcon = '';
                            if (rank === 1) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon mr-2"></i>';
                            } else if (rank === 2) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon silver mr-2"></i>';
                            } else if (rank === 3) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon bronze mr-2"></i>';
                            }
                            
                            tableHtml += `
                                <tr class="bg-blue-900/40 font-semibold hover:bg-slate-700/50 transition-colors duration-200">
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">
                                        ${trophyIcon} ${rank}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${result.team_name}</td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${formatTime(result.time_taken)}</td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${new Date(result.submission_time).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' })}</td>
                                    <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">
                                        <button class="delete-btn" data-id="${result.id}">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });

                        tableHtml += `
                                </tbody>
                            </table>
                        `;
                        resultsDisplay.innerHTML = tableHtml;

                        // Add event listeners for all delete buttons after all tables are rendered
                        document.querySelectorAll('.delete-btn').forEach(button => {
                            button.addEventListener('click', (event) => {
                                const resultId = event.currentTarget.dataset.id;
                                if (confirm('Apakah Anda yakin ingin menghapus hasil ini?')) {
                                    deleteResult(resultId, selectedRound);
                                }
                            });
                        });

                    } else {
                        noResultsMessage.classList.remove('hidden');
                    }
                } else {
                    noResultsMessage.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error fetching results:', error);
                loadingSpinner.classList.add('hidden');
                resultsDisplay.innerHTML = `<p class="text-red-400 text-center py-8">Gagal memuat hasil: ${error.message}</p>`;
            }
        }

        // Function to delete a result
        async function deleteResult(resultId, currentRound) {
            try {
                const response = await fetch('delete_dragdrop_result.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: resultId })
                });
                const data = await response.json();

                if (data.success) {
                    alert('Hasil berhasil dihapus!');
                    fetchAndDisplayResults(currentRound, false); // Refresh results after deletion for the current round
                } else {
                    alert('Gagal menghapus hasil: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting result:', error);
                alert('Terjadi kesalahan saat menghapus hasil.');
            }
        }

        // Function to shuffle an array
        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
        }

        // Auto-refresh functionality
        function startAutoRefresh(intervalSeconds) {
            if (refreshIntervalId) { // Clear existing interval if any
                clearInterval(refreshIntervalId);
            }
            refreshIntervalId = setInterval(() => {
                fetchAndDisplayResults(roundSelect.value, true); // Always shuffle on auto-refresh
            }, intervalSeconds * 1000);
            isAutoRefreshActive = true;
            refreshResultsBtn.innerHTML = '<i class="fas fa-stop mr-2"></i> Stop Auto-Refresh'; // Ubah teks tombol
            refreshResultsBtn.classList.remove('refresh-btn'); // Hapus warna orange
            refreshResultsBtn.classList.add('back-btn'); // Ganti dengan warna biru (atau warna lain yang sesuai)
            console.log(`Auto-refresh started every ${intervalSeconds} seconds.`);
        }

        function stopAutoRefresh() {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
                refreshIntervalId = null;
            }
            isAutoRefreshActive = false;
            refreshResultsBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Auto-Refresh'; // Ubah teks tombol
            refreshResultsBtn.classList.remove('back-btn'); // Hapus warna biru
            refreshResultsBtn.classList.add('refresh-btn'); // Ganti dengan warna orange
            console.log("Auto-refresh stopped.");
        }

        // Initial load of results and event listeners
        document.addEventListener('DOMContentLoaded', () => {
            const initialRound = roundSelect.value;
            if (initialRound) {
                fetchAndDisplayResults(initialRound, false); // Initial load, not shuffled
            } else {
                noResultsMessage.classList.remove('hidden');
                mainTableTitle.textContent = 'Peringkat'; // Set a default title if no rounds
            }

            roundSelect.addEventListener('change', (event) => {
                stopAutoRefresh(); // Stop auto-refresh when changing round
                fetchAndDisplayResults(event.target.value, false);
            });

            refreshResultsBtn.addEventListener('click', () => {
                if (isAutoRefreshActive) {
                    stopAutoRefresh();
                } else {
                    const currentSelectedRound = roundSelect.value;
                    if (currentSelectedRound) {
                        fetchAndDisplayResults(currentSelectedRound, true); // Langsung refresh sekali saat diaktifkan
                        startAutoRefresh(5); // Aktifkan auto-refresh setiap 5 detik
                    } else {
                        alert("Pilih ronde terlebih dahulu untuk mengaktifkan auto-refresh.");
                    }
                }
            });
        });

        // Stop auto-refresh when leaving the page
        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
        });
    </script>
</body>
</html>