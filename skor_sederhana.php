<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kalkulator Skor</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script>
    const skorKelompok = [0, 0];
    const poinTambah = 10;
    const poinLainnya = 5;

    function updateSkorDisplay() {
      document.getElementById('skor1').innerText = skorKelompok[0];
      document.getElementById('skor2').innerText = skorKelompok[1];
    }

    function tambahSkor(index) {
      skorKelompok[index] += poinTambah;
      updateSkorDisplay();
    }

    function kurangSkor(index) {
      skorKelompok[index] -= poinTambah;
      updateSkorDisplay();
    }

    function penalti(index) {
      skorKelompok[index] -= poinLainnya;
      updateSkorDisplay();
    }

    window.onload = updateSkorDisplay;
  </script>
</head>
<body class="bg-gradient-to-br from-blue-800 to-purple-800 text-white min-h-screen p-6">

  <div class="max-w-4xl mx-auto bg-white bg-opacity-10 p-6 rounded-xl shadow-md backdrop-blur">
    <h1 class="text-3xl font-bold text-center mb-8">Kalkulator Skor Kelompok</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Kelompok 1 -->
      <div class="bg-indigo-900 p-6 rounded-lg">
        <h2 class="text-2xl font-semibold mb-4">Kelompok 1</h2>
        <div class="text-6xl font-bold text-yellow-300 mb-4" id="skor1">0</div>
        <div class="flex space-x-4">
          <button onclick="tambahSkor(0)" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded-full"><i class="fas fa-plus"></i> +<?= 10 ?></button>
          <button onclick="kurangSkor(0)" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full"><i class="fas fa-minus"></i> -<?= 10 ?></button>
          <button onclick="penalti(0)" class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded-full"><i class="fas fa-exclamation-circle"></i> -<?= 5 ?> (Lainnya)</button>
        </div>
      </div>

      <!-- Kelompok 2 -->
      <div class="bg-purple-900 p-6 rounded-lg">
        <h2 class="text-2xl font-semibold mb-4">Kelompok 2</h2>
        <div class="text-6xl font-bold text-yellow-300 mb-4" id="skor2">0</div>
        <div class="flex space-x-4">
          <button onclick="tambahSkor(1)" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded-full"><i class="fas fa-plus"></i> +<?= 10 ?></button>
          <button onclick="kurangSkor(1)" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-full"><i class="fas fa-minus"></i> -<?= 10 ?></button>
          <button onclick="penalti(1)" class="bg-gray-500 hover:bg-gray-600 px-4 py-2 rounded-full"><i class="fas fa-exclamation-circle"></i> -<?= 5 ?> (Lainnya)</button>
        </div>
      </div>
    </div>

    <div class="mt-8 text-center">
      <a href="dashboard.php" class="text-white underline text-sm">← Kembali ke Dashboard</a>
    </div>
  </div>

</body>
</html>
