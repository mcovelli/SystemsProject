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



?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Update Requirements'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Add/Delete Requirements'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

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

<?php require __DIR__ . '/partials/footer.php'; ?>

<div id="toast" class="toast hidden">Requirements updated successfully!</div>


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

document.addEventListener("DOMContentLoaded", () => {
    const RequirementSelection = document.getElementById("requirementSelection");
    const ProgramID = document.getElementById("programID");
    const deptFilter = document.getElementById("deptFilter");
    const courseTableBody = document.querySelector("#courseTable tbody");

    let ALL_COURSES = [];

    ProgramID.style.display = "block";

    RequirementSelection.addEventListener("change", function () {
        const value = this.value;

        // RESET
        ProgramID.innerHTML = '<option value="">-- Select --</option>';

        if (!value) return;

        // PROGRAM
        if (value === "program") {
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

        // MAJOR
        if (value === "major") {
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

        // MINOR
        if (value === "minor") {
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
        })
        .catch(err => console.error('Error loading courses:', err));

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

 