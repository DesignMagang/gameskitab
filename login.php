<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
    } else {
        $error = "Username atau password salah.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center h-screen bg-gray-100">
  <form method="post" class="bg-white p-6 rounded shadow-md w-80">
    <h2 class="text-xl mb-4">Login</h2>
    <?php if (isset($error)): ?>
      <div class="bg-red-100 text-red-700 p-2 rounded mb-3"><?= $error ?></div>
    <?php endif; ?>
    <input type="text" name="username" required placeholder="Username"
           class="w-full p-2 border rounded mb-3" />
    <input type="password" name="password" required placeholder="Password"
           class="w-full p-2 border rounded mb-3" />
    <button type="submit" class="bg-green-500 text-white w-full py-2 rounded hover:bg-green-600">
      Login
    </button>
    <p class="text-sm mt-2">Belum punya akun? <a href="register.php" class="text-blue-600">Daftar</a></p>
  </form>
</body>
</html>
