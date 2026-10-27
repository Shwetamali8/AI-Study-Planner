
<?php
session_start();

// =============================================
// 1. CHECK LOGIN
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// =============================================
// 2. DATABASE CONNECTION
// =============================================
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "study_planner";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$user_id = (int) $_SESSION['user_id'];

// =============================================
// 3. HELPER FUNCTIONS
// =============================================

// Safely display text
function e($value) {
    if ($value === null || $value === '') {
        return 'Not provided';
    }

    if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Display an optional value without "Not provided"
function displayValue($value, $suffix = '') {
    if ($value === null || $value === '') {
        return 'Not provided';
    }

    return e($value) . $suffix;
}

// Decode JSON arrays or objects.
// Also supports simple comma-separated text.
function decodeList($value) {
    if (empty($value)) {
        return [];
    }

    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode($value, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Handle objects that wrap their data in a list.
        foreach (['subjects', 'exams', 'assignments', 'items', 'data'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        // A single record may be stored as a JSON object.
        $isList = array_keys($decoded) === range(0, count($decoded) - 1);

        if ($decoded === [] || $isList) {
            return $decoded;
        }

        return [$decoded];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', (string)$value)),
        function ($item) {
            return $item !== '';
        }
    ));
}

// Get a field from a record using possible field names.
function getField($row, $keys, $default = '') {
    if (!is_array($row)) {
        return $default;
    }

    foreach ($keys as $key) {
        if (array_key_exists($key, $row)
            && $row[$key] !== null
            && $row[$key] !== '') {
            return $row[$key];
        }
    }

    return $default;
}

// Format dates safely.
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00') {
        return 'Not provided';
    }

    $timestamp = strtotime((string)$date);

    return $timestamp ? date('d M Y', $timestamp) : e($date);
}

// Convert a JSON list or text into a readable string.
function readableValue($value) {
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $parts[] = getField(
                    $item,
                    ['name', 'subject_name', 'method', 'day', 'title'],
                    ''
                );
            } else {
                $parts[] = $item;
            }
        }

        $value = implode(', ', array_filter($parts));
    }

    return $value === '' || $value === null
        ? 'Not provided'
        : e($value);
}

// =============================================
// 4. FETCH STUDENT PROFILE
// =============================================
$stmt = $conn->prepare(
    "SELECT * FROM student_profilenew WHERE user_id = ? LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();

    // Change this filename if your setup page has a different name.
    header("Location: profilesetup.php");
    exit();
}

$profile = $result->fetch_assoc();
$stmt->close();

// =============================================
// 5. PREPARE PROFILE DATA
// =============================================
$studentName = $_SESSION['user_name'] ?? 'Student';

$subjects = decodeList($profile['subjects_data'] ?? '');
$upcomingExams = decodeList($profile['upcoming_exams'] ?? '');
$assignments = decodeList($profile['assignment_deadlines'] ?? '');

$studyMethods = decodeList($profile['study_methods'] ?? '');
$availableDays = decodeList($profile['available_days'] ?? '');
$weakSubjects = decodeList($profile['weak_subjects'] ?? '');

$weeklyAvailability = $profile['weekly_availability'] ?? '';

$today = date('Y-m-d');

// =============================================
// 6. FETCH TODAY'S STUDY PLAN
// =============================================
$todayTasks = [];
$totalTasks = 0;
$completedTasks = 0;
$progressPercentage = 0;

$planStmt = $conn->prepare(
    "SELECT * FROM study_plans
     WHERE user_id = ? AND plan_date = ?
     ORDER BY start_time ASC"
);

if ($planStmt) {
    $planStmt->bind_param("is", $user_id, $today);
    $planStmt->execute();

    $planResult = $planStmt->get_result();

    while ($row = $planResult->fetch_assoc()) {
        $todayTasks[] = $row;
    }

    $planStmt->close();
}

$totalTasks = count($todayTasks);

foreach ($todayTasks as $task) {
    if (strtolower((string)($task['status'] ?? '')) === 'completed') {
        $completedTasks++;
    }
}

$progressPercentage = $totalTasks > 0
    ? (int) round(($completedTasks / $totalTasks) * 100)
    : 0;

