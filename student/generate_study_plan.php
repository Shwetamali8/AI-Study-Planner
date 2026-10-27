
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "study_planner");

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$sql = "SELECT
            u.name,
            p.subjects_data,
            p.weak_subjects,
            p.study_hours,
            p.study_time,
            p.session_duration,
            p.break_duration,
            p.target_percentage,
            p.exam_date,
            p.study_methods,
            p.available_days,
            p.weekly_availability
        FROM `user` u
        LEFT JOIN student_profilenew p ON p.user_id = u.id
        WHERE u.id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();
$conn->close();

if (!$student || empty($student['subjects_data'])) {
    $message = "Please add your subjects in your profile before creating a study plan.";
} else {
    $message = "Your saved study preferences are ready for the study-plan workflow.";
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate AI Study Plan | MindAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #080d1c;
            color: #f8fafc;
            font-family: Arial, sans-serif;
        }
        .container-box {
            max-width: 800px;
            margin: 55px auto;
            padding: 30px;
            background: #111a2e;
            border: 1px solid #29354b;
            border-radius: 20px;
        }
        .muted { color: #94a3b8; }
        .btn-primary {
            background: linear-gradient(90deg, #06b6d4, #6366f1);
            border: none;
        }
        .detail {
            padding: 12px 0;
            border-bottom: 1px solid #29354b;
        }
    </style>
</head>
<body>
<div class="container-box">
    <h1 class="mb-2">Generate AI Study Plan</h1>
    <p class="muted">Welcome, <?= h($student['name'] ?? 'Student') ?>.</p>

    <div class="alert alert-info"><?= h($message) ?></div>

    <h4 class="mt-4">Your Study Preferences</h4>

    <div class="detail">
        Daily study hours:
        <strong><?= h($student['study_hours'] ?? 'Not set') ?></strong>
    </div>

    <div class="detail">
        Preferred study time:
        <strong><?= h($student['study_time'] ?? 'Not set') ?></strong>
    </div>

    <div class="detail">
        Session duration:
        <strong><?= h($student['session_duration'] ?? 'Not set') ?> minutes</strong>
    </div>

    <div class="detail">
        Break duration:
        <strong><?= h($student['break_duration'] ?? 'Not set') ?> minutes</strong>
    </div>

    <div class="detail">
        Target percentage:
        <strong><?= h($student['target_percentage'] ?? 'Not set') ?>%</strong>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="newdashboard.php" class="btn btn-outline-light">
            Back to Dashboard
        </a>

        <a href="profilesetup.php" class="btn btn-outline-info">
            Edit Preferences
        </a>

        <a href="view_study_plan.php" class="btn btn-primary">
            View My Study Plan
        </a>
    </div>

    <p class="muted mt-4 mb-0">
        Note: This page loads your profile information. Actual automatic
        generation and saving of a study schedule requires the plan-generation
        logic to be implemented and connected to your database.
    </p>
</div>
</body>
</html>
