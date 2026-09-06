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

    $stmt = $mysqli->prepare("SELECT 
          s.StudentID, 
          CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
          sm.MinorID
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        LEFT JOIN StudentMinor sm ON s.StudentID = sm.StudentID
        LEFT JOIN Minor m ON sm.MinorID = m.MinorID
        WHERE s.StudentID = ?
        ORDER BY s.StudentID ASC");

    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else if (isset($_POST['searchStudent'])) {
    $searchId = $_POST['searchID'];

    // Load Student table
    $stmt = $mysqli->prepare("SELECT 
          s.StudentID, 
          CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
          smn.MinorID
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        LEFT JOIN StudentMinor smn ON s.StudentID = smn.StudentID
        LEFT JOIN Minor mn ON smn.MinorID = mn.MinorID
        WHERE s.StudentID = ?
        ORDER BY s.StudentID ASC");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$userId = $_SESSION['user_id'];

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if (isset($_POST['declareMinor'])) {
    $MinorID   = $_POST['MinorID'] ?? '';
    $StudentID = (int)($_POST['studentID'] ?? 0);

    $mysqli->begin_transaction();

    // Count majors for this student
    $major_count_stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM StudentMajor WHERE StudentID = ?");
    $major_count_stmt->bind_param("i", $StudentID);
    $major_count_stmt->execute();
    $major_count = (int)($major_count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $major_count_stmt->close();

    if ($major_count >= 2) {
        $mysqli->rollback();
        echo "<script>alert('Cannot declare a minor when 2 majors are declared.'); window.location='DeclareMinor.php';</script>";
        exit;
    }
    if ($MinorID === "") {
        // Remove minor
        $stmt = $mysqli->prepare("DELETE FROM StudentMinor WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE Student SET MinorID = NULL WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);

        if ($stmt->execute()) {
            $mysqli->commit();
            echo "<script>alert('Minor Removed ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could Not Remove Minor');</script>";
        }
        $stmt->close();

    } else {
        $MinorID = (int)$MinorID;

        // Declare/update minor
        $stmt = $mysqli->prepare("
            INSERT INTO StudentMinor (StudentID, MinorID, DateOfDeclaration)
            VALUES (?, ?, CURRENT_DATE())
            ON DUPLICATE KEY UPDATE
                MinorID = VALUES(MinorID),
                DateOfDeclaration = CURRENT_DATE()
        ");
        $stmt->bind_param("ii", $StudentID, $MinorID);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE Student SET MinorID = ? WHERE StudentID = ?");
        $stmt->bind_param("ii", $MinorID, $StudentID);

        if ($stmt->execute()) {
            $mysqli->commit();
            echo "<script>alert('Minor Declared ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could Not Declare Minor');</script>";
        }
        $stmt->close();
    }
}


?>

<!doctype html>
<html lang="en">
<?php $nu_title = 'Declare Minor'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Declare Minor'; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>
  <main id="main" tabindex="-1">

  <!-- SEARCH Student CARD -->
<?php if (!$isStudent): ?>
<section class="hero card">
    <h2>Search for Student</h2>

    <form method="POST">
        <label for="student">Student</label>
        <input id="student" type="text" name="searchID" required placeholder="Enter StudentID...">
        <button type="submit" name="searchStudent">Search</button>
    </form>
</section>
<?php endif; ?>


<!-- IF Student LOADED, DISPLAY FORM -->
<?php if (!empty($loadedStudent)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Declare Minor: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label for="student-id-read-only">Student ID (read only): </label>
                <input id="student-id-read-only" type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <div class="field-block">
                <label for="MinorID" required>Minor: </label>
                  <select name="MinorID" id ="MinorID">
                    <option value="">-- Undeclared --</option>
                  </select>
            </div>

            <div class="field-block">
                <label for="date-of-declaration">Date of Declaration</label>
                <input id="date-of-declaration" type="text" name="DateOfDeclaration" readonly 
                  value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="declareMinor">Save Changes</button>
            </div>

    </form>

</div>
</section>

<?php endif; ?>

  </main>
 <?php require __DIR__ . '/partials/footer.php'; ?>


<script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch Minors from get_Minors.php
    const currentMinor = "<?php echo $loadedStudent['MinorID']; ?>";

    fetch(`get_minors.php?current=${currentMinor}`)
    .then(response => response.json())
    .then(data => {
        const MinorSelect = document.getElementById('MinorID');

    data.forEach(Minor => {
        const opt = document.createElement('option');
        opt.value = Minor.id;
        opt.textContent = Minor.name;

        if (Minor.id == currentMinor) opt.selected = true;

        MinorSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Minors:', err));

</script>
</body>
</html>