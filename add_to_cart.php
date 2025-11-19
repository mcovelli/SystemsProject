<?php
session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Ensure cart array exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$userId = $_SESSION['user_id'] ?? null;
$crn = trim($_POST['crn'] ?? '');
$courseID = trim($_POST['courseID'] ?? '');
$semester = urlencode($_POST['semester'] ?? ($_GET['Semester'] ?? ''));
$dept     = urlencode($_POST['dept'] ?? ($_GET['dept'] ?? ''));

if (!$userId || $crn === '' || $courseID === '') {
    header("Location: Add_Drop_courses.php");
    exit;
}

/* ------------------------------------------------------
   1️⃣ FETCH STUDENT INFO (type + current enrollments)
------------------------------------------------------ */
// ✅ 1️⃣ Get student type and corresponding max credits from subtype tables
$studentStmt = $mysqli->prepare("
    SELECT 
        s.StudentType,
        COALESCE(ftug.MaxCredits, ptug.MaxCredits, ftg.MaxCredits, ptg.MaxCredits) AS MaxCredits
    FROM Student s
    LEFT JOIN FullTimeUG ftug   ON s.StudentID = ftug.StudentID
    LEFT JOIN PartTimeUG ptug   ON s.StudentID = ptug.StudentID
    LEFT JOIN FullTimeGrad ftg  ON s.StudentID = ftg.StudentID
    LEFT JOIN PartTimeGrad ptg  ON s.StudentID = ptg.StudentID
    WHERE s.StudentID = ?
    LIMIT 1
");
$studentStmt->bind_param('i', $userId);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

if (!$student) {
    die("Student record not found.");
}

$studentType = $student['StudentType']; // 'Undergraduate' or 'Graduate'
$maxCredits = (int)($student['MaxCredits'] ?? 15); // Default fallback

/* ------------------------------------------------------
   2️⃣ FETCH COURSE INFO (credits, level, time slot)
------------------------------------------------------ */
$courseStmt = $mysqli->prepare("
    SELECT 
        cs.CRN,
        cs.SemesterID,
        c.CourseID,
        c.Credits,
        c.CourseType,  -- e.g., 'Undergraduate' or 'Graduate'
        ts.TS_ID,
        GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
        MIN(p.StartTime) AS StartTime,
        MAX(p.EndTime)   AS EndTime
    FROM CourseSection cs
    JOIN Course c ON cs.CourseID = c.CourseID
    JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
    JOIN TimeSlotDay tsd ON tsd.TS_ID = ts.TS_ID
    JOIN Day d ON tsd.DayID = d.DayID
    JOIN TimeSlotPeriod tsp ON tsp.TS_ID = ts.TS_ID
    JOIN Period p ON tsp.PeriodID = p.PeriodID
    WHERE cs.CRN = ?
    GROUP BY cs.CRN
");
$courseStmt->bind_param('i', $crn);
$courseStmt->execute();
$courseInfo = $courseStmt->get_result()->fetch_assoc();
$courseStmt->close();

if (!$courseInfo) {
    die("Course section not found.");
}

/* ------------------------------------------------------
   3️⃣ ENFORCE RULES
------------------------------------------------------ */

// (a) Cross-level enrollment prevention
if (
    ($studentType === 'Undergraduate' && $courseInfo['CourseLevel'] === 'Graduate') ||
    ($studentType === 'Graduate' && $courseInfo['CourseLevel'] === 'Undergraduate')
) {
    $_SESSION['error_message'] = "You cannot enroll in courses outside your degree level.";
    header("Location: Add_Drop_courses.php?Semester={$semester}&dept={$dept}");
    exit;
}

// (b) Already passed this course with C or better
$gradeStmt = $mysqli->prepare("
    SELECT Grade
    FROM StudentEnrollment
    WHERE StudentID = ? AND CourseID = ? AND Grade IN ('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
");
$gradeStmt->bind_param('is', $userId, $courseID);
$gradeStmt->execute();
$gradeStmt->store_result();
if ($gradeStmt->num_rows > 0) {
    $_SESSION['error_message'] = "You have already passed this course with a grade of C or higher.";
    $gradeStmt->close();
    header("Location: Add_Drop_courses.php?Semester={$semester}&dept={$dept}");
    exit;
}
$gradeStmt->close();

// (c) Prevent double-booking (time conflict)
$conflictStmt = $mysqli->prepare("
    SELECT cs.CRN, c.CourseID, p.StartTime, p.EndTime, GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days
    FROM StudentEnrollment e
    JOIN CourseSection cs ON e.CRN = cs.CRN
    JOIN Course c ON cs.CourseID = c.CourseID
    JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
    JOIN TimeSlotDay tsd ON tsd.TS_ID = ts.TS_ID
    JOIN Day d ON tsd.DayID = d.DayID
    JOIN TimeSlotPeriod tsp ON tsp.TS_ID = ts.TS_ID
    JOIN Period p ON tsp.PeriodID = p.PeriodID
    WHERE e.StudentID = ? AND cs.SemesterID = ?
    GROUP BY cs.CRN
");
$conflictStmt->bind_param('is', $userId, $courseInfo['SemesterID']);
$conflictStmt->execute();
$res = $conflictStmt->get_result();

while ($existing = $res->fetch_assoc()) {
    $newDays = explode('/', $courseInfo['Days']);
    $oldDays = explode('/', $existing['Days']);

    $dayOverlap = array_intersect($newDays, $oldDays);
    if (!empty($dayOverlap)) {
        // Convert times to minutes
        $parse = fn($t) => (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);
        $newStart = $parse($courseInfo['StartTime']);
        $newEnd   = $parse($courseInfo['EndTime']);
        $oldStart = $parse($existing['StartTime']);
        $oldEnd   = $parse($existing['EndTime']);

        if ($newStart < $oldEnd && $oldStart < $newEnd) {
            $_SESSION['error_message'] = "Time conflict detected with CRN {$existing['CRN']} ({$existing['CourseID']}).";
            $conflictStmt->close();
            header("Location: Add_Drop_courses.php?Semester={$semester}&dept={$dept}");
            exit;
        }
    }
}
$conflictStmt->close();

// (d) Enforce credit limit
$totalCredits = (int)$courseInfo['Credits'];
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $crnCheck = $item['crn'];
        $credStmt = $mysqli->prepare("
            SELECT c.Credits 
            FROM CourseSection cs 
            JOIN Course c ON cs.CourseID = c.CourseID 
            WHERE cs.CRN = ?
        ");
        $credStmt->bind_param('i', $crnCheck);
        $credStmt->execute();
        $credRes = $credStmt->get_result()->fetch_assoc();
        $credStmt->close();

        if ($credRes) {
            $totalCredits += (int)$credRes['Credits'];
        }
    }
}

if ($totalCredits > $maxCredits) {
    $_SESSION['error_message'] = "Adding this course exceeds your maximum allowed credits ({$maxCredits}).";
    header("Location: Add_Drop_courses.php?Semester={$semester}&dept={$dept}");
    exit;
}

/* ------------------------------------------------------
   4️⃣ ADD TO CART (if all checks pass)
------------------------------------------------------ */
$exists = false;
foreach ($_SESSION['cart'] as $item) {
    if ((string)$item['crn'] === (string)$crn) {
        $exists = true;
        break;
    }
}

if (!$exists) {
    $_SESSION['cart'][] = [
        'crn' => $crn,
        'courseID' => $courseID
    ];
}

/* ------------------------------------------------------
   5️⃣ REDIRECT BACK TO ADD/DROP PAGE
------------------------------------------------------ */
$redirectUrl = "Add_Drop_courses.php";
$queryParts = [];
if ($semester !== '') $queryParts[] = "Semester={$semester}";
if ($dept !== '')     $queryParts[] = "dept={$dept}";
if ($queryParts) $redirectUrl .= '?' . implode('&', $queryParts);

header("Location: {$redirectUrl}");
exit;
?>