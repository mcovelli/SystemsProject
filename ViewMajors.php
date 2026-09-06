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

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

$selectedDept = $_GET['dept'] ?? '';

$sql = "SELECT m.MajorID, m.MajorName, m.DeptID, d.DeptName, d.Email, m.CreditsNeeded, m.Status FROM Major m LEFT JOIN Department d ON m.DeptID = d.DeptID ";

/* Majors are retired rather than deleted, so this list has to be able
   to show the retired ones -- otherwise there is no way to find one and
   reactivate it. LEFT JOIN because a major's DeptID is nullable, and an
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
    $conditions[] = "m.Status = ?";
    $params[] = $selectedStatus;
    $types .= 's';
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY m.MajorName ASC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$majors = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Major Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Major Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

 <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View Majors</h2>
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
        <table id="majorsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Major ID</th><th>Major Name</th><th>Department ID</th><th>Department Name</th><th>Credits Needed</th><th>Department Email</th><th>Status</th></tr></thead>
            <tbody id="majorsBody">
              <?php if (!empty($majors)): ?>
                <?php foreach ($majors as $m): ?>
                  <tr>
                    <td><a href="ViewMajorRequirements.php?majorID=<?= urlencode($m['MajorID']) ?>&MajorName=<?= urlencode($m['MajorName']) ?>">
                      <?= htmlspecialchars($m['MajorID']) ?> </a></td>
                    <td><?= htmlspecialchars($m['MajorName']) ?></td>
                    <td><?= htmlspecialchars($m['DeptID'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['DeptName'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['CreditsNeeded']) ?></td>
                    <td><?= htmlspecialchars($m['Email'] ?? '') ?></td>
                    <td>
                      <span class="status-pill <?= strtoupper($m['Status']) === 'ACTIVE' ? 'on' : 'off' ?>">
                        <?= $m['Status'] === 'ACTIVE' ? 'Active' : 'Retired' ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="7">No majors found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>
    <?php require __DIR__ . '/partials/footer.php'; ?>
    </main>

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

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name.name;
            opt.textContent = name.name;
            if (name === selectedDept) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading departments:', err));

    </script>

  </body>
</html>
