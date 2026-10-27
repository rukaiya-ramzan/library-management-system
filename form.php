<?php
include 'config.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name == '' || $email == '' || $phone == '') {
        die("Please fill in all fileds.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid email address.");
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        die("Phone number must contain exactly 10 digits.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO entries (name, email, phone) VALUES (?, ?, ?)"
    );

    $stmt->bind_param("sss", $name, $email, $phone);

    if ($stmt->execute()) {
        echo "Entry saved successfully!";
        echo '<br><a href="form.html">Go back to the form</a>';
    } else {
        echo "Error saving entry. Please try again.";
    }

    $stmt->close();

} else {
    echo "Please submit the form first.";
}

$conn->close();
?>