// =============================================
// 7. PREPARE PROFILE PHOTO PATH
// =============================================
$photo = trim((string)($profile['profile_photo'] ?? ''));

if ($photo !== '') {
    if (preg_match('/^https?:\/\//i', $photo)) {
        $photoUrl = $photo;
    } elseif (strpos($photo, '../') === 0) {
        $photoUrl = $photo;
    } elseif (strpos($photo, 'uploads/') === 0) {
        // Dashboard is assumed to be inside the student folder.
        $photoUrl = '../' . $photo;
    } else {
        $photoUrl = '../uploads/profile_photos/' . basename($photo);
    }
} else {
    $photoUrl = '';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AI Study Planner | Student Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 25px;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #050816;
            color: #f8fafc;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 255px;
            height: 100vh;
            padding: 28px 18px;
            background: linear-gradient(180deg, #0f172a, #020617);
            border-right: 1px solid rgba(255,255,255,.12);
            overflow-y: auto;
            z-index: 1000;
        }

        .logo {
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 35px;
            padding-left: 8px;
        }

        .logo span {
            color: #38bdf8;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 15px;
            margin-bottom: 8px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 13px;
            transition: .25s;
        }

        .menu a i {
            width: 23px;
            color: #38bdf8;
        }

        .menu a:hover,
        .menu a.active {
            color: white;
            background: linear-gradient(
                90deg,
                rgba(6,182,212,.24),
                rgba(99,102,241,.24)
            );
            transform: translateX(4px);
        }

        .menu .logout {
            margin-top: 25px;
            color: #fca5a5;
        }

        .menu .logout i {
            color: #f87171;
        }

        .main {
            margin-left: 255px;
            padding: 30px;
            min-width: 0;
        }

        .top-card {
            position: relative;
            overflow: hidden;
            padding: 35px;
            margin-bottom: 25px;
            border-radius: 28px;
            background: linear-gradient(135deg, #2563eb, #0891b2, #4f46e5);
            box-shadow: 0 15px 45px rgba(6,182,212,.18);
        }

        .top-card::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -60px;
            top: -100px;
            background: rgba(255,255,255,.12);
            border-radius: 50%;
        }

        .top-card > * {
            position: relative;
            z-index: 1;
        }

        .profile-photo {
            width: 82px;
            height: 82px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,.8);
            box-shadow: 0 0 20px rgba(255,255,255,.25);
        }

        .photo-placeholder {
            width: 82px;
            height: 82px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255,255,255,.8);
            background: rgba(255,255,255,.16);
            font-size: 35px;
        }

        .ai-btn {
            background: white;
            color: #1d4ed8;
            font-weight: 700;
            padding: 12px 20px;
            border: 0;
            text-decoration: none;
            display: inline-block;
            border-radius: 30px;
        }

        .ai-btn:hover {
            color: #1e40af;
            background: #f1f5f9;
        }

        .section-title {
            font-size: 23px;
            font-weight: 750;
            margin-bottom: 18px;
        }

        .section-subtitle {
            color: #94a3b8;
            font-size: 13px;
            margin-top: -10px;
            margin-bottom: 20px;
        }

        .card-box {
            height: 100%;
            min-width: 0;
            padding: 24px;
            border: 1px solid rgba(255,255,255,.11);
            border-radius: 22px;
            background: rgba(255,255,255,.055);
            box-shadow: 0 10px 28px rgba(0,0,0,.12);
        }

        .stat-card {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .icon-box {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(56,189,248,.12);
            color: #38bdf8;
            border-radius: 15px;
            font-size: 23px;
        }

        .muted {
            color: #94a3b8;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .detail-row:last-child {
            border-bottom: 0;
        }

        .detail-label {
            color: #94a3b8;
            font-size: 13px;
            flex: 0 0 42%;
        }

        .detail-value {
            text-align: right;
            overflow-wrap: anywhere;
            font-weight: 600;
            flex: 1;
        }

        .item-card {
            padding: 18px;
            height: 100%;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 17px;
            background: #0f172a;
        }

        .item-card h5 {
            font-size: 16px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .item-card p {
            color: #cbd5e1;
            font-size: 13px;
            margin-bottom: 8px;
            overflow-wrap: anywhere;
        }

        .pill {
            display: inline-block;
            padding: 6px 10px;
            margin: 3px 4px 3px 0;
            color: #bae6fd;
            background: rgba(14,165,233,.13);
            border: 1px solid rgba(56,189,248,.2);
            border-radius: 30px;
            font-size: 12px;
        }

        .task {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 15px;
            background: #0f172a;
            border: 1px solid rgba(255,255,255,.07);
        }

        .task-icon {
            color: #38bdf8;
            margin-top: 3px;
        }

        .status {
            display: inline-block;
            font-size: 11px;
            padding: 5px 9px;
            border-radius: 20px;
            background: rgba(148,163,184,.12);
            color: #cbd5e1;
            text-transform: capitalize;
        }

        .status.completed {
            background: rgba(34,197,94,.14);
            color: #86efac;
        }

        .progress {
            height: 12px;
            background: #263449;
            border-radius: 20px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #06b6d4, #6366f1);
        }

        .empty-state {
            padding: 25px 12px;
            text-align: center;
            color: #94a3b8;
            background: rgba(15,23,42,.6);
            border-radius: 15px;
        }

        .empty-state i {
            display: block;
            font-size: 27px;
            color: #38bdf8;
            margin-bottom: 12px;
        }

        .footer-note {
            color: #64748b;
            font-size: 12px;
            text-align: center;
            padding: 25px 0 10px;
        }

        @media (max-width: 1000px) {
            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 22px;
            }

            .top-card {
                padding: 25px;
            }
        }

        @media (max-width: 760px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 15px;
            }

            .logo {
                margin-bottom: 15px;
            }

            .menu {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .menu a {
                padding: 9px 10px;
                margin: 0;
                font-size: 12px;
            }

            .menu a i {
                width: auto;
            }

            .menu .logout {
                margin-top: 0;
            }

            .main {
                margin-left: 0;
                padding: 16px;
            }

            .top-card h1 {
                font-size: 25px;
            }

            .top-card {
                padding: 22px;
                border-radius: 20px;
            }

            .card-box {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<!-- =========================================
     SIDEBAR
========================================= -->
<aside class="sidebar">
    <div class="logo">🤖 AI <span>Planner</span></div>

    <nav class="menu">
        <a href="dashboard.php" class="active">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <a href="#academic">
            <i class="fa-solid fa-user-graduate"></i> Academic Details
        </a>

        <a href="#subjects">
            <i class="fa-solid fa-book-open"></i> Subjects
        </a>

        <a href="#exams">
            <i class="fa-solid fa-calendar-days"></i> Exams
        </a>

        <a href="#assignments">
            <i class="fa-solid fa-file-lines"></i> Assignments
        </a>

        <a href="#preferences">
            <i class="fa-solid fa-sliders"></i> Preferences
        </a>

        <a href="#tasks">
            <i class="fa-solid fa-list-check"></i> Today's Tasks
        </a>

        <a href="#progress">
            <i class="fa-solid fa-chart-line"></i> Progress
        </a>

        <a href="../auth/logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main">

    <!-- =====================================
         WELCOME HEADER
    ====================================== -->
    <section class="top-card">
        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">

            <?php if ($photoUrl !== ''): ?>
                <img
                    src="<?php echo e($photoUrl); ?>"
                    alt="Student profile photo"
                    class="profile-photo rounded-circle"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div class="photo-placeholder rounded-circle"
                     style="display:none;">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            <?php else: ?>
                <div class="photo-placeholder rounded-circle">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            <?php endif; ?>

            <div>
                <p class="mb-1">YOUR PERSONAL LEARNING SPACE</p>
                <h1 class="fw-bold mb-1">
                    Welcome back, <?php echo e($studentName); ?>!
                </h1>
                <p class="mb-0">
                    Let's make today productive.
                </p>
            </div>
        </div>

        <div class="mb-3">
            <span class="pill">
                <i class="fa-solid fa-graduation-cap me-1"></i>
                <?php echo e($profile['class_name'] ?? ''); ?>
            </span>

            <span class="pill">
                <i class="fa-solid fa-book me-1"></i>
                <?php echo e($profile['board'] ?? ''); ?>
            </span>

            <span class="pill">
                <i class="fa-solid fa-bullseye me-1"></i>
                <?php echo e($profile['target_exam'] ?? ''); ?>
            </span>
        </div>

        <p>
            Target percentage:
            <strong>
                <?php echo displayValue($profile['target_percentage'] ?? null, '%'); ?>
            </strong>
            &nbsp; | &nbsp;
            Daily study goal:
            <strong>
                <?php echo displayValue($profile['study_hours'] ?? null, ' hours'); ?>
            </strong>
        </p>

        <!-- Change generate_plan.php if your generator uses another filename. -->
        <a href="generate_plan.php" class="ai-btn">
            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>
            Generate AI Study Plan
        </a>
    </section>

    <!-- =====================================
         QUICK SUMMARY CARDS
    ====================================== -->
    <section class="row g-3 mb-5">

        <div class="col-sm-6 col-xl-3">
            <div class="card-box stat-card">
                <div class="icon-box">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="muted small">Daily Study Goal</div>
                    <h4 class="mb-0">
                        <?php echo displayValue($profile['study_hours'] ?? null, ' hrs'); ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box stat-card">
                <div class="icon-box">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <div class="muted small">Subjects</div>
                    <h4 class="mb-0"><?php echo count($subjects); ?></h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box stat-card">
                <div class="icon-box">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <div class="muted small">Today's Tasks</div>
                    <h4 class="mb-0"><?php echo $totalTasks; ?></h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card-box stat-card">
                <div class="icon-box">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <div>
                    <div class="muted small">Completed Today</div>
                    <h4 class="mb-0">
                        <?php echo $completedTasks; ?>/<?php echo $totalTasks; ?>
                    </h4>
                </div>
            </div>
        </div>

    </section>

    <!-- =====================================
         ACADEMIC / STUDENT DETAILS
    ====================================== -->
    <section id="academic" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-user-graduate me-2 text-info"></i>
            Academic & Student Details
        </h2>

        <p class="section-subtitle">
            Information saved in your student profile.
        </p>

        <div class="row g-4">

            <div class="col-lg-6">
                <div class="card-box">
                    <h5 class="mb-3">Personal & School Information</h5>

                    <div class="detail-row">
                        <span class="detail-label">Student Name</span>
                        <span class="detail-value"><?php echo e($studentName); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Age</span>
                        <span class="detail-value"><?php echo displayValue($profile['age'] ?? null, ' years'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Gender</span>
                        <span class="detail-value"><?php echo e($profile['gender'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">School Name</span>
                        <span class="detail-value"><?php echo e($profile['school_name'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Class</span>
                        <span class="detail-value"><?php echo e($profile['class_name'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Board</span>
                        <span class="detail-value"><?php echo e($profile['board'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Medium</span>
                        <span class="detail-value"><?php echo e($profile['medium'] ?? ''); ?></span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-box">
                    <h5 class="mb-3">College & Course Information</h5>

                    <div class="detail-row">
                        <span class="detail-label">College Name</span>
                        <span class="detail-value"><?php echo e($profile['college_name'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Course Name</span>
                        <span class="detail-value"><?php echo e($profile['course_name'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Course Year</span>
                        <span class="detail-value"><?php echo e($profile['course_year'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Semester</span>
                        <span class="detail-value"><?php echo e($profile['semester'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Target Exam</span>
                        <span class="detail-value"><?php echo e($profile['target_exam'] ?? ''); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Exam Date</span>
                        <span class="detail-value"><?php echo formatDate($profile['exam_date'] ?? null); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Profile Created</span>
                        <span class="detail-value"><?php echo formatDate($profile['created_at'] ?? null); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- =====================================
         SUBJECT DETAILS
    ====================================== -->
    <section id="subjects" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-book-open me-2 text-info"></i>
            My Subjects
        </h2>

        <p class="section-subtitle">
            Subject information is loaded from subjects_data.
        </p>

        <?php if (count($subjects) > 0): ?>
            <div class="row g-3">

                <?php foreach ($subjects as $index => $subject): ?>
                    <?php
                    if (is_string($subject)) {
                        $subject = ['subject_name' => $subject];
                    }

                    $subjectName = getField(
                        $subject,
                        ['subject_name', 'subject', 'name', 'title'],
                        'Subject ' . ($index + 1)
                    );

                    $marks = getField(
                        $subject,
                        ['marks', 'obtained_marks', 'score', 'subject_marks'],
                        ''
                    );

                    $knowledge = getField(
                        $subject,
                        ['knowledge_level', 'knowledge', 'level', 'proficiency'],
                        ''
                    );

                    $remaining = getField(
                        $subject,
                        ['remaining_topics', 'remaining', 'topics_remaining', 'pending_topics'],
                        ''
                    );

                    $totalMarks = getField(
                        $subject,
                        ['total_marks', 'maximum_marks', 'max_marks'],
                        ''
                    );
                    ?>

                    <div class="col-md-6 col-xl-4">
                        <div class="item-card">
                            <div class="icon-box mb-3">
                                <i class="fa-solid fa-book"></i>
                            </div>

                            <h5><?php echo e($subjectName); ?></h5>

                            <p>
                                <strong>Marks:</strong>
                                <?php
                                if ($marks !== '') {
                                    echo e($marks);
                                    if ($totalMarks !== '') {
                                        echo ' / ' . e($totalMarks);
                                    }
                                } else {
                                    echo 'Not provided';
                                }
                                ?>
                            </p>

                            <p>
                                <strong>Knowledge Level:</strong>
                                <?php echo e($knowledge); ?>
                            </p>

                            <p class="mb-0">
                                <strong>Remaining Topics:</strong>
                                <?php
                                echo is_array($remaining)
                                    ? readableValue($remaining)
                                    : e($remaining);
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-book-open"></i>
                No subject details have been saved in your profile yet.
            </div>
        <?php endif; ?>

        <?php if (count($weakSubjects) > 0): ?>
            <div class="card-box mt-3">
                <h5 class="mb-3">
                    <i class="fa-solid fa-lightbulb text-warning me-2"></i>
                    Subjects Needing More Attention
                </h5>

                <?php foreach ($weakSubjects as $weak): ?>
                    <span class="pill">
                        <?php
                        echo e(is_array($weak)
                            ? getField($weak, ['subject_name', 'name', 'subject'], 'Subject')
                            : $weak);
                        ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================
         UPCOMING EXAMS
    ====================================== -->
    <section id="exams" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-calendar-days me-2 text-info"></i>
            Upcoming Exams
        </h2>

        <p class="section-subtitle">
            Examination records are loaded from upcoming_exams.
        </p>

        <?php if (count($upcomingExams) > 0): ?>
            <div class="row g-3">

                <?php foreach ($upcomingExams as $index => $exam): ?>
                    <?php
                    if (is_string($exam)) {
                        $exam = ['exam_name' => $exam];
                    }

                    $examName = getField(
                        $exam,
                        ['exam_name', 'name', 'title', 'subject'],
                        'Exam ' . ($index + 1)
                    );

                    $examDate = getField(
                        $exam,
                        ['exam_date', 'date', 'scheduled_date'],
                        ''
                    );

                    $examSubject = getField(
                        $exam,
                        ['subject', 'subject_name'],
                        ''
                    );

                    $examNotes = getField(
                        $exam,
                        ['description', 'notes', 'syllabus'],
                        ''
                    );
                    ?>

                    <div class="col-md-6 col-xl-4">
                        <div class="item-card">
                            <div class="icon-box mb-3">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>

                            <h5><?php echo e($examName); ?></h5>

                            <p>
                                <strong>Date:</strong>
                                <?php echo formatDate($examDate); ?>
                            </p>

                            <?php if ($examSubject !== ''): ?>
                                <p>
                                    <strong>Subject:</strong>
                                    <?php echo e($examSubject); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($examNotes !== ''): ?>
                                <p class="mb-0"><?php echo e($examNotes); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-calendar-xmark"></i>
                No upcoming exams have been added yet.
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================
         ASSIGNMENTS / PRACTICALS / PROJECTS
    ====================================== -->
    <section id="assignments" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-file-lines me-2 text-info"></i>
            Assignments & Deadlines
        </h2>

        <p class="section-subtitle">
            Records are loaded from assignment_deadlines.
        </p>

        <?php if (count($assignments) > 0): ?>
            <div class="row g-3">

                <?php foreach ($assignments as $index => $assignment): ?>
                    <?php
                    if (is_string($assignment)) {
                        $assignment = ['title' => $assignment];
                    }

                    $assignmentName = getField(
                        $assignment,
                        ['assignment_name', 'title', 'name', 'task_name'],
                        'Task ' . ($index + 1)
                    );

                    $assignmentType = getField(
                        $assignment,
                        ['type', 'category', 'task_type'],
                        ''
                    );

                    $assignmentSubject = getField(
                        $assignment,
                        ['subject', 'subject_name'],
                        ''
                    );

                    $deadline = getField(
                        $assignment,
                        ['deadline', 'due_date', 'submission_date', 'date'],
                        ''
                    );

                    $assignmentStatus = getField(
                        $assignment,
                        ['status'],
                        ''
                    );

                    $assignmentDescription = getField(
                        $assignment,
                        ['description', 'details', 'notes'],
                        ''
                    );
                    ?>

                    <div class="col-md-6 col-xl-4">
                        <div class="item-card">
                            <div class="icon-box mb-3">
                                <i class="fa-solid fa-file-circle-check"></i>
                            </div>

                            <h5><?php echo e($assignmentName); ?></h5>

                            <p>
                                <strong>Type:</strong>
                                <?php echo e($assignmentType); ?>
                            </p>

                            <p>
                                <strong>Subject:</strong>
                                <?php echo e($assignmentSubject); ?>
                            </p>

                            <p>
                                <strong>Deadline:</strong>
                                <?php echo formatDate($deadline); ?>
                            </p>

                            <?php if ($assignmentStatus !== ''): ?>
                                <span class="status">
                                    <?php echo e($assignmentStatus); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($assignmentDescription !== ''): ?>
                                <p class="mt-2 mb-0">
                                    <?php echo e($assignmentDescription); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-clipboard-check"></i>
                No assignments or deadlines have been added yet.
            </div>
        <?php endif; ?>
    </section>

    <!-- =====================================
         STUDY PREFERENCES
    ====================================== -->
    <section id="preferences" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-sliders me-2 text-info"></i>
            My Study Preferences
        </h2>

        <p class="section-subtitle">
            Your study routine and learning preferences.
        </p>

        <div class="row g-4">

            <div class="col-lg-6">
                <div class="card-box">
                    <h5 class="mb-3">Study Routine</h5>

                    <div class="detail-row">
                        <span class="detail-label">Daily Study Hours</span>
                        <span class="detail-value">
                            <?php echo displayValue($profile['study_hours'] ?? null, ' hours'); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Preferred Study Time</span>
                        <span class="detail-value">
                            <?php echo e($profile['study_time'] ?? ''); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Session Duration</span>
                        <span class="detail-value">
                            <?php echo displayValue($profile['session_duration'] ?? null, ' minutes'); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Break Duration</span>
                        <span class="detail-value">
                            <?php echo displayValue($profile['break_duration'] ?? null, ' minutes'); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Target Percentage</span>
                        <span class="detail-value">
                            <?php echo displayValue($profile['target_percentage'] ?? null, '%'); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Revision Preference</span>
                        <span class="detail-value">
                            <?php echo e($profile['revision_preference'] ?? ''); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-box mb-4">
                    <h5 class="mb-3">
                        <i class="fa-solid fa-brain text-info me-2"></i>
                        Preferred Study Methods
                    </h5>

                    <?php if (count($studyMethods) > 0): ?>
                        <?php foreach ($studyMethods as $method): ?>
                            <span class="pill">
                                <?php
                                echo e(is_array($method)
                                    ? getField($method, ['name', 'method', 'title'], 'Study method')
                                    : $method);
                                ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted mb-0">No study methods provided.</p>
                    <?php endif; ?>
                </div>

                <div class="card-box">
                    <h5 class="mb-3">
                        <i class="fa-solid fa-calendar-week text-info me-2"></i>
                        Available Days
                    </h5>

                    <?php if (count($availableDays) > 0): ?>
                        <?php foreach ($availableDays as $day): ?>
                            <span class="pill">
                                <?php
                                echo e(is_array($day)
                                    ? getField($day, ['day', 'name'], 'Day')
                                    : $day);
                                ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No available days provided.</p>
                    <?php endif; ?>

                    <hr style="border-color:rgba(255,255,255,.1)">

                    <h6>Weekly Availability</h6>

                    <?php
                    if ($weeklyAvailability !== '') {
                        $weeklyDecoded = json_decode($weeklyAvailability, true);

                        if (json_last_error() === JSON_ERROR_NONE
                            && is_array($weeklyDecoded)) {
                            echo '<pre class="text-light mb-0" style="white-space:pre-wrap;overflow-wrap:anywhere;">';
                            echo e(json_encode(
                                $weeklyDecoded,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                            ));
                            echo '</pre>';
                        } else {
                            echo '<p class="mb-0">' . e($weeklyAvailability) . '</p>';
                        }
                    } else {
                        echo '<p class="muted mb-0">No weekly availability provided.</p>';
                    }
                    ?>
                </div>
            </div>

        </div>
    </section>

    <!-- =====================================
         TODAY'S STUDY TASKS
    ====================================== -->
    <section id="tasks" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-list-check me-2 text-info"></i>
            Today's Study Tasks
        </h2>

        <p class="section-subtitle">
            <?php echo date('l, d M Y'); ?>
        </p>

        <div class="card-box">

            <?php if (count($todayTasks) > 0): ?>

                <?php foreach ($todayTasks as $task): ?>
                    <?php
                    $taskTitle = getField(
                        $task,
                        ['task_title', 'title', 'task_name', 'subject', 'description'],
                        'Study task'
                    );

                    $taskStatus = getField($task, ['status'], 'pending');

                    $startTime = getField($task, ['start_time'], '');
                    $endTime = getField($task, ['end_time'], '');

                    $taskDescription = getField(
                        $task,
                        ['task_description', 'details', 'notes'],
                        ''
                    );
                    ?>

                    <div class="task">
                        <div class="task-icon">
                            <?php if (strtolower((string)$taskStatus) === 'completed'): ?>
                                <i class="fa-solid fa-circle-check fa-lg"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-circle fa-lg"></i>
                            <?php endif; ?>
                        </div>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <h6 class="mb-1"><?php echo e($taskTitle); ?></h6>

                                <span class="status <?php
                                    echo strtolower((string)$taskStatus) === 'completed'
                                        ? 'completed'
                                        : '';
                                ?>">
                                    <?php echo e($taskStatus); ?>
                                </span>
                            </div>

                            <?php if ($startTime !== ''): ?>
                                <p class="muted small mb-1">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?php echo e($startTime); ?>

                                    <?php if ($endTime !== ''): ?>
                                        - <?php echo e($endTime); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($taskDescription !== ''): ?>
                                <p class="muted small mb-0">
                                    <?php echo e($taskDescription); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-mug-hot"></i>
                    <h5>No study tasks planned for today</h5>
                    <p class="mb-0">
                        Your dashboard will show tasks after a study plan
                        is saved for today's date.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <!-- =====================================
         REAL TASK PROGRESS
    ====================================== -->
    <section id="progress" class="mb-5">
        <h2 class="section-title">
            <i class="fa-solid fa-chart-line me-2 text-info"></i>
            Today's Progress
        </h2>

        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Task Completion</h5>
                    <p class="muted mb-0">
                        Based on today's saved study plan.
                    </p>
                </div>

                <h3 class="mb-0"><?php echo $progressPercentage; ?>%</h3>
            </div>

            <div class="progress">
                <div class="progress-bar"
                     role="progressbar"
                     style="width:<?php echo $progressPercentage; ?>%"
                     aria-valuenow="<?php echo $progressPercentage; ?>"
                     aria-valuemin="0"
                     aria-valuemax="100">
                </div>
            </div>

            <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                <span class="muted">
                    Completed: <?php echo $completedTasks; ?>
                </span>

                <span class="muted">
                    Total tasks: <?php echo $totalTasks; ?>
                </span>
            </div>

            <?php if ($totalTasks === 0): ?>
                <p class="muted small mt-3 mb-0">
                    Progress will appear when today's tasks are available.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <div class="footer-note">
        AI Study Planner &copy; <?php echo date('Y'); ?>
        | Stay consistent. Keep learning.
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
