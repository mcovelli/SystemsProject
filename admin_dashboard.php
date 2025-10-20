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
  <style>
    #subTypeMenu { display: none; }
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
          <h2>Account</h2>
          <button id="create-tab" class="active">Create New User Account</button>
          <button id="search-tab">Search User Account</button>
        </div>

        <!-- CREATE USER FORM -->
        <form id="CreateUser">
          <label for="UserType">User Type:</label>
          <select id="UserType" name="UserType">
            <option value="">-- Select User Type --</option>
            <option value="student">Student</option>
            <option value="faculty">Faculty</option>
            <option value="admin">Admin</option>
            <option value="staff">Stat Staff</option>
          </select>

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
          <input type="text" id="address" name="address" placeholder="ex. 123 Main St."><br>

          <label for="city">City:</label>
          <input type="text" id="city" name="city"><br>

          <label for="state">State:</label>
          <input type="text" id="state" name="state"><br>

          <label for="zip">Zip Code:</label>
          <input type="text" id="zip" name="zip"><br>

          <label for="status">Status:</label>
          <input type="radio" id="ft" name="status" value="Full Time">
          <label for="ft">Full Time</label>
          <input type="radio" id="pt" name="status" value="Part Time">
          <label for="pt">Part Time</label><br>

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
    </section>
  </main>

  <footer>© 2025 Northport University • All rights reserved</footer>

  <script>
    const UserType = document.getElementById("UserType");
    const subType = document.getElementById("subType");
    const subTypeMenu = document.getElementById("subTypeMenu");

    const options = {
      student: ["Undergraduate", "Graduate"],
      faculty: ["Full Time", "Part Time"],
      admin: ["Update", "View-Only"],
      staff: ["HR", "IT Support"]
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