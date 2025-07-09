<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$sessionId = $input['session_id'] ?? null;
$teamName = $input['team_name'] ?? null; // PASTIKAN INI ADA
$roundNumber = $input['round_number'] ?? null;
$timeTaken = $input['time_taken'] ?? null;

if (!$sessionId || !$teamName || $roundNumber === null || $timeTaken === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data.']);
    exit;
}

try {
    // Cek apakah entri dengan team_name dan round_number yang sama sudah ada untuk sesi ini
    // Khusus untuk 'Final_Score', kita mungkin ingin mengupdate jika sudah ada
    if ($roundNumber === 'Final_Score') {
        $stmt_check = $conn->prepare("SELECT id FROM dragdrop_results WHERE session_id = ? AND team_name = ? AND round_number = 'Final_Score'");
        $stmt_check->bind_param("ss", $sessionId, $teamName);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Update existing final score
            $row = $result_check->fetch_assoc();
            $id_to_update = $row['id'];
            $stmt_update = $conn->prepare("UPDATE dragdrop_results SET time_taken = ?, submission_time = NOW() WHERE id = ?");
            $stmt_update->bind_param("ii", $timeTaken, $id_to_update); // Gunakan 'i' untuk integer
            $stmt_update->execute();
            echo json_encode(['success' => true, 'message' => 'Final score updated successfully.']);
            $stmt_update->close();
        } else {
            // Insert new final score
            $stmt_insert = $conn->prepare("INSERT INTO dragdrop_results (session_id, team_name, round_number, time_taken, submission_time) VALUES (?, ?, ?, ?, NOW())");
            $stmt_insert->bind_param("sssi", $sessionId, $teamName, $roundNumber, $timeTaken); // Gunakan 'i' untuk integer
            $stmt_insert->execute();
            echo json_encode(['success' => true, 'message' => 'Final score inserted successfully.']);
            $stmt_insert->close();
        }
        $stmt_check->close();

    } else {
        // Untuk round_number selain 'Final_Score', selalu insert baru (jika per-ronde di-track)
        $stmt_insert = $conn->prepare("INSERT INTO dragdrop_results (session_id, team_name, round_number, time_taken, submission_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt_insert->bind_param("sssi", $sessionId, $teamName, $roundNumber, $timeTaken); // Gunakan 'i' untuk integer
        $stmt_insert->execute();
        echo json_encode(['success' => true, 'message' => 'Round result inserted successfully.']);
        $stmt_insert->close();
    }

} catch (mysqli_sql_exception $e) {
    // Tangani error database secara spesifik
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Tangani error umum lainnya
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
// Pastikan tidak ada spasi atau baris baru setelah tag penutup ?> (atau hilangkan tag penutup sama sekali)
?>