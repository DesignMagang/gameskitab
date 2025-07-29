<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0; // question_id akan 0 jika tidak ada

if ($quiz_id === 0) {
    die("ID Kuis tidak ditemukan."); // Hanya matikan jika ID kuis tidak ada
}

// Cek kepemilikan kuis
$stmt_check_quiz = $conn->prepare("SELECT name FROM quizz WHERE id = ? AND user_id = ?");
$stmt_check_quiz->bind_param("ii", $quiz_id, $user_id);
$stmt_check_quiz->execute();
$result_check_quiz = $stmt_check_quiz->get_result();
if ($result_check_quiz->num_rows == 0) {
    die("Kuis tidak ditemukan atau bukan milik Anda.");
}
$quiz_name_row = $result_check_quiz->fetch_assoc();
$quiz_name = htmlspecialchars($quiz_name_row['name']);
$stmt_check_quiz->close();

// Ambil data pertanyaan yang akan diedit atau inisialisasi untuk pertanyaan baru
$current_question = null;
if ($question_id > 0) { // Jika ada question_id, coba ambil data pertanyaan
    $stmt_get_question = $conn->prepare("SELECT question_text, question_image_url, answer, answer_image_url, time_limit FROM question_quizz WHERE id = ? AND quiz_id = ? AND user_id = ?");
    $stmt_get_question->bind_param("iii", $question_id, $quiz_id, $user_id);
    $stmt_get_question->execute();
    $result_get_question = $stmt_get_question->get_result();
    if ($result_get_question->num_rows > 0) {
        $current_question = $result_get_question->fetch_assoc();
    } else {
        die("Pertanyaan tidak ditemukan atau bukan milik Anda.");
    }
    $stmt_get_question->close();
} else {
    // Jika question_id adalah 0 (tidak ada), ini berarti kita menambahkan pertanyaan baru
    $current_question = [
        'question_text' => '',
        'question_image_url' => null,
        'answer' => '',
        'answer_image_url' => null,
        'time_limit' => 30 // Default time limit for new questions
    ];
}

$upload_dir = 'uploads/quizz_images/'; // Direktori penyimpanan gambar

// Pastikan direktori upload ada
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Buat direktori jika belum ada
}

