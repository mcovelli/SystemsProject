<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' &&
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

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {

    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'login.html';
        }
        break;
    default:
        $dashboard = 'login.html'; // fallback
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['userID'] ?? '';
    $firstName = $_POST['fname'] ?? '';
    $middleName = $_POST['mname'] ?? '';
    $lastName  = $_POST['lname'] ?? '';
    $dob       = $_POST['DOB'] ?? null;
    if ($dob === '') $dob = null;
    $gender    = $_POST['gender'] ?? null;
    $userType  = $_POST['UserType'] ?? '';
    $subType   = $_POST['subType'] ?? '';
    $subType2  = $_POST['subType2'] ?? '';
    $major     = $_POST['Major'] ?? null;
    $minor     = $_POST['Minor'] ?? null;
    $housenumber = $_POST['housenumber'] ?? null;
     if ($housenumber === '' || $housenumber === null) $housenumber = null;
    $street    = $_POST['street'] ?? '';
    $city      = $_POST['city'] ?? '';
    $state     = $_POST['state'] ?? '';
    $zip       = $_POST['zip'] ?? '';
    $phonenumber = $_POST['PhoneNumber'] ?? '';


    // Validate MajorID before insert
    $majorId = null;
    $minorId = null;

    if (strtolower($userType) === 'student') {
        // Validate MajorID
        if ($major === 'null' || $major === '' || strtolower($major ?? '') === 'undeclared') {
            $majorId = null; // treat as undeclared
        } else {
            $majorId = (int)$major;
            if ($majorId <= 0) {
                throw new Exception("Invalid Major selection: '{$major}'");
            }
        }

        // Validate MinorID if applicable
        if (!empty($minor) && $minor !== 'null' && $minor !== 'None') {
            $minorId = (int)$minor;
        }
    }
    error_log("DEBUG: Major={$major}, MajorID={$majorId}");

    $mysqli->begin_transaction();

    error_log("DEBUG: UserType={$userType}, subType={$subType}, subType2={$subType2}");

    error_log("FORM DEBUG: " . print_r($_POST, true));

    if ($userType === 'StatStaff') {
        $subType = '';
        $subType2 = '';
    }

    try {
      // Normalize UserType to match ENUM
      switch (strtolower(trim($userType))) {
          case 'student':
              $userType = 'Student';
              break;
          case 'faculty':
              $userType = 'Faculty';
              break;
          case 'admin':
              $userType = 'Admin';
              break;
          case 'statstaff':
          case 'staff':
          case 'stat staff':
              $userType = 'StatStaff';
              break;
          default:
              throw new Exception("Invalid user type: " . var_export($userType, true));
      }


        // Generate email
        $stmt = $mysqli->prepare("CALL GenerateUserEmail(?, ?, ?, @genEmail)");
        $stmt->bind_param("sss", $firstName, $middleName, $lastName);
        $stmt->execute();
        $stmt->close();
        $mysqli->next_result();

        $result = $mysqli->query("SELECT @genEmail AS email");
        $generatedEmail = ($result->fetch_assoc())['email'] ?? null;

        // Insert into Users
        $sql = "UPDATE Users 
            SET FirstName = ?, MiddleName = ?, LastName = ?, HouseNumber = ?, Street = ?, City = ?, State = ?, ZIP = ?, Gender = ?, DOB = ?, UserType = ?, Email = ?, PhoneNumber = ?, Status = 'ACTIVE'
            WHERE UserID = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sssisssssssssii",
            $firstName, $middleName, $lastName, $housenumber,
            $street, $city, $state, $zip,
            $gender, $dob, $userType, $generatedEmail, $phonenumber, $userId
        );
        $stmt->execute();
        $stmt->close();

        $password = password_hash('hashed_pw', PASSWORD_DEFAULT);
        $q = "INSERT INTO Login (LoginID, Email, UserType, Password, LoginAttempts, ResetToken, ResetExpiry, MustReset)
              VALUES (?, ?, ?, ?, 0, NULL, NULL, 1)";
        $stmt = $mysqli->prepare($q);
        $stmt->bind_param("isss", $userId, $generatedEmail, $userType, $password);
        $stmt->execute();
        $stmt->close();

        // Role-specific inserts
        switch ($userType) {
        case 'Student':
        // Insert Student
              $q = "UPDATE Student SET MajorID = ?, MinorID = ?, StudentType = ? WHERE StudentID = ?";
              $stmt = $mysqli->prepare($q);
              $stmt->bind_param("iisi", $majorId, $minorId, $subType, $userId);
              $stmt->execute();
              $stmt->close();
          if ($subType === 'Undergraduate') {
              // Insert into Undergraduate
              $q1 = "UPDATE Undergraduate SET DeptID = (SELECT DeptID FROM Major WHERE MajorID = ? LIMIT 1), UGStudentType = ? WHERE StudentID = ?";
              $stmt = $mysqli->prepare($q1);
              $stmt->bind_param("isi", $majorId, $subType2, $userId);
              $stmt->execute();
              $stmt->close();

              // Full/Part Time
              if ($subType2 === 'FullTimeUG') {
                  $q2 = "UPDATE FullTimeUG SET MaxCredits = 18, MinCredits = 12, Year = 'Freshman', CreditsEarned = 0 WHERE StudentID = ?";
              } else {
                  $q2 = "UPDATE PartTimeUG SET MaxCredits = 9, MinCredits = 3, Year = 'Freshman', CreditsEarned = 0 WHERE StudentID = ?";
              }
              $stmt = $mysqli->prepare($q2);
              $stmt->bind_param("i", $userId);
              $stmt->execute();
              $stmt->close();

              // StudentMajor
              if ($majorId !== null) {
                  $q3 = "UPDATE StudentMajor SET MajorID = ?, DateOfDeclaration = CURRENT_DATE WHERE StudentID = ?";
                  $stmt = $mysqli->prepare($q3);
                  $stmt->bind_param("ii", $majorId, $userId);
                  $stmt->execute();
                  $stmt->close();
              } else {
                  // Optionally, log or insert a placeholder record
                  error_log("DEBUG: Skipped StudentMajor insert — undeclared major for UserID={$userId}");
              }

              // StudentMinor (optional)
              if ($minorId) {
                  $q4 = "UPDATE StudentMinor SET MinorID = ?, DateOfDeclaration = CURRENT_DATE WHERE StudentID = ?";
                  $stmt = $mysqli->prepare($q4);
                  $stmt->bind_param("ii", $minorId, $userId);
                  $stmt->execute();
                  $stmt->close();
              }

          } elseif ($subType === 'Graduate') {
              $programId = (int)$major;
              $deptId = null;

              if (empty($major) || !ctype_digit($major)) {
                  throw new Exception("Missing or invalid Program selection for Graduate student.");
              }

              $stmt = $mysqli->prepare("SELECT DeptID FROM Program WHERE ProgramID = ? LIMIT 1");
              $stmt->bind_param("i", $programId);
              $stmt->execute();
              $stmt->bind_result($deptId);
              $stmt->fetch();
              $stmt->close();

              if (!$programId || !$deptId) {
                  throw new Exception("Invalid Program selection (ProgramID={$programId})");
              }

              $q1 = "UPDATE Graduate SET DeptID = ?, Year = 1, ProgramID = ?, GradStudentType = ? WHERE StudentID = ?";
              $stmt = $mysqli->prepare($q1);
              $stmt->bind_param("iisi", $deptId, $programId, $subType2, $userId);
              $stmt->execute();
              $stmt->close();

              // Full/Part Time Grad
              $q2 = ($subType2 === 'FullTimeGrad')
                  ? "UPDATE FullTimeGrad SET Year = 1, CreditsEarned = 0, ThesisYear = NULL WHERE StudentID = ?"
                  : "UPDATE PartTimeGrad SET Year = 1, CreditsEarned = 0, ThesisYear = NULL WHERE StudentID = ?";
              $stmt = $mysqli->prepare($q2);
              $stmt->bind_param("i", $userId);
              $stmt->execute();
              $stmt->close();
          }
          break;

            case 'Faculty':
              $department = $_POST['Department'] ?? null;
              $ranking    = $_POST['Ranking'] ?? 'Asst Prof';
              $specialty  = $_POST['Specialty'] ?? 'Undeclared';
              $office     = $_POST['Office'] ?? null;
              $subType    = $_POST['subType'] ?? ''; // ensure this is captured

              // Validate FacultyType
              if (!in_array($subType, ['FullTimeFaculty', 'PartTimeFaculty'])) {
                  throw new Exception("Invalid FacultyType value: " . var_export($subType, true));
              }

              // Update Faculty
              $q1 = "UPDATE Faculty SET OfficeID = ?, Specialty = ?, Ranking = ?, FacultyType = ? WHERE FacultyID = ?";
              $stmt = $mysqli->prepare($q1);
              $stmt->bind_param("ssssi", $office, $specialty, $ranking, $subType, $userId);
              $stmt->execute();
              $stmt->close();

              // Faculty subtype tables
              if ($subType === 'FullTimeFaculty') {
                  $q2 = "UPDATE FullTimeFaculty SET MaxCourses = 4 WHERE FacultyID = ?";
              } else {
                  $q2 = "UPDATE PartTimeFaculty SET MaxCourses = 2 WHERE FacultyID = ?";
              }
              $stmt = $mysqli->prepare($q2);
              $stmt->bind_param("i", $userId);
              $stmt->execute();
              $stmt->close();

              // Faculty_Dept link
              $q3 = "UPDATE Faculty_Dept SET DeptID = (SELECT DeptID FROM Department WHERE DeptName = ? LIMIT 1), DoA = CURRENT_DATE WHERE FacultyID = ?";
              $stmt = $mysqli->prepare($q3);
              $stmt->bind_param("si", $department, $userId);
              $stmt->execute();
              $stmt->close();
              break;

            case 'Admin':
              // Update base Admin table (no SecurityLevel column)
              $q1 = "UPDATE Admin SET AdminID = ? WHERE AdminID = ?";
              $stmt = $mysqli->prepare($q1);
              $stmt->bind_param("i", $userId);
              $stmt->execute();
              $stmt->close();

              // Insert into subtype table based on admin type
              if ($subType === 'UpdateAdmin') {
                  $q2 = "UPDATE UpdateAdmin SET AdminID = ? WHERE AdminID = ?";
              } else {
                  $q2 = "UPDATE ViewAdmin SET AdminID = ? WHERE AdminID = ?";
              }
              $stmt = $mysqli->prepare($q2);
              $stmt->bind_param("i", $userId);
              $stmt->execute();
              $stmt->close();
              break;

          case 'StatStaff':
            $q1 = "UPDATE StatStaff SET StaffName = (SELECT CONCAT(FirstName, ' ', LastName) FROM Users WHERE UserID = ?), Status = 'ACTIVE' WHERE StatStaffID = ?";
            $stmt = $mysqli->prepare($q1);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            break;
      }

      error_log("DEBUG UPDATE PATH: UserType=$userType, subType=$subType, subType2=$subType2, majorId=$majorId, deptId=" . ($deptId ?? 'NULL'));

        $mysqli->commit();
        header("Location: update_admin_dashboard.php?success=1");
        exit();

    } catch (Exception $e) {
        $mysqli->rollback();
        die("Error creating user: " . $e->getMessage());
        if (!$stmt->execute()) {
    throw new Exception("SQL Error: " . $stmt->error);
}
    }
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
<title>Create Users</title>
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
      <span class="pill">Create Users</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
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
              <h2 class="card-title">Create User</h2>
            </div>
          </div>
        </section>

      <section class="hero card">
        <div>
          <!-- CREATE USER FORM -->
          <div id = "create-section-user">
            <form id="CreateUser" method="POST" action="">
              <label for="UserType">User Type:</label>
              <select id="UserType" name="UserType" required>
                <option value="">-- Select User Type --</option>
                <option value="Student">Student</option>
                <option value="Faculty">Faculty</option>
                <option value="Admin">Admin</option>
                <option value="StatStaff">Stat Staff</option>
              </select>
              <br>

            <div id="subTypeMenu">
              <label for="subType">User Sub Type:</label>
              <select id="subType" name="subType"></select>
            </div>

            <div id="subTypeMenu2">
              <label for="subType2">Student Sub Type:</label>
              <select id="subType2" name="subType2" required></select>
            </div>

            <br>
            <label for ="userID" hidden>User ID:</label>
            <input type = "hidden" id = "userID" name="userID"></br>
            
            <label for="fname">First Name:</label>
            <input type="text" id="fname" name="fname"><br>

            <label for="mname">Middle Name:</label>
            <input type="text" id="mname" name="mname"><br>

            <label for="lname">Last Name:</label>
            <input type="text" id="lname" name="lname"><br>

            <label for="housenumber">House Number:</label>
            <input type="text" id="housenumber" name="housenumber" placeholder="ex. 123">
            <br>

            <label for="street">Street:</label>
            <input type="text" id="street" name="street" placeholder="ex. Main St., Apt 5">
            <br>

            <label for="city">City:</label>
            <input type="text" id="city" name="city" placeholder="ex. Merrick"><br>

            <label for="state">State:</label>
            <input type="text" id="state" name="state" placeholder="ex. NY"><br>

            <label for="zip">Zip Code:</label>
            <input type="text" id="zip" name="zip" placeholder="ex. 11566"><br>

            <br>

            <label for="gender">Gender:</label>
            <select id="gender" name="gender">
              <option value="">-- Select Gender --</option>
              <option value="M">Male</option>
              <option value="F">Female</option>
            </select><br>

            <label for="DOB">Date of Birth:</label>
            <input type="date" id="DOB" name="DOB"><br>

            <div id="MajorMenu">
              <label for="Major">Major:</label>
              <select id="Major" name="Major">
                <option value="" selected>Undeclared</option>
              </select>
            </div>

            <div id="MinorMenu">
              <label for="Minor">Minor:</label>
              <select id="Minor" name="Minor">
                <option value="" selected>Undeclared</option>
              </select>
            </div>

            <div id="DepartmentMenu">
              <label for="Department">Department:</label>
              <select id="Department" name="Department"></select>
            </div>

            <div id="RankingMenu">
              <label for="Ranking">Ranking:</label>
              <select id="Ranking" name="Ranking">
                <option value="">-- Select Ranking --</option>
                <option value="Dr.">Dr.</option>
                <option value="Asst Prof">Asst Prof</option>
                <option value="Assoc Prof">Assoc Prof</option>
                <option value="Professor">Professor</option>
              </select>
            </div>

            <div id="SpecialtyMenu">
              <label for="Specialty">Specialty:</label>
              <input type="text" id="Specialty" name="Specialty" placeholder="e.g. Artificial Intelligence, Microbiology, etc.">
            </div>

            <div id="OfficeMenu">
              <label for="Office">Office:</label>
              <select id="Office" name="Office"></select>
            </div>

            <button type="submit" id="submit">Submit</button>
          </form>
        </div>
      </section>
    </main>

    <footer>© 2025 Northport University • All rights reserved</footer>

   <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<script>
     lucide.createIcons();
    document.addEventListener("DOMContentLoaded", () => {
      const form = document.getElementById("CreateUser");
      subTypeMenu2.style.display = "none";
      MajorMenu.style.display = "none";
      MinorMenu.style.display = "none";
      DepartmentMenu.style.display = "none";
      OfficeMenu.style.display = "none";
      RankingMenu.style.display = "none";
      SpecialtyMenu.style.display = "none";
    });

    const UserType = document.getElementById("UserType");
    const subType = document.getElementById("subType");
    const subTypeMenu = document.getElementById("subTypeMenu");
    const subType2 = document.getElementById("subType2");
    const subTypeMenu2 = document.getElementById("subTypeMenu2");
    const MajorMenu = document.getElementById("MajorMenu");
    const MinorMenu = document.getElementById("MinorMenu");
    const DepartmentMenu = document.getElementById("DepartmentMenu");
    const OfficeMenu = document.getElementById("OfficeMenu");
    const RankingMenu = document.getElementById("RankingMenu");
    const SpecialtyMenu = document.getElementById("SpecialtyMenu");

    const majorSelect = document.getElementById("Major");
    const minorSelect = document.getElementById("Minor");
    const deptSelect = document.getElementById("Department");
    const officeSelect = document.getElementById("Office");

    const options = {
      Student: ["-- Select User SubType --", "Undergraduate", "Graduate"],
      Faculty: ["-- Select User SubType --", "FullTimeFaculty", "PartTimeFaculty"],
      Admin: ["-- Select User SubType --", "UpdateAdmin", "ViewAdmin"],
      StatStaff: [""]
    };

    const options2 = {
      Undergraduate: ["-- Select Student SubType --", "FullTimeUG", "PartTimeUG"],
      Graduate: ["-- Select Student SubType --", "FullTimeGrad", "PartTimeGrad"],
    };

    // STEP 1: When UserType changes
    UserType.addEventListener("change", function() {
      const value = this.value;

      if (value === "StatStaff") {
        subTypeMenu.style.display = "none";
        subType.required = false;
      } else {
        subTypeMenu.style.display = "block";
        subType.required = true;
      }

      // Reset/hide all optional menus
      [subTypeMenu2, MajorMenu, MinorMenu, DepartmentMenu, OfficeMenu, RankingMenu, SpecialtyMenu].forEach(div => {
        div.style.display = "none";
        div.querySelectorAll("select, input").forEach(el => el.required = false);
      });
      subType.innerHTML = "";
      subType2.innerHTML = "";

      if (!value || !options[value]) {
        subTypeMenu.style.display = "none";
        return;
      }

      // Populate subtype dropdown
      options[value].forEach(function(item) {
        const option = document.createElement("option");
        option.textContent = item;
        option.value = item;
        subType.appendChild(option);
      });

      subTypeMenu.style.display = "block";

      // If Faculty selected → load Department, Office, Ranking, Specialty
      if (value === "Faculty") {
        Promise.all([
          fetch('get_departments.php').then(r => r.json()),
          fetch('get_offices.php').then(r => r.json())
        ]).then(([departments, offices]) => {
          // Populate Department
          DepartmentMenu.style.display = "block";
          OfficeMenu.style.display = "block";
          RankingMenu.style.display = "block";
          SpecialtyMenu.style.display = "block";
          // make them required if needed
          deptSelect.required = true;
          officeSelect.required = true;

          deptSelect.innerHTML = "";
          departments.forEach(d => {
            const opt = document.createElement("option");
            opt.textContent = d;
            opt.value = d;
            deptSelect.appendChild(opt);
          });

          // Populate Office
          officeSelect.innerHTML = "";
          offices.forEach(o => {
            const opt = document.createElement("option");
            opt.textContent = o;
            opt.value = o;
            officeSelect.appendChild(opt);
          });
        });
      }
    });

    // STEP 2: When SubType (Undergraduate / Graduate) changes
    subType.addEventListener("change", function() {
      const value2 = this.value;
      subType2.innerHTML = "";
      MajorMenu.style.display = "none";
      MinorMenu.style.display = "none";

      if (!value2 || !options2[value2]) {
        subTypeMenu2.style.display = "none";
        return;
      }

      // Populate Student SubType (FullTimeUG, PartTimeUG, etc.)
      options2[value2].forEach(function(item2) {
        const opt2 = document.createElement("option");
        opt2.textContent = item2;
        opt2.value = item2;
        subType2.appendChild(opt2);
      });
      subTypeMenu2.style.display = "block";

     // Only show Major/Minor if "Undergraduate" or load programs if "Graduate"

      if (value2 === "Undergraduate" || value2 === "Graduate") {
        subType2.required = true;
        majorSelect.required = true;
      }
    if (value2 === "Undergraduate") {
      // Load majors for undergrads
      fetch('get_majors.php')
      .then(res => res.json())
      .then(data => {
        majorSelect.innerHTML = "";
        const undeclared = document.createElement("option");
        undeclared.value = "";
        undeclared.textContent = "Undeclared";
        majorSelect.appendChild(undeclared);
        data.forEach(m => {
          const opt = document.createElement("option");
          opt.textContent = m.name;
          opt.value = m.id; // MajorID
          majorSelect.appendChild(opt);
        });
        MajorMenu.style.display = "block";
      });

      // Load minors for undergrads
      fetch('get_minors.php')
      .then(res => res.json())
      .then(data => {
        minorSelect.innerHTML = "";
        const undeclaredMinor = document.createElement("option");
        undeclaredMinor.value = "";
        undeclaredMinor.textContent = "Undeclared";
        minorSelect.appendChild(undeclaredMinor);
        data.forEach(m => {
          const opt = document.createElement("option");
          opt.textContent = m.name;
          opt.value = m.id;  // send MinorID instead of name
          minorSelect.appendChild(opt);
        });
        MinorMenu.style.display = "block";
      });

    } else if (value2 === "Graduate") {
      // Load graduate programs
      fetch('get_programs.php')
      .then(res => res.json())
      .then(data => {
        majorSelect.innerHTML = "";
        const undeclared = document.createElement("option");
        undeclared.value = "";
        undeclared.textContent = "Undeclared";
        majorSelect.appendChild(undeclared);
        data.forEach(p => {
          const opt = document.createElement("option");
          opt.textContent = p.name;
          opt.value = p.id; // ProgramID
          majorSelect.appendChild(opt);
        });
        MajorMenu.style.display = "block";
        MinorMenu.style.display = "none";// Hide minors for graduate
        });

    } else {
      // Hide if neither Undergraduate nor Graduate
      MajorMenu.style.display = "none";
      MinorMenu.style.display = "none";
    }
  });

    form.addEventListener("submit", (e) => {
      console.log("Form submitted ✅");
    });

    </script>
  </body>
</html>