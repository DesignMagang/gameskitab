Dashboard.php
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

<?php
include 'db.php';
$songs = $conn->query("SELECT * FROM songs ORDER BY uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kategori</title>
    <script src="https://cdn.tailwindcss.com"></script>

     <style>
    @keyframes gradientMove {
      0% {
        background-position: 0% 50%;
      }
      100% {
        background-position: 100% 50%;
      }
    }

    .animated-gradient-text {
      background: linear-gradient(270deg, #f5af19, #f12711, #f5af19);
      background-size: 300% 300%;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: gradientMove 4s linear infinite;
    }

    .gradient-text {
     background: linear-gradient(to left, #1F1C18, #8E0E00);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: transparent;
    }
  </style>
  
</head>

<body class="relative h-screen">
    <!-- Background Pattern -->
    <div class="absolute inset-0 -z-10 h-full w-full pointer-events-none bg-[#1e1e1e] bg-[linear-gradient(to_right,#8080801a_1px,transparent_1px),linear-gradient(to_bottom,#8080801a_1px,transparent_1px)] bg-[size:14px_24px]"></div>


<div class="relative z-10 w-full h-full p-6">
            <div class=" p-6 rounded shadow w-full h-full">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-6xl font-bold animated-gradient-text">
                        KATEGORI
                    </h1>
                    <div class="flex items-center space-x-4">
                        <!-- Tombol tambah -->
                       <button onclick="document.getElementById('modal').classList.remove('hidden')" 
                                class="rounded-lg px-2 py-1 font-black bg-sky-600 text-slate-900 hover:bg-sky-500 font-extrabold">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                class="h-6 w-6 inline-block font-black"  
                                fill="none" 
                                viewBox="0 0 24 24" 
                                stroke="currentColor" 
                                stroke-width="3"> <!-- Ketebalan stroke ditingkatkan -->
                                <path stroke-linecap="round" 
                                    stroke-linejoin="round" 
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </button>

                        <!-- <button nclick="document.getElementById('modal').classList.remove('hidden')" class="rounded-lg px-6 py-3 font-medium bg-sky-400 text-slate-900 hover:bg-sky-300">
                            +
                    </button> -->
                        <span class=" font-semibold text-2xl animated-gradient-text">
                            <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                        </span>

                        <a href="logout.php" class="animated-gradient-text text-2xl hover:underline font-semibold">
                            Keluar
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
                                <!-- Kumpulan kategori -->
                                <div class="flex justify-between items-start">
                                    <a href="category.php?id=<?= $row['id'] ?>" class="text-2xl font-bold hover:no-underline break-words text-cyan-500">
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