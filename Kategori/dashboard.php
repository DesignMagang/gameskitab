<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM categories WHERE user_id = $user_id");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kategori</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-6 bg-gray-100">
    <div class="max-w-xl mx-auto bg-white p-4 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Kategori Kamu</h1>

        <!-- Tombol buka modal -->
        <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            + Tambah Kategori
        </button>

        <!-- Modal -->
        <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
            <div class="bg-white p-6 rounded shadow-md w-full max-w-sm">
                <h2 class="text-xl font-semibold mb-4">Tambah Kategori</h2>
                <form action="add_category.php" method="POST">
                    <input type="text" name="category_name" placeholder="Nama Kategori" required class="w-full px-3 py-2 border rounded mb-4">
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Batal
                        </button>
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar kategori -->
        <ul class="mt-6 space-y-2">
        <?php while ($row = $result->fetch_assoc()): ?>
            <li class="border-b pb-1">
                <a href="category.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:underline">
                    <?= htmlspecialchars($row['name']) ?>
                </a>
            </li>
        <?php endwhile; ?>
        </ul>

        <a href="logout.php" class="block mt-6 text-red-500 hover:underline">Logout</a>
    </div>
</body>
</html>
