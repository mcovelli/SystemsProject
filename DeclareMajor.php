<?php
session_start();
require_once __DIR__ . '/config.php';

$role         = $_SESSION['role'] ?? '';
$studentType  = $_SESSION['student_type'] ?? '';
$adminType    = $_SESSION['admin_type'] ?? '';

if (
    !isset($_SESSION['user_id']) ||
    !(
        $role === 'student' ||
        $studentType === 'undergrad' ||
        ($role === 'admin' && $adminType === 'update')
    )
) {
    redirect(PROJECT_ROOT . "/login.html");
}


$isStudent = ($_SESSION['role'] ?? '') === 'student';

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$loadedStudent = null;

if ($isStudent) {

    $searchId = $_SESSION['user_id'];

    $stmt = $mysqli->prepare("
        SELECT 
            s.StudentID,
            CONCAT(u.FirstName, ' ', u.LastName) AS StudentName
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        WHERE s.StudentID = ?
    ");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else if (isset($_POST['searchStudent'])) {

    $searchId = $_POST['searchID'];

    $stmt = $mysqli->prepare("
        SELECT 
            s.StudentID,
            CONCAT(u.FirstName, ' ', u.LastName) AS StudentName
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        WHERE s.StudentID = ?
    ");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (empty($loadedStudent)) {
    $studentMajorIDs = [];
} else {
    $m_stmt = $mysqli->prepare("SELECT MajorID FROM StudentMajor WHERE StudentID = ?");
    $m_stmt->bind_param("i", $loadedStudent['StudentID']);
    $m_stmt->execute();
    $studentMajorIDs = array_column($m_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'MajorID');
    $m_stmt->close();
}

// Fetch all available majors
$maj_stmt = $mysqli->prepare("SELECT MajorID, MajorName FROM Major WHERE Status = 'ACTIVE' ORDER BY MajorName ASC");
$maj_stmt->execute();
$availableMajors = $maj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$maj_stmt->close();

$userId = $_SESSION['user_id'];

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if (isset($_POST['declareMajor'])) {
    $StudentID = $_POST['studentID'] ?? '';
    $selectedMajors = $_POST['majorIDs'] ?? [];

    $minor_count_stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM StudentMinor WHERE StudentID = ?");
    $minor_count_stmt->bind_param("i", $StudentID);
    $minor_count_stmt->execute();
    $minor_count = $minor_count_stmt->get_result()->fetch_assoc()['cnt'];
    $minor_count_stmt->close();

    if ($minor_count == 1){
            $selectedMajors = array_slice($selectedMajors, 0, 2); // max 1 major if a minor exists
    } else {
        $selectedMajors = array_slice($selectedMajors, 0, 2); // max 2 majors if no minor exists
    }

    $mysqli->begin_transaction();
    $ok = true;
    
    $stmt = $mysqli->prepare("SELECT MajorID FROM StudentMajor WHERE StudentID = ?");
    $stmt->bind_param("i", $StudentID);
    $stmt->execute();
    $existingMajors = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'MajorID');
    $stmt->close();

    $toDelete = array_diff($existingMajors, $selectedMajors);

    if (!empty($toDelete)) {
        $del = $mysqli->prepare("DELETE FROM StudentMajor WHERE StudentID = ? AND MajorID = ?");
        foreach ($toDelete as $mid) {
            if (
                !$del->bind_param("ii", $StudentID, $mid) ||
                !$del->execute()
            ) {
                $ok = false;
            }
        }
        $del->close();

        // If no majors remain selected, update Student.MajorID = NULL
        if (empty($selectedMajors)) {
            $stmtNull = $mysqli->prepare("UPDATE Student SET MajorID = NULL WHERE StudentID = ?");
            $stmtNull->bind_param("i", $StudentID);
            if (!$stmtNull->execute()) {
                $ok = false;
            }
            $stmtNull->close();
        }
    }

    $toInsert = array_diff($selectedMajors, $existingMajors);

    if (!empty($toInsert)) {
        $ins = $mysqli->prepare("
            INSERT INTO StudentMajor (StudentID, MajorID, DateOfDeclaration)
            VALUES (?, ?, CURRENT_DATE())
        ");
        foreach ($toInsert as $mid) {
            if (
                !$ins->bind_param("ii", $StudentID, $mid) ||
                !$ins->execute()
            ) {
                $ok = false;
            }
        }
        $ins->close();
    }

    $primaryMajor = $selectedMajors[0] ?? null;

    if ($primaryMajor !== null) {
        // Set Student.MajorID = first selected major
        $up1 = $mysqli->prepare("
            UPDATE Student SET MajorID = ? WHERE StudentID = ?
        ");
        $up1->bind_param("ii", $primaryMajor, $StudentID);
        if (!$up1->execute()) {
            $ok = false;
        }
        $up1->close();

        // Also update Undergraduate.DeptID
        $dept_stmt = $mysqli->prepare("SELECT DeptID FROM Major WHERE MajorID = ? LIMIT 1");
        $dept_stmt->bind_param("i", $primaryMajor);
        $dept_stmt->execute();
        $dept_res = $dept_stmt->get_result()->fetch_assoc();
        $dept_stmt->close();

        $deptID = $dept_res['DeptID'] ?? null;

        if ($deptID !== null) {
            $ug_stmt = $mysqli->prepare("
                UPDATE Undergraduate SET DeptID = ? WHERE StudentID = ?
            ");
            $ug_stmt->bind_param("ii", $deptID, $StudentID);
            if (!$ug_stmt->execute()) {
                $ok = false;
            }
            $ug_stmt->close();
        }
    }

    if ($ok) {
        $mysqli->commit();
        echo "<script>alert('Major Declared ✅');</script>";
    } else {
        $mysqli->rollback();
        echo "<script>alert('Could Not Declare Major');</script>";
    }
}


?>

<!doctype html>
<html lang="en">
<?php $nu_title = 'Declare Major'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Declare Major'; $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

  <!-- SEARCH Student CARD -->
<?php if (!$isStudent): ?>
<section class="hero card">
    <h2>Search for Student</h2>

    <form method="POST">
        <label>Student</label>
        <input type="text" name="searchID" required placeholder="Enter StudentID...">
        <button type="submit" name="searchStudent">Search</button>
    </form>
</section>
<?php endif; ?>


<!-- IF Student LOADED, DISPLAY FORM -->
<?php if (!empty($loadedStudent)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Declare Major: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>Student ID (read only): </label>
                <input type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <label>Select Major(s) (0–2 allowed):</label><br>

            <?php foreach ($availableMajors as $m): ?>
                <label>
                    <input type="checkbox" name="majorIDs[]" value="<?= $m['MajorID'] ?>"
                           <?= in_array($m['MajorID'], $studentMajorIDs) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($m['MajorName']) ?>
                </label><br>
            <?php endforeach; ?>

            <div class="field-block">
                <label>Date of Declaration</label>
                <input type="text" name="DateOfDeclaration" readonly 
                  value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="declareMajor">Save Changes</button>
            </div>

    </form>

</div>
</section>

<?php endif; ?>

 <?php require __DIR__ . '/partials/footer.php'; ?>


<script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    fetch(`get_majors.php`)
    .then(response => response.json())
    .then(data => {
        const majorSelect = document.getElementById('majorID');

    data.forEach(major => {
        const opt = document.createElement('option');
        opt.value = major.id;
        opt.textContent = major.name;

        if (major.id == currentMajor) opt.selected = true;

        majorSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading majors:', err));

</script>
</body>
</html>