// Logika untuk UPDATE/INSERT pertanyaan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_question') {
    $question_text = trim($_POST['question_text'] ?? '');
    $answer_text = trim($_POST['answer'] ?? '');
    $time_limit = isset($_POST['time_limit']) ? intval($_POST['time_limit']) : 30;
    
    $question_image_url = $current_question['question_image_url']; // Pertahankan URL gambar lama
    $answer_image_url = $current_question['answer_image_url'];     // Pertahankan URL gambar lama

    // --- Penanganan Upload Gambar Pertanyaan Baru ---
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['question_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($file_ext, $allowed_ext)) {
            // Hapus gambar lama jika ada
            if ($question_image_url && file_exists($question_image_url)) {
                unlink($question_image_url);
            }
            $new_file_name = uniqid('q_img_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_name, $destination)) {
                $question_image_url = $destination;
            } else {
                header("Location: quizz_question.php?id=$quiz_id&error=upload_failed&msg=" . urlencode("Gagal mengunggah gambar pertanyaan baru."));
                exit();
            }
        } else {
            header("Location: quizz_question.php?id=$quiz_id&error=invalid_file_type&msg=" . urlencode("Tipe file gambar pertanyaan baru tidak diizinkan."));
            exit();
        }
    } else if (isset($_POST['remove_question_image']) && $_POST['remove_question_image'] == '1') {
        // Hapus gambar jika checkbox "remove" dicentang
        if ($question_image_url && file_exists($question_image_url)) {
            unlink($question_image_url);
        }
        $question_image_url = null;
    }


    // --- Penanganan Upload Gambar Jawaban Baru ---
    if (isset($_FILES['answer_image']) && $_FILES['answer_image']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['answer_image']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['answer_image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($file_ext, $allowed_ext)) {
            // Hapus gambar lama jika ada
            if ($answer_image_url && file_exists($answer_image_url)) {
                unlink($answer_image_url);
            }
            $new_file_name = uniqid('a_img_') . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp_name, $destination)) {
                $answer_image_url = $destination;
            } else {
                header("Location: quizz_question.php?id=$quiz_id&error=upload_failed&msg=" . urlencode("Gagal mengunggah gambar jawaban baru."));
                exit();
            }
        } else {
            header("Location: quizz_question.php?id=$quiz_id&error=invalid_file_type&msg=" . urlencode("Tipe file gambar jawaban baru tidak diizinkan."));
            exit();
        }
    } else if (isset($_POST['remove_answer_image']) && $_POST['remove_answer_image'] == '1') {
        // Hapus gambar jika checkbox "remove" dicentang
        if ($answer_image_url && file_exists($answer_image_url)) {
            unlink($answer_image_url);
        }
        $answer_image_url = null;
    }

    // Validasi: Setidaknya satu dari teks atau gambar pertanyaan harus ada
    if (empty($question_text) && is_null($question_image_url)) {
        header("Location: quizz_question.php?id=$quiz_id&error=validation_failed&msg=" . urlencode("Pertanyaan harus berupa teks atau gambar."));
        exit();
    }

    // Insert atau Update data di database
    if ($question_id === 0) { // Jika menambahkan pertanyaan baru
        $stmt = $conn->prepare("INSERT INTO question_quizz (quiz_id, user_id, question_text, question_image_url, answer, answer_image_url, time_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssi", $quiz_id, $user_id, $question_text, $question_image_url, $answer_text, $answer_image_url, $time_limit);
    } else { // Jika memperbarui pertanyaan yang sudah ada
        $stmt = $conn->prepare("UPDATE question_quizz SET question_text = ?, question_image_url = ?, answer = ?, answer_image_url = ?, time_limit = ? WHERE id = ? AND quiz_id = ? AND user_id = ?");
        $stmt->bind_param("ssssiiii", $question_text, $question_image_url, $answer_text, $answer_image_url, $time_limit, $question_id, $quiz_id, $user_id);
    }

    if ($stmt->execute()) {
        $redirect_success = ($question_id === 0) ? 'added' : 'updated';
        header("Location: quizz_question.php?id=$quiz_id&success=$redirect_success");
        exit();
    } else {
        header("Location: quizz_question.php?id=$quiz_id&error=db_operation_failed&msg=" . urlencode($stmt->error));
        exit();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $question_id === 0 ? 'Tambah' : 'Edit' ?> Pertanyaan untuk Quizz: <?= $quiz_name ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 20px;
        }
        .modal-glass {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5), 0 0 80px rgba(100, 108, 255, 0.3);
            transition: all 0.5s ease;
        }
        .modal-glass h2 {
            color: #E0E7FF;
            text-shadow: 0 0 5px rgba(100, 108, 255, 0.5);
        }
        .modal-glass textarea, 
        .modal-glass input[type="text"], 
        .modal-glass input[type="file"] {
            background: rgba(255, 255, 255, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            color: white !important;
            padding: 0.75rem 1rem !important;
            font-size: 1rem !important;
        }
        .modal-glass select { /* Target select for specific styling */
            background-color: #333 !important; /* Darker background for visibility */
            border: 1px solid rgba(255, 255, 255, 0.4) !important;
            color: white !important;
            padding: 0.75rem 1rem !important;
            font-size: 1rem !important;
            -webkit-appearance: none; /* Remove default browser styling for dropdown arrow */
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23ffffff" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>') !important; /* Custom SVG dropdown arrow */
            background-repeat: no-repeat;
            background-position: right 0.7rem top 50%;
            background-size: 1.5rem auto;
            cursor: pointer;
        }
        .modal-glass select option { /* Style the options within the select */
            background-color: #444; /* Darker background for options */
            color: white;
        }
        .modal-glass textarea::placeholder, 
        .modal-glass input::placeholder,
        .modal-glass select { /* Tambahkan select */
            color: rgba(255, 255, 255, 0.6) !important;
        }
        .modal-glass textarea:focus, 
        .modal-glass input:focus,
        .modal-glass select:focus { /* Tambahkan select */
            outline: none;
            box-shadow: 0 0 0 3px rgba(132, 204, 22, 0.7);
            border-color: rgba(132, 204, 22, 0.7) !important;
        }
        .file-input-label {
            display: inline-block;
            background-color: #3B82F6; /* blue-500 */
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-weight: bold;
        }
        .file-input-label:hover {
            background-color: #2563EB; /* blue-600 */
        }
        .file-input-label input[type="file"] {
            display: none;
        }
        .btn-glow {
            box-shadow: 0 0 10px rgba(100, 108, 255, 0.5);
            transition: all 0.3s ease;
        }
        
        .btn-glow:hover {
            box-shadow: 0 0 20px rgba(100, 108, 255, 0.8);
        }
        .image-preview {
            max-width: 150px;
            height: auto;
            border-radius: 0.5rem;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.3);
        }
    </style>
