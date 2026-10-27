<?php
include 'config.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name == '' || $email == '' || $message == '') {
        die("Please fill in all fileds.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid email address.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)"
    );

    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        echo  "Your message has been sent successfully!";
        echo '<br><a href="contact.html">Go back to Contact Us</a>';
    } else {
        echo "Error sending your message. Please try again.";
    }

    $stmt->close();

} else {
    echo "Please submit the contact form first.";
}

$conn->close();
?>