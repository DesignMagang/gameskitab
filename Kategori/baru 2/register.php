<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // enkripsi

    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $password);
    if ($stmt->execute()) {
        header("Location: login.php");
    } else {
        $error = "Gagal daftar";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center h-screen bg-gray-100">
  <form method="post" class="bg-white p-6 rounded shadow-md w-80">
    <h2 class="text-xl mb-4">Register</h2>
    <input type="text" name="username" required placeholder="Username"
           class="w-full p-2 border rounded mb-3" />
    <input type="password" name="password" required placeholder="Password"
           class="w-full p-2 border rounded mb-3" />
    <button type="submit" class="bg-blue-500 text-white w-full py-2 rounded hover:bg-blue-600">
      Register
    </button>
    <p class="text-sm mt-2">Sudah punya akun? <a href="login.php" class="text-blue-600">Login</a></p>
  </form>
</body>
</html>
