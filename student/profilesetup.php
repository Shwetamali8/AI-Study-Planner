
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli("localhost", "root", "", "study_planner");

if ($conn->connect_error) {
    die("Database connection failed. Please check your database settings.");
}

$conn->set_charset("utf8mb4");

$userId = (int)$_SESSION['user_id'];
$errorMessage = "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function postValue($key, $default = '') {
    return trim((string)($_POST[$key] ?? $default));
}

function jsonArray($value, $default = []) {
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : $default;
}

function isSelected($actual, $expected) {
    return (string)$actual === (string)$expected ? 'selected' : '';
}

function validDate($date) {
    if ($date === '') {
        return true;
    }

    $d = DateTime::createFromFormat('!Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

/* Bind parameters dynamically. All values are passed as strings;
   MySQL converts numeric strings to the column's numeric type. */
function bindAll($stmt, &$values) {
    if (count($values) === 0) {
        return true;
    }

    $types = str_repeat('s', count($values));
    $references = [];

    foreach ($values as $key => &$value) {
        $references[$key] = &$value;
    }

    array_unshift($references, $types);

    return call_user_func_array([$stmt, 'bind_param'], $references);
}

/* -------------------------------------------------------
   LOAD EXISTING PROFILE
------------------------------------------------------- */

$profile = [];

$load = $conn->prepare(
    "SELECT * FROM student_profilenew WHERE user_id = ? LIMIT 1"
);

if (!$load) {
    die("Could not load profile. Check the student_profilenew table.");
}

$load->bind_param("i", $userId);
$load->execute();

$profile = $load->get_result()->fetch_assoc() ?: [];
$load->close();

$subjectsRows = jsonArray($profile['subjects_data'] ?? '[]');
$deadlinesRows = jsonArray($profile['assignment_deadlines'] ?? '[]');

$savedDays = jsonArray($profile['available_days'] ?? '[]');
$savedWeeklyHours = jsonArray($profile['weekly_availability'] ?? '{}');

$plannerPreferences = jsonArray(
    $profile['planner_preferences'] ?? '{}'
);

/* Recover subject exam dates from older saved exam data. */
$oldExams = jsonArray($profile['upcoming_exams'] ?? '[]');

foreach ($subjectsRows as &$subjectRow) {
    if (!isset($subjectRow['exam_date'])) {
        $subjectRow['exam_date'] = '';

        foreach ($oldExams as $oldExam) {
            if (
                isset($oldExam['name'], $subjectRow['name']) &&
                strcasecmp(
                    trim((string)$oldExam['name']),
                    trim((string)$subjectRow['name'])
                ) === 0
            ) {
                $subjectRow['exam_date'] = $oldExam['date'] ?? '';
                break;
            }
        }
    }

    $subjectRow['topic_difficulty'] =
        $subjectRow['topic_difficulty'] ?? '';

    $subjectRow['topic_status'] =
        $subjectRow['topic_status'] ?? 'Not Started';

    $subjectRow['topic_hours'] =
        $subjectRow['topic_hours'] ?? '';

    $subjectRow['quiz_score'] =
        $subjectRow['quiz_score'] ?? '';
}
unset($subjectRow);

if (!$subjectsRows) {
    $subjectsRows = [[
        'name' => '',
        'marks' => '',
        'knowledge' => '',
        'remaining' => '',
        'exam_date' => '',
        'topic_difficulty' => '',
        'topic_status' => 'Not Started',
        'topic_hours' => '',
        'quiz_score' => ''
    ]];
}

if (!$deadlinesRows) {
    $deadlinesRows = [[
        'title' => '',
        'type' => '',
        'date' => '',
        'priority' => 'Medium',
        'estimated_hours' => ''
    ]];
}

$selectedMethods = jsonArray($profile['study_methods'] ?? '[]');

/* -------------------------------------------------------
   SAVE PROFILE
------------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_profile'])
) {
    $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
    $gender = postValue('gender');

    $collegeName = postValue('college_name');
    $courseName = postValue('course_name');
    $courseYear = postValue('course_year');
    $semester = postValue('semester');

    $schoolName = postValue('school_name');
    $className = postValue('class_name');
    $board = postValue('board');
    $targetExam = postValue('target_exam');
    $examDate = postValue('exam_date');
    $medium = postValue('medium');

    $studyHours = filter_var(
        $_POST['study_hours'] ?? null,
        FILTER_VALIDATE_INT
    );

    $studyTime = postValue('study_time');

    $sessionDuration = filter_var(
        $_POST['session_duration'] ?? null,
        FILTER_VALIDATE_INT
    );

    $breakDuration = filter_var(
        $_POST['break_duration'] ?? null,
        FILTER_VALIDATE_INT
    );

    $targetPercentage = filter_var(
        $_POST['target_percentage'] ?? null,
        FILTER_VALIDATE_INT
    );

    $weakSubjects = postValue('weak_subjects');
    $revisionPreference = postValue('revision_preference');

    /* New planner schedule preferences */
    $preferredStartTime = postValue('preferred_start_time', '18:00');

    $collegeHours = filter_var(
        $_POST['college_hours'] ?? 6,
        FILTER_VALIDATE_INT
    );

    $otherUnavailableHours = filter_var(
        $_POST['other_unavailable_hours'] ?? 2,
        FILTER_VALIDATE_INT
    );

    $maxSessionsPerDay = filter_var(
        $_POST['max_sessions_per_day'] ?? 4,
        FILTER_VALIDATE_INT
    );

    $restDay = postValue('rest_day', 'Sunday');

    if ($examDate === '') {
        $examDate = null;
    }

    $allowedGenders = [
        'Female', 'Male', 'Other', 'Prefer not to say'
    ];

    $allowedTimes = [
        'Morning', 'Afternoon', 'Evening', 'Night'
    ];

    $allowedSessions = [25, 45, 60, 90];
    $allowedBreaks = [5, 10, 15, 20];

    $allowedMethods = [
        'Reading',
        'Practice Questions',
        'Video Lectures',
        'Revision',
        'Mock Tests'
    ];

    $allowedKnowledge = [
        'Beginner', 'Intermediate', 'Advanced'
    ];

    $allowedRevision = [
        'Daily Short Revision',
        'Weekly Revision',
        'Spaced Repetition',
        'Practice Tests',
        'Mixed Revision'
    ];

    $allowedDays = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday'
    ];

    $allowedStartTimes = [
        '05:00', '06:00', '07:00', '08:00',
        '09:00', '10:00', '12:00', '14:00',
        '16:00', '18:00', '20:00', '21:00'
    ];

    $allowedRestDays = [
        'None', 'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday', 'Sunday'
    ];

    /* Study methods */
    $methods = $_POST['study_methods'] ?? [];

    if (!is_array($methods)) {
        $methods = [];
    }

    $methods = array_values(
        array_intersect($allowedMethods, $methods)
    );

    $studyMethods = json_encode(
        $methods,
        JSON_UNESCAPED_UNICODE
    );

    /* Basic validation */
    if ($age === false || $age < 5 || $age > 100) {
        $errorMessage = "Enter a valid age between 5 and 100.";

    } elseif (
        $gender !== '' &&
        !in_array($gender, $allowedGenders, true)
    ) {
        $errorMessage = "Select a valid gender.";

    } elseif (
        $collegeName === '' ||
        $courseName === '' ||
        $courseYear === '' ||
        $semester === ''
    ) {
        $errorMessage = "Complete the required college and course details.";

    } elseif (
        $studyHours === false ||
        $studyHours < 1 ||
        $studyHours > 12 ||
        !in_array($studyTime, $allowedTimes, true) ||
        !in_array($sessionDuration, $allowedSessions, true) ||
        !in_array($breakDuration, $allowedBreaks, true) ||
        $targetPercentage === false ||
        $targetPercentage < 1 ||
        $targetPercentage > 100
    ) {
        $errorMessage = "Enter valid study preferences.";

    } elseif (
        $revisionPreference !== '' &&
        !in_array($revisionPreference, $allowedRevision, true)
    ) {
        $errorMessage = "Select a valid revision preference.";

    } elseif (
        !in_array($preferredStartTime, $allowedStartTimes, true)
    ) {
        $errorMessage = "Select a valid preferred study start time.";

    } elseif (
        $collegeHours === false ||
        $collegeHours < 0 ||
        $collegeHours > 16
    ) {
        $errorMessage = "College hours must be between 0 and 16.";

    } elseif (
        $otherUnavailableHours === false ||
        $otherUnavailableHours < 0 ||
        $otherUnavailableHours > 16
    ) {
        $errorMessage = "Other busy hours must be between 0 and 16.";

    } elseif (
        $maxSessionsPerDay === false ||
        $maxSessionsPerDay < 1 ||
        $maxSessionsPerDay > 12
    ) {
        $errorMessage = "Choose between 1 and 12 sessions per day.";

    } elseif (
        !in_array($restDay, $allowedRestDays, true)
    ) {
        $errorMessage = "Select a valid rest day.";

    } elseif ($examDate !== null && !validDate($examDate)) {
        $errorMessage = "Enter a valid overall exam date.";
    }

    /* ---------------------------------------------------
       SUBJECTS AND TOPIC INFORMATION
    --------------------------------------------------- */

    $subjectsInput = $_POST['subjects'] ?? [];
    $subjectsArray = [];

    if ($errorMessage === '' && is_array($subjectsInput)) {
        foreach ($subjectsInput as $subject) {
            if (!is_array($subject)) {
                continue;
            }

            $name = trim((string)($subject['name'] ?? ''));
            $marksText = trim((string)($subject['marks'] ?? ''));
            $knowledge = trim((string)($subject['knowledge'] ?? ''));
            $remaining = trim((string)($subject['remaining'] ?? ''));
            $subjectExamDate = trim(
                (string)($subject['exam_date'] ?? '')
            );

            $difficulty = trim(
                (string)($subject['topic_difficulty'] ?? '')
            );

            $topicStatus = trim(
                (string)($subject['topic_status'] ?? 'Not Started')
            );

            $topicHoursText = trim(
                (string)($subject['topic_hours'] ?? '')
            );

            $quizScoreText = trim(
                (string)($subject['quiz_score'] ?? '')
            );

            if (
                $name === '' &&
                $marksText === '' &&
                $knowledge === '' &&
                $remaining === '' &&
                $subjectExamDate === '' &&
                $difficulty === '' &&
                $topicHoursText === '' &&
                $quizScoreText === ''
            ) {
                continue;
            }

            if ($name === '') {
                $errorMessage = "Enter a name for each subject.";
                break;
            }

            $marks = null;

            if ($marksText !== '') {
                if (
                    !is_numeric($marksText) ||
                    (float)$marksText < 0 ||
                    (float)$marksText > 100
                ) {
                    $errorMessage = "Subject marks must be between 0 and 100.";
                    break;
                }

                $marks = (float)$marksText;
            }

            if (
                $knowledge !== '' &&
                !in_array($knowledge, $allowedKnowledge, true)
            ) {
                $errorMessage = "Select a valid knowledge level.";
                break;
            }

            if (
                $difficulty !== '' &&
                !in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)
            ) {
                $errorMessage = "Select a valid topic difficulty.";
                break;
            }

            if (!in_array(
                $topicStatus,
                ['Not Started', 'In Progress', 'Completed'],
                true
            )) {
                $errorMessage = "Select a valid syllabus completion status.";
                break;
            }

            if (!validDate($subjectExamDate)) {
                $errorMessage = "Enter a valid exam date for " . $name . ".";
                break;
            }

            $topicHours = null;

            if ($topicHoursText !== '') {
                if (
                    !is_numeric($topicHoursText) ||
                    (float)$topicHoursText < 0.5 ||
                    (float)$topicHoursText > 100
                ) {
                    $errorMessage = "Topic study time must be between 0.5 and 100 hours.";
                    break;
                }

                $topicHours = (float)$topicHoursText;
            }

            $quizScore = null;

            if ($quizScoreText !== '') {
                if (
                    !is_numeric($quizScoreText) ||
                    (float)$quizScoreText < 0 ||
                    (float)$quizScoreText > 100
                ) {
                    $errorMessage = "Quiz score must be between 0 and 100.";
                    break;
                }

                $quizScore = (float)$quizScoreText;
            }

            $subjectsArray[] = [
                'name' => $name,
                'marks' => $marks,
                'knowledge' => $knowledge,
                'remaining' => $remaining,
                'exam_date' => $subjectExamDate,
                'topic_difficulty' => $difficulty,
                'topic_status' => $topicStatus,
                'topic_hours' => $topicHours,
                'quiz_score' => $quizScore
            ];
        }
    }

    if ($errorMessage === '' && count($subjectsArray) === 0) {
        $errorMessage = "Please enter at least one subject.";
    }

    $subjectsData = json_encode(
        $subjectsArray,
        JSON_UNESCAPED_UNICODE
    );

    /* Keep upcoming_exams compatible with your dashboard */
    $examsArray = [];

    foreach ($subjectsArray as $subject) {
        if (!empty($subject['exam_date'])) {
            $examsArray[] = [
                'name' => $subject['name'],
                'date' => $subject['exam_date']
            ];
        }
    }

    $upcomingExams = json_encode(
        $examsArray,
        JSON_UNESCAPED_UNICODE
    );

    /* ---------------------------------------------------
       ASSIGNMENTS, PRACTICALS AND PROJECT DEADLINES
    --------------------------------------------------- */

    $deadlinesInput = $_POST['deadlines'] ?? [];
    $deadlinesArray = [];

    $allowedDeadlineTypes = [
        'Assignment', 'Practical', 'Project'
    ];

    $allowedPriorities = ['Low', 'Medium', 'High'];

    if ($errorMessage === '' && is_array($deadlinesInput)) {
        foreach ($deadlinesInput as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string)($item['title'] ?? ''));
            $type = trim((string)($item['type'] ?? ''));
            $date = trim((string)($item['date'] ?? ''));

            $priority = trim(
                (string)($item['priority'] ?? 'Medium')
            );

            $estimatedHoursText = trim(
                (string)($item['estimated_hours'] ?? '')
            );

            if (
                $title === '' &&
                $type === '' &&
                $date === '' &&
                $estimatedHoursText === ''
            ) {
                continue;
            }

            if ($title === '' || $type === '' || $date === '') {
                $errorMessage =
                    "Complete each deadline's title, type and date.";
                break;
            }

            if (!in_array($type, $allowedDeadlineTypes, true)) {
                $errorMessage = "Select a valid deadline type.";
                break;
            }

            if (!in_array($priority, $allowedPriorities, true)) {
                $errorMessage = "Select a valid deadline priority.";
                break;
            }

            if (!validDate($date)) {
                $errorMessage = "Enter a valid deadline date.";
                break;
            }

            $estimatedHours = null;

            if ($estimatedHoursText !== '') {
                if (
                    !is_numeric($estimatedHoursText) ||
                    (float)$estimatedHoursText < 0.5 ||
                    (float)$estimatedHoursText > 200
                ) {
                    $errorMessage =
                        "Estimated assignment time must be between 0.5 and 200 hours.";
                    break;
                }

                $estimatedHours = (float)$estimatedHoursText;
            }

            $deadlinesArray[] = [
                'title' => $title,
                'type' => $type,
                'date' => $date,
                'priority' => $priority,
                'estimated_hours' => $estimatedHours
            ];
        }
    }

    $assignmentDeadlines = json_encode(
        $deadlinesArray,
        JSON_UNESCAPED_UNICODE
    );

    /* ---------------------------------------------------
       WEEKLY AVAILABILITY
    --------------------------------------------------- */

    $daysInput = $_POST['available_days'] ?? [];

    if (!is_array($daysInput)) {
        $daysInput = [];
    }

    $availableDays = array_values(
        array_intersect($allowedDays, $daysInput)
    );

    $hoursInput = $_POST['weekly_hours'] ?? [];

    if (!is_array($hoursInput)) {
        $hoursInput = [];
    }

    $weeklyAvailabilityArray = [];

    foreach ($availableDays as $day) {
        $hours = filter_var(
            $hoursInput[$day] ?? 2,
            FILTER_VALIDATE_INT
        );

        if ($hours === false || $hours < 1 || $hours > 12) {
            $errorMessage =
                "Weekly study hours must be between 1 and 12.";
            break;
        }

        $weeklyAvailabilityArray[$day] = $hours;
    }

    if ($errorMessage === '' && count($availableDays) === 0) {
        $errorMessage = "Select at least one available study day.";
    }

    $availableDaysJson = json_encode(
        $availableDays,
        JSON_UNESCAPED_UNICODE
    );

    $weeklyAvailability = json_encode(
        $weeklyAvailabilityArray,
        JSON_UNESCAPED_UNICODE
    );

    /* Save additional schedule settings as JSON */
    $plannerPreferences = [
        'preferred_start_time' => $preferredStartTime,
        'college_hours' => $collegeHours,
        'other_unavailable_hours' => $otherUnavailableHours,
        'max_sessions_per_day' => $maxSessionsPerDay,
        'rest_day' => $restDay
    ];

    $plannerPreferencesJson = json_encode(
        $plannerPreferences,
        JSON_UNESCAPED_UNICODE
    );

    /* ---------------------------------------------------
       PROFILE PHOTO
    --------------------------------------------------- */

    $photoPath = (string)($profile['profile_photo'] ?? '');

    if (
        $errorMessage === '' &&
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = "Photo upload failed. Please try again.";

        } elseif ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
            $errorMessage = "Photo must be 2 MB or smaller.";

        } else {
            $tmpFile = $_FILES['profile_photo']['tmp_name'];
            $imageInfo = @getimagesize($tmpFile);

            $allowedMime = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $mime = $imageInfo['mime'] ?? '';

            if (!$imageInfo || !isset($allowedMime[$mime])) {
                $errorMessage = "Upload a JPG, PNG or WEBP image.";

            } else {
                $uploadDir = __DIR__ . '/../uploads/profile_photos/';

                if (
                    !is_dir($uploadDir) &&
                    !mkdir($uploadDir, 0755, true)
                ) {
                    $errorMessage = "Could not create the photo folder.";

                } else {
                    $fileName = 'student_' . $userId . '_' .
                        bin2hex(random_bytes(6)) . '.' .
                        $allowedMime[$mime];

                    if (!move_uploaded_file(
                        $tmpFile,
                        $uploadDir . $fileName
                    )) {
                        $errorMessage = "Could not save the uploaded photo.";
                    } else {
                        $photoPath =
                            '../uploads/profile_photos/' . $fileName;
                    }
                }
            }
        }
    }

    /* ---------------------------------------------------
       SAVE TO DATABASE
    --------------------------------------------------- */

    if ($errorMessage === '') {
        $data = [
            'age' => $age,
            'gender' => $gender,
            'college_name' => $collegeName,
            'course_name' => $courseName,
            'course_year' => $courseYear,
            'semester' => $semester,
            'school_name' => $schoolName,
            'class_name' => $className,
            'board' => $board,
            'target_exam' => $targetExam,
            'exam_date' => $examDate,
            'medium' => $medium,
            'study_hours' => $studyHours,
            'study_time' => $studyTime,
            'session_duration' => $sessionDuration,
            'break_duration' => $breakDuration,
            'target_percentage' => $targetPercentage,
            'study_methods' => $studyMethods,
            'weak_subjects' => $weakSubjects,
            'profile_photo' => $photoPath,
            'subjects_data' => $subjectsData,
            'upcoming_exams' => $upcomingExams,
            'assignment_deadlines' => $assignmentDeadlines,
            'revision_preference' => $revisionPreference,
            'available_days' => $availableDaysJson,
            'weekly_availability' => $weeklyAvailability,
            'planner_preferences' => $plannerPreferencesJson
        ];

        $check = $conn->prepare(
            "SELECT id FROM student_profilenew
             WHERE user_id = ? LIMIT 1"
        );

        if (!$check) {
            die("Could not check profile. Verify your database table.");
        }

        $check->bind_param("i", $userId);
        $check->execute();

        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $setParts = [];

            foreach (array_keys($data) as $column) {
                $setParts[] = "`" . $column . "` = ?";
            }

            $sql = "UPDATE student_profilenew SET " .
                implode(', ', $setParts) .
                " WHERE user_id = ?";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                die("Could not prepare profile update. Check database columns.");
            }

            $values = array_values($data);
            $values[] = $userId;

        } else {
            $columns = array_keys($data);
            array_unshift($columns, 'user_id');

            $placeholders = array_fill(0, count($columns), '?');

            $sql = "INSERT INTO student_profilenew (`" .
                implode('`, `', $columns) .
                "`) VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                die("Could not prepare profile insert. Check database columns.");
            }

            $values = array_values($data);
            array_unshift($values, $userId);
        }

        if (!bindAll($stmt, $values)) {
            die("Could not bind profile values.");
        }

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();

            header("Location: newdashboard.php");
            exit();
        }

        $errorMessage =
            "Could not save your profile: " . $stmt->error;

        $stmt->close();

        /* Preserve the submitted data if validation or saving fails. */
        $profile = array_merge($profile, $_POST);

        if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
            $subjectsRows = $_POST['subjects'];
        }

        if (isset($_POST['deadlines']) && is_array($_POST['deadlines'])) {
            $deadlinesRows = $_POST['deadlines'];
        }

        $savedDays = $availableDays ?? [];
        $savedWeeklyHours = $weeklyAvailabilityArray ?? [];
        $selectedMethods = $methods ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MindAI | Profile Setup</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
