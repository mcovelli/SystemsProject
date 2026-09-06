<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])){
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$selectedDept = $_GET['dept'] ?? '';

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

$sql = "SELECT p.ProgramID, p.ProgramName, p.DeptID, p.DegreeLevel, p.CreditsRequired, d.DeptName, d.Email, p.Status FROM Program p LEFT JOIN Department d ON p.DeptID = d.DeptID ";

/* Programs are retired rather than deleted, so this list has to be able
   to show the retired ones -- otherwise there is no way to find one and
   reactivate it. LEFT JOIN because a program's DeptID is nullable, and an
   inner join silently hid those rows. */
$selectedStatus = strtoupper($_GET['status'] ?? '');
if (!in_array($selectedStatus, ['ACTIVE', 'INACTIVE'], true)) {
    $selectedStatus = '';
}

$conditions = [];
$params = [];
$types = '';

if (!empty($selectedDept)) {
    $conditions[] = "d.DeptName = ?";
    $params[] = $selectedDept;
    $types .= 's';
}

if ($selectedStatus !== '') {
    $conditions[] = "p.Status = ?";
    $params[] = $selectedStatus;
    $types .= 's';
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY p.ProgramName ASC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$programs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Program Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Program Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

 <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Programs</h2>
          <div class="sub muted">Filter By Department</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form method="GET" id="filterForm" style="margin-bottom: 20px;">
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <label for="status">Status:</label>
          <select name="status" id="status">
            <option value=""         <?= $selectedStatus === ''         ? 'selected' : '' ?>>All</option>
            <option value="ACTIVE"   <?= $selectedStatus === 'ACTIVE'   ? 'selected' : '' ?>>Active only</option>
            <option value="INACTIVE" <?= $selectedStatus === 'INACTIVE' ? 'selected' : '' ?>>Retired only</option>
          </select>

          <button type="submit">Apply Filters</button>
        </form>
        <p>Click ID to pull up requirements</p>
    </div>

      <div class="table-wrap">
        <table id="programsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Program ID</th><th>Program Name</th><th>Department Name</th><th>Degree Level</th><th>Credits</th><th>Department Email</th><th>Status</th></tr></thead>
            <tbody id="programsBody">
              <?php if (!empty($programs)): ?>
                <?php foreach ($programs as $p): ?>
                  <tr>
                    <td><a href="ViewProgramRequirements.php?ProgramID=<?= urlencode($p['ProgramID']) ?>&ProgramName=<?= urlencode($p['ProgramName']) ?>">
                      <?= htmlspecialchars($p['ProgramID']) ?> </a></td>
                    <td><?= htmlspecialchars($p['ProgramName']) ?></td>
                    <td><?= htmlspecialchars($p['DeptName'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['DegreeLevel']) ?></td>
                    <td><?= htmlspecialchars($p['CreditsRequired']) ?></td>
                    <td><?= htmlspecialchars($p['Email'] ?? '') ?></td>
                    <td>
                      <span class="status-pill <?= strtoupper($p['Status']) === 'ACTIVE' ? 'on' : 'off' ?>">
                        <?= $p['Status'] === 'ACTIVE' ? 'Active' : 'Retired' ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="7">No Programs found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>
    <?php require __DIR__ . '/partials/footer.php'; ?>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

      // Fetch departments from get_departments.php
      fetch('get_departments.php')
        .then(response => response.json())
        .then(data => {
          const deptSelect = document.getElementById('dept');
          const selectedDept = new URLSearchParams(window.location.search).get('dept');

          data.forEach(dept => {
          const opt = document.createElement('option');
          opt.value = dept.name;
          opt.textContent = dept.name;

          if (dept.name === selectedDept) {
            opt.selected = true;
          }

          deptSelect.appendChild(opt);
        });
        })
        .catch(err => console.error('Error loading departments:', err));

    </script>

  </body>
</html>
