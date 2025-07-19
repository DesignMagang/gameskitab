<?php
session_start();
include 'db.php'; // Pastikan file db.php tersedia untuk koneksi database

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Periksa apakah sessionid ada di URL
if (!isset($_GET['sessionid'])) {
    // Lebih baik redirect ke halaman daftar sesi atau dashboard daripada die()
    header("Location: index.php"); // Atau halaman lain yang sesuai
    exit();
}

$sessionid = $_GET['sessionid'];
$user_id = $_SESSION['user_id'];
$session_name = '';

// Inisialisasi pesan error dan success dari session
// Ini penting agar pesan dapat ditampilkan setelah redirect
$error = '';
$success_message = '';

if (isset($_SESSION['form_success_message'])) {
    $success_message = $_SESSION['form_success_message'];
    unset($_SESSION['form_success_message']); // Hapus dari session setelah diambil
}
if (isset($_SESSION['form_error_message'])) {
    $error = $_SESSION['form_error_message'];
    unset($_SESSION['form_error_message']); // Hapus dari session setelah diambil
}


// Ambil data sesi dari database dan verifikasi kepemilikan
$stmt = $conn->prepare("SELECT session_name, created_by FROM dragdrop_sessions WHERE sessionid = ?");
$stmt->bind_param("s", $sessionid);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    // Sesi tidak valid, redirect ke dashboard
    header("Location: index.php");
    exit();
}
$session_data = $result->fetch_assoc();

// Pastikan hanya pembuat sesi yang bisa mengakses halaman ini
if ($session_data['created_by'] !== $user_id) {
    // Tidak memiliki izin, redirect ke dashboard
    header("Location: index.php");
    exit();
}

$session_name = htmlspecialchars($session_data['session_name']);

// --- BAGIAN PROSES MENYIMPAN PERTANYAAN/JAWABAN/RONDE (MODIFIKASI INI PENTING) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_questions'])) {
    $questions_data = json_decode($_POST['questions_json'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['form_error_message'] = 'Data pertanyaan tidak valid. Silakan coba lagi.';
        // REDIRECT SETELAH ERROR VALIDASI
        header("Location: create_dragdrop.php?sessionid=" . urlencode($sessionid));
        exit();
    } else {
        try {
            $conn->begin_transaction();

            // Hapus semua pertanyaan lama untuk sesi ini sebelum menyimpan yang baru
            $stmt_delete = $conn->prepare("DELETE FROM dragdrop_questions WHERE session_id = ?");
            $stmt_delete->bind_param("s", $sessionid);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Siapkan statement untuk insert
            $stmt_insert = $conn->prepare("INSERT INTO dragdrop_questions (session_id, round_number, question_text, correct_answer, drag_options) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($questions_data as $round_num => $round_questions) {
                // Pastikan $round_questions adalah array
                if (!is_array($round_questions)) {
                    continue; // Lewati jika bukan array untuk mencegah error
                }
                foreach ($round_questions as $question) {
                    $round_number = $round_num;
                    $question_text = htmlspecialchars($question['question'] ?? ''); // Pastikan ada default jika kosong
                    $correct_answer = htmlspecialchars($question['correct_answer'] ?? ''); // Pastikan ada default jika kosong
                    
                    // Pisahkan opsi drag, bersihkan, dan gabungkan lagi untuk disimpan
                    $drag_options_array = array_map('trim', explode(',', $question['drag_options'] ?? ''));
                    $drag_options_array = array_filter($drag_options_array); // Hapus yang kosong
                    $drag_options = json_encode($drag_options_array); // Simpan sebagai JSON string

                    $stmt_insert->bind_param("sisss", $sessionid, $round_number, $question_text, $correct_answer, $drag_options);
                    $stmt_insert->execute();
                }
            }
            $stmt_insert->close();
            $conn->commit();
            
            // REDIRECT SETELAH PROSES POST BERHASIL (INI PENTING UNTUK PRG)
            $_SESSION['form_success_message'] = 'Pertanyaan dan ronde berhasil disimpan!';
            header("Location: create_dragdrop.php?sessionid=" . urlencode($sessionid));
            exit(); // SANGAT PENTING: Hentikan eksekusi skrip setelah redirect

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['form_error_message'] = "Gagal menyimpan pertanyaan: " . $e->getMessage();
            // REDIRECT SETELAH ERROR DATABASE
            header("Location: create_dragdrop.php?sessionid=" . urlencode($sessionid));
            exit(); // SANGAT PENTING: Hentikan eksekusi skrip setelah redirect
        }
    }
}
// --- AKHIR BAGIAN MODIFIKASI PENTING ---


