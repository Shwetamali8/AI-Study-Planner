
<?php
session_start();

// =====================================================
// 1. AUTHENTICATION
// =====================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

// =====================================================
// 2. DATABASE CONNECTION
// =====================================================
$conn = new mysqli("localhost", "root", "", "study_planner");

if ($conn->connect_error) {
    error_log("Database connection error: " . $conn->connect_error);
    die("Unable to connect to the database. Please try again later.");
}

$conn->set_charset("utf8mb4");

// =====================================================
// 3. HELPER FUNCTIONS
// =====================================================
function e($value)
{
    if ($value === null || $value === '') {
        return 'Not provided';
    }

    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function decodeData($value)
{
    if (is_array($value)) {
        return $value;
    }

    if ($value === null || trim((string)$value) === '') {
        return [];
    }

    $decoded = json_decode((string)$value, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ([
            'subjects', 'exams', 'assignments',
            'items', 'data', 'records', 'deadlines'
        ] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        if ($decoded === []) {
            return [];
        }

        $keys = array_keys($decoded);
        $isList = $keys === range(0, count($decoded) - 1);

        return $isList ? $decoded : [$decoded];
    }

    return array_values(array_filter(
        array_map('trim', explode(',', (string)$value)),
        function ($item) {
            return $item !== '';
        }
    ));
}

function getField($record, $keys, $default = '')
{
    if (!is_array($record)) {
        return $default;
    }

    foreach ($keys as $key) {
        if (isset($record[$key]) && $record[$key] !== '') {
            return $record[$key];
        }
    }

    return $default;
}

function formatDateValue($value)
{
    if (
        $value === null ||
        $value === '' ||
        $value === '0000-00-00'
    ) {
        return 'Not provided';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp !== false
        ? date('d M Y', $timestamp)
        : (string)$value;
}

function formatLabel($key)
{
    $labels = [
        'subject_name' => 'Subject Name',
        'name' => 'Name',
        'title' => 'Title',
        'marks' => 'Marks',
        'knowledge' => 'Knowledge Level',
        'remaining' => 'Remaining Topics',
        'topics' => 'Topics / Chapters',
        'difficulty' => 'Difficulty Level',
        'estimated_hours' => 'Estimated Study Hours',
        'exam_date' => 'Exam Date',
        'date' => 'Deadline Date',
        'deadline' => 'Deadline',
        'exam_name' => 'Exam Name',
        'assignment_name' => 'Assignment Name',
        'type' => 'Type',
        'subject' => 'Related Subject',
        'priority' => 'Priority',
        'estimated_time' => 'Estimated Time',
        'status' => 'Status',
        'completed' => 'Completed',
        'description' => 'Description',
        'score' => 'Score',
        'total_marks' => 'Total Marks',
        'completion_status' => 'Completion Status'
    ];

    return $labels[$key] ??
        ucwords(str_replace(['_', '-'], ' ', (string)$key));
}

function formatValue($key, $value)
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_array($value)) {
        $items = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = getField(
                    $item,
                    ['name', 'title', 'topic', 'day', 'method'],
                    json_encode($item, JSON_UNESCAPED_UNICODE)
                );
            } else {
                $items[] = $item;
            }
        }

        return implode(', ', $items);
    }

    if (
        stripos($key, 'date') !== false ||
        stripos($key, 'deadline') !== false
    ) {
        return formatDateValue($value);
    }

    return (string)$value;
}

function detailRow($label, $value, $suffix = '')
{
    $isEmpty = $value === null || $value === '';

    echo '<div class="detail-row">';
    echo '<span class="detail-label">' . e($label) . '</span>';
    echo '<span class="detail-value">';

    if ($isEmpty) {
        echo 'Not provided';
    } else {
        echo e($value) . e($suffix);
    }

    echo '</span></div>';
}

function renderRecordFields($record, $hiddenKeys = [])
{
    if (!is_array($record)) {
        return;
    }

    foreach ($record as $key => $value) {
        if (
            in_array((string)$key, $hiddenKeys, true) ||
            $value === null ||
            $value === ''
        ) {
            continue;
        }

        echo '<div class="record-row">';
        echo '<span class="record-label">' .
            e(formatLabel($key)) . '</span>';
        echo '<span class="record-value">' .
            e(formatValue((string)$key, $value)) . '</span>';
        echo '</div>';
    }
}

