<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'faculty' &&
        ($_SESSION['role'] ?? '') !== 'admin' &&
        ($_SESSION['role'] ?? '') !== 'student')
    ) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? ''));

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

$studentID = NULL;
$studentName = NULL;


if (($role === 'admin' || $role === 'faculty') && !empty($_GET['studentID'])) {
    // Admin/faculty can view a specific student
    $studentID = (int)$_GET['studentID'];

} elseif ($role === 'student') {
    // Student always views their own attendance
    $studentID = $userId;

} elseif ($role === 'admin') {
    // Admin with no studentID -> send back to directory
    redirect(PROJECT_ROOT . "/viewDirectory.php");

} else {
    // Shouldn't get here, but safety net
    redirect(PROJECT_ROOT . "/login.html");
}

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    csa.CRN, 
    csa.CourseID, 
    csa.StudentID, 
    csa.AttendanceDate, 
    csa.PresentAbsent, 
    CONCAT(su.FirstName, ' ', su.LastName) AS StudentName,
    cs.SemesterID
FROM CourseSectionAttendance csa
JOIN CourseSection cs ON csa.CRN = cs.CRN
JOIN Users su ON csa.StudentID = su.UserID
WHERE csa.StudentID = ?
";
$params = [$studentID];
$types = "i";

if (!empty($search)) {
    $sql .= "
    AND (
        su.FirstName LIKE CONCAT('%', ?, '%')
        OR su.LastName LIKE CONCAT('%', ?, '%')
        OR csa.CRN LIKE CONCAT('%', ?, '%')
        OR csa.CourseID LIKE CONCAT('%', ?, '%')
        OR csa.AttendanceDate LIKE CONCAT('%', ?, '%')
        OR csa.PresentAbsent LIKE CONCAT('%', ?, '%')
        OR cs.SemesterID LIKE CONCAT('%', ?, '%')
    )
    ";
    $types .= "sssssss";
    array_push($params, $search, $search, $search, $search, $search, $search, $search);
}

$sql .= " ORDER BY csa.CRN DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($attendance)) {
    $studentName = $attendance[0]['StudentName'];
} else {
    $studentName = "Unknown Student";
}


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Attendance History'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Attendance History'; $nu_crumb = ['@profile', '← Back to Profile']; $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div class="table-wrap"><div><strong>StudentID: </strong><?= htmlspecialchars($studentID) ?></div>
            <div><strong>Student Name: </strong><?= htmlspecialchars($studentName) ?></div>

        <form method="GET" style="margin-bottom: 20px;">
          <input type="hidden" name="studentID" value="<?= htmlspecialchars($studentID) ?>">
          <label for="search">Search:</label>
          <input type="text" id="search" name="search"
           placeholder="Search course name or semester..."
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

          <button type="submit">Search</button>
      </form>
      <div class="scroll-box">
      <table border="1" cellpadding="5" cellspacing="0">
        <tbody>
          <?php
            $lastCRN = null;

            foreach ($attendance as $a):

                // When a new CRN starts, print the header row ONE time
                if ($lastCRN !== $a['CRN']): ?>

                    <!-- Spacer between different courses -->
                    <?php if ($lastCRN !== null): ?>
                        <tr><td colspan="4" style="height:12px; background: var(--nu-surface-sunk);"></td></tr>
                    <?php endif; ?>
                    <tr class="crn-header" style="background: var(--nu-primary-soft); font-weight:bold;">
                        <td colspan="4">
                            CRN: <?= htmlspecialchars($a['CRN']) ?> —
                            Course: <?= htmlspecialchars($a['CourseID']) ?>
                        </td>
                    </tr>

                    <tr class="section-labels" style="background: var(--nu-surface-sunk);">
                        <th scope="col">Date</th>
                        <th scope="col">Present/Absent</th>
                        <th scope="col">Semester</th>
                    </tr>

                <?php endif; ?>

                <!-- Row for each attendance record -->
                <tr>
                    <td><?= htmlspecialchars($a['AttendanceDate']) ?></td>
                    <td><?= htmlspecialchars($a['PresentAbsent']) ?></td>
                    <td><?= htmlspecialchars($a['SemesterID']) ?></td>
                </tr>

            <?php
                $lastCRN = $a['CRN'];
            endforeach;
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

  </script>
</html>