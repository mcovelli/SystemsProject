<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    redirect(PROJECT_ROOT . "/login.html");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch admin security type
$adminCheck = $mysqli->prepare("
    SELECT SecurityType 
    FROM Admin 
    WHERE AdminID = ? LIMIT 1
");
$adminCheck->bind_param("i", $_SESSION['user_id']);
$adminCheck->execute();
$adminType = $adminCheck->get_result()->fetch_assoc()['SecurityType'] ?? null;
$adminCheck->close();

if ($adminType !== 'UPDATE') {
    die("<h2 style='color:red;'>Access Denied: You are not an UpdateAdmin.</h2>");
}

$loadedCourse = null;

if (isset($_POST['searchCourse'])) {
    $searchId = $_POST['searchID'];

    // Load Course table
    $stmt = $mysqli->prepare("SELECT * FROM Course c JOIN Department d ON c.DeptID = d.DeptID WHERE CourseID = ?");
    $stmt->bind_param("s", $searchId);
    $stmt->execute();
    $loadedCourse = $stmt->get_result()->fetch_assoc();
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

if (isset($_POST['updateCourse'])) {
    $CourseId   = $_POST['courseID'];
    $CourseName = $_POST['courseName'];
    $DeptId     = $_POST['deptID'];
    $CourseDesc = $_POST['courseDesc'];
    $Credits    = $_POST['credits'];
    $CourseType = $_POST['courseType'];

    $mysqli->begin_transaction();

    $sql = "UPDATE Course 
            SET CourseID = ?, CourseName = ?, DeptID = ?, Course_Desc = ?, Credits = ?, CourseType = ?
            WHERE CourseID = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssiss",
        $CourseId,
        $CourseName,
        $DeptId,
        $CourseDesc,
        $Credits,
        $CourseType,
        $CourseId
    );

    $stmt->execute();
    $mysqli->commit();

    $_SESSION['update_success'] = true;
}


?>


<!doctype html>
<html lang="en">
<?php $nu_title = 'Update Courses'; require __DIR__ . '/partials/head.php'; ?>

<body>

<?php $nu_page = 'Update Courses'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

<div id="toast" class="toast hidden">Course updated successfully!</div>

<?php if (!empty($successMsg)): ?>
    <script>
        showToast("✅ Course updated successfully!");
    </script>
<?php endif; ?>

<main id="main" tabindex="-1" class="page">

<!-- SEARCH Course CARD -->
<section class="hero card">
    <div class="card-head">
        <h2>Search for Course to Update</h2>
    </div>

    <form method="POST" style="margin-top: 10px;">
        <div class="field-block">
            <label for="courseid">CourseID</label>
            <input id="courseid" type="text" name="searchID" required placeholder="Enter CourseID...">
        </div>

        <button type="submit" name="searchCourse" class="btn">Search</button>
    </form>
</section>


<!-- IF Course LOADED, DISPLAY FORM -->
<?php if (!empty($loadedCourse)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Update Course: <?php echo htmlspecialchars($loadedCourse['CourseName'] . " - " . $loadedCourse['CourseID']); ?></h2>

    <form method="POST">

        <input type="hidden" name="CourseID" value="<?php echo $loadedCourse['CourseID']; ?>">
        <input type="hidden" name="CourseType" value="<?php echo $loadedCourse['CourseType']; ?>">

        <!-- COURSE TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label for="courseid-2">CourseID</label>
                <input id="courseid-2" type="text" name="courseID" value="<?php echo $loadedCourse['CourseID']; ?>">
            </div>

            <div class="field-block">
                <label for="course-name">Course Name</label>
                <input id="course-name" type="text" name="courseName" value="<?php echo $loadedCourse['CourseName']; ?>" >
            </div>

            <div class="field-block">
                <label for="deptID">Department: </label>
                                <select name="deptID" id="deptID">
                                    <option value="<?php echo $loadedCourse['DeptID']; ?>"><?php echo $loadedCourse['DeptName']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label for="course-description">Course Description</label>
                <textarea id="course-description" name="courseDesc" rows="4" cols="50"><?php echo $loadedCourse['Course_Desc']; ?></textarea>
            </div>

            <div class="field-block">
                <label for="credits">Credits</label>
                <input id="credits" type="number" name="credits" value="<?php echo $loadedCourse['Credits']; ?>">
            </div>

            <div class="field-block">
                <label for="courseType">Course Type:</label>
                  <select name="courseType" id="courseType">
                    <option value="<?php echo $loadedCourse['CourseType']; ?>"><?php echo $loadedCourse['CourseType']; ?></option>
                  </select>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="updateCourse">Save Changes</button>
            </div>

    </form>

</div>
</section>

<?php endif; ?>


</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
lucide.createIcons();
document.getElementById('year').textContent = new Date().getFullYear();
</script>

<script>
/* ============================================================================
   THEME TOGGLE
============================================================================ */
// Fetch faculty from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');
        const selectedDept = new URLSearchParams(window.location.search).get('deptID');

    data.forEach(dept => {
        const opt = document.createElement('option');
        opt.value = dept.id;
        opt.textContent = dept.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading faculty:', err));

 // Fetch cousetypes from get_coursetype.php
      fetch('get_coursetype.php')
        .then(response => response.json())
        .then(data => {
          const courseTypeSelect = document.getElementById('courseType');
          const selectedType = new URLSearchParams(window.location.search).get('courseType');
          courseTypeSelect.innerHTML = "";

          data.forEach(type => {
            const opt = document.createElement('option');
            opt.value = type.type;
            opt.textContent = type.type;
            if (type === selectedType) opt.selected = true;
            courseTypeSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading course types:', err));


function showToast(message) {
    const toast = document.getElementById("toast");
    toast.textContent = message;
    toast.classList.remove("hidden");

    // Trigger animation
    setTimeout(() => {
        toast.classList.add("show");
    }, 100);

    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.classList.add("hidden"), 300);
    }, 3000);
}

// Show success toast if update was successful
<?php if (!empty($_SESSION['update_success'])): ?>
    showToast("✅ Course updated successfully!");
    <?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</script>
</body>
</html>