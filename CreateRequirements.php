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
    $requirementSelection = $_POST['requirementSelection'] ?? '';
    $programID = $_POST['programID'] ?? '';
    $courses = $_POST['courseID'] ?? [];  // <-- ARRAY
    $requirementType = $_POST['req_type'] ?? NULL;
    $semesterLevel = $_POST['semester_level'] ?? 1;

    $mysqli->begin_transaction();

    try {

        switch ($requirementSelection) {

            case "major":
                $sql = "INSERT INTO MajorRequirement
                        (MajorID, CourseID, RequirementType, SemesterLevel)
                        VALUES (?, ?, ?, ?)";

                $stmt = $mysqli->prepare($sql);

                foreach ($courses as $cid) {
                    $stmt->bind_param("issi", $programID, $cid, $requirementType, $semesterLevel);
                    $stmt->execute();
                }

                $stmt->close();
                break;


            case "minor":
                $sql = "INSERT INTO MinorRequirement
                        (MinorID, CourseID, RequirementType, SemesterLevel)
                        VALUES (?, ?, ?, ?)";

                $stmt = $mysqli->prepare($sql);

                foreach ($courses as $cid) {
                    $stmt->bind_param("issi", $programID, $cid, $requirementType, $semesterLevel);
                    $stmt->execute();
                }

                $stmt->close();
                break;


            case "program":
                $sql = "INSERT INTO ProgramRequirement
                        (ProgramID, CourseID, RequirementType)
                        VALUES (?, ?, ?)";

                $stmt = $mysqli->prepare($sql);

                foreach ($courses as $cid) {
                    $stmt->bind_param("iss", $programID, $cid, $requirementType);
                    $stmt->execute();
                }

                $stmt->close();
                break;
        }

        $mysqli->commit();
        echo "<script>alert('Requirements created for all selected courses!');</script>";

    } catch (Exception $e) {
        $mysqli->rollback();
        echo "<script>alert('Error creating requirements.');</script>";
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
<title>Create Requirements</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Create Requirements</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href="announcements.php"><i data-lucide="bell"></i></button>
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
          <div class="card-head between">
            <div>
              <h2 class="card-title">Create Requirements</h2>
              <h4 class = "card-title">Create by semester level</h4>
            </div>
          </div>
       </section>

    <div id = "create-section-requirement">
        <form id = "reqForm" method = "POST">
            <label for="requirementSelection">Select Type of Requirement:</label>
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

                    <label for="req_type">Requirement Type: </label>
                    <select id="req_type" name="req_type" required>
                      <option value="">-- Select Requirement Type --</option>
                      <option value="Core">Core</option>
                      <option value="Elective">Elective</option>
                    </select>

                    <div class="form-row" id="semesterLevelContainer">
                        <label for="semester_level">Semester Level:</label>
                        <input type="number" id="semester_level" name="semester_level">
                    </div>

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

                    <button type="submit" id = "submit">Submit</button>
        </form>
    </div>

<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

 <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<script>
      // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      // Swap the icon
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      if (window.lucide) lucide.createIcons();
    });

    document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("reqForm");
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

        // Reset
        semesterLevelContainer.style.display = "none";
        SemesterLevel.required = false;
        SemesterLevel.value = "";
        ProgramID.style.display = "block";
        ProgramID.required = true;
        ProgramID.innerHTML = '<option value="">-- Select --</option>';

        if (!value) return;

        // PROGRAM → load programs
        if (value === "program") {
            fetch('get_programs.php')
                .then(r => r.json())
                .then(programs => {
                    ProgramID.style.display = "block";
                    ProgramID.required = true;
                    ProgramID.innerHTML = '<option value="">-- Select --</option>';
                    semesterLevelContainer.style.display = "none";
                    SemesterLevel.required = false;
                    SemesterLevel.value = "";

                    programs.forEach(p => {
                        const opt = document.createElement("option");
                        opt.value = p.id;
                        opt.textContent = p.name;
                        ProgramID.appendChild(opt);
                    });
                });
            return;
        }

        // MAJOR → load majors
        if (value === "major") {
            fetch('get_majors.php')
                .then(r => r.json())
                .then(majors => {
                    ProgramID.style.display = "block";
                    ProgramID.required = true;
                    ProgramID.innerHTML = '<option value="">-- Select --</option>';
                    semesterLevelContainer.style.display = "block";
                    SemesterLevel.required = true;
                    SemesterLevel.value = "";

                    majors.forEach(m => {
                        const opt = document.createElement("option");
                        opt.value = m.id;
                        opt.textContent = m.name;
                        ProgramID.appendChild(opt);
                    });
                });
            return;
        }

        // MINOR → load minors
        if (value === "minor") {
            fetch('get_minors.php')
                .then(r => r.json())
                .then(minors => {
                    ProgramID.style.display = "block";
                    ProgramID.required = true;
                    ProgramID.innerHTML = '<option value="">-- Select --</option>';
                    semesterLevelContainer.style.display = "block";
                    SemesterLevel.required = true;
                    SemesterLevel.value = "";

                    minors.forEach(m => {
                        const opt = document.createElement("option");
                        opt.value = m.id;
                        opt.textContent = m.name;
                        ProgramID.appendChild(opt);
                    });
                });
            return;
        }
    });

    form.addEventListener("submit", (e) => {
        console.log("Form submitted ✅");
    });

    // Load all courses
    fetch("get_courses.php")
        .then(r => r.json())
        .then(data => {
            ALL_COURSES = data;

            // Populate department list
            const departments = [...new Set(data.map(c => c.deptName))];

            deptFilter.insertAdjacentHTML("beforeend", `
                <option value="__ALL__">-- All Departments --</option>
            `);

            departments.forEach(d => {
                deptFilter.insertAdjacentHTML("beforeend", `
                    <option value="${d}">${d}</option>
                `);
            });
        });

    // Filter function
    function updateCourseTable() {
        const selectedRequirement = RequirementSelection.value;
        const selectedDepartments = Array.from(deptFilter.selectedOptions).map(o => o.value);

        // Filter by course type
        let filtered = ALL_COURSES.filter(c => {
            if (selectedRequirement === "major" || selectedRequirement === "minor") {
                return c.level === "UNDERGRAD";
            }
            if (selectedRequirement === "program") {
                return c.level === "GRAD";
            }
            return true;
        });

        // Filter by selected departments
        if (selectedDepartments.includes("__ALL__")) {
            filtered = filtered; // no filtering
        } else if (selectedDepartments.length > 0) {
            filtered = filtered.filter(c => selectedDepartments.includes(c.deptName));
        }

        // Render table
        courseTableBody.innerHTML = "";
        filtered.forEach(c => {
            courseTableBody.insertAdjacentHTML("beforeend", `
                <tr>
                    <td><input type="checkbox" name="courseID[]" value="${c.id}"></td>
                    <td>${c.courseID}</td>
                    <td>${c.courseName}</td>
                    <td>${c.deptName}</td>
                    <td>${c.credits}</td>
                    <td>${c.level}</td>
                </tr>
            `);
        });
    }

    // React when requirement type changes
    RequirementSelection.addEventListener("change", updateCourseTable);

    // React when department filter changes
    deptFilter.addEventListener("change", updateCourseTable);
});


</script>
</body>
</main>

 