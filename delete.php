
<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view.php');
    exit;
}

include 'config.php';

// Validate the record ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: view.php?error=invalid_id');
    exit;
}

// Delete using a prepared statement
$stmt = $conn->prepare('DELETE FROM entries WHERE id = ?');
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $stmt->close();
    header('Location: view.php?status=deleted#entries-table');
    exit;
}

$stmt->close();
http_response_code(500);
echo 'Error deleting the record. Please try again.';
?>