// --- AMBIL PERTANYAAN YANG SUDAH ADA UNTUK SESI INI (TIDAK PERLU BERUBAH) ---
$existing_questions = [];
try {
    $stmt_fetch = $conn->prepare("SELECT round_number, question_text, correct_answer, drag_options FROM dragdrop_questions WHERE session_id = ? ORDER BY round_number ASC, question_id ASC");
    $stmt_fetch->bind_param("s", $sessionid);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();

    while ($row = $result_fetch->fetch_assoc()) {
        $round_num = $row['round_number'];
        if (!isset($existing_questions[$round_num])) {
            $existing_questions[$round_num] = [];
        }
        $existing_questions[$round_num][] = [
            'question' => htmlspecialchars_decode($row['question_text']), // Decode for display in textarea
            'correct_answer' => htmlspecialchars_decode($row['correct_answer']),
            'drag_options' => implode(', ', json_decode($row['drag_options'], true)) // Decode and implode for display
        ];
    }
    $stmt_fetch->close();
} catch (Exception $e) {
    // Tangani error jika terjadi masalah saat memuat pertanyaan
    // Jika $error sudah diisi dari proses POST sebelumnya, jangan timpa
    // Jika belum, baru diisi.
    if (empty($error)) {
        $error = "Gagal memuat pertanyaan: " . $e->getMessage();
    }
}

// Pastikan ada setidaknya satu ronde jika belum ada pertanyaan
if (empty($existing_questions)) {
    $existing_questions[1] = []; // Mulai dengan Round 1 jika belum ada
}

// Catatan: Bagian pengambilan $success_message dan $error dari session
// sudah dipindahkan ke atas, sebelum pengambilan data sesi,
// untuk memastikan pesan tersedia segera setelah redirect.
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Sesi: <?= $session_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="icon" href="logo.png" type="image/png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a; /* Dark slate background */
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        .game-container {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2); /* Blue glowing effect */
        }
        .input-field {
            transition: all 0.3s ease;
            background-color: rgba(30, 41, 59, 0.7); /* Darker, semi-transparent input */
        }
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); /* Blue focus ring */
        }
        .submit-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4); /* Lift and deeper shadow */
        }
        .add-btn {
            background-color: #10b981; /* Emerald-500 */
            transition: all 0.2s ease;
        }
        .add-btn:hover {
            background-color: #059669; /* Emerald-600 */
        }
        .remove-btn {
            background-color: #ef4444; /* Red-500 */
            transition: all 0.2s ease;
        }
        .remove-btn:hover {
            background-color: #dc2626; /* Red-600 */
        }
        .round-header {
            cursor: pointer;
            padding: 0.75rem 1rem;
            background-color: rgba(59, 130, 246, 0.1); /* Light blue background */
            border-bottom: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.5rem 0.5rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .round-content {
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            padding: 1rem;
        }
        .round-content.hidden {
            display: none;
        }
        .error-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #dc2626; /* Red-600 */
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 1001;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            pointer-events: none;
        }
        .error-message.show {
            opacity: 1;
        }
    </style>
