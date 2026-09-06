<?php
session_start();
require_once __DIR__ . '/config.php';

// Allow faculty or update-admin
if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'faculty' &&
        !(($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['admin_type'] ?? '') === 'update')
    )
) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];


$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user and faculty info
$user_stmt = $mysqli->prepare("SELECT FirstName, LastName, Email, DOB FROM Users WHERE UserID = ? LIMIT 1");
$user_stmt->bind_param('i', $userId);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    echo "<p>Faculty member not found.</p>";
    exit;
}

// Fetch current semester
$sem_sql = "
    SELECT SemesterID, SemesterName, Year 
    FROM Semester 
    WHERE CURDATE() BETWEEN StartDate AND (EndDate + 7)
    LIMIT 1
";
$sem_stmt = $mysqli->prepare($sem_sql);
$sem_stmt->execute();
$sem_result = $sem_stmt->get_result();
$current = $sem_result->fetch_assoc();
$sem_stmt->close();

$selectedSemester = $current['SemesterID'] ?? null;

// Fetch schedule for courses taught
$schedule = [];

if ($selectedSemester !== null) {

    $courses_sql = "
        SELECT 
            cs.CRN,
            c.CourseName,
            GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
            DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
            DATE_FORMAT(MAX(p.EndTime), '%l:%i %p') AS EndTime,
            cs.RoomID,
            cs.CourseID
        FROM CourseSection cs
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN Semester s ON cs.SemesterID = s.SemesterID
        JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
        JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
        JOIN Day d ON tsd.DayID = d.DayID
        JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
        JOIN Period p ON tsp.PeriodID = p.PeriodID
        WHERE cs.FacultyID = ?
          AND cs.SemesterID = ?
        GROUP BY cs.CRN, c.CourseName, cs.RoomID
        ORDER BY cs.CRN, MIN(p.StartTime);
    ";

    $courses_stmt = $mysqli->prepare($courses_sql);
    $courses_stmt->bind_param("is", $userId, $selectedSemester);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    $schedule = $courses_result->fetch_all(MYSQLI_ASSOC);
    $courses_stmt->close();
}

$fac_stmt = $mysqli->prepare("SELECT OfficeID, Ranking FROM Faculty WHERE FacultyID = ? LIMIT 1");
$fac_stmt->bind_param('i', $userId);
$fac_stmt->execute();
$fac = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();
$office    = $fac['OfficeID'] ?? 'N/A';
$ranking   = $fac['Ranking'] ?? 'Faculty';

$studentId = $_GET['studentID'] ?? '';
    $crn = $_GET['crn'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = $_POST['grade'] ?? '';
    $studentId = $_POST['studentID'] ?? '';
    $crn = $_POST['crn'] ?? '';
    $courseId = $_POST['courseID'] ?? '';
    $semester = $_POST['semesterID'] ?? '';

$mysqli->begin_transaction();

  $sql = "UPDATE StudentEnrollment SET Grade = ?, Status = 'COMPLETED' WHERE StudentID=? AND CRN =?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("sii", $grade, $studentId, $crn );
  $stmt->execute();
    
  /* The UPDATE above is the whole write. StudentHistory used to receive a
     duplicate of it, and re-grading a student appended a second row rather
     than correcting the first. */

$mysqli->commit();
echo "<script>alert('Grade Submitted ✅');</script>";
}

?>

