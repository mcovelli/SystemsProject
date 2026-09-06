<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
    redirect(PROJECT_ROOT . "/login.html");
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


// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'Create User', 'href' => 'CreateUsers.php',       'icon' => 'user'],
    ['label' => 'Create Courses',      'href' => 'CreateCourses.php',           'icon' => 'book'],
    ['label' => 'Create Sections',   'href' => 'CreateCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'Create Departments',     'href' => 'CreateDepartments.php',                      'icon' => 'building'],
    ['label' => 'Create Majors/Minors',      'href' => 'CreateMajorsMinors.php',                      'icon' => 'brain'],
    ['label' => 'Create Programs',      'href' => 'CreatePrograms.php',                      'icon' => 'brain'],
    ['label' => 'Create Requirements',      'href' => 'CreateRequirements.php',                      'icon' => 'list'],
    ['label' => 'Create Prerequisites',      'href' => 'CreatePreqs.php',                      'icon' => 'list']
];

$updateLinks = [
  ['label' => 'Update User', 'href' => 'UpdateUsers.php',       'icon' => 'user'],
    ['label' => 'Update Courses',      'href' => 'UpdateCourses.php',           'icon' => 'book'],
    ['label' => 'Update Sections',   'href' => 'UpdateCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'Update Majors',      'href' => 'UpdateMajors.php',                      'icon' => 'brain'],
    ['label' => 'Update Minors',      'href' => 'UpdateMinors.php',                      'icon' => 'brain'],
    ['label' => 'Update Programs',      'href' => 'UpdatePrograms.php',                      'icon' => 'brain'],
    ['label' => 'Update Departments',     'href' => 'UpdateDepartments.php',                      'icon' => 'building'],
    ['label' => 'Update Requirements',      'href' => 'UpdateRequirements.php',                      'icon' => 'list'],
    ['label' => 'Updates Prerequisites',      'href' => 'UpdatePrereqs.php',                      'icon' => 'list']

];

$deleteLinks = [
  ['label' => 'User Status', 'href' => 'DeleteUsers.php',       'icon' => 'user-check'],
    ['label' => 'Delete Courses',      'href' => 'DeleteCourses.php',           'icon' => 'x'],
    ['label' => 'Delete Sections',   'href' => 'DeleteCourseSections.php',                      'icon' => 'X'],
    ['label' => 'Delete Departments',     'href' => 'DeleteDepartments.php',                      'icon' => 'x'],
    ['label' => 'Major/Minor Status',      'href' => 'DeleteMajorsMinors.php',                      'icon' => 'archive'],
    ['label' => 'Program Status',      'href' => 'DeletePrograms.php',                      'icon' => 'archive']

];

$otherLinks = [
    ['label' => 'Declare Major',      'href' => 'DeclareMajor.php',           'icon' => 'graduation-cap'],
    ['label' => 'Declare Minor',   'href' => 'DeclareMinor.php',                      'icon' => 'graduation-cap'],
    ['label' => 'Declare Program',     'href' => 'DeclareProgram.php',                      'icon' => 'graduation-cap'],
    ['label' => 'Assign Advisor', 'href' => 'assign_advisor.php',       'icon' => 'check'],
    ['label' => 'Place Hold',     'href' => 'PlaceHold.php',                      'icon' => 'X']
];

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Create Directory'; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

  <main class="container">
      <div class="card">
        <div class="card-title">Create Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
      </div>
  </main>

    <main class="container">
      <div class="card">
        <div class="card-title">Update Actions</div>
        <div class="quick-grid" id="adminUpdateLinks"></div>
      </div>
  </main>

  <main class="container">
      <div class="card">
        <div class="card-title">Delete Actions</div>
        <div class="quick-grid" id="adminDeleteLinks"></div>
      </div>
  </main>

   <main class="container">
      <div class="card">
        <div class="card-title">Other Actions</div>
        <div class="quick-grid" id="adminOtherLinks"></div>
      </div>
  </main>

<?php require __DIR__ . '/partials/footer.php'; ?>


  <script>
     // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Tab switching
    document.querySelectorAll('.tabs .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const target = tab.getAttribute('data-tab');
        document.querySelectorAll('.tab-panel').forEach(panel => {
          panel.classList.toggle('active', panel.id === 'panel-' + target);
        });
      });
    });

    // Insert quick links
    const quickLinks = <?php echo json_encode($quickLinks); ?>;
    const qlContainer = document.getElementById('adminQuickLinks');
    quickLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ql';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      qlContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert update links
    const updateLinks = <?php echo json_encode($updateLinks); ?>;
    const ulContainer = document.getElementById('adminUpdateLinks');
    updateLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ul';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      ulContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert delete links
    const deleteLinks = <?php echo json_encode($deleteLinks); ?>;
    const dlContainer = document.getElementById('adminDeleteLinks');
    deleteLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'dl';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      dlContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert other links
    const otherLinks = <?php echo json_encode($otherLinks); ?>;
    const olContainer = document.getElementById('adminOtherLinks');
    otherLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ol';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      olContainer.appendChild(div);
    });
    lucide.createIcons();

  </script>
</body>
</html>
