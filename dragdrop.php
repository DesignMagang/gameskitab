<?php
session_start();
include 'db.php'; // Pastikan file db.php tersedia untuk koneksi database

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success_message = '';

// Check for messages in session (after PRG redirect)
if (isset($_SESSION['form_success_message'])) {
    $success_message = $_SESSION['form_success_message'];
    unset($_SESSION['form_success_message']); // Clear it after displaying
}
if (isset($_SESSION['form_error_message'])) {
    $error = $_SESSION['form_error_message'];
    unset($_SESSION['form_error_message']); // Clear it after displaying
}

// --- BAGIAN PROSES PEMBUATAN SESI BARU ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_session'])) {
    $session_name = htmlspecialchars($_POST['session_name']);
    $optional_code = isset($_POST['optional_code']) && $_POST['optional_code'] !== '' ? htmlspecialchars($_POST['optional_code']) : null;
    $access_code = null; 
    
    // Generate kode unik sessionid
    $sessionid_unique = false;
    $sessionid = '';
    while (!$sessionid_unique) {
        $sessionid = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); 
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM dragdrop_sessions WHERE sessionid = ?");
        $stmt_check->bind_param("s", $sessionid);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();
        if ($count == 0) {
            $sessionid_unique = true;
        }
    }
    
    try {
        // Simpan ke database
        $stmt = $conn->prepare("INSERT INTO dragdrop_sessions 
                                (sessionid, session_name, access_code, optional_code, created_by) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $sessionid, $session_name, $access_code, $optional_code, $_SESSION['user_id']);
        
        if($stmt->execute()) {
            // Set success message in session before redirecting
            $_SESSION['form_success_message'] = "Sesi '{$session_name}' berhasil dibuat.";
        } else {
            // Set error message in session before redirecting
            $_SESSION['form_error_message'] = "Gagal membuat sesi: " . $stmt->error;
        }
    } catch (Exception $e) {
        // Set error message in session before redirecting
        $_SESSION['form_error_message'] = "Gagal membuat sesi: " . $e->getMessage();
    }
    // Redirect to prevent form resubmission
    header("Location: dragdrop.php");
    exit();
}

// --- BAGIAN PROSES HAPUS SESI ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_session'])) {
    $session_to_delete_id = htmlspecialchars($_POST['session_id_to_delete']);
    
    try {
        // Pastikan hanya pemilik sesi yang bisa menghapus
        $stmt = $conn->prepare("DELETE FROM dragdrop_sessions WHERE sessionid = ? AND created_by = ?");
        $stmt->bind_param("si", $session_to_delete_id, $_SESSION['user_id']);
        
        if($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['form_success_message'] = "Sesi berhasil dihapus.";
            } else {
                $_SESSION['form_error_message'] = "Sesi tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.";
            }
        } else {
            $_SESSION['form_error_message'] = "Gagal menghapus sesi: " . $stmt->error;
        }
    } catch (Exception $e) {
        $_SESSION['form_error_message'] = "Gagal menghapus sesi: " . $e->getMessage();
    }
    // Redirect to prevent form resubmission
    header("Location: dragdrop.php");
    exit();
}


