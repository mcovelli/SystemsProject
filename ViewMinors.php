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

$sql = "SELECT mn.MinorID, mn.MinorName, mn.DeptID, d.DeptName, d.Email, mn.CreditsNeeded, mn.Status FROM Minor mn LEFT JOIN Department d ON mn.DeptID = d.DeptID ";

/* Minors are retired rather than deleted, so this list has to be able
   to show the retired ones -- otherwise there is no way to find one and
   reactivate it. LEFT JOIN because a minor's DeptID is nullable, and an
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
    $conditions[] = "mn.Status = ?";
    $params[] = $selectedStatus;
    $types .= 's';
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY mn.MinorName ASC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$minors = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Minor Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Minor Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View Minors</h2>
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
        <table id="minorsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th scope="col">Minor ID</th><th scope="col">Minor Name</th><th scope="col">Department ID</th><th scope="col">Department Name</th><th scope="col">Credits Needed</th><th scope="col">Department Email</th><th scope="col">Status</th></tr></thead>
            <tbody id="minorsBody">
              <?php if (!empty($minors)): ?>
                <?php foreach ($minors as $m): ?>
                  <tr>
                    <td><a href="ViewMinorRequirements.php?minorID=<?= urlencode($m['MinorID']) ?>&MinorName=<?= urlencode($m['MinorName']) ?>">
                      <?= htmlspecialchars($m['MinorID']) ?> </a></td>
                    <td><?= htmlspecialchars($m['MinorName']) ?></td>
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
                  <tr><td colspan="7">No minors found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>
    </main>
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

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name.name;
            opt.textContent = name.name;
            if (name.name === selectedDept) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading departments:', err));

    </script>

  </body>
</html>
