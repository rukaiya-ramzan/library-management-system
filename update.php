<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $statusCode = 200, $data = []) {
    http_response_code($statusCode);

    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));

    exit;
}

// Check admin login
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    respond(false, 'Your session has expired. Please log in again.', 401);
}

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', 405);
}

require 'config.php';

// Get submitted values
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$name = strtoupper(trim($_POST['name'] ?? ''));
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Validate ID
if (!$id || $id < 1) {
    respond(false, 'Invalid entry ID.', 422);
}

// Validate required fields
if ($name === '' || $email === '' || $phone === '') {
    respond(false, 'All fields are required.', 422);
}

// Validate name
if (mb_strlen($name) < 3) {
    respond(false, 'Name must contain at least 3 characters.', 422);
}

if (mb_strlen($name) > 255) {
    respond(false, 'Name is too long.', 422);
}

// Validate email
if (
    strlen($email) > 255 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(false, 'Please enter a valid email address.', 422);
}

// Validate phone: exactly 10 digits
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    respond(false, 'Phone number must contain exactly 10 digits.', 422);
}

// Check that the entry exists
$stmt = $conn->prepare(
    'SELECT id FROM entries WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    respond(false, 'Unable to process the update. Please try again.', 500);
}

$stmt->bind_param('i', $id);
$stmt->execute();

$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$exists) {
    respond(false, 'This entry could not be found.', 404);
}

// Check duplicate email, excluding this entry
$stmt = $conn->prepare(
    'SELECT id FROM entries
     WHERE LOWER(email) = LOWER(?) AND id <> ?
     LIMIT 1'
);

if (!$stmt) {
    respond(false, 'Unable to process the update. Please try again.', 500);
}

$stmt->bind_param('si', $email, $id);
$stmt->execute();

$emailExists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($emailExists) {
    respond(false, 'This email address is already registered.', 409);
}

// Check duplicate phone, excluding this entry
$stmt = $conn->prepare(
    'SELECT id FROM entries WHERE phone = ? AND id <> ? LIMIT 1'
);

if (!$stmt) {
    respond(false, 'Unable to process the update. Please try again.', 500);
}

$stmt->bind_param('si', $phone, $id);
$stmt->execute();

$phoneExists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($phoneExists) {
    respond(false, 'This phone number is already registered.', 409);
}

// Update the entry
$stmt = $conn->prepare(
    'UPDATE entries
     SET name = ?, email = ?, phone = ?
     WHERE id = ?'
);

if (!$stmt) {
    respond(false, 'Unable to update this entry. Please try again.', 500);
}

$stmt->bind_param('sssi', $name, $email, $phone, $id);

if (!$stmt->execute()) {
    $stmt->close();
    respond(false, 'Could not update the record. Please try again.', 500);
}

$stmt->close();
$conn->close();

// Return updated entry to the AJAX script
respond(true, 'Entry updated successfully!', 200, [
    'entry' => [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'phone' => $phone
    ]
]);
?>