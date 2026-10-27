
<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "study_planner";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data safely
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (
        empty($name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: register.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: register.php");
        exit();
    }

    // Check password match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: register.php");
        exit();
    }

    // Check if email already exists
    $check = $conn->prepare(
        "SELECT id FROM user WHERE email = ? LIMIT 1"
    );

    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();

        $_SESSION['error'] =
            "This email is already registered. Please login or use another email.";

        header("Location: register.php");
        exit();
    }

    $check->close();

    // Hash password
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Insert new user
    $stmt = $conn->prepare(
        "INSERT INTO user (name, email, password)
         VALUES (?, ?, ?)"
    );

    $stmt->bind_param(
        "sss",
        $name,
        $email,
        $hashed_password
    );

    if ($stmt->execute()) {

        $_SESSION['success'] =
            "Registration successful! Please login.";

        $stmt->close();
        $conn->close();

        header("Location: login.php");
        exit();

    } else {
        $_SESSION['error'] =
            "Registration failed. Please try again.";
        
        $stmt->close();
        $conn->close();

        header("Location: register.php");
        exit();
    }
}

$conn->close();
?>
