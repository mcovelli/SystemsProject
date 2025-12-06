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

$loadedItem = null;
$loadedRequirements = [];

if (isset($_POST['searchProgramBtn'])) {
    $search = $_POST['searchName'];
    $type = $_POST['searchType'];

    switch ($type) {

        case 'major':
            $stmt = $mysqli->prepare("
                SELECT MajorID AS id, MajorName AS name, DeptID
                FROM Major
                WHERE MajorName LIKE CONCAT('%', ?, '%')
                   OR MajorID LIKE CONCAT('%', ?, '%')
                LIMIT 1
            ");
            $stmt->bind_param("ss", $search, $search);
            break;

        case 'minor':
            $stmt = $mysqli->prepare("
                SELECT MinorID AS id, MinorName AS name, DeptID
                FROM Minor
                WHERE MinorName LIKE CONCAT('%', ?, '%')
                   OR MinorID LIKE CONCAT('%', ?, '%')
                LIMIT 1
            ");
            $stmt->bind_param("ss", $search, $search);
            break;

        case 'program':
            $stmt = $mysqli->prepare("
                SELECT ProgramID AS id, ProgramName AS name, DeptID
                FROM Program
                WHERE ProgramName LIKE CONCAT('%', ?, '%')
                   OR ProgramID LIKE CONCAT('%', ?, '%')
                LIMIT 1
            ");
            $stmt->bind_param("si", $search, $search);
            break;
    }

    $stmt->execute();
    $loadedItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If matched, load its requirements
    if ($loadedItem) {
        switch ($type) {

            case 'major':
                $stmt = $mysqli->prepare("
                    SELECT mr.CourseID, c.CourseName, mr.RequirementType, mr.SemesterLevel
                    FROM MajorRequirement mr
                    JOIN Course c ON mr.CourseID = c.CourseID
                    WHERE mr.MajorID = ?
                    ORDER BY SemesterLevel ASC
                ");
                break;

            case 'minor':
                $stmt = $mysqli->prepare("
                    SELECT mr.CourseID, c.CourseName, mr.RequirementType, mr.SemesterLevel
                    FROM MinorRequirement mr
                    JOIN Course c ON mr.CourseID = c.CourseID
                    WHERE mr.MinorID = ?
                    ORDER BY SemesterLevel ASC
                ");
                break;

            case 'program':
                $stmt = $mysqli->prepare("
                    SELECT pr.CourseID, c.CourseName, pr.RequirementType
                    FROM ProgramRequirement pr
                    JOIN Course c ON pr.CourseID = c.CourseID
                    WHERE pr.ProgramID = ?
                ");
                break;
        }

        $stmt->bind_param("i", $loadedItem['id']);
        $stmt->execute();
        $loadedRequirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['UpdateRequirements'])) {

    $type = $_POST['requirementSelection'] ?? '';
    $programID = $_POST['programID'] ?? '';
    $selectedCourses = $_POST['courseID'] ?? [];
    $reqType = 'Core';
    $semesterLevel = $_POST['semester_level'] ?? null;

    if (empty($type) || empty($programID)) {
        die("Missing requirement type or program ID.");
    }

    // Begin transaction
    $mysqli->begin_transaction();

    try {

        /** 1) GET ALL EXISTING REQUIREMENTS **/
        switch ($type) {

            case "major":
                $table = "MajorRequirement";
                $idCol = "MajorID";
                break;

            case "minor":
                $table = "MinorRequirement";
                $idCol = "MinorID";
                break;

            case "program":
                $table = "ProgramRequirement";
                $idCol = "ProgramID";
                break;
        }

        // Fetch existing requirement CourseIDs
        $existingSQL = "SELECT CourseID FROM $table WHERE $idCol = ?";
        $stmt = $mysqli->prepare($existingSQL);
        $stmt->bind_param("i", $programID);
        $stmt->execute();
        $existingReqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $existingCourseIDs = array_column($existingReqs, "CourseID");

        /** 2) DELETE REQUIREMENTS NO LONGER SELECTED **/
        if (!empty($existingCourseIDs)) {
            $toDelete = array_diff($existingCourseIDs, $selectedCourses);

            if (!empty($toDelete)) {
                $deleteSQL = "DELETE FROM $table WHERE $idCol = ? AND CourseID = ?";
                $del = $mysqli->prepare($deleteSQL);

                foreach ($toDelete as $cid) {
                    $del->bind_param("is", $programID, $cid);
                    $del->execute();
                }
                $del->close();
            }
        }

        /** 3) INSERT OR UPDATE SELECTED COURSES **/
        $checkSQL = "SELECT COUNT(*) FROM $table WHERE $idCol = ? AND CourseID = ?";
        $check = $mysqli->prepare($checkSQL);

        if ($type === "program") {
            // PROGRAM (no semesterLevel)
            $insertSQL = "INSERT INTO $table ($idCol, CourseID, RequirementType)
                          VALUES (?, ?, ?)";
            $updateSQL = "UPDATE $table SET RequirementType=? WHERE $idCol=? AND CourseID=?";
            $insert = $mysqli->prepare($insertSQL);
            $update = $mysqli->prepare($updateSQL);

            foreach ($selectedCourses as $cid) {

                // Check if exists
                $check->bind_param("is", $programID, $cid);
                $check->execute();
                $exists = $check->get_result()->fetch_row()[0];

                if ($exists) {
                    $update->bind_param("sis", $reqType, $programID, $cid);
                    $update->execute();
                } else {
                    $insert->bind_param("iss", $programID, $cid, $reqType);
                    $insert->execute();
                }
            }
            
            $insert->close();
            $update->close();
        }

        else {
            // MAJOR or MINOR (has semester levels)
            $insertSQL = "INSERT INTO $table ($idCol, CourseID, RequirementType, SemesterLevel)
                          VALUES (?, ?, ?, ?)";
            $updateSQL = "UPDATE $table SET RequirementType=?, SemesterLevel=? 
                          WHERE $idCol=? AND CourseID=?";
            $insert = $mysqli->prepare($insertSQL);
            $update = $mysqli->prepare($updateSQL);

            foreach ($selectedCourses as $cid) {

                // Check if exists
                $check->bind_param("is", $programID, $cid);
                $check->execute();
                $exists = $check->get_result()->fetch_row()[0];

                if ($exists) {
                    $update->bind_param(
                        "siis",
                        $reqType,
                        $semesterLevel,
                        $programID,
                        $cid
                    );
                    $update->execute();
                } else {
                    $insert->bind_param(
                        "issi",
                        $programID,
                        $cid,
                        $reqType,
                        $semesterLevel
                    );
                    $insert->execute();
                }
            }
            
            $insert->close();
            $update->close();
        }

        $check->close();

        // Commit and set success flag
        $mysqli->commit();
        $_SESSION['update_success'] = true;
        
        // Redirect back to the same page
        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
        exit();

    } catch (Exception $e) {

        $mysqli->rollback();
        die("Update failed: " . $e->getMessage());
    }
}

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        $profile = 'student_profile.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        $profile = 'faculty_profile.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile = 'admin_profile.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
            $profile = 'admin_profile.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        $profile = 'admin_profile.php';
        break;
    default:
        $dashboard = 'login.html'; // fallback
        $profile = 'login.html';
}


$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Update Requirements</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
  <style>
    #requirementSelection {
        display: none;
    }

    .toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(-15px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 9999;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
}

.toast.hidden {
    display: none;
}
</style>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Add/Delete Requirements</span>
    </div>
    <div class="top-actions">
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="menu">
          <button>☰ Menu</button>
          <div class="menu-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($profile) ?>">Profile</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

    <main class="page">

        <section class="hero card">
        <h2>Search Existing Major / Minor / Program</h2>

        <form method="POST">
            <label>Search by Name or ID:</label>
            <input type="text" name="searchName" required placeholder="e.g. Biology, MATH, MBA">
            
            <select name="searchType" required>
                <option value="">-- Select Type --</option>
                <option value="major">Major</option>
                <option value="minor">Minor</option>
                <option value="program">Program</option>
            </select>

            <button type="submit" name="searchProgramBtn">Search</button>
        </form>
    </section>

    <?php if (!empty($loadedItem)): ?>
        <div class="card">
            <h3>Editing Requirements For:</h3>
            <p><strong><?= htmlspecialchars($loadedItem['name']) ?></strong></p>
            <p>ID: <?= $loadedItem['id'] ?></p>

            <?php if (!empty($loadedRequirements)): ?>
                <h4>Existing Requirements:</h4>
                <ul>
                    <?php foreach ($loadedRequirements as $req): ?>
                        <li>
                            <?= $req['CourseID'] ?> – <?= $req['CourseName'] ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><em>No requirements set yet.</em></p>
            <?php endif; ?>
        </div>

        <!-- AUTO-SET requirementSelection AND FIRE CHANGE EVENT -->
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const req = document.getElementById("requirementSelection");
            req.value = "<?= $type ?>";     
            req.dispatchEvent(new Event("change")); // critical
        });
        </script>
    <?php endif; ?>

<!-- auto-fill the dropdown -->
<script>
document.addEventListener("DOMContentLoaded", ()=>{
    document.getElementById("requirementSelection").value = "<?= $type ?>";
    document.getElementById("programID").innerHTML =
        `<option value="<?= $loadedItem['id'] ?>" selected><?= $loadedItem['name'] ?? '' ?></option>`;
});
</script>

        <section class="hero card">
          <div class="card-head between">
            <div>
              <h2 class="card-title">Add/Delete Requirements</h2>
            </div>
          </div>
       </section>

    <div id = "create-section-requirement">
        <form id = "reqForm" method = "POST">
            <label for="requirementSelection"></label>
                <select id="requirementSelection" name="requirementSelection" required>
                    <option value="">-- Select --</option>
                    <option value="major">Major Requirement</option>
                    <option value="minor">Minor Requirement</option>
                    <option value="program">Program Requirement</option>
                </select>

                    <div class="form-row">
                        <label for="programID">Program Name:</label>
                        <select id="programID" name="programID">
                            <option value="">-- Select --</option>
                        </select>
                    </div>

                    <input type="hidden" name="req_type" value="Core">

                    <!-- Department Filter -->
                    <div id="departmentFilterContainer" style="margin-top: 20px;">
                        <label for="deptFilter">Filter by Department:</label>
                        <select id="deptFilter" multiple size="5" style="width: 200px;">
                        </select>
                    </div>

                    <!-- Course Table -->
                    <div id="courseTableContainer" style="margin-top: 20px;">
                        <label>Select Courses:</label>

                        <div class="course-table-container">
                          <table class="course-table" id="courseTable">
                            <thead>
                              <tr>
                                <th>Select</th>
                                <th>Course ID</th>
                                <th>Course Name</th>
                                <th>Dept</th>
                                <th>Credits</th>
                                <th>Level</th>
                              </tr>
                            </thead>
                            <tbody></tbody>
                          </table>
                        </div>
                    </div><br>

            <button type="submit" name="UpdateRequirements">Submit</button>
        </form>
    </div>

<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

<div id="toast" class="toast hidden">Requirements updated successfully!</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<?php if (!empty($loadedRequirements)): ?>
<script>
    const EXISTING_COURSES = <?= json_encode(array_column($loadedRequirements, "CourseID")); ?>;
</script>
<?php else: ?>
<script>
    const EXISTING_COURSES = [];
</script>
<?php endif; ?>

<script>
lucide.createIcons();
document.getElementById('year').textContent = new Date().getFullYear();

const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', () => {
  const root = document.documentElement;
  const current = root.getAttribute('data-theme') || 'light';
  root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');

  themeToggle.querySelector('i').setAttribute(
      'data-lucide',
      current === 'light' ? 'sun' : 'moon'
  );
  if (window.lucide) lucide.createIcons();
});

