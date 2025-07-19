<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Handle form submission for new session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['session_name'])) {
        $sessionName = trim($_POST['session_name']);

        // Validate session name
        if (empty($sessionName)) {
            $error = "Nama sesi tidak boleh kosong";
        } else {
            // Generate unique session ID
            $sessionId = substr(bin2hex(random_bytes(6)), 0, 12);

            // Insert into database
            $stmt = $conn->prepare("INSERT INTO sessions (session_id, session_name, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $sessionId, $sessionName, $userId);

            if ($stmt->execute()) {
                $_SESSION['current_session'] = $sessionId;
                header("Location: add_questions.php?session=" . $sessionId);
                exit;
            } else {
                $error = "Gagal membuat sesi game";
            }
        }
    }
}

// Handle delete request
// Handle delete request
if (isset($_GET['delete'])) {
    $sessionId = $_GET['delete'];

    // Verifikasi kepemilikan (sudah benar)
    $checkStmt = $conn->prepare("SELECT created_by FROM sessions WHERE session_id = ?");
    $checkStmt->bind_param("s", $sessionId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $sessionOwner = $checkResult->fetch_assoc();

    if (!$sessionOwner || $sessionOwner['created_by'] != $userId) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk menghapus sesi ini.";
        header("Location: create_matching.php");
        exit;
    }

    // --- START MODIFIKASI BARU ---
    // Urutan penghapusan harus dari tabel anak/cucu terdalam

    // 1. [BARU] Hapus 'cucu': data dari tabel `players` yang terkait dengan sesi ini.
    // Kita gunakan subquery karena `players` terhubung via `game_sessions`.
    $deletePlayersStmt = $conn->prepare(
        "DELETE FROM players WHERE session_code IN (SELECT session_code FROM game_sessions WHERE session_id = ?)"
    );
    $deletePlayersStmt->bind_param("s", $sessionId);
    $deletePlayersStmt->execute();

    // 2. Hapus 'anak': data dari tabel `game_sessions`. Sekarang akan berhasil.
    $deleteGameSessionsStmt = $conn->prepare("DELETE FROM game_sessions WHERE session_id = ?");
    $deleteGameSessionsStmt->bind_param("s", $sessionId);
    $deleteGameSessionsStmt->execute();

    // 3. Hapus 'anak' lainnya: data dari tabel `game_results`
    $deleteGameResultsStmt = $conn->prepare("DELETE FROM game_results WHERE session_id = ?");
    $deleteGameResultsStmt->bind_param("s", $sessionId);
    $deleteGameResultsStmt->execute();

    // 4. Terakhir, hapus 'induk utama': data dari tabel `sessions`
    $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ? AND created_by = ?");
    $stmt->bind_param("si", $sessionId, $userId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Sesi berhasil dihapus beserta seluruh data pemain dan hasilnya.";
        header("Location: create_matching.php");
        exit;
    } else {
        $error = "Gagal menghapus sesi utama setelah membersihkan data terkait.";
    }
    // --- AKHIR MODIFIKASI ---
}

// Get all sessions created by this user
$sessions = [];
$stmt = $conn->prepare("SELECT session_id, session_name, created_at FROM sessions WHERE created_by = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Sesi Game Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
        <link rel="icon" href="logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            min-height: 100vh;
            color: white;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .form-control-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            transition: all 0.3s ease;
        }

        .form-control-glass:focus {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
        }

        .form-control-glass::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .btn-glow {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-glow::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(255, 255, 255, 0),
                rgba(255, 255, 255, 0.3),
                rgba(255, 255, 255, 0)
            );
            transform: rotate(30deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) rotate(30deg); }
            100% { transform: translateX(100%) rotate(30deg); }
        }

        .floating {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .session-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .session-item:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .session-date {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .action-btn {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="glass-card p-4 p-md-5 animate__animated animate__fadeIn">
                    <div class="text-center mb-4 floating">
                        <i class="fas fa-gamepad fa-4x mb-3"></i>
                        <h1 class="fw-bold">Buat Sesi Game Baru</h1>
                        <p class="lead">Mulai petualangan matching game Anda</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success animate__animated animate__fadeIn">
                            <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-heading me-2"></i>Nama Sesi</label>
                            <input type="text" name="session_name" class="form-control form-control-glass py-3" required
                                   placeholder="Contoh: Tokoh Alkitab Perjanjian Lama"
                                   value="<?= isset($_POST['session_name']) ? htmlspecialchars($_POST['session_name']) : '' ?>">
                            <small class="text-white-50">Beri nama yang mudah diingat untuk sesi Anda</small>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg py-3 btn-glow">
                                <i class="fas fa-plus-circle me-2"></i> Buat Sesi & Tambah Pertanyaan
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($sessions)): ?>
                        <div class="mt-5">
                            <h4 class="fw-bold mb-4 text-center"><i class="fas fa-history me-2"></i>Sesi Anda Sebelumnya</h4>

                            <div class="session-list">
                                <?php foreach ($sessions as $session): ?>
                                    <div class="session-item d-flex justify-content-between align-items-center animate__animated animate__fadeInUp">
                                        <div>
                                            <a href="add_questions.php?session=<?= $session['session_id'] ?>" class="text-white text-decoration-none">
                                                <h5 class="mb-1"><?= htmlspecialchars($session['session_name']) ?></h5>
                                            </a>
                                            <div class="session-date">
                                                <i class="far fa-calendar-alt me-1"></i>
                                                <?= date('d M Y H:i', strtotime($session['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="edit_session.php?session=<?= $session['session_id'] ?>" class="action-btn btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="create_matching.php?delete=<?= $session['session_id'] ?>"
                                               class="action-btn btn btn-sm btn-danger"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus sesi ini? Ini juga akan menghapus semua pertanyaan dan data game terkait.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content text-dark">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle text-danger me-2"></i> Konfirmasi Hapus</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menghapus sesi ini?</p>
                                            <p class="text-danger small">Tindakan ini tidak dapat diurungkan dan akan menghapus semua pertanyaan, data pemain, serta hasil game yang terkait.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Ya, Hapus Sesi</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto focus on input when page loads
        document.querySelector('input[name="session_name"]').focus();
    </script>
</body>
</html>