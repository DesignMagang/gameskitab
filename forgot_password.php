forgot_password.php
<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $new_username = isset($_POST['new_username']) ? $_POST['new_username'] : null;

    // Cek apakah user ada
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Update username (jika diisi) dan password
        if ($new_username) {
            $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE username=?");
            $stmt->bind_param("sss", $new_username, $new_password, $username);
        } else {
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE username=?");
            $stmt->bind_param("ss", $new_password, $username);
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Password berhasil diubah." . ($new_username ? " Username juga telah diperbarui." : "");
            header("Location: login.php");
            exit;
        } else {
            $error = "Gagal mengubah data: " . $conn->error;
        }
    } else {
        $error = "Username tidak ditemukan.";
    }

    if ($new_username) {
      $check = $conn->prepare("SELECT id FROM users WHERE username=?");
      $check->bind_param("s", $new_username);
      $check->execute();
      if ($check->get_result()->num_rows > 0) {
          $error = "Username sudah digunakan.";
      }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Game Alkitab</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap');
    
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #0f172a;
    }
    
    .title-font {
      font-family: 'Playfair Display', serif;
    }
    
    .game-container {
      background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
      box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
    }
    
    .input-field {
      transition: all 0.3s ease;
      background-color: rgba(30, 41, 59, 0.7);
    }
    
    .input-field:focus {
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }
    
    .submit-btn {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4);
    }
  </style>
</head>
<body class="relative flex items-center justify-center min-h-screen px-4 bg-cover bg-center">
  <!-- Background Elements -->
  <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
  </div>

  <!-- Decorative Elements -->
  <div class="fixed -z-10 opacity-10">
    <div class="absolute top-10 left-10 text-5xl">✞</div>
    <div class="absolute bottom-10 right-10 text-5xl">🕊️</div>
    <div class="absolute top-1/2 left-1/4 text-4xl">✝</div>
    <div class="absolute top-1/3 right-1/4 text-4xl">⛪</div>
  </div>

  <!-- Main Form Container -->
  <div class="w-full max-w-md mx-auto">
    <form method="post" class="game-container p-8 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
      <div class="text-center mb-8">
        <h2 class="title-font text-3xl font-bold text-white mb-2">Reset Credentials</h2>
        <p class="text-slate-300 text-sm">Update your account information</p>
      </div>

      <?php if (isset($error)): ?>
        <div class="bg-rose-900/50 text-rose-100 p-3 rounded-lg mb-6 text-sm border border-rose-800/50">
          <?= $error ?>
        </div>
      <?php endif; ?>

      <div class="space-y-4">
        <div>
          <label class="block text-slate-300 text-sm font-medium mb-1">Current Username</label>
          <input type="text" name="username" required 
                class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50
                      focus:outline-none focus:border-blue-500
                      placeholder:text-slate-500"
                placeholder="Your current username">
        </div>
        
        <div>
          <label class="block text-slate-300 text-sm font-medium mb-1">New Username (Optional)</label>
          <input type="text" name="new_username" 
                class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50
                      focus:outline-none focus:border-blue-500
                      placeholder:text-slate-500"
                placeholder="Leave blank to keep current">
        </div>

        <div>
          <label class="block text-slate-300 text-sm font-medium mb-1">New Password</label>
          <input type="password" name="new_password" required
                class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50
                      focus:outline-none focus:border-blue-500
                      placeholder:text-slate-500"
                placeholder="Enter new password">
        </div>
      </div>

      <button type="submit" 
              class="w-full mt-6 py-3 px-4 submit-btn text-white font-semibold rounded-lg 
                    hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
        Update Account
      </button>

      <div class="mt-6 text-center">
        <a href="login.php" class="text-blue-400 text-sm hover:text-blue-300 hover:underline transition">
          ← Back to Login
        </a>
      </div>
    </form>
  </div>

  <!-- Mobile Optimization -->
  <script>
    function adjustViewport() {
      let vh = window.innerHeight * 0.01;
      document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    window.addEventListener('resize', adjustViewport);
    adjustViewport();
  </script>
</body>
</html>