document.addEventListener("DOMContentLoaded", () => {
    const RequirementSelection = document.getElementById("requirementSelection");
    const semesterLevelContainer = document.getElementById("semesterLevelContainer");
    const SemesterLevel = document.getElementById("semester_level");
    const ProgramID = document.getElementById("programID");
    const deptFilter = document.getElementById("deptFilter");
    const courseTableBody = document.querySelector("#courseTable tbody");

    let ALL_COURSES = [];

    semesterLevelContainer.style.display = "none";
    SemesterLevel.required = false;
    ProgramID.style.display = "block";

   RequirementSelection.addEventListener("change", function () {
        const value = this.value;

        // RESET
        semesterLevelContainer.style.display = "none";
        SemesterLevel.required = false;
        SemesterLevel.value = "";
        ProgramID.innerHTML = '<option value="">-- Select --</option>';

        if (!value) return;

        // PROGRAM — NO semester level
        if (value === "program") {
            semesterLevelContainer.style.display = "none";
            SemesterLevel.required = false;

            fetch('get_programs.php')
                .then(r => r.json())
                .then(programs => {
                    programs.forEach(p => {
                        ProgramID.insertAdjacentHTML("beforeend",
                            `<option value="${p.id}">${p.name}</option>`);
                    });
                });

            updateCourseTable();
            return;
        }

        // MAJOR — NEED semester level
        if (value === "major") {
            semesterLevelContainer.style.display = "block";
            SemesterLevel.required = true;

            fetch('get_majors.php')
                .then(r => r.json())
                .then(majors => {
                    majors.forEach(m => {
                        ProgramID.insertAdjacentHTML("beforeend",
                            `<option value="${m.id}">${m.name}</option>`);
                    });
                });

            updateCourseTable();
            return;
        }

        // MINOR — NEED semester level
        if (value === "minor") {
            semesterLevelContainer.style.display = "block";
            SemesterLevel.required = true;

            fetch('get_minors.php')
                .then(r => r.json())
                .then(minors => {
                    minors.forEach(m => {
                        ProgramID.insertAdjacentHTML("beforeend",
                            `<option value="${m.id}">${m.name}</option>`);
                    });
                });

            updateCourseTable();
            return;
        }
    });

    // Load all courses
    fetch("get_courses.php")
        .then(r => r.json())
        .then(data => {
            ALL_COURSES = data;

            const departments = [...new Set(data.map(c => c.deptName))];

            // Insert ALL option first
            deptFilter.insertAdjacentHTML("beforeend",
                `<option value="__ALL__">-- All Departments --</option>`);

            // Insert each department
            departments.forEach(d => {
                deptFilter.insertAdjacentHTML("beforeend",
                    `<option value="${d}">${d}</option>`);
            });

            // AUTO-SELECT ALL DEPARTMENTS
            Array.from(deptFilter.options).forEach(opt => {
                if (opt.value === "__ALL__") opt.selected = true;
            });

            // Update course table immediately to show all courses
            updateCourseTable();
        });

    function updateCourseTable() {
        const selectedRequirement = RequirementSelection.value;
        const selectedDepartments = Array.from(deptFilter.selectedOptions).map(o => o.value);

        let filtered = ALL_COURSES.filter(c => {
            if (selectedRequirement === "major" || selectedRequirement === "minor")
                return c.level === "UNDERGRAD";
            if (selectedRequirement === "program")
                return c.level === "GRAD";
            return true;
        });

        if (!selectedDepartments.includes("__ALL__") && selectedDepartments.length > 0) {
            filtered = filtered.filter(c => selectedDepartments.includes(c.deptName));
        }

        courseTableBody.innerHTML = "";

        filtered.forEach(c => {
            const isChecked = EXISTING_COURSES.includes(c.courseID) ? "checked" : "";

            courseTableBody.insertAdjacentHTML("beforeend", `
                <tr>
                    <td><input type="checkbox" name="courseID[]" value="${c.courseID}" ${isChecked}></td>
                    <td>${c.courseID}</td>
                    <td>${c.courseName}</td>
                    <td>${c.deptName}</td>
                    <td>${c.credits}</td>
                    <td>${c.level}</td>
                </tr>
            `);
        });
    }

    RequirementSelection.addEventListener("change", updateCourseTable);
    deptFilter.addEventListener("change", updateCourseTable);
});

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
    showToast("✅ Program updated successfully!");
    <?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</script>
</body>
</main>

 