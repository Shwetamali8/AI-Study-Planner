<?php
session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "study_planner";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
| Update this section if your login system uses another session key.
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| FETCH STUDENT PROFILE
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare(
    "SELECT * FROM student_profilenew
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to prepare profile query. Check your table columns.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    die(
        "Student profile not found. Please complete your profile first."
    );
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/
function h($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function decodeJsonArray($value) {
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function firstAvailable($row, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return $row[$key];
        }
    }

    return $default;
}

/*
|--------------------------------------------------------------------------
| READ SUBJECTS
|--------------------------------------------------------------------------
| Expected subjects_data example:
|
| [
|   {
|     "name": "Java Programming",
|     "marks": 94,
|     "knowledge": "Good",
|     "topics": ["OOP", "Inheritance", "Collections"]
|   }
| ]
*/
$subjectsData = decodeJsonArray(
    $profile['subjects_data'] ?? ''
);

/*
|--------------------------------------------------------------------------
| READ EXAMS AND ASSIGNMENTS
|--------------------------------------------------------------------------
*/
$exams = decodeJsonArray(
    $profile['upcoming_exams'] ?? ''
);

$assignments = decodeJsonArray(
    $profile['assignment_deadlines'] ?? ''
);

$weakSubjects = array_filter(
    array_map(
        'trim',
        explode(
            ',',
            (string)($profile['weak_subjects'] ?? '')
        )
    )
);

/*
|--------------------------------------------------------------------------
| NORMALIZE SUBJECT DATA
|--------------------------------------------------------------------------
*/
$subjects = [];

foreach ($subjectsData as $index => $item) {

    // Support both object-style and simple string subjects.
    if (is_string($item)) {
        $item = ['name' => $item];
    }

    if (!is_array($item)) {
        continue;
    }

    $name = trim((string)firstAvailable(
        $item,
        ['name', 'subject', 'subject_name', 'title'],
        ''
    ));

    if ($name === '') {
        continue;
    }

    $topics = decodeJsonArray(
        $item['topics']
        ?? $item['syllabus']
        ?? $item['chapters']
        ?? $item['units']
        ?? []
    );

    $normalizedTopics = [];

    foreach ($topics as $topic) {
        if (is_string($topic)) {
            $normalizedTopics[] = [
                'title' => $topic,
                'status' => 'pending'
            ];
        } elseif (is_array($topic)) {
            $topicTitle = firstAvailable(
                $topic,
                ['title', 'name', 'topic', 'chapter'],
                ''
            );

            if ($topicTitle !== '') {
                $normalizedTopics[] = [
                    'title' => $topicTitle,
                    'status' => (
                        strtolower(
                            (string)($topic['status'] ?? '')
                        ) === 'completed'
                    ) ? 'completed' : 'pending'
                ];
            }
        }
    }

    $marks = $item['marks'] ?? null;

    if ($marks === '' || !is_numeric($marks)) {
        $marks = null;
    } else {
        $marks = max(0, min(100, (float)$marks));
    }

    $knowledge = firstAvailable(
        $item,
        ['knowledge', 'knowledge_level', 'level'],
        'Not provided'
    );

    $isWeak = false;

    foreach ($weakSubjects as $weak) {
        if (strcasecmp($weak, $name) === 0) {
            $isWeak = true;
            break;
        }
    }

    $subjects[] = [
        'name' => $name,
        'marks' => $marks,
        'knowledge' => $knowledge,
        'topics' => $normalizedTopics,
        'weak' => $isWeak
    ];
}

/*
|--------------------------------------------------------------------------
| TOPIC COMPLETION STORAGE
|--------------------------------------------------------------------------
| Create the table once. Completion records are linked to user ID,
| subject name, and topic name so students have separate progress.
*/
$conn->query("
    CREATE TABLE IF NOT EXISTS syllabus_topic_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        topic_name VARCHAR(500) NOT NULL,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_topic
            (user_id, subject_name, topic_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/*
|--------------------------------------------------------------------------
| UPDATE TOPIC COMPLETION
|--------------------------------------------------------------------------
*/
$message = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_topic'])
) {
    $subjectName = trim(
        (string)($_POST['subject_name'] ?? '')
    );

    $topicName = trim(
        (string)($_POST['topic_name'] ?? '')
    );

    $completed = isset($_POST['completed']) ? 1 : 0;

    // Only permit subjects and topics belonging to this profile.
    $validTopic = false;

    foreach ($subjects as $subject) {
        if ($subject['name'] !== $subjectName) {
            continue;
        }

        foreach ($subject['topics'] as $topic) {
            if ($topic['title'] === $topicName) {
                $validTopic = true;
                break 2;
            }
        }
    }

    if (
        $validTopic
        && $subjectName !== ''
        && $topicName !== ''
    ) {
        $save = $conn->prepare("
            INSERT INTO syllabus_topic_progress
                (user_id, subject_name, topic_name, is_completed)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_completed = VALUES(is_completed)
        ");

        $save->bind_param(
            "issi",
            $userId,
            $subjectName,
            $topicName,
            $completed
        );

        $save->execute();
        $save->close();
    }

    // Prevent duplicate submission when refreshing.
    $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $redirectUrl);
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD SAVED TOPIC COMPLETION
|--------------------------------------------------------------------------
*/
$completedTopics = [];

$progressStmt = $conn->prepare("
    SELECT subject_name, topic_name, is_completed
    FROM syllabus_topic_progress
    WHERE user_id = ?
");

$progressStmt->bind_param("i", $userId);
$progressStmt->execute();

$progressResult = $progressStmt->get_result();

while ($row = $progressResult->fetch_assoc()) {
    $key = $row['subject_name'] . '||' . $row['topic_name'];
    $completedTopics[$key] = (int)$row['is_completed'];
}

$progressStmt->close();

/*
|--------------------------------------------------------------------------
| CALCULATE SUBJECT AND TOPIC PROGRESS
|--------------------------------------------------------------------------
*/
$totalTopics = 0;
$completedCount = 0;

foreach ($subjects as &$subject) {
    $subjectCompleted = 0;

    foreach ($subject['topics'] as &$topic) {
        $key = $subject['name'] . '||' . $topic['title'];

        if (isset($completedTopics[$key])) {
            $topic['status'] = $completedTopics[$key]
                ? 'completed'
                : 'pending';
        }

        $totalTopics++;

        if ($topic['status'] === 'completed') {
            $completedCount++;
            $subjectCompleted++;
        }
    }

    unset($topic);

    $subject['completed_topics'] = $subjectCompleted;
    $subject['total_topics'] = count($subject['topics']);

    $subject['progress'] = $subject['total_topics'] > 0
        ? round(
            ($subjectCompleted / $subject['total_topics']) * 100
        )
        : 0;
}

unset($subject);

$overallProgress = $totalTopics > 0
    ? round(($completedCount / $totalTopics) * 100)
    : 0;

$studentName = firstAvailable(
    $profile,
    ['user_name', 'student_name', 'name'],
    'Student'
);

$course = firstAvailable(
    $profile,
    ['class_name', 'course', 'education_level'],
    'Student'
);

$targetExam = firstAvailable(
    $profile,
    ['target_exam', 'exam_name'],
    'Not specified'
);

$examDate = firstAvailable(
    $profile,
    ['exam_date'],
    ''
);

$studyTime = firstAvailable(
    $profile,
    ['study_time'],
    'Not specified'
);

$studyHours = firstAvailable(
    $profile,
    ['study_hours'],
    'Not specified'
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Subjects & Syllabus | AI Study Planner</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
      rel="stylesheet">

<style>
:root {
    --primary: #6554f0;
    --primary-dark: #4d3be0;
    --sidebar: #10213d;
    --page-bg: #f4f6fc;
    --text: #172b4d;
    --muted: #7181a0;
    --border: #e3e9f7;
    --green: #10a879;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--page-bg);
    color: var(--text);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 250px;
    background: linear-gradient(180deg, #122542, #0c1930);
    color: #fff;
    padding: 24px 15px;
    z-index: 10;
    overflow-y: auto;
}

.brand {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 0 8px 28px;
}

.brand-icon {
    width: 45px;
    height: 45px;
    border-radius: 14px;
    background: var(--primary);
    display: grid;
    place-items: center;
    font-size: 22px;
}

.brand strong {
    font-size: 17px;
}

.brand small {
    display: block;
    color: #aebbd5;
    margin-top: 4px;
}

.nav-link-custom {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 14px;
    color: #d8e2f5;
    text-decoration: none;
    border-radius: 10px;
    margin-bottom: 7px;
    font-size: 14px;
    transition: .2s;
}

.nav-link-custom:hover,
.nav-link-custom.active {
    background: var(--primary);
    color: white;
}

.sidebar-bottom {
    margin-top: 35px;
}

.main {
    margin-left: 250px;
    padding: 28px;
    min-height: 100vh;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 26px;
    flex-wrap: wrap;
}

.topbar h1 {
    font-size: 27px;
    font-weight: 750;
    margin-bottom: 6px;
}

.subtitle {
    color: var(--muted);
    font-size: 14px;
}

.profile-chip {
    background: white;
    padding: 12px 17px;
    border: 1px solid var(--border);
    border-radius: 14px;
    font-size: 13px;
}

.card-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 17px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 4px 18px rgba(32, 54, 100, .025);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 20px;
    min-width: 0;
}

.stat-icon {
    width: 43px;
    height: 43px;
    border-radius: 13px;
    background: #efedff;
    color: var(--primary);
    display: grid;
    place-items: center;
    font-size: 19px;
    margin-bottom: 15px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

.stat-number {
    font-size: 27px;
    font-weight: 750;
    margin-top: 4px;
    overflow-wrap: anywhere;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.section-heading h2 {
    font-size: 19px;
    font-weight: 750;
    margin: 0;
}

.btn-primary-custom {
    background: var(--primary);
    color: white;
    border: 0;
    border-radius: 10px;
    padding: 11px 16px;
    font-weight: 650;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
}

.btn-primary-custom:hover {
    background: var(--primary-dark);
    color: white;
}

.btn-outline-custom {
    background: white;
    border: 1px solid #d9d3ff;
    color: var(--primary);
    border-radius: 10px;
    padding: 9px 14px;
    text-decoration: none;
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.subject-card {
    border: 1px solid var(--border);
    background: white;
    border-radius: 16px;
    padding: 20px;
    transition: transform .2s, box-shadow .2s;
    min-width: 0;
}

.subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(32, 54, 100, .07);
}

.subject-top {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.subject-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #efedff;
    color: var(--primary);
    display: grid;
    place-items: center;
    flex-shrink: 0;
    font-size: 20px;
}

.subject-name {
    font-size: 17px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

.subject-meta {
    font-size: 12px;
    color: var(--muted);
    margin-top: 5px;
}

.badge-weak {
    background: #fff0f0;
    color: #d84a4a;
    border-radius: 20px;
    padding: 6px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.badge-normal {
    background: #edf9f4;
    color: #09875d;
    border-radius: 20px;
    padding: 6px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.progress {
    height: 8px;
    background: #edf0f8;
    border-radius: 20px;
    margin-top: 10px;
}

.progress-bar {
    background: linear-gradient(90deg, #7663ff, #9d75ff);
    border-radius: 20px;
}

.subject-footer {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 17px;
    color: var(--muted);
    font-size: 12px;
    flex-wrap: wrap;
}

.topic-list {
    margin-top: 18px;
    border-top: 1px solid var(--border);
    padding-top: 13px;
}

.topic-row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid #f0f2f8;
    font-size: 13px;
}

.topic-row:last-child {
    border-bottom: 0;
}

.topic-row label {
    display: flex;
    align-items: center;
    gap: 9px;
    cursor: pointer;
    min-width: 0;
    overflow-wrap: anywhere;
}

.topic-row input {
    accent-color: var(--primary);
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.topic-completed {
    color: #8995aa;
    text-decoration: line-through;
}

.topic-save {
    border: 0;
    background: #f0eeff;
    color: var(--primary);
    border-radius: 7px;
    padding: 5px 8px;
    font-size: 11px;
    flex-shrink: 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.info-tile {
    background: #f8f9fe;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 15px;
    min-width: 0;
}

.info-tile small {
    display: block;
    color: var(--muted);
    margin-bottom: 7px;
}

.info-tile strong {
    overflow-wrap: anywhere;
}

.empty-state {
    text-align: center;
    padding: 45px 20px;
    color: var(--muted);
}

.empty-state i {
    font-size: 40px;
    color: #b4a9ff;
    margin-bottom: 15px;
}

.exam-item {
    padding: 13px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
}

.exam-item:last-child {
    border-bottom: 0;
}

@media (max-width: 1100px) {
    .sidebar {
        width: 215px;
    }

    .main {
        margin-left: 215px;
        padding: 20px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .sidebar {
        position: static;
        width: 100%;
        padding: 15px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .sidebar nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 5px;
    }

    .nav-link-custom {
        padding: 10px;
        font-size: 12px;
        margin: 0;
    }

    .main {
        margin-left: 0;
        padding: 16px;
    }

    .topbar h1 {
        font-size: 23px;
    }

    .subject-grid,
    .info-grid {
        grid-template-columns: 1fr;
    }

    .card-box {
        padding: 16px;
    }
}

@media (max-width: 420px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-card {
        padding: 13px;
    }

    .stat-number {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<aside class="sidebar">

    <div class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-brain"></i>
        </div>

        <div>
            <strong>AI Study Planner</strong>
            <small>Plan • Learn • Grow</small>
        </div>
    </div>

    <nav>
        <a class="nav-link-custom" href="newdashboard.php">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>

        <a class="nav-link-custom" href="view_study_plan.php">
            <i class="fa-regular fa-calendar"></i> My Study Plan
        </a>

        <a class="nav-link-custom active" href="subjects_syllabus.php">
            <i class="fa-solid fa-book-open"></i> Subjects & Syllabus
        </a>

        <a class="nav-link-custom" href="progress.php">
            <i class="fa-solid fa-chart-line"></i> Progress & Reports
        </a>

        <a class="nav-link-custom" href="ai_recommendations.php">
            <i class="fa-solid fa-wand-magic-sparkles"></i> AI Recommendations
        </a>

        <a class="nav-link-custom" href="profile_setup.php">
            <i class="fa-regular fa-user"></i> Profile Settings
        </a>
    </nav>

    <div class="sidebar-bottom">
        <a class="nav-link-custom" href="../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>

<!-- MAIN CONTENT -->
<main class="main">

    <header class="topbar">
        <div>
            <h1>Subjects & Syllabus 📚</h1>
            <div class="subtitle">
                Manage your subjects, review your syllabus and track
                your learning progress.
            </div>
        </div>

        <div class="profile-chip">
            <i class="fa-regular fa-user me-2"></i>
            <strong><?php echo h($studentName); ?></strong>
            <div class="subtitle mt-1">
                <?php echo h($course); ?>
            </div>
        </div>
    </header>

    <!-- SUMMARY CARDS -->
    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-book"></i>
            </div>
            <div class="stat-label">My Subjects</div>
            <div class="stat-number">
                <?php echo count($subjects); ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div class="stat-label">Syllabus Topics</div>
            <div class="stat-number">
                <?php echo $totalTopics; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="stat-label">Topics Completed</div>
            <div class="stat-number">
                <?php echo $completedCount; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="stat-label">Syllabus Progress</div>
            <div class="stat-number">
                <?php echo $overallProgress; ?>%
            </div>
            <div class="progress">
                <div class="progress-bar"
                     style="width: <?php echo $overallProgress; ?>%">
                </div>
            </div>
        </div>

    </section>

    <!-- STUDENT STUDY PREFERENCES -->
    <section class="card-box">

        <div class="section-heading">
            <h2>
                <i class="fa-solid fa-user-graduate me-2"
                   style="color:var(--primary)"></i>
                My Learning Profile
            </h2>
        </div>

        <div class="info-grid">
            <div class="info-tile">
                <small>Target Exam</small>
                <strong><?php echo h($targetExam); ?></strong>
            </div>

            <div class="info-tile">
                <small>Preferred Study Time</small>
                <strong><?php echo h($studyTime); ?></strong>
            </div>

            <div class="info-tile">
                <small>Daily Study Hours</small>
                <strong>
                    <?php
                    echo is_numeric($studyHours)
                        ? h($studyHours) . ' hours'
                        : h($studyHours);
                    ?>
                </strong>
            </div>
        </div>

        <?php if ($examDate !== ''): ?>
            <div class="subtitle mt-3">
                <i class="fa-regular fa-calendar me-1"></i>
                Exam date: <?php echo h($examDate); ?>
            </div>
        <?php endif; ?>

    </section>

    <!-- SUBJECT LIST -->
    <section class="card-box">

        <div class="section-heading">
            <div>
                <h2>
                    <i class="fa-solid fa-book-open me-2"
                       style="color:var(--primary)"></i>
                    My Subjects
                </h2>

                <div class="subtitle mt-2">
                    Subjects loaded from your saved student profile.
                </div>
            </div>

            <a href="view_study_plan.php"
               class="btn-primary-custom">
                <i class="fa-solid fa-calendar-days me-2"></i>
                View Study Plan
            </a>
        </div>

        <?php if (empty($subjects)): ?>

            <div class="empty-state">
                <i class="fa-solid fa-book-open d-block"></i>

                <h5>No subjects found</h5>

                <p>
                    Your profile does not currently contain readable
                    subject records in the subjects_data field.
                </p>

                <p class="small">
                    Open Profile Settings and save your subjects.
                    If you have stored a single subject in a separate
                    column, update the subject-fetching logic to read it.
                </p>

                <a href="profile_setup.php"
                   class="btn-primary-custom">
                    Update Profile
                </a>
            </div>

        <?php else: ?>

            <div class="subject-grid">

                <?php foreach ($subjects as $index => $subject): ?>

                    <article class="subject-card">

                        <div class="subject-top">

                            <div class="subject-icon">
                                <i class="fa-solid fa-book-bookmark"></i>
                            </div>

                            <div class="flex-grow-1">

                                <div class="subject-name">
                                    <?php echo h($subject['name']); ?>
                                </div>

                                <div class="subject-meta">
                                    Knowledge:
                                    <?php echo h($subject['knowledge']); ?>
                                </div>

                            </div>

                            <?php if ($subject['weak']): ?>
                                <span class="badge-weak">
                                    Weak Subject
                                </span>
                            <?php elseif (
                                $subject['marks'] !== null
                                && $subject['marks'] >= 75
                            ): ?>
                                <span class="badge-normal">
                                    Good Marks
                                </span>
                            <?php endif; ?>

                        </div>

                        <div class="row g-3 mt-2">

                            <div class="col-6">
                                <div class="subtitle">Marks</div>

                                <strong>
                                    <?php
                                    echo $subject['marks'] !== null
                                        ? h($subject['marks']) . '%'
                                        : 'Not provided';
                                    ?>
                                </strong>
                            </div>

                            <div class="col-6">
                                <div class="subtitle">Topics</div>

                                <strong>
                                    <?php
                                    echo $subject['completed_topics']
                                        . ' / '
                                        . $subject['total_topics'];
                                    ?>
                                </strong>
                            </div>

                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <small class="subtitle">
                                Syllabus completion
                            </small>

                            <strong>
                                <?php echo $subject['progress']; ?>%
                            </strong>
                        </div>

                        <div class="progress">
                            <div class="progress-bar"
                                 style="width: <?php
                                    echo $subject['progress'];
                                 ?>%">
                            </div>
                        </div>

                        <div class="topic-list">

                            <strong class="small">
                                Syllabus Topics
                            </strong>

                            <?php if (empty($subject['topics'])): ?>

                                <p class="subtitle mt-3 mb-0">
                                    No syllabus topics were saved for
                                    this subject.
                                </p>

                            <?php else: ?>

                                <?php foreach (
                                    $subject['topics'] as $topic
                                ): ?>

                                    <form method="POST"
                                          class="topic-row">

                                        <input type="hidden"
                                               name="subject_name"
                                               value="<?php
                                                   echo h($subject['name']);
                                               ?>">

                                        <input type="hidden"
                                               name="topic_name"
                                               value="<?php
                                                   echo h($topic['title']);
                                               ?>">

                                        <label>
                                            <input type="checkbox"
                                                   name="completed"
                                                   value="1"
                                                   <?php
                                                   echo $topic['status']
                                                       === 'completed'
                                                       ? 'checked'
                                                       : '';
                                                   ?>
                                                   onchange="
                                                       this.form.submit()
                                                   ">

                                            <span class="<?php
                                                echo $topic['status']
                                                    === 'completed'
                                                    ? 'topic-completed'
                                                    : '';
                                            ?>">
                                                <?php
                                                echo h($topic['title']);
                                                ?>
                                            </span>
                                        </label>

                                        <input type="hidden"
                                               name="save_topic"
                                               value="1">

                                        <noscript>
                                            <button type="submit"
                                                    class="topic-save">
                                                Save
                                            </button>
                                        </noscript>

                                    </form>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- EXAMS -->
    <section class="card-box">

        <div class="section-heading">
            <h2>
                <i class="fa-solid fa-clock me-2"
                   style="color:var(--primary)"></i>
                Upcoming Exams
            </h2>
        </div>

        <?php if (empty($exams)): ?>

            <p class="subtitle mb-0">
                No upcoming exams have been saved in your profile.
            </p>

        <?php else: ?>

            <?php foreach ($exams as $exam): ?>

                <?php
                if (is_string($exam)) {
                    $exam = ['name' => $exam];
                }

                if (!is_array($exam)) {
                    continue;
                }

                $examName = firstAvailable(
                    $exam,
                    ['name', 'subject', 'title'],
                    'Exam'
                );

                $examDay = firstAvailable(
                    $exam,
                    ['date', 'exam_date'],
                    'Date not provided'
                );
                ?>

                <div class="exam-item">
                    <strong><?php echo h($examName); ?></strong>
                    <span class="subtitle">
                        <?php echo h($examDay); ?>
                    </span>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <!-- ASSIGNMENTS -->
    <section class="card-box">

        <div class="section-heading">
            <h2>
                <i class="fa-solid fa-file-pen me-2"
                   style="color:var(--primary)"></i>
                Assignment Deadlines
            </h2>
        </div>

        <?php if (empty($assignments)): ?>

            <p class="subtitle mb-0">
                No assignment deadlines have been saved in your profile.
            </p>

        <?php else: ?>

            <?php foreach ($assignments as $assignment): ?>

                <?php
                if (is_string($assignment)) {
                    $assignment = ['title' => $assignment];
                }

                if (!is_array($assignment)) {
                    continue;
                }

                $assignmentTitle = firstAvailable(
                    $assignment,
                    ['title', 'name', 'assignment'],
                    'Assignment'
                );

                $assignmentDate = firstAvailable(
                    $assignment,
                    ['date', 'deadline', 'due_date'],
                    'Date not provided'
                );
                ?>

                <div class="exam-item">
                    <strong>
                        <?php echo h($assignmentTitle); ?>
                    </strong>

                    <span class="subtitle">
                        <?php echo h($assignmentDate); ?>
                    </span>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <footer class="text-center subtitle py-3">
        AI Study Planner • Plan better, learn smarter.
    </footer>

</main>

</body>
</html>
