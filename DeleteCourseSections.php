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

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CRN = $_POST['crn'] ?? '';
    
$mysqli->begin_transaction();

  $sql = "DELETE FROM CourseSection WHERE CRN = ? ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("i", $CRN);
        
  if ($stmt->execute()) {
    echo "alert('$CRN deleted ✅');";
  } else {
    echo "alert('Could not delete course section');";
        }
  
$mysqli->commit();
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Course Sections'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Delete Course Sections'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Delete Course Section</h1>
                </div>
            </div>
                <div id = "delete-section-course">
                    <form id = "DeleteCourseSection" method = "POST" action = "">
                        <label for="crn">CRN: </label>
                             <select name="crn" id="crn">
                              <option>--SELECT--</option>
                                </select><br>

                        <button type="submit" id = "submit">Delete</button>
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

    // Fetch course sections from get_coursesection.php
    fetch('get_coursesection.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('crn');
        const selected = new URLSearchParams(window.location.search).get('crn');

    data.forEach(cs => {
        const opt = document.createElement('option');
        opt.value = cs.crn;
        opt.textContent = cs.crn + ' - ' + cs.courseID;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading course sections:', err));

    // Delete course section
    document.getElementById("DeleteCourseSection").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>