<!doctype html>
<html lang="en">
<?php $nu_title = 'Grading'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Grading Portal'; require __DIR__ . '/partials/header.php'; ?>

  <section>
    <div class="card">
        <h1>Current Semester Grades</h1>
        <h4>Click course section row to view roster and submit grades</h4>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="w-90">CRN</th>
                <th>Course</th>
                <th>Days</th>
                <th>Time</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
                <?php foreach ($schedule as $row): ?>

                <?php
                    $crn = $row['CRN'];

                    $roster_sql = "
                        SELECT 
                            u.FirstName, 
                            u.LastName,
                            se.StudentID,
                            se.Grade
                        FROM StudentEnrollment se
                        JOIN Users u ON se.StudentID = u.UserID
                        JOIN CourseSection cs ON se.CRN = cs.CRN
                        WHERE cs.FacultyID = ?
                          AND cs.SemesterID = ?
                          AND cs.CRN = ?
                        ORDER BY u.LastName, u.FirstName
                    ";
                    $stmt = $mysqli->prepare($roster_sql);
                    $stmt->bind_param("isi", $userId, $selectedSemester, $crn);
                    $stmt->execute();
                    $roster = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                ?>

                <tr class="cs" data-crn="<?= $crn ?>">
                    <td><?= htmlspecialchars($row['CRN']) ?></td>
                    <td><?= htmlspecialchars($row['CourseName']) ?></td>
                    <td><?= htmlspecialchars($row['Days']) ?></td>
                    <td>
                        <?php 
                            $time = trim(($row['StartTime'] ?? '') . ' – ' . ($row['EndTime'] ?? ''));
                            echo htmlspecialchars($time ?: 'TBA');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['RoomID']) ?></td>
                </tr>

                <tr id="roster-<?= $crn ?>" class="roster">
                    <td colspan="5">

                            <?php if (empty($roster)): ?>
                                No students enrolled.
                            <?php else: ?>

                            <table class="inner-roster">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roster as $r): ?>
                                        <?php $name = trim(($r['FirstName'] ?? '') . ' ' . ($r['LastName'] ?? '')) ?: '—'; ?>
                                        <tr>
                                            <td>
                                                <a href="student_profile.php?studentID=<?= urlencode($r['StudentID']) ?>">
                                                    <?= htmlspecialchars($name) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <form class="grade-form"
                                                  data-student="<?= $r['StudentID']     ?>"
                                                  data-crn="<?= $crn ?>"
                                                  data-course="<?= $row['CourseID'] ?>"
                                                  data-semester="<?= $selectedSemester ?>">
                                                    <select name="grade" class="grade-select">
                                                        <option value=""><?= $r['Grade'] !== null ? $r['Grade'] : '---' ?></option>

                                                        <?php 
                                                        $grades = ["A","A-","B+","B","B-","C+","C","C-","D+","D","D-","F"];
                                                        foreach ($grades as $g):
                                                        ?>
                                                            <option value="<?= $g ?>" <?= ($r['Grade'] === $g) ? "selected" : "" ?>>
                                                                <?= $g ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php endif; ?>

                        </div>

                    </td>
                </tr>

            <?php endforeach; ?>
                </tbody>
          </table>
        </div>

  
    </section>

      <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    // Create icons on load
    lucide.createIcons();
     document.getElementById('year').textContent = new Date().getFullYear();

    // Toggle roster rows
       document.querySelectorAll(".cs").forEach(row => {
        row.addEventListener("click", () => {
            const crn = row.dataset.crn;
            const id = "roster-" + crn;
            const rosterRow = document.getElementById(id);

            console.log("CRN:", JSON.stringify(crn));
            console.log("Looking for ID:", id);
            console.log("Found row?", rosterRow);
            
            rosterRow?.classList.toggle("open");
            row.classList.toggle("selected");
        });
    });

    document.querySelectorAll(".grade-form").forEach(form => {
    const select = form.querySelector(".grade-select");

    select.addEventListener("change", async () => {

        const data = new FormData();
        data.append("studentID", form.dataset.student);
        data.append("crn", form.dataset.crn);
        data.append("courseID", form.dataset.course);
        data.append("semesterID", form.dataset.semester);
        data.append("grade", select.value);

        const response = await fetch("grade_update.php", {
            method: "POST",
            body: data
        });

        const result = await response.json();

        if (result.ok) {
            console.log("Grade saved!");
            alert("Grade Saved ✔");
        } else {
            alert(result.message || "Error saving grade ❌");
        }
    });
});

</script>

</body>
</html>