</head>
<body class="p-6 relative">
    <div class="modal-glass p-6 rounded-xl max-w-lg w-full animate__animated animate__fadeIn">
        <h2 class="text-xl font-bold text-white mb-4"><?= $question_id === 0 ? 'Tambah' : 'Edit' ?> Pertanyaan Quizz: <span class="text-amber-300"><?= $quiz_name ?></span></h2>
        <form action="edit_quizz_question.php?quiz_id=<?= $quiz_id ?>&question_id=<?= $question_id ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_question">
            
            <div class="mb-4">
                <label for="question_text" class="block text-white text-sm font-bold mb-2">Teks Pertanyaan:</label>
                <textarea name="question_text" id="question_text" placeholder="Tulis pertanyaan..." 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 h-24 resize-y"><?= htmlspecialchars($current_question['question_text'] ?? '') ?></textarea>
            </div>

            <div class="mb-6">
                <label for="question_image" class="block text-white text-sm font-bold mb-2">Gambar Pertanyaan (Opsional):</label>
                <?php if ($current_question['question_image_url']): ?>
                    <div class="mb-2">
                        <img src="<?= htmlspecialchars($current_question['question_image_url']) ?>" alt="Gambar Pertanyaan Saat Ini" class="image-preview">
                        <label class="inline-flex items-center mt-2 text-gray-300 text-sm">
                            <input type="checkbox" name="remove_question_image" value="1" class="form-checkbox h-4 w-4 text-red-600">
                            <span class="ml-2">Hapus Gambar</span>
                        </label>
                    </div>
                <?php endif; ?>
                <label class="file-input-label flex items-center justify-center gap-2">
                    <i class="fas fa-upload"></i> Pilih Gambar Baru
                    <input type="file" name="question_image" id="question_image" accept="image/*">
                </label>
                <span id="question_image_name" class="ml-3 text-gray-400 text-sm"></span>
                <p class="text-xs text-gray-500 mt-1">Format: JPG, JPEG, PNG, GIF</p>
            </div>

            <hr class="border-gray-700 my-6">

            <div class="mb-4">
                <label for="answer" class="block text-white text-sm font-bold mb-2">Teks Jawaban:</label>
                <textarea name="answer" id="answer" placeholder="Tulis jawaban..." 
                            class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 h-24 resize-y"><?= htmlspecialchars($current_question['answer'] ?? '') ?></textarea>
            </div>

            <div class="mb-6">
                <label for="answer_image" class="block text-white text-sm font-bold mb-2">Gambar Jawaban (Opsional):</label>
                <?php if ($current_question['answer_image_url']): ?>
                    <div class="mb-2">
                        <img src="<?= htmlspecialchars($current_question['answer_image_url']) ?>" alt="Gambar Jawaban Saat Ini" class="image-preview">
                        <label class="inline-flex items-center mt-2 text-gray-300 text-sm">
                            <input type="checkbox" name="remove_answer_image" value="1" class="form-checkbox h-4 w-4 text-red-600">
                            <span class="ml-2">Hapus Gambar</span>
                        </label>
                    </div>
                <?php endif; ?>
                <label class="file-input-label flex items-center justify-center gap-2">
                    <i class="fas fa-upload"></i> Pilih Gambar Baru
                    <input type="file" name="answer_image" id="answer_image" accept="image/*">
                </label>
                <span id="answer_image_name" class="ml-3 text-gray-400 text-sm"></span>
                <p class="text-xs text-gray-500 mt-1">Format: JPG, JPEG, PNG, GIF</p>
            </div>

            <div class="mb-6">
                <label for="time_limit" class="block text-white text-sm font-bold mb-2">Batas Waktu (Detik):</label>
                <select name="time_limit" id="time_limit" 
                        class="w-full px-4 py-3 bg-gray-800 bg-opacity-50 text-white border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="0" <?= ($current_question['time_limit'] ?? 30) == 0 ? 'selected' : '' ?>>Tanpa Waktu</option>
                    <option value="20" <?= ($current_question['time_limit'] ?? 30) == 20 ? 'selected' : '' ?>>20 Detik</option>
                    <option value="30" <?= ($current_question['time_limit'] ?? 30) == 30 ? 'selected' : '' ?>>30 Detik</option>
                    <option value="40" <?= ($current_question['time_limit'] ?? 30) == 40 ? 'selected' : '' ?>>40 Detik</option>
                    <option value="60" <?= ($current_question['time_limit'] ?? 30) == 60 ? 'selected' : '' ?>>60 Detik</option>
                    <option value="80" <?= ($current_question['time_limit'] ?? 30) == 80 ? 'selected' : '' ?>>80 Detik</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <a href="quizz_question.php?id=<?= $quiz_id ?>" 
                                 class="btn-glow bg-gray-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-700 transition-all">
                    Batal
                </a>
                <button type="submit" 
                                 class="btn-glow bg-gradient-to-r from-yellow-500 to-amber-500 text-gray-900 px-4 py-2 rounded-lg font-bold hover:from-yellow-600 hover:to-amber-600 transition-all">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('question_image').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : '';
            document.getElementById('question_image_name').textContent = fileName;
        });

        document.getElementById('answer_image').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : '';
            document.getElementById('answer_image_name').textContent = fileName;
        });
    </script>
</body>
</html>