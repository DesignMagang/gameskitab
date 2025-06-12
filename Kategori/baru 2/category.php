<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$category_id = intval($_GET['id']);

// Cek kepemilikan kategori
$cek = $conn->query("SELECT * FROM categories WHERE id = $category_id AND user_id = $user_id");
if ($cek->num_rows == 0) {
    die("Jika tidak ditemukan atau bukan milik kamu.");
}

$questions = $conn->query("SELECT * FROM questions WHERE category_id = $category_id ORDER BY created_at ASC");
// $questions = $conn->query("SELECT * FROM questions WHERE category_id = $category_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pertanyaan</title>
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
    </style>
</head>
<body class="p-6 bg-gray-100">
      <div class="absolute inset-0 -z-10 h-full w-full pointer-events-none bg-[#1e1e1e] bg-[linear-gradient(to_right,#8080801a_1px,transparent_1px),linear-gradient(to_bottom,#8080801a_1px,transparent_1px)] bg-[size:14px_24px]"></div>
<div class="max-w-xl mx-auto bg-white p-4 rounded shadow">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
        <div class="flex gap-4 flex-wrap items-center" id="navButtons">
          <a href="dashboard.php" class="bg-gray-200 font-bold text-black px-4 py-2 rounded hover:bg-gray-300 text-sm sm:text-base">
            ← Kembali 
          </a>
          <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-green-500 text-black px-4 py-2 rounded font-bold hover:bg-green-600 text-sm sm:text-base">
            + Pertanyaan
          </button>
          <button onclick="resetQuestions()" class="bg-yellow-400 text-black px-4 py-2 rounded    font-bold hover:bg-yellow-500 text-sm sm:text-base">
            Reset
          </button>
        </div>


    </div>

    <ul id="questionList" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
    <?php
    $index = 1;
    $questions->data_seek(0);
    while ($q = $questions->fetch_assoc()):
        $id = $q['id'];
        $text = htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8');
    ?>
        <li>
  <button id="question-<?= $id ?>"
    onclick="showQuestion('<?= $text ?>', <?= $id ?>)"
    class="w-full h-24 flex items-center justify-center bg-gradient-to-r from-indigo-500 to-purple-500 text-white font-bold text-xl rounded-xl shadow hover:scale-105 transition transform duration-200"
  >
    Soal <?= $index++ ?>
  </button>
</li>

    <?php endwhile; ?>
    </ul>
</div>


<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded shadow-md w-full max-w-sm">
        <h2 class="text-xl font-semibold mb-4">Tambah Pertanyaan</h2>
        <form action="add_question.php" method="POST">
            <input type="hidden" name="category_id" value="<?= $category_id ?>">
            <textarea name="question_text" placeholder="Tulis pertanyaan..." required class="w-full px-3 py-2 border rounded mb-4"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">Batal</button>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal untuk tampilkan dan edit pertanyaan -->
<div id="questionModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-50">
  <div class="bg-white p-6 rounded shadow max-w-xl w-full relative">
    
    <!-- Ikon Edit dan Hapus -->
    <div class="absolute top-2 right-10 flex space-x-3">
      
      <button onclick="toggleEdit()" title="Edit">
        <svg class="w-5 h-5 text-yellow-500 hover:text-yellow-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6-6m2-2a2.828 2.828 0 114 4L9 21H5v-4L17.232 3.232z" />
        </svg>
      </button>
      <a id="deleteBtn" href="#" onclick="return confirm('Yakin ingin menghapus pertanyaan ini?')" title="Hapus">
        <svg class="w-5 h-5 text-red-500 hover:text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6" />
        </svg>
      </a>
    </div>

    <!-- Tombol Close -->
    <button onclick="hideModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>

    <!-- Isi Modal -->
    <!-- <h2 class="text-lg font-bold mb-4">Pertanyaan</h2> -->

    <!-- Mode tampilan -->
    <p id="questionText" class="text-3xl font-bold hover:no-underline break-words text-cyan-500"></p>

    <!-- Mode edit -->
    <form id="editForm" class="hidden mt-4" method="POST" action="update_question.php">
      <input type="hidden" name="question_id" id="editQuestionId">
      <input type="hidden" name="category_id" value="<?= $category_id ?>">
      <textarea name="question_text" id="editQuestionText" class="w-full border p-2 mb-4 rounded"></textarea>
      <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 w-full">Simpan</button>
    </form>

    <!-- <button onclick="hideModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 w-full mt-2">Tutup</button> -->
  </div>
</div>



<script>
  function showQuestion(text, id) {
    // Tampilkan modal
    document.getElementById('questionModal').classList.remove('hidden');

    // Isi data
    document.getElementById('questionText').textContent = text;
    document.getElementById('editQuestionText').value = text;
    document.getElementById('editQuestionId').value = id;
    document.getElementById('deleteBtn').href = 'delete_question.php?id=' + id + '&category=<?= $category_id ?>';

    // Sembunyikan soal yang diklik
    var questionItem = document.getElementById('question-' + id);
    if (questionItem) {
      questionItem.style.display = 'none';
      questionItem.classList.add('hidden-question');
    }

    // Sembunyikan tombol navigasi
    document.getElementById('navButtons').style.display = 'none';

    // Sembunyikan form edit
    document.getElementById('editForm').classList.add('hidden');
    document.getElementById('questionText').classList.remove('hidden');
  }

  function hideModal() {
    // Tutup modal
    document.getElementById('questionModal').classList.add('hidden');

    // Tampilkan kembali tombol navigasi
    document.getElementById('navButtons').style.display = 'flex';
  }

  function toggleEdit() {
    document.getElementById('editForm').classList.toggle('hidden');
    document.getElementById('questionText').classList.toggle('hidden');
  }

  function resetQuestions() {
    const hiddenItems = document.querySelectorAll('.hidden-question');
    hiddenItems.forEach(item => {
      item.style.display = 'block';
      item.classList.remove('hidden-question');
    });
  }
</script>


</body>
</html>