// --- AMBIL DAFTAR SESI YANG DIBUAT OLEH PENGGUNA INI ---
$user_sessions = [];
try {
    $stmt = $conn->prepare("SELECT sessionid, session_name, optional_code FROM dragdrop_sessions WHERE created_by = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_sessions[] = $row;
    }
} catch (Exception $e) {
    $error = "Gagal mengambil daftar sesi: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bible Drag & Drop - Sesi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="icon" href="logo.png" type="image/png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #0f172a; /* Dark slate background */
        }
        .title-font {
            font-family: 'Playfair Display', serif;
        }
        .game-container {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2); /* Blue glowing effect */
        }
        .input-field {
            transition: all 0.3s ease;
            background-color: rgba(30, 41, 59, 0.7); /* Darker, semi-transparent input */
        }
        .input-field:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); /* Blue focus ring */
        }
        .submit-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); /* Blue gradient */
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 78, 216, 0.4); /* Lift and deeper shadow */
        }
        .toggle-btn {
            background-color: transparent;
            border: none;
            cursor: pointer;
            text-decoration: underline;
        }
        .toggle-btn:hover {
            text-decoration: none;
        }

        /* Modal Styles - Combined for both join and delete */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: #1e293b;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 400px;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            position: relative;
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #e2e8f0; /* Light slate text for modal content */
        }
        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }
        .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #ccc;
            cursor: pointer;
        }
        .error-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #dc2626; /* Red-600 */
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 1001;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            pointer-events: none;
        }
        .error-message.show {
            opacity: 1;
        }

        /* Message inside modal */
        #modalErrorMessage {
            position: static;
            transform: none;
            margin-top: 1rem;
            background-color: transparent;
            color: #dc2626;
            padding: 0;
            font-size: 0.875rem;
            font-weight: normal;
            text-align: center;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        #modalErrorMessage.show {
            opacity: 1;
        }

        .session-item {
            display: flex; /* Use flexbox for layout */
            justify-content: space-between; /* Space out content and delete button */
            align-items: center; /* Vertically align items */
            gap: 1rem; /* Space between content and button */
        }
        .session-item-content {
            flex-grow: 1; /* Allow content to take available space */
            cursor: pointer; /* Indicate clickable for joining */
        }
        .delete-btn {
            background-color: #ef4444; /* Red-500 */
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            transition: background-color 0.2s ease;
            white-space: nowrap; /* Prevent button text from wrapping */
            cursor: pointer;
        }
        .delete-btn:hover {
            background-color: #dc2626; /* Red-600 */
        }
        .confirm-delete-btn {
            background-color: #ef4444; /* Red for delete */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }
        .confirm-delete-btn:hover {
            background-color: #dc2626;
        }
        .cancel-delete-btn {
            background-color: #475569; /* Slate-600 for cancel */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }
        .cancel-delete-btn:hover {
            background-color: #334155;
        }
    </style>
</head>
<body class="relative flex flex-col items-center justify-center min-h-screen px-4 bg-cover bg-center">
    <div class="fixed inset-0 -z-10 overflow-hidden opacity-20">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/concrete-wall.png')]"></div>
    </div>

    <div id="globalMessage" class="error-message hidden"></div>

    <div class="w-full max-w-md mx-auto mb-8"> 
        <div class="text-center mb-10">
            <h1 class="title-font text-4xl font-bold text-white mb-2">Bible Drag & Drop</h1>
            <p class="text-slate-300">Buat sesi permainan baru Anda</p>
        </div>

        <div class="game-container p-6 rounded-2xl border border-slate-700/50 backdrop-blur-sm">
            <h2 class="font-bold text-xl text-white mb-4 text-center">Buat Sesi Baru</h2>
            
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-slate-300 text-sm font-medium mb-1">Nama Sesi</label>
                        <input type="text" name="session_name" required 
                               class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50
                                      focus:outline-none focus:border-blue-500
                                      placeholder:text-slate-500"
                               placeholder="Contoh: Kelompok Pemuda">
                    </div>
                    
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-slate-300 text-sm font-medium">Tambahkan Kode Akses (Opsional)</label>
                            <button type="button" id="toggle-optional-code" class="text-xs text-blue-400 toggle-btn">
                                Tambahkan Kode
                            </button>
                        </div>
                        <div id="optional-code-container" class="hidden">
                            <input type="text" name="optional_code" 
                                   class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50
                                          focus:outline-none focus:border-blue-500
                                          placeholder:text-slate-500"
                                   placeholder="Contoh: KODEKU">
                            <p class="text-xs text-slate-400 mt-1">Kode ini akan digunakan untuk bergabung ke sesi.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" name="create_session"
                        class="w-full mt-6 py-3 px-4 submit-btn text-white font-semibold rounded-lg 
                               hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    <i class="fas fa-plus-circle mr-2"></i> Buat Sesi
                </button>
            </form>
        </div>
    </div>

    ---

    <div class="w-full max-w-md mx-auto mt-6">
        <h2 class="font-bold text-xl text-white mb-4 text-center">Sesi Saya</h2>
        <?php if (empty($user_sessions)): ?>
            <p class="text-slate-400 text-center">Anda belum membuat sesi apapun. Buat sesi pertama Anda di atas!</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($user_sessions as $session): ?>
                    <div class="game-container p-4 rounded-lg border border-slate-700/50 session-item">
                        <div class="session-item-content"
                             data-sessionid="<?= htmlspecialchars($session['sessionid']) ?>"
                             data-hascode="<?= !empty($session['optional_code']) ? 'true' : 'false' ?>">
                            <p class="text-white font-semibold"><?= htmlspecialchars($session['session_name']) ?></p>
                            <p class="text-slate-400 text-sm">
                                <?= !empty($session['optional_code']) ? 'Membutuhkan Kode' : 'Tanpa Kode' ?>
                            </p>
                        </div>
                        <button type="button" 
                                class="delete-btn" 
                                data-sessionid-delete="<?= htmlspecialchars($session['sessionid']) ?>"
                                data-sessionname-delete="<?= htmlspecialchars($session['session_name']) ?>">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="joinCodeModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-btn" id="closeJoinCodeModal">&times;</button>
            <h3 class="text-white text-lg font-bold mb-4">Gabung Sesi</h3>
            <p class="text-slate-300 text-sm mb-4">Sesi "<span id="modalSessionName" class="font-semibold text-blue-300"></span>"</p>
            <div class="mb-4" id="modalCodeInputContainer">
                <label for="modalCodeInput" class="block text-slate-300 text-sm font-medium mb-1">Masukkan Kode Akses:</label>
                <input type="text" id="modalCodeInput" class="w-full px-4 py-3 input-field text-white rounded-lg border border-slate-600/50 focus:outline-none focus:border-blue-500 placeholder:text-slate-500" placeholder="Kode yang dibuat saat sesi dibuat">
            </div>
            <button id="submitModalCode" class="w-full py-3 px-4 submit-btn text-white font-semibold rounded-lg 
                               hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                Lanjutkan
            </button>
            <div id="modalErrorMessage" class="text-rose-500 text-sm mt-2 hidden"></div>
        </div>
    </div>

    <div id="deleteConfirmModal" class="modal-overlay">
        <div class="modal-content text-center">
            <button class="close-btn" id="closeDeleteConfirmModal">&times;</button>
            <h3 class="text-white text-lg font-bold mb-4">Konfirmasi Hapus Sesi</h3>
            <p class="text-slate-300 mb-6">Apakah Anda yakin ingin menghapus sesi "<span id="deleteModalSessionName" class="font-semibold text-rose-300"></span>"?</p>
            <p class="text-slate-400 text-sm mb-6">Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex justify-center gap-4">
                <button id="cancelDeleteBtn" class="cancel-delete-btn">Batal</button>
                <form id="deleteSessionForm" method="post" style="display:inline;">
                    <input type="hidden" name="session_id_to_delete" id="hiddenSessionIdToDelete">
                    <button type="submit" name="delete_session" class="confirm-delete-btn">Hapus Sesi</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global message display function
        function showGlobalMessage(message, isError = false) {
            const globalMessageDiv = document.getElementById('globalMessage');
            globalMessageDiv.textContent = message;
            globalMessageDiv.className = 'error-message'; // Reset class
            if (!isError) {
                globalMessageDiv.style.backgroundColor = '#10B981'; // Tailwind emerald-500
            } else {
                globalMessageDiv.style.backgroundColor = '#dc2626'; // Tailwind red-600
            }
            globalMessageDiv.classList.add('show');

            setTimeout(() => {
                globalMessageDiv.classList.remove('show');
                setTimeout(() => {
                    globalMessageDiv.classList.add('hidden');
                }, 300); // Wait for fade out transition
            }, 3000); // Show for 3 seconds
        }

        // Display PHP success/error messages on page load
        <?php if (!empty($error)): ?>
            showGlobalMessage("<?= $error ?>", true);
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            showGlobalMessage("<?= $success_message ?>", false);
        <?php endif; ?>


        // Toggle optional_code input for session creation
        document.getElementById('toggle-optional-code').addEventListener('click', function() {
            const container = document.getElementById('optional-code-container');
            const input = container.querySelector('input[name="optional_code"]');
            if(container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                this.textContent = 'Hapus Kode';
                input.focus();
            } else {
                container.classList.add('hidden');
                input.value = ''; // Clear value when hidden
                this.textContent = 'Tambahkan Kode';
            }
        });

        // --- Modal Logic for JOINING SESSION ---
        const joinCodeModal = document.getElementById('joinCodeModal');
        const closeJoinCodeModalBtn = document.getElementById('closeJoinCodeModal');
        const modalSessionName = document.getElementById('modalSessionName');
        const modalCodeInput = document.getElementById('modalCodeInput');
        const modalCodeInputContainer = document.getElementById('modalCodeInputContainer');
        const submitModalCodeBtn = document.getElementById('submitModalCode');
        const modalErrorMessage = document.getElementById('modalErrorMessage');
        let currentSessionId = null;
        let currentSessionName = null;
        let currentHasCode = false;

        document.querySelectorAll('.session-item-content').forEach(item => { // Select the clickable content area
            item.addEventListener('click', function() {
                currentSessionId = this.dataset.sessionid;
                currentSessionName = this.querySelector('p:first-child').textContent;
                currentHasCode = this.dataset.hascode === 'true';

                modalSessionName.textContent = currentSessionName;
                modalCodeInput.value = '';
                modalErrorMessage.classList.add('hidden');
                modalErrorMessage.textContent = '';

                if (currentHasCode) {
                    modalCodeInputContainer.style.display = 'block';
                    modalCodeInput.placeholder = 'Kode yang dibuat saat sesi dibuat';
                } else {
                    modalCodeInputContainer.style.display = 'none';
                    modalCodeInput.value = '';
                }

                joinCodeModal.classList.add('show');
            });
        });

        closeJoinCodeModalBtn.addEventListener('click', () => {
            joinCodeModal.classList.remove('show');
            modalErrorMessage.classList.add('hidden');
        });

        // Close join modal if clicked outside
        joinCodeModal.addEventListener('click', (event) => {
            if (event.target === joinCodeModal) {
                joinCodeModal.classList.remove('show');
                modalErrorMessage.classList.add('hidden');
            }
        });

        submitModalCodeBtn.addEventListener('click', () => {
            const enteredCode = modalCodeInput.value.trim();
            
            modalErrorMessage.classList.add('hidden');
            modalErrorMessage.textContent = '';

            // Client-side validation for empty code if required
            if (currentHasCode && enteredCode === '') {
                showModalErrorMessage('Sesi ini membutuhkan kode. Harap masukkan kode.');
                return;
            }
            // Client-side validation: If session does NOT have a code, but input is NOT empty
            if (!currentHasCode && enteredCode !== '') {
                 showModalErrorMessage('Sesi ini tidak memerlukan kode. Harap kosongkan input.');
                 return;
            }

            fetch('validate_session_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: currentSessionId,
                    entered_code: enteredCode // Ini adalah optional_code dari user
                })
            })
            .then(response => {
                if (!response.ok) {
                    console.error(`HTTP error! status: ${response.status}`);
                    return response.json().then(err => { throw new Error(err.message || 'Server responded with an error.'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Redirect directly to create_dragdrop.php with session ID
                    window.location.href = `create_dragdrop.php?sessionid=${currentSessionId}`;
                }
                else {
                    showModalErrorMessage(data.message || 'Kode sesi tidak sesuai!');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showModalErrorMessage('Terjadi kesalahan koneksi atau server.');
            });
        });

        function showModalErrorMessage(message) {
            modalErrorMessage.textContent = message;
            modalErrorMessage.classList.remove('hidden');
            modalErrorMessage.classList.add('show');

            setTimeout(() => {
                modalErrorMessage.classList.remove('show');
                setTimeout(() => {
                    modalErrorMessage.classList.add('hidden');
                    modalErrorMessage.textContent = '';
                }, 300);
            }, 1000);
        }

        // --- Modal Logic for DELETING SESSION ---
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const closeDeleteConfirmModalBtn = document.getElementById('closeDeleteConfirmModal');
        const deleteModalSessionName = document.getElementById('deleteModalSessionName');
        const hiddenSessionIdToDelete = document.getElementById('hiddenSessionIdToDelete');
        const deleteSessionForm = document.getElementById('deleteSessionForm');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const sessionIdToDelete = this.dataset.sessionidDelete;
                const sessionNameToDelete = this.dataset.sessionnameDelete;

                deleteModalSessionName.textContent = sessionNameToDelete;
                hiddenSessionIdToDelete.value = sessionIdToDelete; // Set the hidden input value
                deleteConfirmModal.classList.add('show');
            });
        });

        closeDeleteConfirmModalBtn.addEventListener('click', () => {
            deleteConfirmModal.classList.remove('show');
        });

        cancelDeleteBtn.addEventListener('click', () => {
            deleteConfirmModal.classList.remove('show');
        });

        // Close delete modal if clicked outside
        deleteConfirmModal.addEventListener('click', (event) => {
            if (event.target === deleteConfirmModal) {
                deleteConfirmModal.classList.remove('show');
            }
        });
    </script>
</body>
</html>