function renderRecords($records, $type)
{
    if (empty($records)) {
        echo '<div class="empty-state">';
        echo '<i class="fa-regular fa-folder-open"></i>';
        echo '<p>No ' . e($type) . ' details have been saved yet.</p>';
        echo '</div>';
        return;
    }

    echo '<div class="record-grid">';

    foreach ($records as $index => $record) {
        if (!is_array($record)) {
            $record = ['name' => $record];
        }

        if ($type === 'subjects') {
            $title = getField(
                $record,
                ['subject_name', 'name', 'subject', 'title'],
                'Subject ' . ($index + 1)
            );
            $icon = 'fa-book-open';
        } elseif ($type === 'exams') {
            $title = getField(
                $record,
                ['exam_name', 'title', 'name'],
                'Exam ' . ($index + 1)
            );
            $icon = 'fa-calendar-check';
        } else {
            $title = getField(
                $record,
                ['assignment_name', 'title', 'name', 'task_name'],
                'Assignment ' . ($index + 1)
            );
            $icon = 'fa-file-lines';
        }

        echo '<article class="record-card">';
        echo '<div class="record-card-icon"><i class="fa-solid ' .
            e($icon) . '"></i></div>';
        echo '<h4>' . e($title) . '</h4>';

        renderRecordFields($record, [
            'subject_name', 'exam_name', 'assignment_name',
            'task_name', 'title', 'name'
        ]);

        echo '</article>';
    }

    echo '</div>';
}

function renderChips(
    $items,
    $fieldNames = ['name', 'title', 'subject', 'day', 'method']
) {
    if (empty($items)) {
        echo '<span class="muted">Not provided</span>';
        return;
    }

    foreach ($items as $item) {
        if (is_array($item)) {
            $item = getField($item, $fieldNames, 'Item');
        }

        echo '<span class="chip">' . e($item) . '</span>';
    }
}

