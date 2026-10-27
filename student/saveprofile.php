<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "study_planner");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
// Profile Photo Upload

$profilePhoto = "";

if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0){

    $allowed = ['jpg','jpeg','png','webp'];

    $fileName = $_FILES['profile_photo']['name'];

    $tmpName = $_FILES['profile_photo']['tmp_name'];

    $fileSize = $_FILES['profile_photo']['size'];

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if(!in_array($extension, $allowed)){
        die("Only JPG, JPEG, PNG and WEBP images are allowed.");
    }

    if($fileSize > 2 * 1024 * 1024){
        die("Image size must be less than 2MB.");
    }

    $newName = "user_" . $user_id . "_" . time() . "." . $extension;

    $uploadPath = "../uploads/profiles/" . $newName;

   $uploadPath = "../uploads/profiles/" . $newName;

if (move_uploaded_file($tmpName, $uploadPath)) {

    // Save the path that will be used in the browser
    $profilePhoto = "uploads/profiles/" . $newName;

} else {

    die("Failed to upload image.");

}

}

// Prevent duplicate profile
$check = $conn->prepare("SELECT id FROM student_profilenew WHERE user_id=?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
   header("Location: dashboard.php");
exit();
}

// Get Form Data
$age = $_POST['age'];
$gender = $_POST['gender'];

$school = trim($_POST['school']);
$class = trim($_POST['class']);
$board = trim($_POST['board']);
$medium = trim($_POST['medium']);

$exam = trim($_POST['exam']);

if ($exam === "Other" && !empty($_POST['other_exam'])) {
    $exam = trim($_POST['other_exam']);
}
if(isset($_POST['exam_date']) && $_POST['exam_date'] != "")
{
    $exam_date = $_POST['exam_date'];
}
else
{
    $exam_date = NULL;
}

$study_hours = $_POST['study_hours'];
$study_time = $_POST['study_time'];

$weak_subjects = trim($_POST['weak_subjects']);

$session_duration = isset($_POST['session_duration'])
    ? (int)$_POST['session_duration']
    : 60;

$break_duration = isset($_POST['break_duration'])
    ? (int)$_POST['break_duration']
    : 10;

$target_percentage = isset($_POST['target_percentage'])
    ? (float)$_POST['target_percentage']
    : 0;

$study_methods = "";

if(isset($_POST['study_methods']) && is_array($_POST['study_methods'])){
    $study_methods = implode(", ", $_POST['study_methods']);
}

// Save Student Profile
$stmt = $conn->prepare("
INSERT INTO student_profilenew
(
    user_id,
    age,
    gender,
    school_name,
    class_name,
    board,
    target_exam,
    exam_date,
    study_hours,
    medium,
    study_time,
    weak_subjects,
    profile_photo
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
"iissssssissss",
$user_id,
$age,
$gender,
$school,
$class,
$board,
$exam,
$exam_date,
$study_hours,
$medium,
$study_time,
$weak_subjects,
$profilePhoto
);

if (!$stmt->execute()) {
    die("Error: " . $stmt->error);
}

/* ===========================
   AUTO CREATE SUBJECTS
=========================== */

$subjects = [];

// JEE
if (
    stripos($exam, "JEE") !== false ||
    stripos($exam, "MHT") !== false
) {
    $subjects = [
        "Physics",
        "Chemistry",
        "Mathematics",
        "English"
    ];
}

// NEET
elseif (stripos($exam, "NEET") !== false) {

    $subjects = [
        "Physics",
        "Chemistry",
        "Biology",
        "English"
    ];
}

// UPSC
elseif (stripos($exam, "UPSC") !== false) {

    $subjects = [
        "History",
        "Geography",
        "Polity",
        "Economics",
        "Current Affairs"
    ];
}

// GATE
elseif (stripos($exam, "GATE") !== false) {

    $subjects = [
        "Core Subject",
        "Mathematics",
        "Reasoning",
        "English"
    ];
}

// Default
else {

    $subjects = [
        "Mathematics",
        "Science",
        "English"
    ];

}

// Insert Subjects
$insertSubject = $conn->prepare("
INSERT INTO subjects
(user_id, subject_name, priority)
VALUES
(?,?,?)
");

foreach ($subjects as $subject) {

    $priority = "Medium";

    $insertSubject->bind_param(
        "iss",
        $user_id,
        $subject,
        $priority
    );

    $insertSubject->execute();

}

$conn->close();

// Redirect
header("Location: dashboard.php");
exit();

?>