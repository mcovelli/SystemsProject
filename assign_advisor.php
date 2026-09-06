<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT u.UserID, u.FirstName, u.LastName, u.Email, u.UserType, u.Status, u.DOB, a.SecurityType, a.AdminID
        FROM Users u JOIN Admin a ON u.UserID = a.AdminID WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

$search = $_GET['search'] ?? '';

$sql = "SELECT 
        s.StudentID,
        CONCAT(u.FirstName, ' ', u.LastName) AS StudentName
    FROM Student s
    LEFT JOIN Advisor a ON s.StudentID = a.StudentID
    JOIN Users u ON s.StudentID = u.UserID
    WHERE a.FacultyID IS NULL";

if (!empty($search)) {
    $sql .= " 
    GROUP BY s.StudentID, u.FirstName, u.LastName, u.Email
    HAVING 
        StudentID LIKE CONCAT('%', ?, '%')
    ";
} else {
    $sql .= "
    GROUP BY s.StudentID, u.FirstName, u.LastName, u.Email
    ";
}

$sql .= " ORDER BY s.StudentID ASC";


$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("s", $search);
}

$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $FacultyID = $_POST['facultyID'] ?? '';
    $StudentID = $_POST['studentID'] ?? '';
    $DOA = date('Y-m-d');
    $Status = 'ACTIVE';
    $AssignedBy = $userId;

$mysqli->begin_transaction();

  $sql = "
    INSERT INTO Advisor (FacultyID, StudentID, DOA, Status, AssignedBy)
    VALUES (?, ?, CURRENT_DATE(), ?, ?)
    ON DUPLICATE KEY UPDATE
        FacultyID = VALUES(FacultyID),
        DOA = CURRENT_DATE(),
        Status = VALUES(Status),
        AssignedBy = VALUES(AssignedBy)
    ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iisi", $FacultyID, $StudentID, $Status, $AssignedBy);
        
  if ($stmt->execute()) {
    echo "alert('Advisor Assigned ✅');";
  } else {
    echo "alert('Could not assign advisor');";
        }
  
$mysqli->commit();
}

?>

<!doctype html>
<html lang="en">
<?php $nu_title = 'Assign Advisor'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Admin Portal'; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>
  <main id="main" tabindex="-1">

  <section class="hero card">
          <div class="card-head between">
            <div>
              <h2 class="card-title">Assign Advisor</h2>
            </div>
          </div>
       </section>

  <div>
    <form name = "assignStudent" method = "POST" action = "">
        <label for="studentID" required>StudentID: </label>
          <input type="text" id="studentID" name="studentID" placeholder="Enter StudentID"><br>
          <label for="facultyID" required>Faculty: </label>
          <select name="facultyID" id ="facultyID">
            <option value="">-- Select Faculty--</option>
          </select><br>
      <button type="submit">Assign</button>
    </form>
 </div>
  </main>
 <?php require __DIR__ . '/partials/footer.php'; ?>


<script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch faculty from get_faculty.php
    fetch('get_faculty.php')
  .then(response => response.json())
  .then(data => {
    const deptSelect = document.getElementById('facultyID');
    const selected = new URLSearchParams(window.location.search).get('facultyID');

    data.forEach(faculty => {
      const opt = document.createElement('option');
      opt.value = faculty.FacultyID;
      opt.textContent = faculty.FacultyID + ' - ' + faculty.FacultyName + ' - ' + faculty.DeptNames;

      if (faculty.FacultyID == selected) {
        opt.selected = true;
      }

      deptSelect.appendChild(opt);
    });
  });

</script>
</body>
</html>