// =====================================================
// 4. FETCH STUDENT AND PROFILE
// =====================================================
$sql = "
    SELECT
        u.name,
        u.email,
        p.id AS profile_id,
        p.user_id,
        p.age,
        p.gender,
        p.school_name,
        p.class_name,
        p.board,
        p.target_exam,
        p.exam_date,
        p.study_hours,
        p.created_at,
        p.medium,
        p.study_time,
        p.weak_subjects,
        p.profile_photo,
        p.session_duration,
        p.break_duration,
        p.target_percentage,
        p.study_methods,
        p.college_name,
        p.course_name,
        p.course_year,
        p.semester,
        p.subjects_data,
        p.upcoming_exams,
        p.assignment_deadlines,
        p.revision_preference,
        p.available_days,
        p.weekly_availability
    FROM `user` AS u
    LEFT JOIN student_profilenew AS p
        ON p.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Profile query preparation failed: " . $conn->error);
    die("Unable to load your profile. Please try again later.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();

if (!$student) {
    $conn->close();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

if (empty($student['profile_id'])) {
    $conn->close();
    header("Location: profilesetup.php");
    exit();
}

// =====================================================
// 5. PREPARE DASHBOARD DATA
// =====================================================
$name = $student['name'] ?? 'Student';
$email = $student['email'] ?? '';

$initial = strtoupper(substr(trim($name), 0, 1));

if ($initial === '') {
    $initial = 'S';
}

$subjects = decodeData($student['subjects_data'] ?? '');
$upcomingExams = decodeData($student['upcoming_exams'] ?? '');
$assignments = decodeData($student['assignment_deadlines'] ?? '');
$studyMethods = decodeData($student['study_methods'] ?? '');
$availableDays = decodeData($student['available_days'] ?? '');
$weakSubjects = decodeData($student['weak_subjects'] ?? '');
$weeklyData = decodeData($student['weekly_availability'] ?? '');

$studyHours = $student['study_hours'] ?? '';
$studyTime = $student['study_time'] ?? '';
$sessionDuration = $student['session_duration'] ?? '';
$breakDuration = $student['break_duration'] ?? '';
$targetPercentage = $student['target_percentage'] ?? '';
$examDate = $student['exam_date'] ?? '';

// =====================================================
// 6. EXAM COUNTDOWN
// =====================================================
$daysLeft = null;

if ($examDate && $examDate !== '0000-00-00') {
    try {
        $today = new DateTime('today');
        $exam = new DateTime($examDate);
        $daysLeft = (int)$today->diff($exam)->format('%r%a');
    } catch (Exception $exception) {
        $daysLeft = null;
    }
}

// =====================================================
// 7. FIND NEXT UPCOMING EXAM
// =====================================================
$nextExam = null;
$todayString = date('Y-m-d');

foreach ($upcomingExams as $examRecord) {
    if (!is_array($examRecord)) {
        continue;
    }

    $date = getField($examRecord, ['exam_date', 'date']);

    if (
        $date !== '' &&
        $date >= $todayString &&
        (
            $nextExam === null ||
            $date < getField(
                $nextExam,
                ['exam_date', 'date'],
                '9999-12-31'
            )
        )
    ) {
        $nextExam = $examRecord;
    }
}

if (
    $nextExam === null &&
    $examDate >= $todayString &&
    $examDate !== ''
) {
    $nextExam = [
        'exam_name' => $student['target_exam'] ?: 'Main Examination',
        'exam_date' => $examDate
    ];
}

// =====================================================
// 8. COUNT PENDING ASSIGNMENTS
// =====================================================
$pendingAssignments = 0;

foreach ($assignments as $assignment) {
    if (!is_array($assignment)) {
        $pendingAssignments++;
        continue;
    }

    $status = strtolower(trim((string)getField(
        $assignment,
        ['status', 'completion_status'],
        'pending'
    )));

    if (!in_array($status, ['completed', 'done', 'submitted'], true)) {
        $pendingAssignments++;
    }
}

// =====================================================
// 9. PROFILE PHOTO
// =====================================================
$photoPath = '';
$savedPhoto = trim((string)($student['profile_photo'] ?? ''));

if ($savedPhoto !== '') {
    if (preg_match('/^https?:\/\//i', $savedPhoto)) {
        $photoPath = $savedPhoto;
    } elseif (strpos($savedPhoto, '../') === 0) {
        $photoPath = $savedPhoto;
    } elseif (strpos($savedPhoto, 'uploads/') === 0) {
        $photoPath = '../' . $savedPhoto;
    } else {
        $photoPath = '../uploads/profile_photos/' . basename($savedPhoto);
    }
}

// =====================================================
// 10. PAGE PATHS
// These files must be in the same student folder.
// =====================================================
$generatePlanPage = 'generate_study_plan.php';
$viewPlanPage = 'view_study_plan.php';
$profilePage = 'profilesetup.php';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MindAI | Student Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --bg:#080d1c;
    --panel:#111a2e;
    --panel2:#0f172a;
    --border:rgba(148,163,184,.16);
    --muted:#94a3b8;
    --cyan:#22d3ee;
    --purple:#8b5cf6;
    --green:#34d399;
    --orange:#fb923c;
}

* { box-sizing:border-box; }

html {
    scroll-behavior:smooth;
    scroll-padding-top:20px;
}

body {
    margin:0;
    min-height:100vh;
    font-family:'Inter',sans-serif;
    color:#f8fafc;
    background:
        radial-gradient(ellipse at 90% 0%,rgba(99,102,241,.13),transparent 35%),
        var(--bg);
}

a { text-decoration:none; }

.sidebar {
    position:fixed;
    inset:0 auto 0 0;
    width:250px;
    padding:25px 17px;
    background:rgba(12,19,37,.97);
    border-right:1px solid var(--border);
    z-index:1000;
    overflow-y:auto;
    display:flex;
    flex-direction:column;
}

.brand {
    font-size:25px;
    font-weight:800;
    color:white;
    padding:0 12px;
    margin-bottom:30px;
}

.brand span { color:var(--cyan); }

.nav-label {
    color:#64748b;
    font-size:10px;
    font-weight:800;
    letter-spacing:1.5px;
    padding:0 12px;
    margin:15px 0 12px;
}

.side-link {
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 13px;
    margin-bottom:6px;
    color:#aab7cc;
    border-radius:12px;
    font-size:12px;
    font-weight:600;
    transition:.2s;
}

.side-link i {
    width:20px;
    text-align:center;
    flex-shrink:0;
}

.side-link:hover,
.side-link.active {
    color:white;
    background:linear-gradient(100deg,rgba(6,182,212,.2),rgba(99,102,241,.16));
}

.side-link.active {
    border:1px solid rgba(34,211,238,.17);
}

.sidebar-bottom { margin-top:auto; }

.main {
    margin-left:250px;
    padding:28px 32px 40px;
    min-height:100vh;
}

.topbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    margin-bottom:27px;
}

.page-title {
    font-size:27px;
    font-weight:800;
    margin:0 0 7px;
}

.muted, .small-muted { color:var(--muted); }
.small-muted { font-size:11px; }

.profile-mini {
    display:flex;
    align-items:center;
    gap:12px;
    padding:9px 13px;
    background:rgba(255,255,255,.035);
    border:1px solid var(--border);
    border-radius:15px;
    max-width:100%;
}

