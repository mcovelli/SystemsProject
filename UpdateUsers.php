<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* --------------------------------------------------------------------------
   1. SECURITY CHECK — ONLY UpdateAdmin CAN ACCESS THIS PAGE
--------------------------------------------------------------------------- */

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

/* --------------------------------------------------------------------------
   2. LOAD STATIC LOOKUP DATA
--------------------------------------------------------------------------- */

function loadMajors($mysqli) {
    $res = $mysqli->query("SELECT MajorID, MajorName FROM Major ORDER BY MajorName");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function loadMinors($mysqli) {
    $res = $mysqli->query("SELECT MinorID, MinorName FROM Minor ORDER BY MinorName");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function loadPrograms($mysqli) {
    $res = $mysqli->query("SELECT ProgramID, ProgramName FROM Program ORDER BY ProgramName");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function loadDepartments($mysqli) {
    $res = $mysqli->query("SELECT DeptID, DeptName FROM Department ORDER BY DeptName");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function loadOffices($mysqli) {
    $res = $mysqli->query("SELECT RoomID FROM Room ORDER BY RoomID");
    return $res->fetch_all(MYSQLI_ASSOC);
}

/* --------------------------------------------------------------------------
   3. INITIALIZE VARIABLES
--------------------------------------------------------------------------- */

$loadedUser = null;
$studentData = null;
$facultyData = null;
$adminData = null;
$statData = null;
$facultyDepartments = [];

/* --------------------------------------------------------------------------
   4. SEARCH HANDLER
--------------------------------------------------------------------------- */

if (isset($_POST['searchUser'])) {
    $searchId = intval($_POST['searchID']);

    // Load Users table
    $stmt = $mysqli->prepare("SELECT * FROM Users WHERE UserID = ?");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($loadedUser) {
        $role = $loadedUser['UserType'];

        /** Load Student Data **/
        if ($role === 'Student') {
            $q = $mysqli->prepare("SELECT * FROM Student WHERE StudentID = ?");
            $q->bind_param("i", $searchId);
            $q->execute();
            $studentData = $q->get_result()->fetch_assoc();
            $q->close();

            // Undergraduate or Graduate sub-tables
            if ($studentData['StudentType'] === 'Undergraduate') {
                $q = $mysqli->prepare("SELECT * FROM Undergraduate WHERE StudentID = ?");
                $q->bind_param("i", $searchId);
                $q->execute();
                $studentUG = $q->get_result()->fetch_assoc();
                $q->close();
                $studentData['UG'] = $studentUG;
            } else {
                $q = $mysqli->prepare("SELECT * FROM Graduate WHERE StudentID = ?");
                $q->bind_param("i", $searchId);
                $q->execute();
                $studentGrad = $q->get_result()->fetch_assoc();
                $q->close();
                $studentData['GR'] = $studentGrad;
            }
        }

        /** Load Faculty Data **/
        if ($role === 'Faculty') {
            $q = $mysqli->prepare("SELECT * FROM Faculty WHERE FacultyID = ?");
            $q->bind_param("i", $searchId);
            $q->execute();
            $facultyData = $q->get_result()->fetch_assoc();
            $q->close();

            // Load multiple departments
            $dep = $mysqli->prepare("SELECT DeptID FROM Faculty_Dept WHERE FacultyID = ?");
            $dep->bind_param("i", $searchId);
            $dep->execute();
            $facultyDepartments = array_column($dep->get_result()->fetch_all(MYSQLI_ASSOC), 'DeptID');
            $dep->close();
        }

        /** Load Admin Data **/
        if ($role === 'Admin') {
            $q = $mysqli->prepare("SELECT * FROM Admin WHERE AdminID = ?");
            $q->bind_param("i", $searchId);
            $q->execute();
            $adminData = $q->get_result()->fetch_assoc();
            $q->close();
        }

        /** Load StatStaff Data **/
        if ($role === 'StatStaff') {
            $q = $mysqli->prepare("SELECT * FROM StatStaff WHERE StatStaffID = ?");
            $q->bind_param("i", $searchId);
            $q->execute();
            $statData = $q->get_result()->fetch_assoc();
            $q->close();
        }
    }
}

/* --------------------------------------------------------------------------
   5. UPDATE HANDLER
--------------------------------------------------------------------------- */

if (isset($_POST['updateUser'])) {

    $uid = intval($_POST['UserID']); // READ ONLY FIELD
    $role = $_POST['UserType'];      // READ ONLY FIELD

    $mysqli->begin_transaction();

    try {

      $status = strtoupper(trim($_POST['Status']));
      if (!in_array($status, ['ACTIVE', 'INACTIVE'])) {
          die("Invalid status value: $status");
      }

        /* ----------------------
           UPDATE USERS TABLE
        ----------------------- */
        $sql = "UPDATE Users 
                SET FirstName=?, MiddleName=?, LastName=?, HouseNumber=?, Street=?, City=?, State=?, ZIP=?, Gender=?, DOB=?, PhoneNumber=?, Status=?
                WHERE UserID=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sssissssssssi",
            $_POST['FirstName'],
            $_POST['MiddleName'],
            $_POST['LastName'],
            $_POST['HouseNumber'],
            $_POST['Street'],
            $_POST['City'],
            $_POST['State'],
            $_POST['ZIP'],
            $_POST['Gender'],
            $_POST['DOB'],
            $_POST['PhoneNumber'],
            $status,
  
            $uid
        );
        $stmt->execute();
        $stmt->close();

        /* ----------------------
           STUDENT UPDATE
        ----------------------- */
        if ($role === 'Student') {

            // Update Student main table
            $q = $mysqli->prepare("
                UPDATE Student
                SET MajorID=?, MinorID=?, StudentType=?
                WHERE StudentID=?
            ");
            $q->bind_param("iisi", $_POST['MajorID'], $_POST['MinorID'], $_POST['StudentType'], $uid);
            $q->execute();
            $q->close();

            /* --- IF UNDERGRADUATE --- */
            if ($_POST['StudentType'] === "Undergraduate") {

                // Delete graduate row if exists
                $mysqli->query("DELETE FROM Graduate WHERE StudentID = $uid");

                // Update Undergraduate
                $q = $mysqli->prepare("
                    REPLACE INTO Undergraduate(StudentID, DeptID, UGStudentType)
                    VALUES (?, (SELECT DeptID FROM Major WHERE MajorID=?), ?)
                ");
                $q->bind_param("iis", $uid, $_POST['MajorID'], $_POST['UGStudentType']);
                $q->execute();
                $q->close();

            } else {

                // Delete undergraduate row if exists
                $mysqli->query("DELETE FROM Undergraduate WHERE StudentID = $uid");

                // Update Graduate
                $q = $mysqli->prepare("
                    REPLACE INTO Graduate(StudentID, DeptID, Year, GradStudentType, ProgramID)
                    VALUES (?, (SELECT DeptID FROM Program WHERE ProgramID=?), 1, ?, ?)
                ");
                $q->bind_param("issi", $uid, $_POST['ProgramID'], $_POST['GradStudentType'], $_POST['ProgramID']);
                $q->execute();
                $q->close();
            }

            // Reset StudentMajor
            $mysqli->query("DELETE FROM StudentMajor WHERE StudentID = $uid");
            if (!empty($_POST['MajorID'])) {
                $q = $mysqli->prepare("INSERT INTO StudentMajor(StudentID, MajorID, DateOfDeclaration) VALUES (?, ?, CURRENT_DATE)");
                $q->bind_param("ii", $uid, $_POST['MajorID']);
                $q->execute();
                $q->close();
            }

            // Reset StudentMinor
            $mysqli->query("DELETE FROM StudentMinor WHERE StudentID = $uid");
            if (!empty($_POST['MinorID'])) {
                $q = $mysqli->prepare("INSERT INTO StudentMinor(StudentID, MinorID, DateOfDeclaration) VALUES (?, ?, CURRENT_DATE)");
                $q->bind_param("ii", $uid, $_POST['MinorID']);
                $q->execute();
                $q->close();
            }
        }

        /* ----------------------
           FACULTY UPDATE
        ----------------------- */
        if ($role === 'Faculty') {

            // Update Faculty table
            $q = $mysqli->prepare("
                UPDATE Faculty
                SET OfficeID=?, Specialty=?, Ranking=?, FacultyType=?
                WHERE FacultyID=?
            ");
            $q->bind_param(
                "ssssi",
                $_POST['OfficeID'],
                $_POST['Specialty'],
                $_POST['Ranking'],
                $_POST['FacultyType'],
                $uid
            );
            $q->execute();
            $q->close();

            // Reset multi-departments
            $mysqli->query("DELETE FROM Faculty_Dept WHERE FacultyID = $uid");

            if (!empty($_POST['Departments'])) {
                foreach ($_POST['Departments'] as $dept) {
                    $ins = $mysqli->prepare("
                        INSERT INTO Faculty_Dept(FacultyID, DeptID, DOA)
                        VALUES (?, ?, CURRENT_DATE)
                    ");
                    $ins->bind_param("ii", $uid, $dept);
                    $ins->execute();
                    $ins->close();
                }
            }
        }

        /* ----------------------
           ADMIN UPDATE
        ----------------------- */
        if ($role === 'Admin') {
            $q = $mysqli->prepare("UPDATE Admin SET SecurityType=? WHERE AdminID=?");
            $q->bind_param("si", $_POST['SecurityType'], $uid);
            $q->execute();
            $q->close();
        }

        /* ----------------------
           STAT STAFF UPDATE
        ----------------------- */
        if ($role === 'StatStaff') {
            $q = $mysqli->prepare("
                UPDATE StatStaff
                SET StaffName=(SELECT CONCAT(FirstName,' ',LastName) FROM Users WHERE UserID=?)
                WHERE StatStaffID=?
            ");
            $q->bind_param("ii", $uid, $uid);
            $q->execute();
            $q->close();
        }

        $mysqli->commit();
        $successMsg = "User updated successfully!";
    
    } catch (Exception $e) {
        $mysqli->rollback();
        die("Error updating user: " . $e->getMessage());
    }
}
?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Update Users • Northport University</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="./stylesGrade.css" />
<style>
/* Inline enhancements */
.field-block {
    margin-bottom: 12px;
}
label {
    font-weight: 600;
    display: block;
    margin-bottom: 3px;
}
input[type=text], input[type=date], select {
    width: 280px;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.multiselect {
    height: 120px;
    width: 300px;
    padding: 6px;
}
.section-card {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 10px;
    margin-top: 10px;
    background: var(--card-bg);
}
</style>
</head>

<body>
<header class="topbar">
    <div class="brand">
        <div class="logo"><i data-lucide="graduation-cap"></i></div>
        <h1>Northport University</h1>
        <span class="pill">Update Users Portal</span>
    </div>

    <div class="top-actions">
        <button id="themeToggle" class="icon-btn"><i data-lucide="moon"></i></button>

        <div class="divider"></div>

        <div class="header-left">
            <div class="menu">
                <button>☰ Menu</button>
                <div class="menu-content">
                    <a href="admin_profile.php">Profile</a>
                    <a href="update_admin_dashboard.php">Dashboard</a>
                    <a href="viewDirectory.php">View Directory</a>
                    <a href="createDirectory.php">Create User</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>


<main class="page">

<!-- SEARCH USER CARD -->
<section class="hero card">
    <div class="card-head">
        <h2>Search for User to Update</h2>
    </div>

    <form method="POST" style="margin-top: 10px;">
        <div class="field-block">
            <label>UserID</label>
            <input type="text" name="searchID" required placeholder="Enter UserID...">
        </div>

        <button type="submit" name="searchUser" class="btn">Search</button>
    </form>
</section>


<!-- IF USER LOADED, DISPLAY FORM -->
<?php if (!empty($loadedUser)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Update User: <?php echo htmlspecialchars($loadedUser['FirstName'] . " " . $loadedUser['LastName']); ?></h2>

    <?php if (!empty($successMsg)): ?>
        <p style="color: green; font-weight: 600;"><?php echo $successMsg; ?></p>
    <?php endif; ?>

    <form method="POST">

    <input type="hidden" name="UserID" value="<?php echo $loadedUser['UserID']; ?>">
    <input type="hidden" name="UserType" value="<?php echo $loadedUser['UserType']; ?>">

    <!-- USERS TABLE FIELDS -->
    <div class="section-card">
        <h3>Basic Information</h3>

        <div class="field-block">
            <label>UserID (Read Only)</label>
            <input type="text" value="<?php echo $loadedUser['UserID']; ?>" readonly>
        </div>

        <div class="field-block">
            <label>Email (Read Only)</label>
            <input type="text" value="<?php echo $loadedUser['Email']; ?>" readonly>
        </div>

        <div class="field-block">
            <label>First Name</label>
            <input type="text" name="FirstName" value="<?php echo $loadedUser['FirstName']; ?>">
        </div>

        <div class="field-block">
            <label>Middle Name</label>
            <input type="text" name="MiddleName" value="<?php echo $loadedUser['MiddleName']; ?>">
        </div>

        <div class="field-block">
            <label>Last Name</label>
            <input type="text" name="LastName" value="<?php echo $loadedUser['LastName']; ?>">
        </div>

        <div class="field-block">
            <label>House Number</label>
            <input type="text" name="HouseNumber" value="<?php echo $loadedUser['HouseNumber']; ?>">
        </div>

        <div class="field-block">
            <label>Street</label>
            <input type="text" name="Street" value="<?php echo $loadedUser['Street']; ?>">
        </div>

        <div class="field-block">
            <label>City</label>
            <input type="text" name="City" value="<?php echo $loadedUser['City']; ?>">
        </div>

        <div class="field-block">
            <label>State</label>
            <input type="text" name="State" value="<?php echo $loadedUser['State']; ?>">
        </div>

        <div class="field-block">
            <label>ZIP</label>
            <input type="text" name="ZIP" value="<?php echo $loadedUser['ZIP']; ?>">
        </div>

        <div class="field-block">
            <label>Phone Number</label>
            <input type="text" name="PhoneNumber" value="<?php echo $loadedUser['PhoneNumber']; ?>">
        </div>

        <div class="field-block">
            <label>Date of Birth</label>
            <input type="date" name="DOB" value="<?php echo $loadedUser['DOB']; ?>">
        </div>

        <div class="field-block">
          <label>Status</label>
             <select name="Status">
                <option value="ACTIVE" <?php if ($loadedUser['Status'] === 'ACTIVE') echo 'selected'; ?>>ACTIVE</option>
                <option value="INACTIVE" <?php if ($loadedUser['Status'] === 'INACTIVE') echo 'selected'; ?>>INACTIVE</option>
            </select>
        </div>

        <div class="field-block">
            <label>Gender</label>
            <select name="Gender">
                <option value="M" <?php if ($loadedUser['Gender'] === 'M') echo 'selected'; ?>>Male</option>
                <option value="F" <?php if ($loadedUser['Gender'] === 'F') echo 'selected'; ?>>Female</option>
            </select>
        </div>
    </div>


<!-- STUDENT SECTION -->
<?php if ($loadedUser['UserType'] === 'Student') : ?>
    <div class="section-card" id="studentSection">
        <h3>Student Information</h3>

        <div class="field-block">
            <label>Student Type</label>
            <select name="StudentType" id="StudentType">
                <option value="Undergraduate" <?php if ($studentData['StudentType'] === "Undergraduate") echo "selected"; ?>>Undergraduate</option>
                <option value="Graduate" <?php if ($studentData['StudentType'] === "Graduate") echo "selected"; ?>>Graduate</option>
            </select>
        </div>

        <div class="field-block">
            <label>Major</label>
            <select name="MajorID" id="MajorID">
                <?php foreach (loadMajors($mysqli) as $m): ?>
                    <option value="<?php echo $m['MajorID']; ?>"
                        <?php if ($studentData['MajorID'] == $m['MajorID']) echo 'selected'; ?>>
                        <?php echo $m['MajorName']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-block" id="MinorBlock">
            <label>Minor</label>
            <select name="MinorID">
                <option value="">None</option>
                <?php foreach (loadMinors($mysqli) as $n): ?>
                    <option value="<?php echo $n['MinorID']; ?>"
                        <?php if ($studentData['MinorID'] == $n['MinorID']) echo 'selected'; ?>>
                        <?php echo $n['MinorName']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Undergrad sub-options -->
        <?php if ($studentData['StudentType'] === 'Undergraduate') : ?>
        <div class="field-block" id="UGTypeBlock">
            <label>Undergrad Type</label>
            <select name="UGStudentType">
                <option value="FullTimeUG" <?php if ($studentData['UG']['UGStudentType'] === 'FullTimeUG') echo 'selected'; ?>>Full Time UG</option>
                <option value="PartTimeUG" <?php if ($studentData['UG']['UGStudentType'] === 'PartTimeUG') echo 'selected'; ?>>Part Time UG</option>
            </select>
        </div>
        <?php endif; ?>

        <!-- Graduate sub-options -->
        <?php if ($studentData['StudentType'] === 'Graduate') : ?>
        <div class="field-block" id="GradTypeBlock">
            <label>Grad Enrollment Type</label>
            <select name="GradStudentType">
                <option value="FullTimeGrad" <?php if ($studentData['GR']['GradStudentType'] === 'FullTimeGrad') echo 'selected'; ?>>Full Time Grad</option>
                <option value="PartTimeGrad" <?php if ($studentData['GR']['GradStudentType'] === 'PartTimeGrad') echo 'selected'; ?>>Part Time Grad</option>
            </select>
        </div>

        <div class="field-block" id="ProgramBlock">
            <label>Graduate Program</label>
            <select name="ProgramID">
                <?php foreach (loadPrograms($mysqli) as $p): ?>
                    <option value="<?php echo $p['ProgramID']; ?>"
                        <?php if ($studentData['GR']['ProgramID'] == $p['ProgramID']) echo 'selected'; ?>>
                        <?php echo $p['ProgramName']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>


<!-- FACULTY SECTION -->
<?php if ($loadedUser['UserType'] === 'Faculty'): ?>
    <div class="section-card" id="facultySection">
        <h3>Faculty Information</h3>

        <div class="field-block">
            <label>Faculty Type</label>
            <select name="FacultyType">
                <option value="FullTimeFaculty" <?php if ($facultyData['FacultyType'] === 'FullTimeFaculty') echo 'selected'; ?>>Full Time Faculty</option>
                <option value="PartTimeFaculty" <?php if ($facultyData['FacultyType'] === 'PartTimeFaculty') echo 'selected'; ?>>Part Time Faculty</option>
            </select>
        </div>

        <div class="field-block">
            <label>Office</label>
            <select name="OfficeID">
                <?php foreach (loadOffices($mysqli) as $o): ?>
                    <option value="<?php echo $o['RoomID']; ?>"
                        <?php if ($facultyData['OfficeID'] == $o['RoomID']) echo 'selected'; ?>>
                        <?php echo $o['RoomID']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-block">
            <label>Ranking</label>
            <select name="Ranking">
                <option value="Dr." <?php if ($facultyData['Ranking'] === "Dr.") echo 'selected'; ?>>Dr.</option>
                <option value="Asst Prof" <?php if ($facultyData['Ranking'] === "Asst Prof") echo 'selected'; ?>>Asst Prof</option>
                <option value="Assoc Prof" <?php if ($facultyData['Ranking'] === "Assoc Prof") echo 'selected'; ?>>Assoc Prof</option>
                <option value="Professor" <?php if ($facultyData['Ranking'] === "Professor") echo 'selected'; ?>>Professor</option>
            </select>
        </div>

        <div class="field-block">
            <label>Specialty</label>
            <input type="text" name="Specialty" value="<?php echo $facultyData['Specialty']; ?>">
        </div>

        <div class="field-block">
            <label>Departments (Multi-Select)</label>
            <select name="Departments[]" class="multiselect" multiple>
                <?php foreach (loadDepartments($mysqli) as $d): ?>
                <option value="<?php echo $d['DeptID']; ?>"
                    <?php if (in_array($d['DeptID'], $facultyDepartments)) echo "selected"; ?>>
                    <?php echo $d['DeptName']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
<?php endif; ?>


<!-- ADMIN SECTION -->
<?php if ($loadedUser['UserType'] === 'Admin'): ?>
    <div class="section-card" id="adminSection">
        <h3>Admin Options</h3>

        <div class="field-block">
            <label>Security Type</label>
            <select name="SecurityType">
                <option value="VIEW" <?php if ($adminData['SecurityType'] === 'VIEW') echo 'selected'; ?>>View Admin</option>
                <option value="UPDATE" <?php if ($adminData['SecurityType'] === 'UPDATE') echo 'selected'; ?>>Update Admin</option>
            </select>
        </div>
    </div>
<?php endif; ?>


<!-- STAT STAFF SECTION -->
<?php if ($loadedUser['UserType'] === 'StatStaff'): ?>
    <div class="section-card" id="statSection">
        <h3>Statistical Staff</h3>
        <p>Name is generated automatically from Users table on update.</p>
    </div>
<?php endif; ?>


<!-- SUBMIT BUTTON -->
<div style="margin-top: 20px;">
    <button type="submit" name="updateUser">Save Changes</button>
</div>

</form>

</section>

<?php endif; ?>


</main>

<footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved
</footer>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
lucide.createIcons();
document.getElementById('year').textContent = new Date().getFullYear();
</script>

<script>
/* ============================================================================
   THEME TOGGLE
============================================================================ */
const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', () => {
    const root = document.documentElement;
    const cur = root.getAttribute('data-theme') || 'light';
    root.setAttribute('data-theme', cur === 'light' ? 'dark' : 'light');
    themeToggle.querySelector('i').setAttribute('data-lucide', cur === 'light' ? 'sun' : 'moon');
    lucide.createIcons();
});


/* ============================================================================
   STUDENT DYNAMIC FORM CONTROL
   Handles:
   - Switching between Undergraduate and Graduate
   - Showing UGStudentType
   - Showing GradStudentType
   - Showing Grad Program
   - Hiding Minor for Grad
============================================================================ */
document.addEventListener("DOMContentLoaded", () => {

    const studentType = document.getElementById("StudentType");
    if (studentType) {
        studentType.addEventListener("change", updateStudentFormDisplay);
        updateStudentFormDisplay(); // run once on load
    }
});

function updateStudentFormDisplay() {

    const type = document.getElementById("StudentType")?.value;

    const minorBlock = document.getElementById("MinorBlock");
    const UGTypeBlock = document.getElementById("UGTypeBlock");
    const GradTypeBlock = document.getElementById("GradTypeBlock");
    const ProgramBlock = document.getElementById("ProgramBlock");

    // Hide all by default
    if (UGTypeBlock) UGTypeBlock.style.display = "none";
    if (GradTypeBlock) GradTypeBlock.style.display = "none";
    if (ProgramBlock) ProgramBlock.style.display = "none";

    // Show relevant fields
    if (type === "Undergraduate") {
        if (minorBlock) minorBlock.style.display = "block";
        if (UGTypeBlock) UGTypeBlock.style.display = "block";
        if (GradTypeBlock) GradTypeBlock.style.display = "none";
        if (ProgramBlock) ProgramBlock.style.display = "none";
    }

    if (type === "Graduate") {
        if (minorBlock) minorBlock.style.display = "none"; // Grad students do NOT have minors
        if (UGTypeBlock) UGTypeBlock.style.display = "none";
        if (GradTypeBlock) GradTypeBlock.style.display = "block";
        if (ProgramBlock) ProgramBlock.style.display = "block";
    }
}

lucide.createIcons();
</script>
</body>
</html>