</head>
<body class="relative flex flex-col items-center justify-center min-h-screen px-4 bg-cover bg-center pb-12">
    <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
    </div>

    <div id="globalMessage" class="error-message hidden"></div>

    <div class="w-full max-w-2xl mx-auto mb-8"> 
        <div class="text-center mb-10">
            <h1 class="title-font text-4xl font-bold text-white mb-2">Kelola Sesi</h1>
            <p class="text-slate-300">Sesi: <span class="font-semibold text-blue-300"><?= $session_name ?></span></p>
            <p class="text-slate-400 text-sm mt-1">ID Sesi: <span class="font-mono text-slate-300"><?= $sessionid ?></span></p>
        </div>

        <div class="game-container p-6 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
            <h2 class="font-bold text-2xl text-white mb-6 text-center">Buat Pertanyaan & Ronde</h2>
            
            <form id="questionsForm" method="post">
                <input type="hidden" name="sessionid" value="<?= $sessionid ?>">
                <input type="hidden" name="questions_json" id="questionsJsonInput">

                <div id="rounds-container" class="space-y-6">
                    </div>

                <div class="flex flex-col sm:flex-row justify-between items-center mt-8 gap-4">
                    <button type="button" id="add-round-btn" class="w-full sm:w-auto py-3 px-6 add-btn text-white font-semibold rounded-lg 
                                hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                        <i class="fas fa-plus-circle mr-2"></i> 
                        Ronde
                    </button>
                    <button type="button" id="preview-dragdrop-btn" class="w-full sm:w-auto py-3 px-6 submit-btn text-white font-semibold rounded-lg 
                                hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                        <!-- <i class="fas fa-eye mr-2"></i>  -->
                        Mainkan
                    </button>
                    <button type="button" id="view-scores-btn" class="w-full sm:w-auto py-3 px-6 submit-btn text-white font-semibold rounded-lg 
                                hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                        <i class="fas fa-chart-bar mr-2"></i>Skor
                    </button>
                    <button type="submit" name="save_questions" class="w-full sm:w-auto py-3 px-6 submit-btn text-white font-semibold rounded-lg 
                                hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                        <!-- <i class="fas fa-save mr-2"></i>  -->
                        Simpan 
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global message display function
        function showGlobalMessage(message, isError = false) {
            const globalMessageDiv = document.getElementById('globalMessage');
            globalMessageDiv.textContent = message;
            globalMessageDiv.className = 'error-message'; // Reset class
            if (!isError) {
                globalMessageDiv.style.backgroundColor = '#10B981'; // Tailwind emerald-500
            } else {
                globalMessageDiv.style.backgroundColor = '#dc2626'; // Tailwind red-600
            }
            globalMessageDiv.classList.add('show');

            setTimeout(() => {
                globalMessageDiv.classList.remove('show');
                setTimeout(() => {
                    globalMessageDiv.classList.add('hidden');
                }, 300); // Wait for fade out transition
            }, 3000); // Show for 3 seconds
        }

        // Display PHP success/error messages on page load
        <?php if (!empty($error)): ?>
            showGlobalMessage("<?= $error ?>", true);
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            showGlobalMessage("<?= $success_message ?>", false);
        <?php endif; ?>

        const roundsContainer = document.getElementById('rounds-container');
        const addRoundBtn = document.getElementById('add-round-btn');
        const questionsForm = document.getElementById('questionsForm');
        const questionsJsonInput = document.getElementById('questionsJsonInput');
        const previewDragdropBtn = document.getElementById('preview-dragdrop-btn');
        const viewScoresBtn = document.getElementById('view-scores-btn'); // Dapatkan tombol baru

        let currentRoundNumber = 0;
        let questionsData = {}; // Object to hold all questions organized by round

        // Load existing questions if any
        const existingQuestions = <?= json_encode($existing_questions) ?>;
        if (Object.keys(existingQuestions).length > 0) {
            questionsData = existingQuestions;
            // Determine the highest round number to continue from
            currentRoundNumber = Math.max(...Object.keys(existingQuestions).map(Number));
            renderAllRounds();
        } else {
            // Start with one round if no existing questions
            addRound();
        }

        addRoundBtn.addEventListener('click', addRound);

        function addRound() {
            currentRoundNumber++;
            questionsData[currentRoundNumber] = questionsData[currentRoundNumber] || []; // Ensure round exists in data
            renderRound(currentRoundNumber);
            // Scroll to the newly added round
            setTimeout(() => {
                const newRoundElement = document.getElementById(`round-${currentRoundNumber}`);
                if (newRoundElement) {
                    newRoundElement.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }
            }, 100);
        }

        function renderAllRounds() {
            roundsContainer.innerHTML = ''; // Clear existing content
            const sortedRoundNumbers = Object.keys(questionsData).map(Number).sort((a, b) => a - b);
            sortedRoundNumbers.forEach(roundNum => {
                renderRound(roundNum);
            });
        }

        function renderRound(roundNum) {
            const roundDiv = document.createElement('div');
            roundDiv.id = `round-${roundNum}`;
            roundDiv.className = 'bg-slate-800 rounded-lg shadow-lg overflow-hidden';

            const roundHeader = document.createElement('div');
            roundHeader.className = 'round-header text-white font-bold text-lg';
            roundHeader.innerHTML = `
                <span class="flex items-center"><i class="fas fa-chevron-down mr-2 text-blue-300 transition-transform duration-300"></i> Ronde ${roundNum}</span>
                <div class="flex gap-2">
                    <button type="button" class="add-question-btn text-xs text-blue-300 py-1 px-2 rounded hover:bg-blue-800" data-round="${roundNum}">
                        <i class="fas fa-plus mr-1"></i> Pertanyaan
                    </button>
                    <button type="button" class="remove-round-btn text-xs text-rose-300 py-1 px-2 rounded hover:bg-rose-800" data-round="${roundNum}">
                        <i class="fas fa-trash-alt mr-1"></i> Ronde
                    </button>
                </div>
            `;
            roundDiv.appendChild(roundHeader);

            const roundContent = document.createElement('div');
            roundContent.className = 'round-content';
            roundDiv.appendChild(roundContent);
            
            // Render existing questions for this round
            if (questionsData[roundNum] && questionsData[roundNum].length > 0) {
                questionsData[roundNum].forEach((q, index) => {
                    addQuestionToRound(roundContent, roundNum, q.question, q.correct_answer, q.drag_options, index);
                });
            } else {
                    // If no questions in this round, add an empty one
                    addQuestionToRound(roundContent, roundNum);
            }


            roundsContainer.appendChild(roundDiv);

            // Event Listeners for new round elements
            roundHeader.querySelector('.add-question-btn').addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent header toggle
                addQuestionToRound(roundContent, roundNum);
            });

            roundHeader.querySelector('.remove-round-btn').addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent header toggle
                if (confirm(`Apakah Anda yakin ingin menghapus Ronde ${roundNum} beserta semua pertanyaannya?`)) {
                    delete questionsData[roundNum];
                    roundDiv.remove();
                    reindexRounds(); // Reindex rounds after deletion
                }
            });

            roundHeader.addEventListener('click', () => {
                roundContent.classList.toggle('hidden');
                const icon = roundHeader.querySelector('i');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });
        }

        function reindexRounds() {
            const newQuestionsData = {};
            const sortedRoundNumbers = Object.keys(questionsData).map(Number).sort((a, b) => a - b);
            let newRoundIndex = 1;

            sortedRoundNumbers.forEach(oldRoundNum => {
                newQuestionsData[newRoundIndex] = questionsData[oldRoundNum];
                newRoundIndex++;
            });
            questionsData = newQuestionsData;
            currentRoundNumber = newRoundIndex - 1; // Update global counter

            renderAllRounds(); // Re-render all rounds with new indexing
        }


        function addQuestionToRound(roundContentElement, roundNum, questionText = '', correctAnswer = '', dragOptions = '', questionIndex = -1) {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'bg-slate-700 p-4 rounded-lg mb-4 border border-slate-600 relative';
            questionDiv.innerHTML = `
                <button type="button" class="remove-question-btn absolute top-2 right-2 text-slate-400 hover:text-red-400" title="Hapus Pertanyaan">
                    <i class="fas fa-times-circle"></i>
                </button>
                <div class="mb-3">
                    <label class="block text-slate-300 text-sm font-medium mb-1">Pertanyaan:</label>
                    <textarea class="w-full px-3 py-2 input-field text-white rounded-md border border-slate-600 focus:outline-none focus:border-blue-500 placeholder:text-slate-500" 
                                rows="3" placeholder="Masukkan pertanyaan untuk drag & drop ini">${questionText}</textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-slate-300 text-sm font-medium mb-1">Jawaban Benar:</label>
                    <input type="text" class="w-full px-3 py-2 input-field text-white rounded-md border border-slate-600 focus:outline-none focus:border-blue-500 placeholder:text-slate-500" 
                               placeholder="Jawaban yang benar untuk pertanyaan di atas" value="${correctAnswer}">
                </div>
                <div>
                    <label class="block text-slate-300 text-sm font-medium mb-1">Opsi Jawaban Lain (dipisahkan koma):</label>
                    <input type="text" class="w-full px-3 py-2 input-field text-white rounded-md border border-slate-600 focus:outline-none focus:border-blue-500 placeholder:text-slate-500" 
                               placeholder="Contoh: kata1, kata2, kalimat panjang" value="${dragOptions}">
                    <p class="text-xs text-slate-400 mt-1">Masukkan kata/frasa yang akan muncul sebagai opsi drag. Pisahkan dengan koma.</p>
                </div>
            `;
            roundContentElement.appendChild(questionDiv);

            // Add to questionsData if new, or update existing at specific index
            if (questionIndex === -1) { // New question
                questionsData[roundNum].push({
                    question: questionText,
                    correct_answer: correctAnswer,
                    drag_options: dragOptions
                });
                questionIndex = questionsData[roundNum].length - 1; // Get the actual index
            }
            questionDiv.dataset.questionIndex = questionIndex; // Store index on the element

            // Event Listeners for question fields to update questionsData
            const textarea = questionDiv.querySelector('textarea');
            const correctAnswerInput = questionDiv.querySelectorAll('input')[0];
            const dragOptionsInput = questionDiv.querySelectorAll('input')[1];

            textarea.addEventListener('input', (e) => {
                questionsData[roundNum][questionDiv.dataset.questionIndex].question = e.target.value;
            });
            correctAnswerInput.addEventListener('input', (e) => {
                questionsData[roundNum][questionDiv.dataset.questionIndex].correct_answer = e.target.value;
            });
            dragOptionsInput.addEventListener('input', (e) => {
                questionsData[roundNum][questionDiv.dataset.questionIndex].drag_options = e.target.value;
            });

            // Remove question button listener
            questionDiv.querySelector('.remove-question-btn').addEventListener('click', () => {
                if (confirm('Apakah Anda yakin ingin menghapus pertanyaan ini?')) {
                    questionsData[roundNum].splice(questionDiv.dataset.questionIndex, 1);
                    questionDiv.remove();
                    reindexQuestionsInRound(roundNum, roundContentElement); // Reindex after deletion
                }
            });
        }

        function reindexQuestionsInRound(roundNum, roundContentElement) {
            // Update questionIndex data attribute for remaining questions in this round
            Array.from(roundContentElement.children).forEach((questionEl, index) => {
                questionEl.dataset.questionIndex = index;
            });
            // Ensure questionsData reflects the new order (splice already handles this, but good to be explicit)
            // No need to explicitly re-order questionsData as splice already updates it
            // This function is mainly for updating the `data-question-index` attribute on the DOM elements
        }


        // Prepare data for submission
        questionsForm.addEventListener('submit', function(event) {
            // Update the hidden input with the current state of questionsData
            questionsJsonInput.value = JSON.stringify(questionsData);
            // Allow the form to submit
        });

        // Preview button (not implemented yet, but structure is ready)
        // Preview button
        previewDragdropBtn.addEventListener('click', function() {
            // Pertama, simpan dulu semua pertanyaan yang mungkin baru diedit
            // Ini memastikan data yang di-preview adalah yang terbaru
            questionsJsonInput.value = JSON.stringify(questionsData);
            
            // Buat form sementara untuk melakukan POST request ke server
            // ini adalah cara yang lebih baik untuk memastikan data tersimpan sebelum redirect
            // atau jika Anda ingin langsung membuka tab baru tanpa menyimpan, cukup gunakan window.open
            
            // --- Opsi 1: Langsung Redirect ke halaman preview (tanpa menyimpan secara eksplisit jika belum disave) ---
            window.open(`play_dragdrop.php?sessionid=<?= $sessionid ?>`, '_blank');
            
            // --- Opsi 2 (Lebih Disarankan): Kirim form save dulu, lalu redirect setelah sukses ---
            // Jika Anda ingin preview selalu menampilkan data yang sudah tersimpan di database:
            /*
            fetch(questionsForm.action, {
                method: questionsForm.method,
                body: new FormData(questionsForm) // Kirim data form
            })
            .then(response => response.text()) // Ambil respons dalam bentuk teks
            .then(data => {
                // Respons dari server bisa berupa pesan sukses/error PHP
                // Anda bisa mengurai 'data' untuk melihat apakah penyimpanan berhasil.
                // Untuk demo sederhana, kita asumsikan berhasil.
                showGlobalMessage("Data tersimpan. Membuka Preview...", false);
                setTimeout(() => {
                    window.open(`preview_dragdrop.php?sessionid=<?= $sessionid ?>`, '_blank');
                }, 500); // Beri sedikit jeda sebelum membuka tab baru
            })
            .catch(error => {
                console.error('Error saving questions before preview:', error);
                showGlobalMessage("Gagal menyimpan data sebelum preview. Coba lagi.", true);
            });
            */
        });

        // Event listener for the new "Lihat Skor" button
        viewScoresBtn.addEventListener('click', function() {
            // Redirect to dragdrop_score_host.php with the current session ID
            window.location.href = `dragdrop_score_host.php?sessionid=<?= $sessionid ?>`;
        });
    </script>
</body>
</html>