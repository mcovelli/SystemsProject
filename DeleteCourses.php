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
    $CourseId = $_POST['courseID'] ?? '';

$mysqli->begin_transaction();

  $sql = "DELETE FROM Course WHERE CourseID = ?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("s", $CourseId);
        
  if ($stmt->execute()) {
    echo "alert('Course $CourseId deleted ✅');";
  } else {
    echo "alert('Could not delete course');";
        }
  
$mysqli->commit();
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Courses'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Delete Courses'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Delete Course</h1>
                </div>
            </div>
                <div id = "create-section-course">
                    <form id = "DeleteCourse" method = "POST" action = "">
                        <label for="courseID">Course ID: </label>
                        <input type = "text" id="courseID" name="courseID" required placeholder = "ex. BIOL 100">
                        <br>

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

    // Delete courses
    
    document.getElementById("DeleteCourse").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
</body>
</html>
