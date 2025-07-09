<?php
session_start();
require_once 'db.php';

if (!isset($_GET['session'])) {
    header("Location: create_matching.php");
    exit;
}

$sessionId = $_GET['session'];
$userId = $_SESSION['user_id'];

// Get session data
$stmt = $conn->prepare("SELECT session_name FROM sessions WHERE session_id = ? AND created_by = ?");
$stmt->bind_param("si", $sessionId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();

if (!$session) {
    die("Sesi tidak ditemukan");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = trim($_POST['session_name']);
    
    if (empty($newName)) {
        $error = "Nama sesi tidak boleh kosong";
    } else {
        $update = $conn->prepare("UPDATE sessions SET session_name = ? WHERE session_id = ? AND created_by = ?");
        $update->bind_param("ssi", $newName, $sessionId, $userId);
        
        if ($update->execute()) {
            $_SESSION['message'] = "Nama sesi berhasil diperbarui";
            header("Location: create_matching.php");
            exit;
        } else {
            $error = "Gagal memperbarui nama sesi";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Sesi Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
        
        .back-btn {
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="glass-card p-4 p-md-5 animate__animated animate__fadeIn">
                    <div class="text-center mb-4 floating">
                        <i class="fas fa-edit fa-4x mb-3"></i>
                        <h1 class="fw-bold">Edit Sesi Game</h1>
                        <p class="lead">Perbarui nama sesi Anda</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger animate__animated animate__shakeX">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-heading me-2"></i>Nama Sesi Baru</label>
                            <input type="text" name="session_name" class="form-control form-control-glass py-3" required 
                                   placeholder="Masukkan nama sesi baru"
                                   value="<?= htmlspecialchars($session['session_name']) ?>">
                            <small class="text-white-50">Beri nama yang mudah diingat untuk sesi Anda</small>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="create_matching.php" class="btn btn-outline-light back-btn">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg py-3 btn-glow">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
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