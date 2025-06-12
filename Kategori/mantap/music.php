<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // asumsi user sudah login dan session diset
// $user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM songs WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $user_id);


// Jika kategori dipilih melalui URL, tampilkan lagu berdasarkan kategori
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM songs WHERE user_id = ? AND category_id = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("ii", $user_id, $category_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM songs WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$songs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Lagu</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-3xl font-bold mb-6">Daftar Lagu</h1>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if ($songs->num_rows > 0): ?>
            <?php while ($song = $songs->fetch_assoc()): ?>
                <div class="bg-white p-4 rounded shadow">
                    <h2 class="text-xl font-semibold"><?= htmlspecialchars($song['title']) ?></h2>
                    <audio controls class="mt-2 w-full">
                        <source src="<?= htmlspecialchars($song['file_path']) ?>" type="audio/mpeg">
                        Browser Anda tidak mendukung elemen audio.
                    </audio>
                    <p class="text-sm text-gray-500 mt-1">Diupload: <?= htmlspecialchars($song['uploaded_at']) ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Tidak ada lagu yang tersedia.</p>
        <?php endif; ?>
    </div>
</body>
</html>
