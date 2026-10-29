
<?php
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $statusCode = 200) {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', 405);
}

require 'config.php';

// Get and clean form values
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Validate required fields
if ($name === '' || $email === '' || $phone === '') {
    respond(false, 'All fields are required.', 422);
}

// Validate name
if (strlen($name) > 255) {
    respond(false, 'Name is too long.', 422);
}

// Validate email
if (
    strlen($email) > 255 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(false, 'Please enter a valid email address.', 422);
}

// Validate phone number
if (!preg_match('/^\d{10}$/', $phone)) {
    respond(false, 'Phone number must contain exactly 10 digits.', 422);
}

// Check whether email already exists
$stmt = $conn->prepare(
    'SELECT id FROM entries WHERE LOWER(email) = LOWER(?) LIMIT 1'
);

if (!$stmt) {
    respond(false, 'Unable to process the entry. Please try again.', 500);
}

$stmt->bind_param('s', $email);
$stmt->execute();

$emailExists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($emailExists) {
    respond(
        false,
        'This email address is already registered. Please use another email.',
        409
    );
}

// Check whether phone number already exists
$stmt = $conn->prepare(
    'SELECT id FROM entries WHERE phone = ? LIMIT 1'
);

if (!$stmt) {
    respond(false, 'Unable to process the entry. Please try again.', 500);
}

$stmt->bind_param('s', $phone);
$stmt->execute();

$phoneExists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($phoneExists) {
    respond(
        false,
        'This phone number is already registered. Please use another number.',
        409
    );
}

// Insert the new entry
$stmt = $conn->prepare(
    'INSERT INTO entries (name, email, phone) VALUES (?, ?, ?)'
);

if (!$stmt) {
    respond(false, 'Unable to save the entry. Please try again.', 500);
}

$stmt->bind_param('sss', $name, $email, $phone);

if ($stmt->execute()) {
    $stmt->close();

    respond(true, 'Entry submitted successfully!');
}

$errorNumber = $stmt->errno;
$stmt->close();

// Handle duplicate key errors if unique constraints exist
if ($errorNumber === 1062) {
    respond(
        false,
        'This email address or phone number is already registered.',
        409
    );
}

// Handle other database errors
respond(
    false,
    'Unable to save the entry. Please try again.',
    500
);
?>
