<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

#Fetch section from database
$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CourseId = $_POST['courseID'] ?? '';
    $CourseName = $_POST['courseName'] ?? '';
    $DeptId = $_POST['dept'] ?? '';
    $CourseDesc = $_POST['courseDesc'] ?? '';
    $Credits = $_POST['credits'] ?? '';
    $CourseType = $_POST['courseType'] ?? '';

$mysqli->begin_transaction();
#inserts data into database
  $sql = "INSERT INTO Course (CourseID, CourseName, DeptID, Course_Desc, Credits, CourseType) VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ssssis", $CourseId, $CourseName, $DeptId, $CourseDesc, $Credits, $CourseType);
        
if ($stmt->execute()) {
  $mysqli->commit();
  header("Location: CreateCourses.php?success=1&name=" . urlencode($CourseName));
  exit;
} else {
  $mysqli->rollback();

  $msg = $stmt->error ?: "Could not create course";
  header("Location: CreateCourses.php?error=1&msg=" . urlencode($msg));
  exit;
}
  
$mysqli->commit();
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Courses'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Create Courses'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <div id="toast" class="toast hidden"></div>

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Create Course</h1>
                </div>
            </div>
                <div id = "create-section-course">
                    <form id = "CreateCourse" method = "POST" action = "">
                        <label for="courseID">Course ID: </label>
                        <input type = "text" id="courseID" name="courseID" required placeholder = "ex. BIOL 100">
                        <br>

                        <label for="courseName">Course Name: </label>
                             <input type = "text" id="courseName" name="courseName" required placeholder="ex. Biology Foundations"><br>

                        <label for="dept">Department: </label>
                             <select name="dept" id="dept">
                                </select><br>

                        <label for ="courseDesc">Course Description: </label>
                            <input type = "text" id="courseDesc" name="courseDesc" required placeholder="Introductory course with essential concepts and skills."><br>

                        <label for = "credits">Credits Needed:</label>
                            <input type = "number" id = "credits" name = "credits" required placeholder="ex. 3"><br>

                        <label for="courseType">Course Type: </label>
                             <select name="courseType" id="courseType" required>
                                </select><br>

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>
    <?php require __DIR__ . '/partials/footer.php'; ?>

<script>
     // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('dept');
        const selected = new URLSearchParams(window.location.search).get('dept');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.id;
        opt.textContent = name.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

    // Fetch course type from get_coursetype.php
    fetch('get_coursetype.php')
    .then(response => response.json())
    .then(data => {
        const courseTypeSelect = document.getElementById('courseType');
        const selectedCourseType = new URLSearchParams(window.location.search).get('courseType');

    data.forEach(type => {
        const opt = document.createElement('option');
        opt.value = type.type;
        opt.textContent = type.type;
        courseTypeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Course Types:', err));

    const form = document.getElementById("CreateCourse");
    form?.addEventListener("submit", () => console.log("Form submitted ✅"));
</script>

<script>
   function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  toast.textContent = message;

  toast.classList.remove("hidden", "error");
  if (type === "error") toast.classList.add("error");

  setTimeout(() => toast.classList.add("show"), 100);

  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.classList.add("hidden"), 300);
  }, 3000);
}

  document.addEventListener("DOMContentLoaded", () => {
  const params = new URLSearchParams(window.location.search);

  if (params.get("success") === "1") {
    const name = params.get("name") || "Course";
    showToast(`✅ ${name} created`);
    history.replaceState({}, "", window.location.pathname);
  }

  if (params.get("error") === "1") {
    const msg = params.get("msg") || "An error occurred";
    showToast(`❌ ${msg}`, "error");
    history.replaceState({}, "", window.location.pathname);
  }
});
</script>
</body>
</html>
