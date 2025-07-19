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

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
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
        <link rel="icon" href="logo.png" type="image/png">
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
        .round-table-container {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #334155; /* slate-700 */
        }
        .round-table-container h3 {
            color: #93c5fd; /* blue-300 */
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="relative flex flex-col items-center justify-center min-h-screen px-4 bg-cover bg-center pb-12">
    <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
    </div>

    <div class="w-full max-w-3xl mx-auto mb-8">
        <div class="text-center mb-10">
            <h1 class="title-font text-4xl font-bold text-white mb-2">Hasil Peringkat</h1>
            <p class="text-slate-300">Sesi: <span class="font-semibold text-blue-300"><?= $session_name ?></span></p>
        </div>

        <div class="container-results p-6 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-white" id="mainTableTitle">Peringkat Akhir Tim</h2>
                <div class="flex space-x-3">
                    <button id="refreshResultsBtn" class="py-2 px-4 refresh-btn text-white font-semibold rounded-lg flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i> Auto-Refresh
                    </button>
                    <button onclick="window.location.href='index.php'" class="py-2 px-4 back-btn text-white font-semibold rounded-lg">
                        <i class="fas fa-home mr-2"></i> Kembali ke Dashboard
                    </button>
                </div>
            </div>

            <div id="loadingSpinner" class="flex justify-center items-center py-8 hidden">
                <div class="loading-spinner"></div>
                <p class="text-slate-400 ml-3">Memuat peringkat...</p>
            </div>

            <div id="resultsTableContainer">
                </div>
            
            <div id="noResultsMessage" class="hidden text-center text-slate-400 py-8">
                Belum ada hasil untuk sesi ini.
            </div>

            <div id="roundResultsContainer">
                </div>
        </div>
    </div>

    <script>
        const sessionId = "<?= $sessionId ?>";
        const resultsTableContainer = document.getElementById('resultsTableContainer');
        const roundResultsContainer = document.getElementById('roundResultsContainer'); // New container for round tables
        const loadingSpinner = document.getElementById('loadingSpinner');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const refreshResultsBtn = document.getElementById('refreshResultsBtn');
        const mainTableTitle = document.getElementById('mainTableTitle');

        let refreshIntervalId; // To store the interval ID for auto-refresh
        let isAutoRefreshActive = false; // Default: mati


        // Function to format time from seconds to MM:SS
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        async function fetchAndDisplayResults(shuffle = false) {
            loadingSpinner.classList.remove('hidden');
            resultsTableContainer.innerHTML = '';
            roundResultsContainer.innerHTML = ''; // Clear round tables
            noResultsMessage.classList.add('hidden');

            try {
                const response = await fetch(`fetch_dragdrop_results.php?sessionid=${sessionId}`);
                const data = await response.json();

                loadingSpinner.classList.add('hidden');

                if (data.success && data.results.length > 0) {
                    let allResults = data.results;

                    if (shuffle) {
                        shuffleArray(allResults);
                    } else {
                        // Keep the overall results sorted by time_taken for 'Final_Score' display
                        allResults.sort((a, b) => {
                            // Sort 'Final_Score' entries first, then other rounds
                            const isAFinal = a.round_number === 'Final_Score';
                            const isBFinal = b.round_number === 'Final_Score';

                            if (isAFinal && !isBFinal) return -1;
                            if (!isAFinal && isBFinal) return 1;

                            // If both are Final_Score or both are not, sort by time_taken then submission_time
                            if (a.time_taken !== b.time_taken) {
                                return a.time_taken - b.time_taken;
                            }
                            return new Date(a.submission_time) - new Date(b.submission_time);
                        });
                    }

                    // Separate final scores from individual round scores
                    const finalScores = allResults.filter(result => result.round_number === 'Final_Score');
                    const roundScores = allResults.filter(result => result.round_number !== 'Final_Score');

                    // Display Final Scores
                    if (finalScores.length > 0) {
                        mainTableTitle.textContent = 'Peringkat Akhir Tim'; // Ensure title is correct
                        let finalTableHtml = `
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

                        finalScores.forEach((result, index) => {
                            const rank = index + 1;
                            let trophyIcon = '';
                            if (rank === 1) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon mr-2"></i>';
                            } else if (rank === 2) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon silver mr-2"></i>';
                            } else if (rank === 3) {
                                trophyIcon = '<i class="fas fa-trophy trophy-icon bronze mr-2"></i>';
                            }
                            
                            finalTableHtml += `
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

                        finalTableHtml += `
                                </tbody>
                            </table>
                        `;
                        resultsTableContainer.innerHTML = finalTableHtml;
                    } else {
                        resultsTableContainer.innerHTML = `<p class="text-slate-400 text-center py-4">Belum ada peringkat akhir untuk sesi ini.</p>`;
                    }

                    // Group and display individual round scores
                    const groupedRoundScores = {};
                    roundScores.forEach(result => {
                        if (!groupedRoundScores[result.round_number]) {
                            groupedRoundScores[result.round_number] = [];
                        }
                        groupedRoundScores[result.round_number].push(result);
                    });

                    const sortedRoundNumbers = Object.keys(groupedRoundScores).sort((a, b) => parseInt(a) - parseInt(b));

                    sortedRoundNumbers.forEach(roundNum => {
                        const roundData = groupedRoundScores[roundNum];
                        if (roundData.length > 0) {
                            // Sort each round's data by time_taken then submission_time
                            roundData.sort((a, b) => {
                                if (a.time_taken !== b.time_taken) {
                                    return a.time_taken - b.time_taken;
                                }
                                return new Date(a.submission_time) - new Date(b.submission_time);
                            });

                            let roundTableHtml = `
                                <div class="round-table-container">
                                    <h3 class="text-blue-300">Hasil Ronde ${roundNum}</h3>
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

                            roundData.forEach((result, index) => {
                                const rank = index + 1;
                                let trophyIcon = '';
                                if (rank === 1) {
                                    trophyIcon = '<i class="fas fa-trophy trophy-icon mr-2"></i>';
                                } else if (rank === 2) {
                                    trophyIcon = '<i class="fas fa-trophy trophy-icon silver mr-2"></i>';
                                } else if (rank === 3) {
                                    trophyIcon = '<i class="fas fa-trophy trophy-icon bronze mr-2"></i>';
                                }

                                roundTableHtml += `
                                    <tr class="bg-slate-900/30 hover:bg-slate-700/50 transition-colors duration-200">
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

                            roundTableHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            `;
                            roundResultsContainer.insertAdjacentHTML('beforeend', roundTableHtml);
                        }
                    });

                    // If no final scores and no round scores, show no results message
                    if (finalScores.length === 0 && roundScores.length === 0) {
                        noResultsMessage.classList.remove('hidden');
                    } else {
                        // Add event listeners for all delete buttons after all tables are rendered
                        document.querySelectorAll('.delete-btn').forEach(button => {
                            button.addEventListener('click', (event) => {
                                const resultId = event.currentTarget.dataset.id;
                                if (confirm('Apakah Anda yakin ingin menghapus hasil ini?')) {
                                    deleteResult(resultId);
                                }
                            });
                        });
                    }

                } else {
                    noResultsMessage.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error fetching results:', error);
                loadingSpinner.classList.add('hidden');
                resultsTableContainer.innerHTML = `<p class="text-red-400 text-center py-8">Gagal memuat hasil: ${error.message}</p>`;
            }
        }

        // Function to delete a result
        async function deleteResult(resultId) {
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
                    fetchAndDisplayResults(false); // Refresh results after deletion
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
                fetchAndDisplayResults(true); // Always shuffle on auto-refresh
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

        // Initial load of results
        document.addEventListener('DOMContentLoaded', () => {
            fetchAndDisplayResults(false); // Initial load, not shuffled

            refreshResultsBtn.addEventListener('click', () => {
                if (isAutoRefreshActive) {
                    stopAutoRefresh();
                } else {
                    fetchAndDisplayResults(true); // Langsung refresh sekali saat diaktifkan
                    startAutoRefresh(5); // Aktifkan auto-refresh setiap 5 detik
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