.avatar {
    width:44px;
    height:44px;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    border-radius:14px;
    background:linear-gradient(135deg,#06b6d4,#6366f1);
    color:white;
    font-size:19px;
    font-weight:800;
}

.avatar img {
    width:100%;
    height:100%;
    object-fit:cover;
}

.large-avatar {
    width:85px;
    height:85px;
    border-radius:24px;
    font-size:30px;
    margin:0 auto 14px;
}

.welcome-card {
    position:relative;
    overflow:hidden;
    padding:30px;
    border-radius:24px;
    border:1px solid rgba(99,102,241,.25);
    background:linear-gradient(115deg,#142442,#172044 60%,#20204b);
    margin-bottom:24px;
}

.welcome-card::after {
    content:"✦";
    position:absolute;
    right:35px;
    top:-15px;
    font-size:150px;
    color:rgba(255,255,255,.045);
    pointer-events:none;
}

.welcome-card > * {
    position:relative;
    z-index:1;
}

.welcome-card h2 {
    font-size:clamp(23px,3vw,32px);
    font-weight:800;
    margin:12px 0;
    overflow-wrap:anywhere;
}

.welcome-card p {
    color:#cbd5e1;
    font-size:13px;
    line-height:1.8;
    max-width:650px;
}

.tag, .chip {
    display:inline-block;
    padding:7px 11px;
    border-radius:20px;
    font-size:10px;
    font-weight:800;
    overflow-wrap:anywhere;
}

.tag {
    color:#a5f3fc;
    background:rgba(34,211,238,.1);
    border:1px solid rgba(34,211,238,.2);
    letter-spacing:1px;
}

.chip {
    margin:4px 4px 4px 0;
    border-radius:9px;
    background:rgba(139,92,246,.13);
    border:1px solid rgba(139,92,246,.2);
    color:#ddd6fe;
    font-size:11px;
}

.btn-gradient,
.btn-outline-custom {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    gap:9px;
    padding:12px 16px;
    border-radius:12px;
    font-size:12px;
    font-weight:750;
    transition:transform .2s,opacity .2s;
}

.btn-gradient {
    border:0;
    background:linear-gradient(90deg,#06b6d4,#6366f1);
    color:white;
}

.btn-gradient:hover {
    color:white;
    opacity:.9;
    transform:translateY(-2px);
}

.btn-outline-custom {
    border:1px solid rgba(148,163,184,.28);
    background:rgba(255,255,255,.04);
    color:#e2e8f0;
}

.btn-outline-custom:hover {
    color:white;
    background:rgba(255,255,255,.09);
}

.hero-actions {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:18px;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:15px;
    margin-bottom:25px;
}

.stat-card, .panel {
    min-width:0;
    background:rgba(17,26,46,.88);
    border:1px solid var(--border);
    border-radius:19px;
}

.stat-card { padding:19px; }

.stat-top {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:8px;
    margin-bottom:18px;
}

.stat-icon {
    width:40px;
    height:40px;
    display:flex;
    justify-content:center;
    align-items:center;
    border-radius:12px;
    background:rgba(34,211,238,.12);
    color:var(--cyan);
}

.stat-number {
    font-size:24px;
    font-weight:800;
    overflow-wrap:anywhere;
}

.stat-label {
    font-size:11px;
    color:var(--muted);
}

.content-grid {
    display:grid;
    grid-template-columns:minmax(0,1.25fr) minmax(270px,.85fr);
    gap:20px;
    align-items:start;
}

.panel {
    padding:22px;
    margin-bottom:20px;
}

.panel-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
}

.panel-heading h3 {
    font-size:16px;
    font-weight:800;
    margin:0;
}

.section-icon {
    width:35px;
    height:35px;
    margin-right:8px;
    display:inline-flex;
    justify-content:center;
    align-items:center;
    border-radius:11px;
    background:rgba(139,92,246,.14);
    color:#c4b5fd;
}

.detail-row, .record-row {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    padding:12px 0;
    border-bottom:1px solid var(--border);
}

.detail-row:last-child,
.record-row:last-child {
    border-bottom:0;
}

.detail-label, .record-label {
    color:var(--muted);
    font-size:11px;
    flex:0 0 42%;
    overflow-wrap:anywhere;
}

.detail-value, .record-value {
    text-align:right;
    font-size:12px;
    font-weight:650;
    max-width:60%;
    overflow-wrap:anywhere;
}

.record-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:15px;
}

.record-card {
    min-width:0;
    padding:18px;
    border:1px solid var(--border);
    border-radius:17px;
    background:var(--panel2);
}

.record-card h4 {
    font-size:14px;
    font-weight:800;
    margin:12px 0;
    overflow-wrap:anywhere;
}

.record-card-icon {
    width:38px;
    height:38px;
    display:flex;
    justify-content:center;
    align-items:center;
    border-radius:12px;
    background:rgba(34,211,238,.12);
    color:var(--cyan);
}

.method-row {
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 0;
    border-bottom:1px solid var(--border);
    font-size:12px;
    overflow-wrap:anywhere;
}

.method-row:last-child { border-bottom:0; }

.method-row i {
    color:var(--cyan);
    width:18px;
    flex-shrink:0;
}

.empty-state {
    padding:25px 15px;
    text-align:center;
    color:var(--muted);
    font-size:12px;
    background:rgba(15,23,42,.6);
    border-radius:14px;
}

.empty-state i {
    display:block;
    font-size:26px;
    color:var(--cyan);
    margin-bottom:12px;
}

.profile-summary {
    text-align:center;
    padding:8px 0 18px;
}

.profile-summary h3 {
    font-size:18px;
    font-weight:800;
    overflow-wrap:anywhere;
}

.profile-summary p {
    color:var(--muted);
    font-size:12px;
    overflow-wrap:anywhere;
}

.quick-action {
    display:flex;
    align-items:center;
    gap:13px;
    padding:14px;
    margin-bottom:10px;
    border:1px solid var(--border);
    border-radius:14px;
    background:rgba(15,23,42,.55);
    color:#f8fafc;
    transition:.2s;
}

.quick-action:last-child { margin-bottom:0; }

.quick-action:hover {
    color:white;
    border-color:rgba(34,211,238,.35);
    transform:translateY(-2px);
}

.quick-action-icon {
    width:39px;
    height:39px;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    background:rgba(34,211,238,.1);
    color:var(--cyan);
}

.quick-action strong {
    display:block;
    font-size:12px;
    margin-bottom:4px;
}

.quick-action small {
    display:block;
    color:var(--muted);
    font-size:10px;
    line-height:1.5;
}

.exam-box {
    padding:17px;
    border-radius:15px;
    background:rgba(34,211,238,.07);
    border:1px solid rgba(34,211,238,.15);
}

.exam-box h4 {
    font-size:15px;
    font-weight:800;
    overflow-wrap:anywhere;
}

.progress-track {
    height:8px;
    overflow:hidden;
    border-radius:20px;
    background:#253149;
    margin:12px 0;
}

.progress-fill {
    height:100%;
    border-radius:20px;
    background:linear-gradient(90deg,#06b6d4,#8b5cf6);
}

.footer {
    padding-top:15px;
    color:#64748b;
    font-size:11px;
    text-align:center;
}

@media(max-width:1200px) {
    .stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .content-grid { grid-template-columns:1fr; }
}

@media(max-width:760px) {
    .sidebar {
        position:relative;
        width:100%;
        height:auto;
        padding:16px;
        border-right:0;
        border-bottom:1px solid var(--border);
    }

    .brand { margin-bottom:15px; }
    .nav-label, .sidebar-bottom { display:none; }

    .sidebar nav {
        display:flex;
        flex-wrap:wrap;
        gap:5px;
    }

    .side-link {
        margin:0;
        padding:9px;
        font-size:11px;
    }

    .main {
        margin-left:0;
        padding:20px 14px 30px;
    }

    .topbar {
        align-items:flex-start;
        flex-direction:column;
    }

    .welcome-card { padding:22px; }

    .welcome-card::after {
        right:5px;
        font-size:90px;
    }

    .stats-grid { gap:10px; }
    .stat-card { padding:14px; }
    .stat-number { font-size:20px; }
    .panel { padding:17px; }
    .record-grid { grid-template-columns:1fr; }
    .hero-actions > a { width:100%; }
}

@media(max-width:380px) {
    .stats-grid { grid-template-columns:1fr; }
}
</style>
</head>

<body>

<!-- ===================================================
     SIDEBAR
==================================================== -->
<aside class="sidebar">

    <a class="brand" href="newdashboard.php">
        <span>◈ Mind</span>AI
    </a>

    <div class="nav-label">WORKSPACE</div>

    <nav>
        <a class="side-link active" href="newdashboard.php">
            <i class="fa-solid fa-border-all"></i> Dashboard
        </a>

        <a class="side-link" href="#student-details">
            <i class="fa-solid fa-id-card"></i> Student Details
        </a>

        <a class="side-link" href="#college-details">
            <i class="fa-solid fa-graduation-cap"></i> College & Course
        </a>

        <a class="side-link" href="#subjects">
            <i class="fa-solid fa-book-open"></i> Subjects & Topics
        </a>

        <a class="side-link" href="#exams">
            <i class="fa-solid fa-calendar-days"></i> Upcoming Exams
        </a>

        <a class="side-link" href="#assignments">
            <i class="fa-solid fa-file-lines"></i> Assignments
        </a>

        <a class="side-link" href="#preferences">
            <i class="fa-solid fa-sliders"></i> Study Preferences
        </a>

        <a class="side-link" href="#availability">
            <i class="fa-solid fa-calendar-week"></i> Availability
        </a>

        <a class="side-link" href="<?php echo e($generatePlanPage); ?>">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Study Plan
        </a>

        <a class="side-link" href="<?php echo e($viewPlanPage); ?>">
            <i class="fa-solid fa-calendar-check"></i> View Study Plan
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="nav-label">ACCOUNT</div>

        <a class="side-link" href="<?php echo e($profilePage); ?>">
            <i class="fa-solid fa-user-gear"></i> Edit Profile
        </a>

        <a class="side-link" href="../auth/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>

<!-- ===================================================
     MAIN DASHBOARD
==================================================== -->
<main class="main">

    <header class="topbar">
        <div>
            <h1 class="page-title">Student Dashboard</h1>
            <p class="muted mb-0">
                Your academic information, learning goals, and personalized study planner.
            </p>
        </div>

        <div class="profile-mini">
            <div class="avatar">
                <?php if ($photoPath !== ''): ?>
                    <img
                        src="<?php echo e($photoPath); ?>"
                        alt="Student photo"
                        onerror="this.style.display='none';this.parentElement.textContent='S';">
                <?php else: ?>
                    <?php echo e($initial); ?>
                <?php endif; ?>
            </div>

            <div>
                <div class="fw-bold" style="font-size:13px;">
                    <?php echo e($name); ?>
                </div>
                <div class="small-muted">Student Account</div>
            </div>
        </div>
    </header>

    <!-- WELCOME CARD -->
    <section class="welcome-card">
        <span class="tag">YOUR PERSONAL LEARNING SPACE</span>

        <h2>Hello, <?php echo e($name); ?>! 👋</h2>

        <p>
            Ready to study smarter? Organize your subjects, prioritize
            difficult topics, plan revision sessions, and prepare a study
            schedule around your saved preferences.
        </p>

        <div class="hero-actions">

    <a href="<?php echo e($generatePlanPage); ?>" class="btn-gradient">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        Generate AI Study Plan
    </a>

    <a href="<?php echo e($viewPlanPage); ?>" class="btn-outline-custom">
        <i class="fa-solid fa-calendar-check"></i>
        View My Study Plan
    </a>

    <a href="<?php echo e($profilePage); ?>" class="btn-outline-custom">
        <i class="fa-solid fa-pen-to-square"></i>
        Update Profile
    </a>

</div>
    </section>

    <!-- SUMMARY CARDS -->
    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Daily Study Goal</span>
                <span class="stat-icon">
                    <i class="fa-regular fa-clock"></i>
                </span>
            </div>

            <div class="stat-number">
                <?php if ($studyHours !== ''): ?>
                    <?php echo e($studyHours); ?>
                    <span style="font-size:12px;">hrs</span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>

            <div class="small-muted mt-2">Study hours per day</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Subjects Added</span>
                <span class="stat-icon">
                    <i class="fa-solid fa-book-open"></i>
                </span>
            </div>

            <div class="stat-number"><?php echo count($subjects); ?></div>
            <div class="small-muted mt-2">Saved subject records</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Upcoming Exams</span>
                <span class="stat-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
            </div>

            <div class="stat-number"><?php echo count($upcomingExams); ?></div>
            <div class="small-muted mt-2">Saved exam records</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <span class="stat-label">Pending Assignments</span>
                <span class="stat-icon">
                    <i class="fa-solid fa-list-check"></i>
                </span>
            </div>

            <div class="stat-number"><?php echo $pendingAssignments; ?></div>
            <div class="small-muted mt-2">Tasks to complete or submit</div>
        </div>

    </section>

    <!-- MAIN CONTENT -->
    <section class="content-grid">

        <div>

            <!-- STUDENT DETAILS -->
            <div class="panel" id="student-details">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-user-graduate"></i>
                        </span>
                        Student Details
                    </h3>
                </div>

                <?php
                detailRow('Full Name', $name);
                detailRow('Email Address', $email);
                detailRow('Age', $student['age'], ' years');
                detailRow('Gender', $student['gender']);
                detailRow('School Name', $student['school_name']);
                detailRow('Class', $student['class_name']);
                detailRow('Board', $student['board']);
                detailRow('Medium of Instruction', $student['medium']);
                ?>
            </div>

            <!-- COLLEGE DETAILS -->
            <div class="panel" id="college-details">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-building-columns"></i>
                        </span>
                        College & Course Details
                    </h3>
                </div>

                <?php
                detailRow('College Name', $student['college_name']);
                detailRow('Course Name', $student['course_name']);
                detailRow('Course Year', $student['course_year']);
                detailRow('Semester', $student['semester']);
                ?>
            </div>

            <!-- SUBJECTS -->
            <div class="panel" id="subjects">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-book-open"></i>
                        </span>
                        Subjects & Topics
                    </h3>

                    <span class="small-muted">
                        <?php echo count($subjects); ?> subjects
                    </span>
                </div>

                <p class="small-muted mb-3">
                    Subjects, marks, knowledge level, remaining topics,
                    difficulty, and estimated study time.
                </p>

                <?php renderRecords($subjects, 'subjects'); ?>

                <a href="<?php echo e($profilePage); ?>" class="btn-outline-custom mt-3">
                    <i class="fa-solid fa-plus"></i> Update Subjects
                </a>
            </div>

            <!-- EXAMS -->
            <div class="panel" id="exams">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </span>
                        Upcoming Exams
                    </h3>

                    <span class="small-muted">
                        <?php echo count($upcomingExams); ?> exams
                    </span>
                </div>

                <?php renderRecords($upcomingExams, 'exams'); ?>

                <?php if ($nextExam !== null): ?>
                    <div class="exam-box mt-3">
                        <div class="small-muted mb-2">NEXT EXAM</div>

                        <h4>
                            <?php
                            echo e(getField(
                                $nextExam,
                                ['exam_name', 'title', 'name'],
                                'Upcoming Examination'
                            ));
                            ?>
                        </h4>

                        <p class="small-muted mb-0">
                            <i class="fa-regular fa-calendar"></i>
                            <?php
                            echo e(formatDateValue(
                                getField($nextExam, ['exam_date', 'date'])
                            ));
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <a href="<?php echo e($profilePage); ?>" class="btn-outline-custom mt-3">
                    <i class="fa-solid fa-plus"></i> Update Exams
                </a>
            </div>

            <!-- ASSIGNMENTS -->
            <div class="panel" id="assignments">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-file-lines"></i>
                        </span>
                        Assignments & Deadlines
                    </h3>

                    <span class="small-muted">
                        <?php echo count($assignments); ?> tasks
                    </span>
                </div>

                <p class="small-muted mb-3">
                    Assignment due dates, priority, related subject,
                    estimated time, and completion status.
                </p>

                <?php renderRecords($assignments, 'assignments'); ?>

                <a href="<?php echo e($profilePage); ?>" class="btn-outline-custom mt-3">
                    <i class="fa-solid fa-plus"></i> Update Assignments
                </a>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div>

            <!-- PROFILE SUMMARY -->
            <div class="panel">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-id-badge"></i>
                        </span>
                        Profile Summary
                    </h3>
                </div>

                <div class="profile-summary">
                    <div class="avatar large-avatar">
                        <?php if ($photoPath !== ''): ?>
                            <img
                                src="<?php echo e($photoPath); ?>"
                                alt="Profile photo"
                                onerror="this.style.display='none';this.parentElement.textContent='S';">
                        <?php else: ?>
                            <?php echo e($initial); ?>
                        <?php endif; ?>
                    </div>

                    <h3><?php echo e($name); ?></h3>
                    <p><?php echo e($email); ?></p>

                    <span class="tag">
                        <?php echo e($student['target_exam'] ?: 'Student'); ?>
                    </span>
                </div>

                <?php
                detailRow('Target Examination', $student['target_exam']);
                detailRow('Main Exam Date', formatDateValue($examDate));
                detailRow('Profile Created', formatDateValue($student['created_at']));
                ?>

                <?php if ($daysLeft !== null): ?>
                    <div class="exam-box mt-3">
                        <div class="small-muted mb-1">MAIN EXAM COUNTDOWN</div>

                        <h4 style="color:var(--cyan);">
                            <?php
                            if ($daysLeft > 0) {
                                echo e($daysLeft) . ' days left';
                            } elseif ($daysLeft === 0) {
                                echo 'Exam is today!';
                            } else {
                                echo 'Exam date has passed';
                            }
                            ?>
                        </h4>

                        <div class="small-muted">
                            <?php echo e(formatDateValue($examDate)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="<?php echo e($profilePage); ?>" class="btn-gradient w-100 mt-3">
                    <i class="fa-solid fa-pen"></i> Edit Profile
                </a>
            </div>

            <!-- AI STUDY PLANNER QUICK ACTIONS -->
            <div class="panel">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </span>
                        AI Study Planner
                    </h3>
                </div>

                <p class="small-muted mb-3">
                    Create and review a structured study routine based on
                    your subjects, study time, weak areas, and exam dates.
                </p>

                <a class="quick-action" href="<?php echo e($generatePlanPage); ?>">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>

                    <div>
                        <strong>Generate a Study Plan</strong>
                        <small>Create a schedule around your study goals.</small>
                    </div>
                </a>

                <a class="quick-action" href="<?php echo e($viewPlanPage); ?>">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>

                    <div>
                        <strong>View My Study Plan</strong>
                        <small>Review your saved study schedule.</small>
                    </div>
                </a>

                <a class="quick-action" href="<?php echo e($profilePage); ?>">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-sliders"></i>
                    </div>

                    <div>
                        <strong>Personalize Preferences</strong>
                        <small>Update study hours, breaks, and methods.</small>
                    </div>
                </a>
            </div>

            <!-- STUDY PREFERENCES -->
            <div class="panel" id="preferences">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-sliders"></i>
                        </span>
                        Study Preferences
                    </h3>
                </div>

                <?php
                detailRow('Daily Study Hours', $studyHours, ' hours');
                detailRow('Preferred Study Time', $studyTime);
                detailRow('Session Duration', $sessionDuration, ' minutes');
                detailRow('Break Duration', $breakDuration, ' minutes');
                detailRow('Target Percentage', $targetPercentage, '%');
                detailRow('Revision Preference', $student['revision_preference']);
                ?>

                <div class="fw-bold mt-4 mb-2" style="font-size:13px;">
                    Preferred Study Methods
                </div>

                <?php if (!empty($studyMethods)): ?>
                    <?php foreach ($studyMethods as $method): ?>
                        <div class="method-row">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>
                                <?php
                                echo e(is_array($method)
                                    ? getField($method, ['method', 'name', 'title'], 'Study method')
                                    : $method);
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        No study methods have been saved yet.
                    </div>
                <?php endif; ?>

                <div class="fw-bold mt-4 mb-2" style="font-size:13px;">
                    Subjects Needing More Attention
                </div>

                <?php
                renderChips(
                    $weakSubjects,
                    ['subject_name', 'name', 'subject', 'title']
                );
                ?>
            </div>

            <!-- WEEKLY AVAILABILITY -->
            <div class="panel" id="availability">
                <div class="panel-heading">
                    <h3>
                        <span class="section-icon">
                            <i class="fa-solid fa-calendar-week"></i>
                        </span>
                        Weekly Availability
                    </h3>
                </div>

                <div class="fw-bold mb-3" style="font-size:13px;">
                    Available Days
                </div>

                <?php renderChips($availableDays, ['day', 'name', 'title']); ?>

                <hr style="border-color:var(--border);">

                <div class="fw-bold mb-3" style="font-size:13px;">
                    Weekly Availability Details
                </div>

                <?php if (!empty($weeklyData)): ?>
                    <?php
                    $keys = array_keys($weeklyData);
                    $isList = $keys === range(0, count($weeklyData) - 1);

                    if (!$isList) {
                        foreach ($weeklyData as $day => $availability) {
                            echo '<div class="detail-row">';

                            echo '<span class="detail-label">' .
                                e(ucwords(str_replace('_', ' ', (string)$day))) .
                                '</span>';

                            echo '<span class="detail-value">' .
                                e(formatValue((string)$day, $availability)) .
                                '</span>';

                            echo '</div>';
                        }
                    } else {
                        foreach ($weeklyData as $item) {
                            echo '<div class="method-row">';
                            echo '<i class="fa-regular fa-clock"></i>';

                            echo '<span>' .
                                e(is_array($item)
                                    ? json_encode($item, JSON_UNESCAPED_UNICODE)
                                    : $item) .
                                '</span>';

                            echo '</div>';
                        }
                    }
                    ?>
                <?php else: ?>
                    <p class="small-muted mb-0">
                        No weekly availability details have been saved yet.
                    </p>
                <?php endif; ?>

                <a href="<?php echo e($profilePage); ?>" class="btn-outline-custom mt-3">
                    <i class="fa-solid fa-calendar-plus"></i>
                    Update Availability
                </a>
            </div>

            <!-- ACADEMIC TARGET -->
            <?php if ($targetPercentage !== '' && is_numeric($targetPercentage)): ?>
                <?php
                $safeTarget = max(0, min(100, (float)$targetPercentage));
                ?>

                <div class="panel">
                    <div class="panel-heading">
                        <h3>
                            <span class="section-icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </span>
                            Academic Target
                        </h3>
                    </div>

                    <div class="d-flex justify-content-between gap-2">
                        <span class="small-muted">Target percentage</span>
                        <strong><?php echo e($targetPercentage); ?>%</strong>
                    </div>

                    <div class="progress-track">
                        <div class="progress-fill"
                             style="width:<?php echo $safeTarget; ?>%;"></div>
                    </div>

                    <p class="small-muted mb-0">
                        This is your desired score, not your actual examination result.
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <footer class="footer">
        © <?php echo date('Y'); ?> MindAI Study Planner.
        Learn smarter, one session at a time.
    </footer>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
