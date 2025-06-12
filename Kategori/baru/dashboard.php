<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM categories WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>.
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kategori</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100">
    <div class="w-full h-full p-6 bg-gray-100">
        <div class="bg-white p-6 rounded shadow w-full h-full">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Kategori</h1>
                <div class="flex items-center space-x-4">
                    <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-blue-500 text-white px-1 text-[25px] rounded hover:bg-blue-400 font-extrabold">
                        + 
                    </button>

                    <span class="text-gray-700 font-semibold">
                        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </span>

                    <a href="logout.php" class="text-red-500 hover:underline font-semibold">
                        Logout
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
                <div id="notif" class="mb-4 p-3 bg-green-200 text-green-800 rounded">Kategori berhasil dihapus.</div>
                <script>
                    setTimeout(() => document.getElementById('notif')?.remove(), 2000);
                </script>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
                <div id="notif" class="mb-4 p-3 bg-yellow-200 text-yellow-800 rounded">Kategori berhasil diperbarui.</div>
                <script>
                    setTimeout(() => document.getElementById('notif')?.remove(), 2000);
                </script>
            <?php endif; ?>

            <!-- Modal Tambah -->
            <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
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

            <!-- Modal Edit -->
            <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                <div class="bg-white p-6 rounded shadow-md w-full max-w-sm">
                    <h2 class="text-xl font-semibold mb-4">Edit Kategori</h2>
                    <form action="edit_category.php" method="POST">
                        <input type="hidden" name="category_id" id="editCategoryId">
                        <input type="text" name="category_name" id="editCategoryName" required class="w-full px-3 py-2 border rounded mb-4">
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Batal
                            </button>
                            <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Grid Kategori -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-6">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="bg-white p-4 border rounded shadow flex flex-col justify-between">
                            <div class="flex justify-between items-start">
                                <a href="category.php?id=<?= $row['id'] ?>" class="text-lg font-semibold text-blue-600 hover:underline break-words">
                                    <?= htmlspecialchars($row['name']) ?>
                                </a>
                                <div class="flex items-center space-x-2">
                                    <!-- Edit -->
                                    <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')" class="text-yellow-500 hover:text-yellow-700" title="Edit">✏️</button>
                                    <!-- Delete -->
                                    <form action="delete_category.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                                        <input type="hidden" name="category_id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700" title="Hapus">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">Tambahkan satu untuk memulai.</p>
                <?php endif; ?>
            </div>

            <!-- <a href="logout.php" class="block mt-10 text-red-500 hover:underline text-center">Logout</a> -->
        </div>
    </div>

    <script>
        function openEditModal(id, name) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
