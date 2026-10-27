<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

/* =====================================================
   1. DATABASE CONNECTION
===================================================== */

$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "study_planner";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Database connection failed. Please check db_config.php settings.");
}

$conn->set_charset("utf8mb4");

/* =====================================================
   2. LOGIN SESSION
===================================================== */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* =====================================================
   3. CREATE TABLES FOR TASK STATUS AND QUIZ SCORES
===================================================== */

$conn->query("
CREATE TABLE IF NOT EXISTS planner_task_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    study_date DATE NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    topic VARCHAR(255) NOT NULL,
    status ENUM('Pending','Completed') NOT NULL DEFAULT 'Pending',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_task
        (user_id, study_date, subject_name, topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS planner_quiz_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    topic VARCHAR(255) DEFAULT '',
    score DECIMAL(5,2) NOT NULL,
    total_marks DECIMAL(5,2) NOT NULL,
    quiz_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* =====================================================
   4. HELPER FUNCTIONS
===================================================== */

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

function minutesToTime($minutes) {
    $minutes = max(0, (int)$minutes);
    $hour = (int) floor($minutes / 60);
    $minute = $minutes % 60;

    return sprintf("%02d:%02d", $hour, $minute);
}

function formatTime($time) {
    return date("h:i A", strtotime($time));
}

function displayDate($date) {
    return date("d M Y", strtotime($date));
}

function getTopics($subject) {
    $topics = [
        'Mathematics' => ['Concept revision', 'Solved examples', 'Practice questions'],
        'Maths' => ['Concept revision', 'Solved examples', 'Practice questions'],
        'Java Programming' => ['Core concepts', 'Programs and examples', 'Coding practice'],
        'Data Structures' => ['Fundamentals', 'Trees and graphs', 'Practice problems'],
        'DBMS' => ['Database concepts', 'SQL queries', 'Normalization'],
        'Software Engineering' => ['Software process models', 'SDLC concepts', 'Revision questions'],
        'Computer Networks' => ['Network fundamentals', 'Protocols', 'Practice questions'],
        'English' => ['Grammar revision', 'Reading comprehension', 'Practice exercises'],
        'Computer Science' => ['Core concepts', 'Problem solving', 'Revision questions'],
        'Data Science' => ['Theory and concepts', 'Practical exercises', 'Revision questions'],
        'AIML' => ['AI fundamentals', 'Machine learning concepts', 'Practice questions']
    ];

    foreach ($topics as $name => $list) {
        if (stripos($subject, $name) !== false ||
            stripos($name, $subject) !== false) {
            return $list;
        }
    }

    return [
        'Learn core concepts',
        'Practice questions',
        'Revision and self-test'
    ];
}

/* =====================================================
   5. FETCH THE LOGGED-IN STUDENT PROFILE

   IMPORTANT:
   This query uses the user_id column in student_profilenew.
===================================================== */

$stmt = $conn->prepare("
    SELECT *
    FROM student_profilenew
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die(
        "Student profile not found. Please complete your profile first."
    );
}

/* =====================================================
   6. READ STUDENT PREFERENCES
===================================================== */

$studentName = $_SESSION['user_name']
    ?? $_SESSION['username']
    ?? 'Student';

$studyHours = (int)($student['study_hours'] ?? 2);
$studyHours = max(1, min(12, $studyHours));

$sessionDuration = (int)($student['session_duration'] ?? 45);
$sessionDuration = in_array(
    $sessionDuration, [25, 45, 60, 90], true
) ? $sessionDuration : 45;

$breakDuration = (int)($student['break_duration'] ?? 10);
$breakDuration = max(5, min(30, $breakDuration));

$studyTime = $student['study_time'] ?? 'Morning';
$weakSubjects = trim($student['weak_subjects'] ?? '');

$studyMethods = decodeJsonArray(
    $student['study_methods'] ?? '[]'
);

$availableDays = decodeJsonArray(
    $student['available_days'] ?? '[]'
);

$weeklyAvailability = decodeJsonArray(
    $student['weekly_availability'] ?? '{}'
);

$subjectRecords = decodeJsonArray(
    $student['subjects_data'] ?? '[]'
);

$upcomingExams = decodeJsonArray(
    $student['upcoming_exams'] ?? '[]'
);

$assignmentDeadlines = decodeJsonArray(
    $student['assignment_deadlines'] ?? '[]'
);

$examDate = $student['exam_date'] ?? '';

/* =====================================================
   7. EXTRACT SUBJECT NAMES FROM subjects_data

   Expected example:
   [
       {"name":"Java Programming","marks":94,"knowledge":"Good"},
       {"name":"Data Science","marks":75,"knowledge":"Average"}
   ]
===================================================== */

$subjects = [];

foreach ($subjectRecords as $record) {
    if (is_string($record)) {
        $record = ['name' => $record];
    }

    if (!is_array($record)) {
        continue;
    }

    $name = trim(
        $record['name']
        ?? $record['subject_name']
        ?? $record['subject']
        ?? ''
    );

    if ($name === '') {
        continue;
    }

    $subjects[] = [
        'name' => $name,
        'marks' => $record['marks'] ?? null,
        'knowledge' => $record['knowledge'] ?? '',
        'topics' => $record['topics'] ?? []
    ];
}

/* Use weak_subjects as a fallback only if subjects_data is empty. */
if (empty($subjects) && $weakSubjects !== '') {
    $weakList = preg_split('/[,;]+/', $weakSubjects);

    foreach ($weakList as $name) {
        $name = trim($name);

        if ($name !== '') {
            $subjects[] = [
                'name' => $name,
                'marks' => null,
                'knowledge' => '',
                'topics' => []
            ];
        }
    }
}

/* Remove duplicate subject names. */
$uniqueSubjects = [];

foreach ($subjects as $subject) {
    $key = strtolower($subject['name']);
    $uniqueSubjects[$key] = $subject;
}

$subjects = array_values($uniqueSubjects);

/* =====================================================
   8. HANDLE DATE AND TAB SELECTION
===================================================== */

$today = date('Y-m-d');

$selectedDate = $_GET['date'] ?? $today;

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (!$dateObject ||
    $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = $today;
}

$tab = $_GET['tab'] ?? 'today';

$allowedTabs = ['today', 'week', 'quiz', 'progress'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'today';
}

$weekStart = date(
    'Y-m-d',
    strtotime('monday this week', strtotime($selectedDate))
);

$weekEnd = date(
    'Y-m-d',
    strtotime('sunday this week', strtotime($selectedDate))
);

/* =====================================================
   9. TASK COMPLETION HANDLER
===================================================== */

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_task') {

        $taskDate = $_POST['study_date'] ?? $today;
        $subjectName = trim($_POST['subject_name'] ?? '');
        $topic = trim($_POST['topic'] ?? '');
        $newStatus = $_POST['status'] ?? 'Completed';

        $validDate = DateTime::createFromFormat('!Y-m-d', $taskDate);

        if (!$validDate ||
            $validDate->format('Y-m-d') !== $taskDate ||
            $subjectName === '' ||
            $topic === '' ||
            !in_array($newStatus, ['Pending', 'Completed'], true)) {

            $error = 'Invalid task information. Please try again.';

        } else {

            $check = $conn->prepare("
                SELECT id
                FROM planner_task_status
                WHERE user_id = ?
                  AND study_date = ?
                  AND subject_name = ?
                  AND topic = ?
                LIMIT 1
            ");

            $check->bind_param(
                "isss",
                $userId,
                $taskDate,
                $subjectName,
                $topic
            );

            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {

                $update = $conn->prepare("
                    UPDATE planner_task_status
                    SET status = ?
                    WHERE user_id = ?
                      AND study_date = ?
                      AND subject_name = ?
                      AND topic = ?
                ");

                $update->bind_param(
                    "sisss",
                    $newStatus,
                    $userId,
                    $taskDate,
                    $subjectName,
                    $topic
                );

                $update->execute();
                $update->close();

            } else {

                $insert = $conn->prepare("
                    INSERT INTO planner_task_status
                    (user_id, study_date, subject_name, topic, status)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $insert->bind_param(
                    "issss",
                    $userId,
                    $taskDate,
                    $subjectName,
                    $topic,
                    $newStatus
                );

                $insert->execute();
                $insert->close();
            }

            header(
                "Location: view_study_plan.php?tab="
                . urlencode($tab)
                . "&date="
                . urlencode($selectedDate)
                . "&saved=1"
            );

            exit;
        }
    }

    /* =================================================
       10. QUIZ SCORE HANDLER
    ================================================= */

    if ($action === 'save_quiz') {

        $quizSubject = trim($_POST['quiz_subject'] ?? '');
        $quizTopic = trim($_POST['quiz_topic'] ?? '');
        $score = filter_var($_POST['score'] ?? null, FILTER_VALIDATE_FLOAT);
        $total = filter_var($_POST['total_marks'] ?? null, FILTER_VALIDATE_FLOAT);
        $quizDate = $_POST['quiz_date'] ?? $today;

        $validDate = DateTime::createFromFormat('!Y-m-d', $quizDate);

        if ($quizSubject === '' ||
            $score === false ||
            $total === false ||
            $total <= 0 ||
            $score < 0 ||
            $score > $total ||
            !$validDate ||
            $validDate->format('Y-m-d') !== $quizDate) {

            $error = 'Please enter a valid subject, date and quiz score.';

        } else {

            $insert = $conn->prepare("
                INSERT INTO planner_quiz_scores
                (user_id, subject_name, topic, score, total_marks, quiz_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $insert->bind_param(
                "issdds",
                $userId,
                $quizSubject,
                $quizTopic,
                $score,
                $total,
                $quizDate
            );

            $insert->execute();
            $insert->close();

            header(
                "Location: view_study_plan.php?tab=quiz&date="
                . urlencode($selectedDate)
                . "&quiz_saved=1"
            );

            exit;
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Task status updated successfully.';
}

if (isset($_GET['quiz_saved'])) {
    $message = 'Quiz score saved successfully.';
}

/* =====================================================
   11. GENERATE A PERSONALIZED TIMETABLE

   The timetable is generated from the saved profile.
   It does not require an existing study_plans table.
===================================================== */

function generateTimetable(
    $subjects,
    $studyHours,
    $sessionDuration,
    $breakDuration,
    $studyTime,
    $selectedDate,
    $availableDays,
    $weeklyAvailability,
    $weakSubjects,
    $studyMethods
) {
    $sessions = [];

    if (empty($subjects)) {
        return [];
    }

    $dayName = date('l', strtotime($selectedDate));

    if (!empty($availableDays) &&
        !in_array($dayName, $availableDays, true)) {
        return [];
    }

    $minutesAvailable = $studyHours * 60;

    if (isset($weeklyAvailability[$dayName])) {
        $dailyHours = (float)$weeklyAvailability[$dayName];

        if ($dailyHours > 0) {
            $minutesAvailable = min(
                $minutesAvailable,
                (int)round($dailyHours * 60)
            );
        }
    }

    if ($minutesAvailable <= 0) {
        return [];
    }

    $weakNames = array_map(
        'trim',
        preg_split('/[,;]+/', strtolower($weakSubjects))
    );

    $startTimes = [
        'Morning' => '07:00',
        'Afternoon' => '13:00',
        'Evening' => '17:00',
        'Night' => '19:00'
    ];

    $startTime = $startTimes[$studyTime] ?? '07:00';

    $cursor = (int) (
        substr($startTime, 0, 2) * 60
        + substr($startTime, 3, 2)
    );

    $remaining = $minutesAvailable;
    $index = 0;
    $subjectCount = count($subjects);

    /*
     * Prioritize explicitly listed weak subjects.
     * Rotate through the remaining subjects afterward.
     */
    usort($subjects, function ($a, $b) use ($weakNames) {
        $aWeak = false;
        $bWeak = false;

        foreach ($weakNames as $weak) {
            if ($weak !== '' &&
                stripos($a['name'], $weak) !== false) {
                $aWeak = true;
            }

            if ($weak !== '' &&
                stripos($b['name'], $weak) !== false) {
                $bWeak = true;
            }
        }

        return (int)$bWeak <=> (int)$aWeak;
    });

    while ($remaining >= 15 && $index < 30) {

        $subject = $subjects[$index % $subjectCount];

        $duration = min($sessionDuration, $remaining);

        if ($duration < 15) {
            break;
        }

        $subjectName = $subject['name'];

        $topics = !empty($subject['topics'])
            ? $subject['topics']
            : getTopics($subjectName);

        if (!is_array($topics) || empty($topics)) {
            $topics = getTopics($subjectName);
        }

        $topic = (string)$topics[
            (int)floor($index / max(1, $subjectCount))
            % count($topics)
        ];

        $start = minutesToTime($cursor);
        $end = minutesToTime($cursor + $duration);

        $sessions[] = [
            'date' => $selectedDate,
            'start' => $start,
            'end' => $end,
            'subject' => $subjectName,
            'topic' => $topic,
            'type' => (
                !empty($studyMethods)
                ? $studyMethods[$index % count($studyMethods)]
                : 'Study'
            ),
            'duration' => $duration
        ];

        $cursor += $duration;
        $remaining -= $duration;

        if ($remaining >= $breakDuration + 15) {
            $cursor += $breakDuration;
            $remaining -= $breakDuration;
        }

        $index++;
    }

    return $sessions;
}

/* =====================================================
   12. GET SAVED TASK STATUSES
===================================================== */

$taskStatuses = [];

$taskQuery = $conn->prepare("
    SELECT study_date, subject_name, topic, status
    FROM planner_task_status
    WHERE user_id = ?
      AND study_date BETWEEN ? AND ?
");

$taskQuery->bind_param(
    "iss",
    $userId,
    $weekStart,
    $weekEnd
);

$taskQuery->execute();
$taskResult = $taskQuery->get_result();

while ($row = $taskResult->fetch_assoc()) {
    $key = $row['study_date'] . '|'
         . $row['subject_name'] . '|'
         . $row['topic'];

    $taskStatuses[$key] = $row['status'];
}

$taskQuery->close();

/* =====================================================
   13. GENERATE TODAY / SELECTED DATE / WEEK
===================================================== */

$allSessions = [];

for (
    $dayTimestamp = strtotime($weekStart);
    $dayTimestamp <= strtotime($weekEnd);
    $dayTimestamp = strtotime('+1 day', $dayTimestamp)
) {
    $date = date('Y-m-d', $dayTimestamp);

    $daySessions = generateTimetable(
        $subjects,
        $studyHours,
        $sessionDuration,
        $breakDuration,
        $studyTime,
        $date,
        $availableDays,
        $weeklyAvailability,
        $weakSubjects,
        $studyMethods
    );

    foreach ($daySessions as $session) {
        $key = $session['date'] . '|'
             . $session['subject'] . '|'
             . $session['topic'];

        $session['status'] = $taskStatuses[$key] ?? 'Pending';

        $allSessions[] = $session;
    }
}

$visibleSessions = [];

foreach ($allSessions as $session) {
    if ($tab === 'week' ||
        $session['date'] === $selectedDate) {
        $visibleSessions[] = $session;
    }
}

/* =====================================================
   14. FETCH QUIZ SCORES
===================================================== */

$quizScores = [];
$subjectQuizTotals = [];

$quizStmt = $conn->prepare("
    SELECT subject_name, topic, score, total_marks, quiz_date
    FROM planner_quiz_scores
    WHERE user_id = ?
    ORDER BY quiz_date DESC, id DESC
");

$quizStmt->bind_param("i", $userId);
$quizStmt->execute();

$quizResult = $quizStmt->get_result();

while ($quiz = $quizResult->fetch_assoc()) {
    $quizScores[] = $quiz;

    $subjectKey = $quiz['subject_name'];

    if (!isset($subjectQuizTotals[$subjectKey])) {
        $subjectQuizTotals[$subjectKey] = [
            'score' => 0,
            'total' => 0
        ];
    }

    $subjectQuizTotals[$subjectKey]['score'] +=
        (float)$quiz['score'];

    $subjectQuizTotals[$subjectKey]['total'] +=
        (float)$quiz['total_marks'];
}

$quizStmt->close();

/* =====================================================
   15. CALCULATE COMPLETION AND PROGRESS
===================================================== */

$totalTasks = count($allSessions);
$completedTasks = 0;
$completedMinutes = 0;
$plannedMinutes = 0;

$subjectProgress = [];

foreach ($allSessions as $session) {

    $plannedMinutes += $session['duration'];

    if ($session['status'] === 'Completed') {
        $completedTasks++;
        $completedMinutes += $session['duration'];
    }

    $name = $session['subject'];

    if (!isset($subjectProgress[$name])) {
        $subjectProgress[$name] = [
            'total' => 0,
            'completed' => 0
        ];
    }

    $subjectProgress[$name]['total']++;

    if ($session['status'] === 'Completed') {
        $subjectProgress[$name]['completed']++;
    }
}

$completionPercent = $totalTasks > 0
    ? (int)round(($completedTasks / $totalTasks) * 100)
    : 0;

$totalQuizScore = 0;
$totalQuizMarks = 0;

foreach ($quizScores as $quiz) {
    $totalQuizScore += (float)$quiz['score'];
    $totalQuizMarks += (float)$quiz['total_marks'];
}

$averageQuizPercent = $totalQuizMarks > 0
    ? round(($totalQuizScore / $totalQuizMarks) * 100, 1)
    : 0;

/* =====================================================
   16. ADAPTIVE RECOMMENDATIONS
===================================================== */

$recommendations = [];

foreach ($subjectProgress as $name => $progress) {

    $completion = $progress['total'] > 0
        ? ($progress['completed'] / $progress['total']) * 100
        : 0;

    $quizPercent = null;

    if (isset($subjectQuizTotals[$name]) &&
        $subjectQuizTotals[$name]['total'] > 0) {

        $quizPercent = (
            $subjectQuizTotals[$name]['score']
            / $subjectQuizTotals[$name]['total']
        ) * 100;
    }

    if ($quizPercent !== null && $quizPercent < 60) {

        $recommendations[] = [
            'title' => 'Improve ' . $name,
            'description' =>
                'Your recorded quiz average is below 60%. '
                . 'Revise the topic and attempt practice questions.',
            'action' => 'Revision + quiz practice'
        ];

    } elseif ($completion < 50) {

        $recommendations[] = [
            'title' => 'Complete more ' . $name . ' sessions',
            'description' =>
                'Several planned sessions remain incomplete. '
                . 'Try a shorter revision session and mark it complete.',
            'action' => 'Complete pending tasks'
        ];

    } else {

        $recommendations[] = [
            'title' => 'Continue practising ' . $name,
            'description' =>
                'Maintain consistency and test your understanding '
                . 'with a short quiz.',
            'action' => 'Practice and self-test'
        ];
    }
}

if (empty($recommendations)) {
    $recommendations[] = [
        'title' => 'Complete your student profile',
        'description' =>
            'Add your subjects and availability to create '
            . 'a personalized timetable.',
        'action' => 'Update profile'
    ];
}

$daysUntilExam = null;

if (!empty($examDate) &&
    strtotime($examDate) !== false) {

    $daysUntilExam = (int)floor(
        (strtotime($examDate) - strtotime($today)) / 86400
    );
}

/* =====================================================
   17. HELPER FOR TAB LINKS
===================================================== */

function tabUrl($tab, $date) {
    return 'view_study_plan.php?tab=' . urlencode($tab)
        . '&date=' . urlencode($date);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Study Plan | AI Study Planner</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --primary: #6652ef;
    --primary-dark: #5040d5;
    --navy: #10213d;
    --bg: #f4f6fc;
    --border: #e3e9f7;
    --text: #172b4d;
    --muted: #70809e;
    --green: #13a985;
    --orange: #f59e42;
    --red: #ed6298;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    font-family: 'Inter', sans-serif;
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 255px;
    background: linear-gradient(180deg, #10213d, #0b1930);
    color: white;
    padding: 24px 15px;
    flex-shrink: 0;
}

.brand {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 4px 8px 28px;
}

.brand-icon {
    background: var(--primary);
    width: 43px;
    height: 43px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 20px;
}

.brand h2 {
    font-size: 17px;
    margin: 0 0 5px;
}

.brand small {
    color: #b9c6e3;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 13px;
    color: #dce5f8;
    padding: 14px 13px;
    border-radius: 10px;
    margin-bottom: 7px;
    font-size: 13px;
    transition: .2s;
}

.nav-link i {
    width: 20px;
    text-align: center;
}

.nav-link:hover,
.nav-link.active {
    background: linear-gradient(90deg, #6b59ef, #5048cc);
    color: white;
}

.sidebar-bottom {
    margin-top: 45px;
}

.main {
    flex: 1;
    min-width: 0;
    padding: 25px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 25px;
}

.topbar h1 {
    font-size: 25px;
    margin: 0 0 7px;
}

.muted {
    color: var(--muted);
    font-size: 13px;
    line-height: 1.7;
}

.profile-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    border: 1px solid var(--border);
    padding: 12px 16px;
    border-radius: 13px;
    font-size: 13px;
}

.profile-pill i {
    font-size: 22px;
    color: var(--primary);
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 17px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(35, 60, 110, .025);
    margin-bottom: 20px;
    min-width: 0;
}

.stat {
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    border-radius: 15px;
    background: #eeebff;
    color: var(--primary);
    font-size: 19px;
    flex-shrink: 0;
}

.stat-icon.green {
    background: #e2f8f0;
    color: var(--green);
}

.stat-icon.orange {
    background: #fff2df;
    color: var(--orange);
}

.stat-icon.blue {
    background: #e3efff;
    color: #3988ee;
}

.stat-label {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 7px;
}

.stat-value {
    font-size: 23px;
    font-weight: 800;
}

.stat-note {
    font-size: 11px;
    color: var(--muted);
    margin-top: 6px;
}

.progress-track {
    height: 8px;
    background: #e9edf7;
    border-radius: 20px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6a52f1, #9a72ff);
    border-radius: 20px;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
}

.section-heading h2 {
    margin: 0 0 7px;
    font-size: 19px;
}

.section-heading h2 i {
    color: var(--primary);
    margin-right: 8px;
}

.primary-btn,
.outline-btn {
    border: none;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.primary-btn {
    background: var(--primary);
    color: white;
}

.primary-btn:hover {
    background: var(--primary-dark);
}

.outline-btn {
    background: white;
    border: 1px solid #d9d3ff;
    color: var(--primary);
}

.tabs {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    margin: 20px 0;
}

.tab {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 18px;
    background: white;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
}

.tab.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.date-form {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 12px;
    margin: 15px 0 22px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field label {
    font-size: 12px;
    font-weight: 600;
}

input,
select {
    padding: 12px;
    min-height: 43px;
    border: 1px solid #dce4f3;
    border-radius: 9px;
    font: inherit;
    color: var(--text);
    background: white;
    max-width: 100%;
}

.table-wrap {
    overflow-x: auto;
    border: 1px solid #edf0f8;
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
}

thead {
    background: #f4f6fc;
}

th,
td {
    padding: 15px 13px;
    text-align: left;
    border-bottom: 1px solid #edf0f8;
    font-size: 12px;
    vertical-align: middle;
}

th {
    color: #536583;
    font-weight: 700;
}

td strong {
    font-size: 12px;
}

td small {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    line-height: 1.5;
}

.badge {
    display: inline-block;
    border-radius: 20px;
    background: #eeebff;
    color: #6048d9;
    padding: 6px 10px;
    font-size: 10px;
    font-weight: 700;
}

.badge.completed {
    background: #e3f8ef;
    color: #0b9c74;
}

.badge.pending {
    background: #fff1dc;
    color: #b97614;
}

.small-btn {
    padding: 8px 10px;
    font-size: 11px;
}

.empty {
    text-align: center;
    padding: 55px 20px;
    color: var(--muted);
}

.empty i {
    display: block;
    color: #b8aaff;
    font-size: 38px;
    margin-bottom: 16px;
}

.empty h3 {
    color: #687b9a;
    margin-bottom: 8px;
}

.notice {
    padding: 13px 16px;
    border-radius: 10px;
    background: #e5f8ef;
    color: #087e5e;
    font-size: 13px;
    margin-bottom: 18px;
}

.error {
    background: #fff0f0;
    color: #b42318;
}

.two-col {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(270px, 1fr);
    gap: 20px;
}

.subject-progress {
    margin-bottom: 19px;
}

.subject-progress-head {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 8px;
    gap: 10px;
}

.recommendation {
    padding: 15px;
    border: 1px solid #ece8ff;
    background: #faf9ff;
    border-radius: 12px;
    margin-bottom: 12px;
}

.recommendation h4 {
    margin: 0 0 8px;
    font-size: 13px;
}

.recommendation p {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.7;
    margin: 0 0 10px;
}

.recommendation span {
    font-size: 11px;
    color: var(--primary);
    font-weight: 700;
}

.chart-box {
    position: relative;
    height: 250px;
    width: 100%;
}

.quiz-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.quiz-form .primary-btn {
    align-self: end;
    min-height: 43px;
}

footer {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    padding: 10px;
}

@media (max-width: 1100px) {
    .sidebar {
        width: 210px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .two-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 12px;
    }

    .brand {
        padding-bottom: 12px;
    }

    .nav-link {
        display: inline-flex;
        padding: 10px;
        margin: 3px;
        font-size: 11px;
    }

    .sidebar-bottom {
        margin-top: 8px;
    }

    .main {
        padding: 14px;
    }

    .topbar {
        align-items: flex-start;
    }

    .topbar h1 {
        font-size: 20px;
    }

    .profile-pill {
        display: none;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat {
        align-items: flex-start;
        flex-direction: column;
    }

    .card {
        padding: 15px;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .quiz-form {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-brain"></i>
            </div>
            <div>
                <h2>AI Study Planner</h2>
                <small>Plan • Learn • Grow</small>
            </div>
        </div>

        <a class="nav-link" href="newdashboard.php">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>

        <a class="nav-link active"
           href="view_study_plan.php">
            <i class="fa-regular fa-calendar-days"></i> My Study Plan
        </a>

        <a class="nav-link" href="subjects_syllabus.php">
            <i class="fa-solid fa-book-open"></i> Subjects &amp; Syllabus
        </a>

        <a class="nav-link" href="progress.php">
            <i class="fa-solid fa-chart-line"></i> Progress &amp; Reports
        </a>

        <a class="nav-link" href="recommendations.php">
            <i class="fa-solid fa-wand-magic-sparkles"></i> AI Recommendations
        </a>

        <a class="nav-link" href="profile_setup.php">
            <i class="fa-regular fa-user"></i> Profile Settings
        </a>

        <div class="sidebar-bottom">
            <a class="nav-link" href="../auth/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

        <header class="topbar">
            <div>
                <h1>My Study Plan</h1>
                <div class="muted">
                    Your personalized timetable, task completion and learning progress.
                </div>
            </div>

            <div class="profile-pill">
                <i class="fa-solid fa-user-graduate"></i>
                <div>
                    <strong><?php echo h($studentName); ?></strong>
                    <div class="muted">
                        <?php echo h(
                            $student['class_name']
                            ?? $student['course_name']
                            ?? 'Student'
                        ); ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="notice">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="notice error">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <!-- SUMMARY CARDS -->
        <section class="stats">

            <div class="card stat">
                <div class="stat-icon">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div>
                    <div class="stat-label">Subjects</div>
                    <div class="stat-value">
                        <?php echo count($subjects); ?>
                    </div>
                    <div class="stat-note">From your saved profile</div>
                </div>
            </div>

            <div class="card stat">
                <div class="stat-icon blue">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div>
                    <div class="stat-label">Daily Study Target</div>
                    <div class="stat-value">
                        <?php echo $studyHours; ?> hours
                    </div>
                    <div class="stat-note">
                        <?php echo $sessionDuration; ?>-minute sessions
                    </div>
                </div>
            </div>

            <div class="card stat">
                <div class="stat-icon green">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div>
                    <div class="stat-label">Tasks Completed</div>
                    <div class="stat-value">
                        <?php echo $completedTasks; ?>
                        / <?php echo $totalTasks; ?>
                    </div>
                    <div class="stat-note">This week</div>
                </div>
            </div>

            <div class="card stat">
                <div class="stat-icon orange">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div>
                    <div class="stat-label">Weekly Completion</div>
                    <div class="stat-value">
                        <?php echo $completionPercent; ?>%
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill"
                             style="width:<?php echo $completionPercent; ?>%">
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- PERSONALIZED TIMETABLE -->
        <section class="card">

            <div class="section-heading">
                <div>
                    <h2>
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        Personalized Study Timetable
                    </h2>
                    <div class="muted">
                        Built from your saved subjects, weak areas, exam date
                        and study preferences.
                    </div>
                </div>

                <a class="primary-btn"
                   href="view_study_plan.php?tab=today&date=<?php echo h($today); ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    View Today's Plan
                </a>
            </div>

            <?php if ($daysUntilExam !== null): ?>
                <div class="muted" style="margin-bottom:12px;">
                    <?php if ($daysUntilExam >= 0): ?>
                        <i class="fa-regular fa-calendar"></i>
                        <?php echo $daysUntilExam; ?> days until your exam.
                    <?php else: ?>
                        Your saved exam date has passed. Update it in your profile if needed.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <a class="tab <?php echo $tab === 'today' ? 'active' : ''; ?>"
                   href="<?php echo h(tabUrl('today', $selectedDate)); ?>">
                    Today / Selected Date
                </a>

                <a class="tab <?php echo $tab === 'week' ? 'active' : ''; ?>"
                   href="<?php echo h(tabUrl('week', $selectedDate)); ?>">
                    This Week
                </a>

                <a class="tab <?php echo $tab === 'quiz' ? 'active' : ''; ?>"
                   href="<?php echo h(tabUrl('quiz', $selectedDate)); ?>">
                    Quiz Scores
                </a>

                <a class="tab <?php echo $tab === 'progress' ? 'active' : ''; ?>"
                   href="<?php echo h(tabUrl('progress', $selectedDate)); ?>">
                    Progress
                </a>
            </div>

            <form class="date-form" method="GET">
                <input type="hidden" name="tab"
                       value="<?php echo h($tab); ?>">

                <div class="field">
                    <label for="date">Choose date</label>
                    <input type="date" id="date" name="date"
                           value="<?php echo h($selectedDate); ?>"
                           required>
                </div>

                <button class="outline-btn" type="submit">
                    View Date
                </button>
            </form>

            <?php if (empty($subjects)): ?>

                <div class="empty">
                    <i class="fa-solid fa-book-open"></i>
                    <h3>No subjects found</h3>
                    <p>
                        Your profile was found, but its subjects_data field
                        does not contain usable subject names.
                    </p>
                    <p>
                        Save your subjects in the profile using JSON such as:
                    </p>
                    <code>
                        [{"name":"Java Programming"},{"name":"Data Science"}]
                    </code>
                    <p>
                        Make sure the profile form saves the subjects to
                        student_profilenew.subjects_data.
                    </p>
                    <a href="profile_setup.php" class="primary-btn">
                        Update Profile
                    </a>
                </div>

            <?php elseif (empty($visibleSessions) && $tab !== 'quiz' && $tab !== 'progress'): ?>

                <div class="empty">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    <h3>No study sessions found for this date</h3>
                    <p>
                        Check your available days and weekly availability
                        in your student profile.
                    </p>
                    <a href="view_study_plan.php?tab=week&date=<?php echo h($selectedDate); ?>"
                       class="primary-btn">
                        View Weekly Timetable
                    </a>
                </div>

            <?php elseif ($tab === 'quiz'): ?>

                <h3>Record a Quiz Score</h3>
                <p class="muted">
                    Save your marks after a practice quiz to improve your
                    personalized recommendations.
                </p>

                <form method="POST" class="quiz-form">
                    <input type="hidden" name="action" value="save_quiz">

                    <div class="field">
                        <label>Subject</label>
                        <select name="quiz_subject" required>
                            <option value="">Select subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo h($subject['name']); ?>">
                                    <?php echo h($subject['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>Topic</label>
                        <input type="text" name="quiz_topic"
                               placeholder="e.g. SQL Queries">
                    </div>

                    <div class="field">
                        <label>Quiz date</label>
                        <input type="date" name="quiz_date"
                               value="<?php echo h($selectedDate); ?>" required>
                    </div>

                    <div class="field">
                        <label>Marks obtained</label>
                        <input type="number" name="score" min="0"
                               step="0.01" placeholder="e.g. 8" required>
                    </div>

                    <div class="field">
                        <label>Total marks</label>
                        <input type="number" name="total_marks" min="0.01"
                               step="0.01" placeholder="e.g. 10" required>
                    </div>

                    <button type="submit" class="primary-btn">
                        <i class="fa-solid fa-floppy-disk"></i> Save Score
                    </button>
                </form>

                <div class="section-heading">
                    <h2>Recorded Quiz Scores</h2>
                    <strong><?php echo $averageQuizPercent; ?>% overall</strong>
                </div>

                <?php if (empty($quizScores)): ?>
                    <div class="empty">
                        <i class="fa-solid fa-clipboard-question"></i>
                        <h3>No quiz scores yet</h3>
                        <p>Record your first quiz above.</p>
                    </div>
                <?php else: ?>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Topic</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($quizScores as $quiz): ?>
                                <tr>
                                    <td><?php echo h(displayDate($quiz['quiz_date'])); ?></td>
                                    <td><?php echo h($quiz['subject_name']); ?></td>
                                    <td><?php echo h($quiz['topic'] ?: 'General quiz'); ?></td>
                                    <td>
                                        <?php echo h($quiz['score']); ?>
                                        /
                                        <?php echo h($quiz['total_marks']); ?>
                                    </td>
                                    <td>
                                        <?php echo round(
                                            ((float)$quiz['score'] /
                                            (float)$quiz['total_marks']) * 100,
                                            1
                                        ); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            <?php elseif ($tab === 'progress'): ?>

                <div class="two-col">
                    <div>
                        <h3>Weekly Task Completion</h3>
                        <div class="chart-box">
                            <canvas id="completionChart"></canvas>
                        </div>
                    </div>

                    <div>
                        <h3>Quiz Performance</h3>
                        <div class="chart-box">
                            <canvas id="quizChart"></canvas>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>Subject</th>
                                <th>Topic</th>
                                <th>Study Method</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($visibleSessions as $session): ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?php echo h(formatTime($session['start'])); ?>
                                        -
                                        <?php echo h(formatTime($session['end'])); ?>
                                    </strong>
                                    <small>
                                        <?php echo h(displayDate($session['date'])); ?>
                                    </small>
                                </td>

                                <td>
                                    <strong><?php echo h($session['subject']); ?></strong>
                                </td>

                                <td><?php echo h($session['topic']); ?></td>

                                <td>
                                    <span class="badge">
                                        <?php echo h($session['type']); ?>
                                    </span>
                                </td>

                                <td><?php echo (int)$session['duration']; ?> min</td>

                                <td>
                                    <span class="badge <?php
                                        echo $session['status'] === 'Completed'
                                            ? 'completed' : 'pending';
                                    ?>">
                                        <?php echo h($session['status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="action"
                                               value="update_task">

                                        <input type="hidden" name="study_date"
                                               value="<?php echo h($session['date']); ?>">

                                        <input type="hidden" name="subject_name"
                                               value="<?php echo h($session['subject']); ?>">

                                        <input type="hidden" name="topic"
                                               value="<?php echo h($session['topic']); ?>">

                                        <input type="hidden" name="status"
                                               value="<?php echo $session['status'] === 'Completed'
                                                   ? 'Pending' : 'Completed'; ?>">

                                        <button type="submit"
                                                class="<?php echo $session['status'] === 'Completed'
                                                    ? 'outline-btn' : 'primary-btn'; ?> small-btn">
                                            <?php echo $session['status'] === 'Completed'
                                                ? 'Undo' : 'Complete'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="muted" style="margin-top:15px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Task completion is saved to your account. Use the
                    Complete button after finishing a session.
                </p>

            <?php endif; ?>

        </section>

        <!-- SUBJECT PROGRESS AND RECOMMENDATIONS -->
        <?php if ($tab === 'today' || $tab === 'week'): ?>

            <div class="two-col">

                <section class="card">
                    <div class="section-heading">
                        <div>
                            <h2>
                                <i class="fa-solid fa-chart-column"></i>
                                Subject-wise Progress
                            </h2>
                            <div class="muted">
                                Based on your recorded completed tasks.
                            </div>
                        </div>
                    </div>

                    <?php foreach ($subjectProgress as $name => $progress): ?>

                        <?php
                        $percent = $progress['total'] > 0
                            ? round(
                                ($progress['completed'] /
                                $progress['total']) * 100
                            )
                            : 0;
                        ?>

                        <div class="subject-progress">
                            <div class="subject-progress-head">
                                <strong><?php echo h($name); ?></strong>
                                <span><?php echo $percent; ?>%</span>
                            </div>

                            <div class="progress-track">
                                <div class="progress-fill"
                                     style="width:<?php echo $percent; ?>%">
                                </div>
                            </div>

                            <div class="muted">
                                <?php echo $progress['completed']; ?>
                                of <?php echo $progress['total']; ?>
                                weekly sessions completed
                            </div>
                        </div>

                    <?php endforeach; ?>

                    <?php if (empty($subjectProgress)): ?>
                        <p class="muted">No timetable sessions are available yet.</p>
                    <?php endif; ?>

                </section>

                <section class="card">
                    <div class="section-heading">
                        <div>
                            <h2>
                                <i class="fa-solid fa-robot"></i>
                                Adaptive Recommendations
                            </h2>
                            <div class="muted">
                                Recommendations based on your saved task
                                completion and quiz results.
                            </div>
                        </div>
                    </div>

                    <?php foreach ($recommendations as $recommendation): ?>
                        <div class="recommendation">
                            <h4><?php echo h($recommendation['title']); ?></h4>
                            <p><?php echo h($recommendation['description']); ?></p>
                            <span>
                                <i class="fa-solid fa-lightbulb"></i>
                                <?php echo h($recommendation['action']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>

                    <a href="view_study_plan.php?tab=quiz&date=<?php echo h($selectedDate); ?>"
                       class="outline-btn">
                        Record Quiz Results
                    </a>

                </section>

            </div>

        <?php endif; ?>

        <?php if ($tab === 'progress'): ?>

            <section class="card">
                <h2>Progress Summary</h2>

                <p class="muted">
                    Weekly tasks completed:
                    <strong><?php echo $completedTasks; ?></strong>
                    out of
                    <strong><?php echo $totalTasks; ?></strong>.
                </p>

                <p class="muted">
                    Recorded quiz average:
                    <strong><?php echo $averageQuizPercent; ?>%</strong>.
                </p>

                <p class="muted">
                    Planned study time:
                    <strong><?php echo round($plannedMinutes / 60, 1); ?> hours</strong>.
                </p>

                <p class="muted">
                    Completed study time:
                    <strong><?php echo round($completedMinutes / 60, 1); ?> hours</strong>.
                </p>
            </section>

        <?php endif; ?>

        <footer>
            AI Study Planner • Plan better, learn smarter, achieve your goals.
        </footer>

    </main>

</div>

<script>
/* =====================================================
   PROGRESS CHARTS
===================================================== */

const subjectNames = <?php
    echo json_encode(
        array_keys($subjectProgress),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const subjectCompleted = <?php
    echo json_encode(
        array_map(
            function($item) { return $item['completed']; },
            array_values($subjectProgress)
        )
    );
?>;

const subjectPending = <?php
    echo json_encode(
        array_map(
            function($item) {
                return max(0, $item['total'] - $item['completed']);
            },
            array_values($subjectProgress)
        )
    );
?>;

const quizSubjects = <?php
    echo json_encode(
        array_keys($subjectQuizTotals),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
?>;

const quizPercentages = <?php
    $quizChartValues = [];

    foreach ($subjectQuizTotals as $totals) {
        $quizChartValues[] = $totals['total'] > 0
            ? round(($totals['score'] / $totals['total']) * 100, 1)
            : 0;
    }

    echo json_encode($quizChartValues);
?>;

if (document.getElementById('completionChart')) {
    new Chart(document.getElementById('completionChart'), {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending'],
            datasets: [{
                data: [
                    <?php echo (int)$completedTasks; ?>,
                    <?php echo (int)max(0, $totalTasks - $completedTasks); ?>
                ],
                backgroundColor: ['#6652ef', '#e7eaf5'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    new Chart(document.getElementById('quizChart'), {
        type: 'bar',
        data: {
            labels: quizSubjects,
            datasets: [{
                label: 'Quiz percentage',
                data: quizPercentages,
                backgroundColor: '#6652ef',
                borderRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>
```