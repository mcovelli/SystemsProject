<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: " . PROJECT_ROOT . "/login.html");
    exit;
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

if (!$admin) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Northport University Admin Dashboard</title>
  <link rel="stylesheet" href="admindashboardstyles.css">
</head>
<body>
  <header>
    <h1>🎓 Northport University Admin</h1>
    <h3>Welcome, <?php echo htmlspecialchars(
    $admin['FirstName'] . ' ' . $admin['LastName'] . $admn['UserType']); ?></h3>
    <input type="text" placeholder="Search courses, people, etc..." />
  </header>
  <main>
    <section>
    <div class="card">
        <div class="tab-buttons">
        <h2>Account</h2>
          <button id="create-tab" class="active">Create New User Account</button>
          <button id="search-tab">Search User Account</button>
        </div>
        <div id="create" class="create active">
          <button id="student-tab" class="active">Student</button>
          <form>
            <label for="program">Program</label><br>
            <input type="text" id="program" name="program" value="Enter program"><br>
            <label for="enrollmentyr">Enrollment Year</label><br>
            <input type="number" id="enrollmentyr" name="enrollmentyr" value="Enter enrollment year"><br>
             <label for="status">Status</label><br>
            <select id="status" name="student_status">
              <option value="active">Active</option>
              <option value="leave">Leave</option>
              <option value="graduated">Graduated</option>
            </select>
          </form>
        </div>
          <button id="faculty-tab">Faculty</button>
          <form>
            <label for="department">Department</label><br>
            <input type="text" id="department" name="department" value="Enter department"><br>
            <label for="facultyrole">Faculty Role</label><br>
            <select id="facultyrole" name="role">
              <option value="Lecturer">Lecturer</option>
              <option value="professor">Professor</option>
            </select><br>
            <label for="employmenttype">Employment Type</label><br>
            <select id="employmenttype" name="employment">
              <option value="full-time">Full Time</option>
              <option value="part-time">Part Time</option>
            </select>
          </form>
      </div>

      <div class="card">
        <h2>Manage Courses</h2>
        <form>
            <label for="code">Course Code</label><br>
            <input type="text" id="code" name="crn" value="Enter course code"><br>
            <label for="course_title">Course Title</label><br>
            <input type="text" id="title" name="title" value="Enter course title"><br>
            <label for="course_credits">Course Credits</label><br>
            <input type="number" id="course_credits" name="credit" value="Enter number of course credits"><br>
             <label for="course_dept">Course Department</label><br>
            <input type="text" id="course_dept" name="dept" value="Enter course department"><br>
            <label for="course_title">Course Instructor</label><br>
            <input type="text" id="instructor" name="instructor" value="Enter course instructor"><br>
            </select>
          </form>
      </div>
      </div>
    </section>

    <aside>
      <div class="card">
        <h2>Create Courses</h2>
        <form>
            <label for="CRN">CRN</label><br>
            <input type="text" id="CRN" name="crn" value="Enter course registration number" required><br>
            <label for="course_title">Course Title</label><br>
            <input type="text" id="title" name="title" value="Enter course title" required><br>
            <label for="section">Course Section</label><br>
            <input type="number" id="section" name="section" value="Enter course section" required><br>
             <label for="course_dept">Faculty</label><br>
            <input type="text" id="course_dept" name="dept" value="Enter course department" required><br>
            <label for="course_title">Course Instructor</label><br>
            <input type="text" id="instructor" name="instructor" value="Enter course instructor" required><br>
            </select>
          </form>
      </div>
    </aside>
  </main>

  <footer>© 2025 Northport University • All rights reserved</footer>

  <script>
    const createTab = document.getElementById('create-tab');
    const searchTab = document.getElementById('search-tab');
    const create = document.getElementById('create');
    const search = document.getElementById('search');

    createTab.addEventListener('click', () => {
      create.classList.add('active');
      search.classList.remove('active');
      createTab.classList.add('active');
      searchTab.classList.remove('active');
    });

    searchTab.addEventListener('click', () => {
      search.classList.add('active');
      create.classList.remove('active');
      searchTab.classList.add('active');
      createTab.classList.remove('active');
    });
  </script>
</body>
</html>