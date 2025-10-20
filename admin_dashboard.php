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

$sql = "SELECT u.UserID, u.FirstName, u.LastName, u.Email, u.UserType, u.Status, u.DOB, a.SecurityType, a.AdminID
        FROM Users u JOIN Admin a ON u.UserID = a.AdminID WHERE UserID = ? LIMIT 1";
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

<!-- ****Add Email and UserID generation**** -->


<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Northport University Admin Dashboard</title>
  <link rel="stylesheet" href="admindashboardstyles.css">
  <style>
    #subTypeMenu { display: none; }
    .tab-selection { display: none; }

    .tab-selection.active { display: block; }

    .tab-buttons button.active 
    {
      background-color: #004080;
      color: white;
    }
  </style>
</head>
<body>
  <header>
    <h1>🎓 Northport University Admin</h1>
    <h3>
      Welcome, <?php echo htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName'] . ' (' . $admin['UserType'] . ')'); ?>
    </h3>
    <input type="text" placeholder="Search courses, people, etc..." />
  </header>

  <main>
    <section>
      <div class="card">
        <div class="tab-buttons">
          <button id="account-tab" class="active">Account</button>
          <button id="create-tab">Create New User Account</button>
          <button id="search-tab">Search User Account</button>
        </div>

          <!-- Account Info -->
        <div id = "account-section" class = "tab-selection active">
      
          <p>Name: <?php echo htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']); ?> </p>
          <p>Security Level: <?php echo htmlspecialchars($admin['SecurityType']); ?> </p>

          <p>Admin ID: <?php echo htmlspecialchars($admin['AdminID']); ?> </p>

          <p>Email: <?php echo htmlspecialchars($admin['Email']); ?> </p>

        </div>

        <!-- CREATE USER FORM -->
        <div id = "create-section" class= "tab-selection">
          <form id="CreateUser">
            <label for="UserType">User Type:</label>
            <select id="UserType" name="UserType">
              <option value="">-- Select User Type --</option>
              <option value="student">Student</option>
              <option value="faculty">Faculty</option>
              <option value="admin">Admin</option>
              <option value="staff">Stat Staff</option>
            </select>
            <br>

          <div id="subTypeMenu">
            <label for="subType">Select User Sub Type:</label>
            <select id="subType"></select>
          </div>

          <br>
          <label for="fname">First Name:</label>
          <input type="text" id="fname" name="fname" required><br>

          <label for="mname">Middle Name:</label>
          <input type="text" id="mname" name="mname"><br>

          <label for="lname">Last Name:</label>
          <input type="text" id="lname" name="lname" required><br>

          <label for="address">Address:</label>
          <input type="text" id="address" name="address" placeholder="ex. 123 Main St.">
          <br>

          <label for="city">City:</label>
          <input type="text" id="city" name="city"><br>

          <label for="state">State:</label>
          <input type="text" id="state" name="state"><br>

          <label for="zip">Zip Code:</label>
          <input type="text" id="zip" name="zip"><br>

          <br>

          <label for="gender">Gender:</label>
          <select id="gender" name="gender">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select><br>

          <label for="DOB">Date of Birth:</label>
          <input type="date" id="DOB" name="DOB"><br>

          <button type="submit" id="submit">Submit</button>
        </form>
      </div>

      <!-- Search User -->
      <div id = "search-section" class = "tab-selection">
        <form id = "searchUser">
          <label for = "search">Search: </label>
          <input type = "text" id = "userSearch" name = "userSearch">
          <button type="submit" id="submit">Search</button>
        </form>

      </div>

    </section>
  </main>

  <footer>© 2025 Northport University • All rights reserved</footer>

  <script>

    // --- Tab switching logic ---
    const accountTab = document.getElementById("account-tab");
    const createTab = document.getElementById("create-tab");
    const searchTab = document.getElementById("search-tab");

    const accountSection = document.getElementById("account-section");
    const createSection = document.getElementById("create-section");
    const searchSection = document.getElementById("search-section");

    function activateTab(tab, section) {
      // Remove active from all
      [accountTab, createTab, searchTab].forEach(btn => btn.classList.remove("active"));
      [accountSection, createSection, searchTab].forEach(div => div.classList.remove("active"));

      // Activate chosen tab + section
      tab.classList.add("active");
      section.classList.add("active");
    }

    accountTab.addEventListener("click", () => activateTab(accountTab, accountSection));
    createTab.addEventListener("click", () => activateTab(createTab, createSection));
    searchTab.addEventListener("click", () => activateTab(searchTab, searchSection));

    // Subtype menu

    const UserType = document.getElementById("UserType");
    const subType = document.getElementById("subType");
    const subTypeMenu = document.getElementById("subTypeMenu");

    const options = {
      student: ["Undergraduate", "Graduate"],
      faculty: ["Full Time", "Part Time"],
      admin: ["Update", "View-Only"],
      staff: [""]
    };

    UserType.addEventListener("change", function() {
      const value = this.value.toLowerCase();

      if (!value || !options[value]) {
        subTypeMenu.style.display = "none";
        subType.innerHTML = "";
        return;
      }

      // Populate dropdown
      subType.innerHTML = "";
      options[value].forEach(function(item) {
        const option = document.createElement("option");
        option.textContent = item;
        option.value = item;
        subType.appendChild(option);
      });

      subTypeMenu.style.display = "block";
    });
  </script>
</body>
</html>