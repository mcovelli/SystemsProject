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

/* Users are never removed. Status carries the account state instead:
   login.php turns away anyone who is not ACTIVE, and a BEFORE DELETE
   trigger on Users rejects a hard delete outright. Deactivating keeps
   their enrollments, transcript and degree audit intact, which is what
   makes reactivating possible at all. */
$statusMessage = '';
$statusOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userID = $_POST['userID'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!in_array($action, ['deactivate', 'reactivate'], true)) {
        $statusMessage = 'Choose Deactivate or Reactivate.';
    } elseif (!ctype_digit((string)$userID)) {
        $statusMessage = 'Enter a numeric User ID.';
    } else {
        $newStatus = $action === 'reactivate' ? 'ACTIVE' : 'INACTIVE';

        $lookup = $mysqli->prepare(
            "SELECT FirstName, LastName, Status FROM Users WHERE UserID = ? LIMIT 1"
        );
        $lookup->bind_param("i", $userID);
        $lookup->execute();
        $target = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$target) {
            $statusMessage = "No user with ID $userID.";
        } elseif ($target['Status'] === $newStatus) {
            $name = $target['FirstName'] . ' ' . $target['LastName'];
            $statusMessage = "$name is already " . strtolower($newStatus) . '.';
        } else {
            $mysqli->begin_transaction();

            $stmt = $mysqli->prepare("UPDATE Users SET Status = ? WHERE UserID = ?");
            $stmt->bind_param("si", $newStatus, $userID);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $mysqli->commit();
                $name = $target['FirstName'] . ' ' . $target['LastName'];
                $statusMessage = $newStatus === 'ACTIVE'
                    ? "$name reactivated. They can sign in again."
                    : "$name deactivated. Their records are kept and sign-in is blocked.";
                $statusOk = true;
            } else {
                $mysqli->rollback();
                $statusMessage = 'Could not change that account.';
            }
            $stmt->close();
        }
    }
}

$userRole = strtolower($_SESSION['role'] ?? '');
$adminType = $_SESSION['admin_type'] ?? '';

switch ($userRole) {

    case 'admin':
        if ($adminType === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile   = 'admin_profile.php';
        } else {
            $dashboard = 'login.html';
            $profile   = 'login.html';
        }
        break;

    default:
        $dashboard = 'login.html';
        $profile   = 'login.html';
        break;
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'User Status'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'User Status'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">User Status</h1>
                  <p class="muted">Accounts are never deleted. Deactivating blocks sign-in and keeps
                     the person's enrollments, transcript and degree audit intact, so the account
                     can be reactivated later.</p>
                </div>
            </div>
                <div id = "create-section-user">
                    <?php if ($statusMessage !== ''): ?>
                      <p class="status-note <?= $statusOk ? 'ok' : 'warn' ?>" role="status">
                        <?= htmlspecialchars($statusMessage) ?>
                      </p>
                    <?php endif; ?>
                    <form id = "DeleteUser" method = "POST" action = "">
                        <label for="userID">User ID: </label>
                        <input type = "text" id="userID" name="userID" required placeholder = "ex. 12345"
                               inputmode="numeric" pattern="[0-9]+"
                               value="<?= htmlspecialchars($_POST['userID'] ?? '') ?>">
                        <button type="submit" name="action" value="deactivate">Deactivate</button>
                        <button type="submit" name="action" value="reactivate">Reactivate</button>
                    </form>
                </div>
        </section>
    </main>
    <?php require __DIR__ . '/partials/footer.php'; ?>

<script>
      // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Delete departments from get_departments.php
    document.getElementById("DeleteUser").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
</body>
</html>
