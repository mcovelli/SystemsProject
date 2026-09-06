<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] ?? '') !== 'admin') {
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

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    UserID,
    CONCAT(FirstName, ' ', LastName) AS Name,
    CONCAT(HouseNumber, ' ', Street, ' ', City, ' ', State, ' ', Zip) AS Address,
    PhoneNumber,
    Email,
    Gender,
    DOB,
    UserType,
    Status
FROM Users
";

/* Accounts are deactivated rather than deleted, so this list has to be
   able to show the inactive ones -- otherwise there is no way to find
   an account and reactivate it. */
$selectedStatus = strtoupper($_GET['status'] ?? '');
if (!in_array($selectedStatus, ['ACTIVE', 'INACTIVE'], true)) {
    $selectedStatus = '';
}

$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(
    FirstName LIKE CONCAT('%', ?, '%')
    OR LastName LIKE CONCAT('%', ?, '%')
    OR UserType LIKE CONCAT('%', ?, '%')
    OR UserID LIKE CONCAT('%', ?, '%')
)";
    array_push($params, $search, $search, $search, $search);
    $types .= 'ssss';
}

if ($selectedStatus !== '') {
    $conditions[] = "Status = ?";
    $params[] = $selectedStatus;
    $types .= 's';
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY UserID ASC";

$stmt = $mysqli->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$users = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'User Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'User Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Users</h2>
        </div>
      </div>

      <form method="GET" style="margin-bottom: 20px;">
        <label for="search">Search:</label>
        <input type="text" id="search" name="search"
         placeholder="Search name or department..."
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

        <label for="status">Status:</label>
        <select name="status" id="status">
          <option value=""         <?= $selectedStatus === ''         ? 'selected' : '' ?>>All</option>
          <option value="ACTIVE"   <?= $selectedStatus === 'ACTIVE'   ? 'selected' : '' ?>>Active only</option>
          <option value="INACTIVE" <?= $selectedStatus === 'INACTIVE' ? 'selected' : '' ?>>Inactive only</option>
        </select>

        <button type="submit">Search</button>
      </form>
  
    <div class="table-wrap">
      <table border="1" cellpadding="5" cellspacing="0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Address</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Date of Birth</th>
            <th>Gender</th>
            <th>Type</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['UserID']) ?></td>
                <td><?= htmlspecialchars($u['Name']) ?></td>
                <td><?= htmlspecialchars($u['Address']) ?></td>
                <td><?= htmlspecialchars($u['PhoneNumber']) ?></td>
                <td><?= htmlspecialchars($u['Email']) ?></td>
                <td><?= htmlspecialchars($u['DOB']) ?></td>
                <td><?= htmlspecialchars($u['Gender']) ?></td>
                <td><?= htmlspecialchars($u['UserType']) ?></td>
                <td>
                  <span class="status-pill <?= strtoupper($u['Status']) === 'ACTIVE' ? 'on' : 'off' ?>">
                    <?= htmlspecialchars($u['Status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="9">No users match that search.</td></tr>
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

    </script>
  </body>
</html>