*{box-sizing:border-box}
body{
    margin:0;font-family:Inter,sans-serif;color:#f8fafc;
    background:radial-gradient(ellipse at 10% 10%,rgba(37,99,235,.16),transparent 40%),
    radial-gradient(ellipse at 90% 35%,rgba(6,182,212,.12),transparent 35%),#050816;
    min-height:100vh
}
.navbar-mindai{
    position:fixed;top:16px;left:5%;width:90%;z-index:1000;
    background:rgba(255,255,255,.08);backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:12px 25px
}
.brand{font-size:25px;font-weight:800;color:white;text-decoration:none}
.brand span{color:#38bdf8}
.navbar-mindai .nav-link{color:#ddd!important;margin:0 10px}
.navbar-mindai .nav-link:hover{color:#38bdf8!important}
.profile-page{padding:130px 0 60px}
.profile-heading{text-align:center;max-width:760px;margin:0 auto 35px}
.eyebrow{
    display:inline-block;color:#67e8f9;background:rgba(6,182,212,.1);
    border:1px solid rgba(56,189,248,.25);border-radius:30px;
    padding:8px 16px;font-size:12px;font-weight:700;letter-spacing:1px
}
.profile-heading h1{font-size:clamp(30px,5vw,48px);font-weight:800;margin-top:18px}
.gradient-text{
    background:linear-gradient(90deg,#22d3ee,#8b5cf6);
    -webkit-background-clip:text;background-clip:text;color:transparent
}
.profile-heading p{color:#cbd5e1;line-height:1.8;margin-top:15px}
.study-preferences{
    overflow:hidden;background:rgba(255,255,255,.055);
    border:1px solid rgba(255,255,255,.13);border-radius:25px;
    box-shadow:0 25px 70px rgba(0,0,0,.22);margin-bottom:25px
}
.study-preferences:before{
    content:"";display:block;height:4px;
    background:linear-gradient(90deg,#06b6d4,#6366f1,#8b5cf6)
}
.preferences-content{padding:30px}
.preferences-header{
    display:flex;align-items:center;gap:16px;padding:20px;margin-bottom:25px;
    background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.08);
    border-radius:17px
}
.header-icon{
    width:58px;height:58px;min-width:58px;display:flex;align-items:center;
    justify-content:center;border-radius:16px;color:white;font-size:25px;
    background:linear-gradient(135deg,#06b6d4,#6366f1)
}
.preferences-header h4{margin:0 0 7px;font-size:21px;font-weight:800}
.preferences-header p{color:#94a3b8;font-size:13px;line-height:1.6;margin:0}
.section-heading{
    display:flex;align-items:center;gap:10px;margin:15px 0 10px;
    font-size:17px;font-weight:750
}
.heading-icon{
    width:36px;height:36px;display:inline-flex;align-items:center;
    justify-content:center;border-radius:11px;background:rgba(99,102,241,.15);
    color:#67e8f9
}
.helper-text{display:block;color:#94a3b8;font-size:12px;line-height:1.7;margin-top:8px}
.field-box{margin-bottom:20px}
.form-label{display:block;color:#e2e8f0;font-size:13px;font-weight:650;margin-bottom:9px}
.form-control,.form-select{
    width:100%;min-height:48px;padding:12px 14px;color:#f8fafc;
    background-color:rgba(15,23,42,.85);border:1px solid rgba(148,163,184,.27);
    border-radius:12px;box-shadow:none;font-size:14px
}
.form-control::placeholder{color:#94a3b8}
.form-control:focus,.form-select:focus{
    color:#fff;background-color:#0f172a;border-color:#22d3ee;
    box-shadow:0 0 0 3px rgba(34,211,238,.13)
}
.form-select option{color:#f8fafc;background:#0f172a}
.study-methods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px;margin-top:16px}
.method-option{
    display:flex;align-items:center;gap:11px;min-height:55px;padding:13px;
    border:1px solid rgba(148,163,184,.22);border-radius:14px;
    background:rgba(255,255,255,.035);cursor:pointer
}
.method-option:has(input:checked){border-color:#22d3ee;background:rgba(6,182,212,.12)}
.method-option input{width:17px;height:17px;accent-color:#22d3ee}
.method-option span{color:#e2e8f0;font-size:12px;font-weight:600}
.dynamic-entry{
    background:rgba(255,255,255,.035);border:1px solid rgba(148,163,184,.20);
    border-radius:16px;padding:18px;margin-bottom:16px
}
.dynamic-entry .field-box{margin-bottom:12px}
.dynamic-entry textarea.form-control{min-height:75px;resize:vertical}
.subject-card-title{
    display:flex;align-items:center;justify-content:space-between;
    gap:10px;margin-bottom:16px;color:#67e8f9;font-weight:750
}
.profile-submit{
    display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
    padding:15px 25px;border:0;border-radius:30px;
    background:linear-gradient(90deg,#06b6d4,#6366f1);
    color:white;font-size:15px;font-weight:750;cursor:pointer;transition:.2s
}
.profile-submit:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(37,99,235,.25)}
.photo-preview{
    width:76px;height:76px;object-fit:cover;border-radius:50%;
    border:2px solid #22d3ee;margin-bottom:12px
}
.profile-footer{padding:25px 15px;text-align:center;color:#94a3b8;background:#020617;font-size:13px}
.profile-footer h5{color:white;font-weight:750}
hr{opacity:.25}
@media(max-width:768px){
    .profile-page{padding-top:115px}.navbar-mindai{padding:10px 15px}
    .preferences-content{padding:20px}.study-methods{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:480px){
    .preferences-content{padding:15px}.study-methods{grid-template-columns:1fr}
    .preferences-header{align-items:flex-start;padding:15px}
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-mindai">
    <div class="container-fluid">
        <a class="brand" href="../index.php"><span>◈ Mind</span>AI</a>
        <button class="navbar-toggler bg-light" type="button"
            data-bs-toggle="collapse" data-bs-target="#profileMenu"
            aria-controls="profileMenu" aria-expanded="false"
            aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="profileMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="../features.php">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="../aboutus.php">About</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="profile-page">
<div class="container">

<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
<?php endif; ?>

<header class="profile-heading">
    <span class="eyebrow">YOUR PERSONAL LEARNING JOURNEY</span>
    <h1>Set Up Your <span class="gradient-text">AI Study Profile</span></h1>
    <p>Share your academic details, subjects, deadlines and availability so MindAI can help build a personalized study routine.</p>
</header>

<form method="POST" enctype="multipart/form-data">

<!-- COLLEGE DETAILS -->
<section class="study-preferences">
<div class="preferences-content">
    <div class="preferences-header">
        <div class="header-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <div><h4>College &amp; Course Details</h4><p>Your academic background.</p></div>
    </div>

    <div class="row">
        <div class="col-md-6 field-box">
            <label class="form-label" for="college_name">College Name *</label>
            <input class="form-control" id="college_name" name="college_name" maxlength="150" required
                value="<?php echo h($profile['college_name'] ?? ''); ?>" placeholder="Enter college name">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="course_name">Course Name *</label>
            <input class="form-control" id="course_name" name="course_name" maxlength="100" required
                value="<?php echo h($profile['course_name'] ?? ''); ?>" placeholder="e.g. MCA, BCA, B.Tech">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="course_year">Course Year *</label>
            <select class="form-select" id="course_year" name="course_year" required>
                <option value="">Select year</option>
                <?php foreach (['First Year','Second Year','Third Year','Fourth Year','Final Year'] as $v): ?>
                    <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['course_year'] ?? '', $v); ?>>
                        <?php echo h($v); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="semester">Current Semester *</label>
            <select class="form-select" id="semester" name="semester" required>
                <option value="">Select semester</option>
                <?php foreach (['Semester 1','Semester 2','Semester 3','Semester 4','Semester 5','Semester 6','Semester 7','Semester 8'] as $v): ?>
                    <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['semester'] ?? '', $v); ?>>
                        <?php echo h($v); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 field-box">
            <label class="form-label" for="age">Age *</label>
            <input class="form-control" type="number" id="age" name="age" min="5" max="100" required
                value="<?php echo h($profile['age'] ?? ''); ?>">
        </div>

        <div class="col-md-4 field-box">
            <label class="form-label" for="gender">Gender</label>
            <select class="form-select" id="gender" name="gender">
                <option value="">Choose option</option>
                <?php foreach (['Female','Male','Other','Prefer not to say'] as $v): ?>
                    <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['gender'] ?? '', $v); ?>>
                        <?php echo h($v); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 field-box">
            <label class="form-label" for="medium">Medium of Study</label>
            <select class="form-select" id="medium" name="medium">
                <option value="">Select medium</option>
                <?php foreach (['English','Marathi','Hindi','Other'] as $v): ?>
                    <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['medium'] ?? '', $v); ?>>
                        <?php echo h($v); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="school_name">School / Previous Institution</label>
            <input class="form-control" id="school_name" name="school_name"
                value="<?php echo h($profile['school_name'] ?? ''); ?>" placeholder="Previous institution">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="class_name">Class / Current Level</label>
            <input class="form-control" id="class_name" name="class_name"
                value="<?php echo h($profile['class_name'] ?? ''); ?>" placeholder="e.g. MCA-I">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="board">University / Board</label>
            <input class="form-control" id="board" name="board"
                value="<?php echo h($profile['board'] ?? ''); ?>" placeholder="University or board">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="target_exam">Target Exam</label>
            <input class="form-control" id="target_exam" name="target_exam"
                value="<?php echo h($profile['target_exam'] ?? ''); ?>" placeholder="e.g. Semester examination">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="exam_date">Overall Target Exam Date</label>
            <input class="form-control" type="date" id="exam_date" name="exam_date"
                value="<?php echo h($profile['exam_date'] ?? ''); ?>">
            <small class="helper-text">Individual subject dates can be entered below.</small>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="profile_photo">Profile Photo (max 2 MB)</label>
            <?php if (!empty($profile['profile_photo'])): ?>
                <div><img class="photo-preview" src="<?php echo h($profile['profile_photo']); ?>" alt="Profile photo"></div>
            <?php endif; ?>
            <input class="form-control" type="file" id="profile_photo" name="profile_photo"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <small class="helper-text">JPG, PNG or WEBP. Leave blank to keep the current photo.</small>
        </div>
    </div>
</div>
</section>

<!-- SUBJECTS -->
<section class="study-preferences">
<div class="preferences-content">
    <div class="preferences-header">
        <div class="header-icon"><i class="fa-solid fa-list-check"></i></div>
        <div><h4>Subjects &amp; Academic Progress</h4><p>Enter subject knowledge, remaining topics and study estimates.</p></div>
    </div>

    <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-book"></i></span>Your Subjects</div>
    <p class="helper-text">Use one card for each subject. Topic time and quiz score help prioritize your study plan.</p>

    <div id="subjectsContainer">
    <?php foreach ($subjectsRows as $i => $s): ?>
        <div class="dynamic-entry subject-entry">
            <div class="subject-card-title">
                <span><i class="fa-solid fa-book-open me-2"></i>Subject <span class="subject-number"><?php echo (int)$i + 1; ?></span></span>
                <span class="badge text-bg-info">Subject Details</span>
            </div>

            <div class="row">
                <div class="col-md-6 field-box">
                    <label class="form-label">Subject Name *</label>
                    <input class="form-control" data-field="name" name="subjects[<?php echo (int)$i; ?>][name]"
                        value="<?php echo h($s['name'] ?? ''); ?>" placeholder="e.g. Java Programming">
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Current Marks / 100</label>
                    <input class="form-control" type="number" data-field="marks"
                        name="subjects[<?php echo (int)$i; ?>][marks]" min="0" max="100" step="0.01"
                        value="<?php echo h($s['marks'] ?? ''); ?>" placeholder="e.g. 78">
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Knowledge Level</label>
                    <select class="form-select" data-field="knowledge" name="subjects[<?php echo (int)$i; ?>][knowledge]">
                        <option value="">Select level</option>
                        <?php foreach (['Beginner','Intermediate','Advanced'] as $v): ?>
                            <option value="<?php echo h($v); ?>" <?php echo isSelected($s['knowledge'] ?? '', $v); ?>>
                                <?php echo h($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Exam Date</label>
                    <input class="form-control" type="date" data-field="exam_date"
                        name="subjects[<?php echo (int)$i; ?>][exam_date]"
                        value="<?php echo h($s['exam_date'] ?? ''); ?>">
                </div>

                <div class="col-12 field-box">
                    <label class="form-label">Remaining Syllabus / Topics</label>
                    <textarea class="form-control" rows="3" data-field="remaining"
                        name="subjects[<?php echo (int)$i; ?>][remaining]"
                        placeholder="Separate topics with commas"><?php echo h($s['remaining'] ?? ''); ?></textarea>
                    <small class="helper-text">Example: Inheritance, Interfaces, Exception Handling.</small>
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Topic Difficulty</label>
                    <select class="form-select" data-field="topic_difficulty"
                        name="subjects[<?php echo (int)$i; ?>][topic_difficulty]">
                        <option value="">Select difficulty</option>
                        <?php foreach (['Easy','Medium','Hard'] as $v): ?>
                            <option value="<?php echo h($v); ?>" <?php echo isSelected($s['topic_difficulty'] ?? '', $v); ?>>
                                <?php echo h($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Syllabus Completion</label>
                    <select class="form-select" data-field="topic_status"
                        name="subjects[<?php echo (int)$i; ?>][topic_status]">
                        <?php foreach (['Not Started','In Progress','Completed'] as $v): ?>
                            <option value="<?php echo h($v); ?>" <?php echo isSelected($s['topic_status'] ?? 'Not Started', $v); ?>>
                                <?php echo h($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Estimated Topic Study Time (hours)</label>
                    <input class="form-control" type="number" min="0.5" max="100" step="0.5"
                        data-field="topic_hours" name="subjects[<?php echo (int)$i; ?>][topic_hours]"
                        value="<?php echo h($s['topic_hours'] ?? ''); ?>" placeholder="e.g. 3">
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Latest Practice / Quiz Score (%)</label>
                    <input class="form-control" type="number" min="0" max="100" step="0.1"
                        data-field="quiz_score" name="subjects[<?php echo (int)$i; ?>][quiz_score]"
                        value="<?php echo h($s['quiz_score'] ?? ''); ?>" placeholder="e.g. 72">
                </div>
            </div>

            <button type="button" class="btn btn-outline-danger btn-sm remove-entry">
                <i class="fa-solid fa-trash me-1"></i> Remove Subject
            </button>
        </div>
    <?php endforeach; ?>
    </div>

    <button type="button" class="btn btn-outline-info mb-4" id="addSubject">
        <i class="fa-solid fa-plus me-2"></i>Add Subject
    </button>

    <hr>

    <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-clipboard-check"></i></span>Assignment &amp; Practical Deadlines</div>
    <p class="helper-text">Add submission dates, priorities and estimated completion times.</p>

    <div id="deadlinesContainer">
    <?php foreach ($deadlinesRows as $i => $item): ?>
        <div class="dynamic-entry deadline-entry">
            <div class="row">
                <div class="col-md-5 field-box">
                    <label class="form-label">Task Title</label>
                    <input class="form-control" data-field="title"
                        name="deadlines[<?php echo (int)$i; ?>][title]"
                        value="<?php echo h($item['title'] ?? ''); ?>" placeholder="e.g. Java Practical 3">
                </div>

                <div class="col-md-3 field-box">
                    <label class="form-label">Task Type</label>
                    <select class="form-select" data-field="type" name="deadlines[<?php echo (int)$i; ?>][type]">
                        <option value="">Select type</option>
                        <?php foreach (['Assignment','Practical','Project'] as $v): ?>
                            <option value="<?php echo h($v); ?>" <?php echo isSelected($item['type'] ?? '', $v); ?>>
                                <?php echo h($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 field-box">
                    <label class="form-label">Submission Date</label>
                    <input class="form-control" type="date" data-field="date"
                        name="deadlines[<?php echo (int)$i; ?>][date]"
                        value="<?php echo h($item['date'] ?? ''); ?>">
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Priority</label>
                    <select class="form-select" data-field="priority" name="deadlines[<?php echo (int)$i; ?>][priority]">
                        <?php foreach (['High','Medium','Low'] as $v): ?>
                            <option value="<?php echo h($v); ?>" <?php echo isSelected($item['priority'] ?? 'Medium', $v); ?>>
                                <?php echo h($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 field-box">
                    <label class="form-label">Estimated Completion Time (hours)</label>
                    <input class="form-control" type="number" min="0.5" max="200" step="0.5"
                        data-field="estimated_hours" name="deadlines[<?php echo (int)$i; ?>][estimated_hours]"
                        value="<?php echo h($item['estimated_hours'] ?? ''); ?>" placeholder="e.g. 4">
                </div>
            </div>

            <button type="button" class="btn btn-outline-danger btn-sm remove-entry">
                <i class="fa-solid fa-trash me-1"></i>Remove Deadline
            </button>
        </div>
    <?php endforeach; ?>
    </div>

    <button type="button" class="btn btn-outline-info mb-4" id="addDeadline">
        <i class="fa-solid fa-plus me-2"></i>Add Deadline
    </button>
</div>
</section>

<!-- STUDY PREFERENCES -->
<section class="study-preferences">
<div class="preferences-content">
    <div class="preferences-header">
        <div class="header-icon"><i class="fa-solid fa-brain"></i></div>
        <div><h4>Study Preferences</h4><p>Choose how much and how you prefer to study.</p></div>
    </div>

    <div class="row">
        <div class="col-md-4 field-box">
            <label class="form-label" for="study_hours">Daily Study Hours *</label>
            <select class="form-select" id="study_hours" name="study_hours" required>
                <?php for ($i=1; $i<=12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo isSelected($profile['study_hours'] ?? 2, $i); ?>>
                        <?php echo $i; ?> <?php echo $i === 1 ? 'Hour' : 'Hours'; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-4 field-box">
            <label class="form-label" for="study_time">Preferred Study Period *</label>
            <select class="form-select" id="study_time" name="study_time" required>
                <?php foreach (['Morning','Afternoon','Evening','Night'] as $v): ?>
                    <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['study_time'] ?? 'Evening', $v); ?>>
                        <?php echo h($v); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4 field-box">
            <label class="form-label" for="session_duration">Session Duration *</label>
            <select class="form-select" id="session_duration" name="session_duration" required>
                <?php foreach ([25,45,60,90] as $v): ?>
                    <option value="<?php echo $v; ?>" <?php echo isSelected($profile['session_duration'] ?? 60, $v); ?>>
                        <?php echo $v; ?> Minutes
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="break_duration">Break Duration *</label>
            <select class="form-select" id="break_duration" name="break_duration" required>
                <?php foreach ([5,10,15,20] as $v): ?>
                    <option value="<?php echo $v; ?>" <?php echo isSelected($profile['break_duration'] ?? 10, $v); ?>>
                        <?php echo $v; ?> Minutes
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="target_percentage">Target Percentage *</label>
            <input class="form-control" type="number" id="target_percentage" name="target_percentage"
                min="1" max="100" required value="<?php echo h($profile['target_percentage'] ?? '85'); ?>">
        </div>
    </div>

    <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-book-open-reader"></i></span>Preferred Study Methods</div>

    <div class="study-methods">
        <?php
        $methodLabels = [
            'Reading' => '📚 Reading',
            'Practice Questions' => '✍️ Practice Questions',
            'Video Lectures' => '🎬 Video Lectures',
            'Revision' => '🔄 Revision',
            'Mock Tests' => '📝 Mock Tests'
        ];
        foreach ($methodLabels as $v => $label):
        ?>
            <label class="method-option">
                <input type="checkbox" name="study_methods[]" value="<?php echo h($v); ?>"
                    <?php echo in_array($v, $selectedMethods, true) ? 'checked' : ''; ?>>
                <span><?php echo h($label); ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="section-heading mt-4"><span class="heading-icon"><i class="fa-solid fa-arrows-rotate"></i></span>Revision Preference</div>
    <div class="field-box">
        <label class="form-label" for="revision_preference">How do you prefer to revise?</label>
        <select class="form-select" id="revision_preference" name="revision_preference">
            <option value="">Choose revision style</option>
            <?php foreach (['Daily Short Revision','Weekly Revision','Spaced Repetition','Practice Tests','Mixed Revision'] as $v): ?>
                <option value="<?php echo h($v); ?>" <?php echo isSelected($profile['revision_preference'] ?? '', $v); ?>>
                    <?php echo h($v); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <hr>

    <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-calendar-week"></i></span>Weekly Availability</div>
    <p class="helper-text">Select your available days and the number of study hours you can manage on each day.</p>

    <div class="row">
        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
            <div class="col-md-6 col-lg-4 field-box">
                <div class="dynamic-entry">
                    <label class="method-option mb-3">
                        <input type="checkbox" class="availability-day" name="available_days[]"
                            value="<?php echo h($day); ?>" data-day="<?php echo h($day); ?>"
                            <?php echo in_array($day, $savedDays, true) ? 'checked' : ''; ?>>
                        <span><?php echo h($day); ?></span>
                    </label>
                    <label class="form-label" for="hours_<?php echo h($day); ?>">Available study hours</label>
                    <select class="form-select weekly-hours" id="hours_<?php echo h($day); ?>"
                        name="weekly_hours[<?php echo h($day); ?>]" data-day="<?php echo h($day); ?>">
                        <?php for ($hour=1; $hour<=12; $hour++): ?>
                            <option value="<?php echo $hour; ?>"
                                <?php echo isSelected($savedWeeklyHours[$day] ?? 2, $hour); ?>>
                                <?php echo $hour; ?> <?php echo $hour === 1 ? 'hour' : 'hours'; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-calendar-check"></i></span>AI Planner Daily Schedule</div>
    <p class="helper-text">Tell MindAI when you can study and how much time is occupied by college or other commitments.</p>

    <div class="row">
        <div class="col-md-6 field-box">
            <label class="form-label" for="preferred_start_time">Preferred Start Time *</label>
            <select class="form-select" id="preferred_start_time" name="preferred_start_time" required>
                <?php
                $startTimes = [
                    '05:00'=>'5:00 AM','06:00'=>'6:00 AM','07:00'=>'7:00 AM',
                    '08:00'=>'8:00 AM','09:00'=>'9:00 AM','10:00'=>'10:00 AM',
                    '12:00'=>'12:00 PM','14:00'=>'2:00 PM','16:00'=>'4:00 PM',
                    '18:00'=>'6:00 PM','20:00'=>'8:00 PM','21:00'=>'9:00 PM'
                ];
                foreach ($startTimes as $value => $label):
                ?>
                    <option value="<?php echo h($value); ?>"
                        <?php echo isSelected($plannerPreferences['preferred_start_time'] ?? '18:00', $value); ?>>
                        <?php echo h($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="college_hours">College / Class Hours Per Day *</label>
            <input class="form-control" type="number" id="college_hours" name="college_hours"
                min="0" max="16" required
                value="<?php echo h($plannerPreferences['college_hours'] ?? '6'); ?>">
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="other_unavailable_hours">Other Busy Hours Per Day *</label>
            <input class="form-control" type="number" id="other_unavailable_hours"
                name="other_unavailable_hours" min="0" max="16" required
                value="<?php echo h($plannerPreferences['other_unavailable_hours'] ?? '2'); ?>">
            <small class="helper-text">For commuting, household work or other commitments.</small>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="max_sessions_per_day">Maximum Study Sessions Per Day *</label>
            <select class="form-select" id="max_sessions_per_day" name="max_sessions_per_day" required>
                <?php for ($n=1; $n<=12; $n++): ?>
                    <option value="<?php echo $n; ?>"
                        <?php echo isSelected($plannerPreferences['max_sessions_per_day'] ?? 4, $n); ?>>
                        <?php echo $n; ?> Sessions
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="col-md-6 field-box">
            <label class="form-label" for="rest_day">Preferred Rest Day *</label>
            <select class="form-select" id="rest_day" name="rest_day" required>
                <?php foreach (['None','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                    <option value="<?php echo h($day); ?>"
                        <?php echo isSelected($plannerPreferences['rest_day'] ?? 'Sunday', $day); ?>>
                        <?php echo h($day === 'None' ? 'No fixed rest day' : $day); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="dynamic-entry">
        <div class="section-heading"><span class="heading-icon"><i class="fa-solid fa-lightbulb"></i></span>Subjects Needing Extra Attention</div>
        <label class="form-label" for="weak_subjects">Weak Subjects</label>
        <input class="form-control" id="weak_subjects" name="weak_subjects"
            value="<?php echo h($profile['weak_subjects'] ?? ''); ?>"
            placeholder="e.g. Mathematics, DBMS, Programming">
        <small class="helper-text">Enter multiple subjects separated by commas.</small>
    </div>
</div>
</section>

<button type="submit" name="save_profile" value="1" class="profile-submit">
    <i class="fa-solid fa-wand-magic-sparkles"></i>
    Save Profile &amp; Continue
    <i class="fa-solid fa-arrow-right"></i>
</button>

</form>
</div>
</main>

<footer class="profile-footer">
    <h5>🤖 MindAI Study Planner</h5>
    <p>Learn smarter. Achieve faster.</p>
    <p class="mb-0">© 2026 AI Study Planner</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* Fields used by the dynamic subject cards */
const subjectFields = [
    'name', 'marks', 'knowledge', 'exam_date', 'remaining',
    'topic_difficulty', 'topic_status', 'topic_hours', 'quiz_score'
];

const deadlineFields = [
    'title', 'type', 'date', 'priority', 'estimated_hours'
];

function renumberEntries(containerId, prefix, fields) {
    const container = document.getElementById(containerId);
    const entries = container.querySelectorAll(':scope > .dynamic-entry');

    entries.forEach(function(entry, index) {
        fields.forEach(function(field) {
            const element = entry.querySelector('[data-field="' + field + '"]');
            if (element) {
                element.name = prefix + '[' + index + '][' + field + ']';
            }
        });

        const number = entry.querySelector('.subject-number');
        if (number) {
            number.textContent = index + 1;
        }
    });
}

/* Create a subject card */
document.getElementById('addSubject').addEventListener('click', function() {
    const container = document.getElementById('subjectsContainer');
    const index = container.querySelectorAll(':scope > .dynamic-entry').length;

    const wrapper = document.createElement('div');
    wrapper.className = 'dynamic-entry subject-entry';

    wrapper.innerHTML = `
        <div class="subject-card-title">
            <span><i class="fa-solid fa-book-open me-2"></i>
            Subject <span class="subject-number">${index + 1}</span></span>
            <span class="badge text-bg-info">Subject Details</span>
        </div>
        <div class="row">
            <div class="col-md-6 field-box">
                <label class="form-label">Subject Name *</label>
                <input class="form-control" data-field="name" placeholder="e.g. Java Programming">
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Current Marks / 100</label>
                <input class="form-control" type="number" data-field="marks" min="0" max="100" step="0.01" placeholder="e.g. 78">
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Knowledge Level</label>
                <select class="form-select" data-field="knowledge">
                    <option value="">Select level</option>
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate">Intermediate</option>
                    <option value="Advanced">Advanced</option>
                </select>
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Exam Date</label>
                <input class="form-control" type="date" data-field="exam_date">
            </div>
            <div class="col-12 field-box">
                <label class="form-label">Remaining Syllabus / Topics</label>
                <textarea class="form-control" rows="3" data-field="remaining" placeholder="Separate topics with commas"></textarea>
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Topic Difficulty</label>
                <select class="form-select" data-field="topic_difficulty">
                    <option value="">Select difficulty</option>
                    <option value="Easy">Easy</option>
                    <option value="Medium">Medium</option>
                    <option value="Hard">Hard</option>
                </select>
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Syllabus Completion</label>
                <select class="form-select" data-field="topic_status">
                    <option value="Not Started">Not Started</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Estimated Topic Study Time (hours)</label>
                <input class="form-control" type="number" min="0.5" max="100" step="0.5" data-field="topic_hours" placeholder="e.g. 3">
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Latest Practice / Quiz Score (%)</label>
                <input class="form-control" type="number" min="0" max="100" step="0.1" data-field="quiz_score" placeholder="e.g. 72">
            </div>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm remove-entry">
            <i class="fa-solid fa-trash me-1"></i>Remove Subject
        </button>
    `;

    subjectFields.forEach(function(field) {
        const element = wrapper.querySelector('[data-field="' + field + '"]');
        if (element) {
            element.name = 'subjects[' + index + '][' + field + ']';
        }
    });

    container.appendChild(wrapper);
});

/* Create an assignment card */
document.getElementById('addDeadline').addEventListener('click', function() {
    const container = document.getElementById('deadlinesContainer');
    const index = container.querySelectorAll(':scope > .dynamic-entry').length;

    const wrapper = document.createElement('div');
    wrapper.className = 'dynamic-entry deadline-entry';

    wrapper.innerHTML = `
        <div class="row">
            <div class="col-md-5 field-box">
                <label class="form-label">Task Title</label>
                <input class="form-control" data-field="title" placeholder="e.g. Java Practical 3">
            </div>
            <div class="col-md-3 field-box">
                <label class="form-label">Task Type</label>
                <select class="form-select" data-field="type">
                    <option value="">Select type</option>
                    <option value="Assignment">Assignment</option>
                    <option value="Practical">Practical</option>
                    <option value="Project">Project</option>
                </select>
            </div>
            <div class="col-md-4 field-box">
                <label class="form-label">Submission Date</label>
                <input class="form-control" type="date" data-field="date">
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Priority</label>
                <select class="form-select" data-field="priority">
                    <option value="High">High</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="Low">Low</option>
                </select>
            </div>
            <div class="col-md-6 field-box">
                <label class="form-label">Estimated Completion Time (hours)</label>
                <input class="form-control" type="number" min="0.5" max="200" step="0.5" data-field="estimated_hours" placeholder="e.g. 4">
            </div>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm remove-entry">
            <i class="fa-solid fa-trash me-1"></i>Remove Deadline
        </button>
    `;

    deadlineFields.forEach(function(field) {
        const element = wrapper.querySelector('[data-field="' + field + '"]');
        if (element) {
            element.name = 'deadlines[' + index + '][' + field + ']';
        }
    });

    container.appendChild(wrapper);
});

/* Remove cards and renumber remaining fields */
document.addEventListener('click', function(event) {
    const button = event.target.closest('.remove-entry');
    if (!button) {
        return;
    }

    const entry = button.closest('.dynamic-entry');
    const container = entry.parentElement;
    const entries = container.querySelectorAll(':scope > .dynamic-entry');

    if (entries.length > 1) {
        entry.remove();
    } else {
        entry.querySelectorAll('input, textarea').forEach(function(input) {
            if (input.type !== 'checkbox' && input.type !== 'radio') {
                input.value = '';
            }
        });

        entry.querySelectorAll('select').forEach(function(select) {
            select.selectedIndex = 0;
        });
    }

    if (container.id === 'subjectsContainer') {
        renumberEntries('subjectsContainer', 'subjects', subjectFields);
    }

    if (container.id === 'deadlinesContainer') {
        renumberEntries('deadlinesContainer', 'deadlines', deadlineFields);
    }
});

/* Enable weekly hours only for selected days */
function updateDayHours(day) {
    const checkbox = document.querySelector(
        '.availability-day[data-day="' + day + '"]'
    );

    const hours = document.querySelector(
        '.weekly-hours[data-day="' + day + '"]'
    );

    if (checkbox && hours) {
        hours.disabled = !checkbox.checked;
    }
}

document.querySelectorAll('.availability-day').forEach(function(checkbox) {
    updateDayHours(checkbox.dataset.day);

    checkbox.addEventListener('change', function() {
        updateDayHours(this.dataset.day);
    });
});
</script>

</body>
</html>
