<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
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
$user = $res->fetch_assoc();
$stmt->close();


// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'View Faculty', 'href' => 'ViewFaculty.php',       'icon' => 'file-text'],
    ['label' => 'View Courses',      'href' => 'ViewCourses.php',           'icon' => 'book'],
    ['label' => 'View Sections',   'href' => 'ViewCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'View Departments',     'href' => 'ViewDepartments.php',                      'icon' => 'mail'],
    ['label' => 'View Programs',      'href' => 'ViewPrograms.php',                      'icon' => 'brain'],
    ['label' => 'View Majors',      'href' => 'ViewMajors.php',                      'icon' => 'brain'],
    ['label' => 'View Minors',      'href' => 'ViewMinors.php',                      'icon' => 'brain'],
    ['label' => 'View Prerequisites', 'href' => 'ViewPrereqs.php',       'icon' => 'brain'],
];


if ($userRole === 'admin') {

    $quickLinks[] = [
        'label' => 'View Students',
        'href'  => 'ViewStudents.php',
        'icon'  => 'pencil'
    ];

    $quickLinks[] = [
        'label' => 'View All Users',
        'href'  => 'ViewUsers.php',
        'icon'  => 'user'
    ];
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'View Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'View Directory'; require __DIR__ . '/partials/header.php'; ?>

  <main class="container">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
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

  </script>
</body>
</html>
