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
        .active-auto-refresh-btn { /* Gaya baru untuk tombol auto-refresh aktif */
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .active-auto-refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4);
        }
        .back-btn { /* Digunakan juga untuk tombol musik */
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4);
        }
        .music-toggle-btn { /* Gaya untuk tombol musik */
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); /* Green gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .music-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(22, 163, 74, 0.4);
        }
        .trophy-icon {
            color: #FFD700; /* Gold */
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
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

        /* Responsifitas Tabel */
        .table-wrapper {
            overflow-x: auto; /* Memungkinkan tabel di-scroll secara horizontal */
            -webkit-overflow-scrolling: touch; /* Untuk smooth scrolling di iOS */
        }
        /* Opsional: Styling untuk membuat kolom tertentu tetap terlihat di mobile */
        /* Contoh: jika Anda ingin kolom Nama Tim selalu terlihat */
        /* @media (max-width: 768px) {
            .min-w-full th:nth-child(2),
            .min-w-full td:nth-child(2) {
                position: sticky;
                left: 0;
                background-color: inherit; // Sesuaikan dengan warna background baris
                z-index: 1;
            }
        } */
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
            <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                <h2 class="text-2xl font-bold text-white">Peringkat Tim</h2>
                <div class="flex flex-wrap gap-3">
                    <button id="toggleMusicBtn" class="py-2 px-4 music-toggle-btn text-white font-semibold rounded-lg flex items-center">
                        <i class="fas fa-music mr-2"></i> Play Music
                    </button>
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

            <div id="resultsTableContainer" class="table-wrapper">
                </div>
            
            <div id="noResultsMessage" class="hidden text-center text-slate-400 py-8">
                Belum ada hasil untuk sesi ini.
            </div>

            <audio id="backgroundMusic" loop>
                <source src="assets/music/background_music.mp3" type="audio/mpeg">
                Your browser does not support the audio element.
            </audio>
        </div>
    </div>

    <script>
        const sessionId = "<?= $sessionId ?>";
        const resultsTableContainer = document.getElementById('resultsTableContainer');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const noResultsMessage = document.getElementById('noResultsMessage');
        const refreshResultsBtn = document.getElementById('refreshResultsBtn');
        const backgroundMusic = document.getElementById('backgroundMusic');
        const toggleMusicBtn = document.getElementById('toggleMusicBtn');

        let refreshIntervalId; // To store the interval ID for auto-refresh
        let isAutoRefreshActive = false; // Status auto-refresh
        let isMusicPlaying = false; // Status musik

        // Function to format time from seconds to MM:SS
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        async function fetchAndDisplayResults(shuffle = false) {
            loadingSpinner.classList.remove('hidden');
            resultsTableContainer.innerHTML = '';
            noResultsMessage.classList.add('hidden');

            try {
                const response = await fetch(`fetch_dragdrop_results.php?sessionid=${sessionId}`);
                const data = await response.json();

                loadingSpinner.classList.add('hidden');

                if (data.success && data.results.length > 0) {
                    let results = data.results;

                    if (shuffle) {
                        // Shuffle the array of results for display purposes
                        shuffleArray(results);
                    } else {
                        // Default sort: By time_taken (ascending), then by submission_time (ascending)
                        results.sort((a, b) => {
                            if (a.time_taken !== b.time_taken) {
                                return a.time_taken - b.time_taken;
                            }
                            // Fallback to submission time if times are equal
                            return new Date(a.submission_time) - new Date(b.submission_time);
                        });
                    }

                    let tableHtml = `
                        <table class="min-w-full bg-slate-800 rounded-lg overflow-hidden shadow-lg">
                            <thead class="bg-slate-700">
                                <tr>
                                    <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Peringkat</th>
                                    <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Nama Tim</th>
                                    <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Ronde</th>
                                    <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Waktu</th>
                                    <th class="py-3 px-4 text-left text-white font-bold text-sm uppercase tracking-wider">Waktu Submit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700">
                    `;

                    results.forEach((result, index) => {
                        const rank = index + 1;
                        // Ikon piala hanya untuk peringkat 1 (karena Final_Score sudah difilter)
                        const trophyIcon = rank === 1 ? '<i class="fas fa-trophy trophy-icon mr-2"></i>' : '';
                        const rowClass = 'bg-slate-900/30'; // Selalu gunakan kelas ini untuk ronde biasa

                        tableHtml += `
                            <tr class="${rowClass} hover:bg-slate-700/50 transition-colors duration-200">
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">
                                    ${trophyIcon} ${rank}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${result.team_name}</td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">
                                    Ronde ${result.round_number}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${formatTime(result.time_taken)}</td>
                                <td class="py-3 px-4 whitespace-nowrap text-sm text-slate-200">${new Date(result.submission_time).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' })}</td>
                            </tr>
                        `;
                    });

                    tableHtml += `
                            </tbody>
                        </table>
                    `;
                    resultsTableContainer.innerHTML = tableHtml;
                } else {
                    noResultsMessage.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error fetching results:', error);
                loadingSpinner.classList.add('hidden');
                resultsTableContainer.innerHTML = `<p class="text-red-400 text-center py-8">Gagal memuat hasil: ${error.message}</p>`;
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
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
            }
            refreshIntervalId = setInterval(() => {
                fetchAndDisplayResults(true); // Always shuffle on auto-refresh
            }, intervalSeconds * 1000);
            isAutoRefreshActive = true;
            refreshResultsBtn.innerHTML = '<i class="fas fa-stop mr-2"></i> Stop Auto-Refresh';
            refreshResultsBtn.classList.remove('refresh-btn');
            refreshResultsBtn.classList.add('active-auto-refresh-btn'); // Ganti kelas untuk warna aktif
            console.log(`Auto-refresh started every ${intervalSeconds} seconds.`);
        }

        function stopAutoRefresh() {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
                refreshIntervalId = null;
            }
            isAutoRefreshActive = false;
            refreshResultsBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Auto-Refresh';
            refreshResultsBtn.classList.remove('active-auto-refresh-btn'); // Hapus kelas warna aktif
            refreshResultsBtn.classList.add('refresh-btn'); // Kembalikan ke warna default
            console.log("Auto-refresh stopped.");
        }

        // Fungsi untuk toggle musik
        function toggleMusic() {
            if (isMusicPlaying) {
                backgroundMusic.pause();
                toggleMusicBtn.innerHTML = '<i class="fas fa-music mr-2"></i> Play Music';
                toggleMusicBtn.classList.remove('back-btn'); // Hapus kelas biru jika ada
                toggleMusicBtn.classList.add('music-toggle-btn'); // Kembali ke warna hijau
                isMusicPlaying = false;
            } else {
                backgroundMusic.play().then(() => {
                    toggleMusicBtn.innerHTML = '<i class="fas fa-pause mr-2"></i> Pause Music';
                    toggleMusicBtn.classList.remove('music-toggle-btn'); // Hapus warna hijau
                    toggleMusicBtn.classList.add('back-btn'); // Ganti ke warna biru saat aktif
                    isMusicPlaying = true;
                }).catch(error => {
                    console.error("Error playing music:", error);
                    alert("Gagal memutar musik. Beberapa browser membutuhkan interaksi pengguna sebelum memutar media.");
                });
            }
        }

        // Initial load of results
        document.addEventListener('DOMContentLoaded', () => {
            fetchAndDisplayResults(false); // Initial load, not shuffled

            // Event listener untuk tombol Auto-Refresh
            refreshResultsBtn.addEventListener('click', () => {
                if (isAutoRefreshActive) {
                    stopAutoRefresh();
                } else {
                    fetchAndDisplayResults(true); // Langsung refresh sekali saat diaktifkan
                    startAutoRefresh(5); // Aktifkan auto-refresh setiap 5 detik
                }
            });

            // Event listener untuk tombol musik
            toggleMusicBtn.addEventListener('click', toggleMusic);
        });

        // Stop auto-refresh and music when leaving the page
        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
            if (isMusicPlaying) {
                backgroundMusic.pause();
            }
        });
    </script>
</body>
</html>