<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])){
    redirect(PROJECT_ROOT . "/login.html");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$selectedDept = $_GET['dept'] ?? '';

$sql = "SELECT p.ProgramID, p.ProgramName, p.DeptID, p.DegreeLevel, p.CreditsRequired, d.DeptName, d.Email FROM Program p JOIN Department d ON p.DeptID = d.DeptID ";

if (!empty($selectedDept)) {
    $sql .= " WHERE d.DeptName = ?";
}

$stmt = $mysqli->prepare($sql);
if (!empty($selectedDept)) {
    $stmt->bind_param("s", $selectedDept);
}

$stmt->execute();
$res = $stmt->get_result();
$programs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>


<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — View Programs</title>
  <style>
    /* === Base theme tokens === */
    :root{ --maxcr:18; --scrim: rgba(15,23,42,.55); --card-shadow: 0 10px 30px rgba(0,0,0,.20); --row-shadow: 0 8px 24px rgba(0,0,0,.12); }
[data-theme="dark"]{
  color-scheme: dark;
  --bg:#0b1020; --panel:#0f172a; --panel-2:#111827; --text:#e5e7eb; --muted:#9ca3af; --border:#1f2937; --primary:#4f46e5; --ring:#60a5fa66;
  --topbar-bg: linear-gradient(180deg,#0f172abf,#0f172aef);
  --btn-bg: linear-gradient(180deg,#0f172a,#0c1324); --btn-fg:#e5e7eb;
  --chip-ok:#22c55e1f; --chip-low:#f59e0b1f; --chip-full:#ef44441f;
}
[data-theme="light"]{
  color-scheme: light;
  --bg: linear-gradient(180deg,#f8fafc,#eef2ff 50%,#f8fafc); --panel:#ffffff; --panel-2:#f8fafc; --text:#0f172a; --muted:#6b7280; --border:#e5e7eb; --primary:#4f46e5; --ring:#93c5fd66;
  --topbar-bg: linear-gradient(180deg,#ffffffcc,#ffffffff);
  --btn-bg:#ffffff; --btn-fg:#0f172a;
  --card-shadow: 0 8px 24px rgba(2,6,23,.06); --row-shadow: 0 6px 16px rgba(2,6,23,.06);
  --chip-ok:#16a34a1a; --chip-low:#f59e0b1a; --chip-full:#ef44441a;
}
    html,body{height:100%}
    body{margin:0;background:var(--bg);color:var(--text);font:14px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Inter, "Helvetica Neue", Arial, Noto Sans, "Apple Color Emoji","Segoe UI Emoji"}
    a{color:inherit}

    /* === Topbar === */
    .topbar{position:sticky; top:0; z-index:30; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 16px; border-bottom:1px solid var(--border); background:var(--topbar-bg); align-items:center; gap:12px}
    .logo{width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-weight:800; letter-spacing:.5px; background:linear-gradient(135deg,#4f46e5,#06b6d4); box-shadow:0 6px 18px rgba(0,0,0,.25)}
    .brand h1{font-size:18px; margin:0}
    .pill{font-size:.8rem; padding:6px 10px; border-radius:999px; border:1px solid var(--border); background:linear-gradient(135deg,#3b82f61a,#6366f11a)}

    .top-actions{display:flex; align-items:center; gap:10px}
    .search{display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid var(--border); border-radius:12px; background:var(--panel)}
    .search input{all:unset; width:100%;}

    /* === Layout === */
    .page{max-width:1200px; margin:24px auto; padding:0 16px; display:grid; gap:16px}
    .columns{display:grid; grid-template-columns:1fr; gap:16px}
    @media (min-width:1100px){.columns{grid-template-columns:1.35fr .65fr}}
    .stack{display:grid; gap:16px}

    /* === Cards & buttons === */
    .card{border:1px solid var(--border); border-radius:16px; background:linear-gradient(180deg, color-mix(in srgb, var(--panel) 92%, transparent), var(--panel)); box-shadow:var(--card-shadow); align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border)}
    .card-title{font-weight:800}
    .sub{font-size:.92rem}
    .between{display:flex; align-items:center; justify-content:space-between}
    .row{display:flex; gap:8px}
    .gap{gap:8px}
    .badge{display:inline-block; padding:6px 10px; border-radius:999px; background:linear-gradient(135deg,#3b82f61a,#6366f11a); border:1px solid var(--border); font-size:.85rem}

    .btn{cursor:pointer; padding:8px 12px; border-radius:12px; border:1px solid var(--border); background:var(--btn-bg); color:var(--btn-fg);}
    .btn:focus{outline:2px solid var(--ring); outline-offset:2px}
    .btn.outline{background:transparent}
    .btn.gradient{background:linear-gradient(135deg,#4f46e5,#06b6d4); color:#fff; border:0}

    /* === Controls === */
    .controls{display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px 16px}
    .controls select,.controls input[type="text"],.controls input[type="search"]{padding:10px 12px; border:1px solid var(--border); border-radius:12px; background:var(--panel); color:var(--text); box-shadow:0 1px 0 rgba(0,0,0,.04)}
     .crumb a{color:var(--muted);text-decoration:none}

    /* === Tables === */
    .table-wrap{padding:10px 12px}
    .table{width:100%; border-collapse:separate; border-spacing:0 10px}
    .table thead th{padding:6px 8px; font-size:.85rem; color:var(--muted); font-weight:600; text-align:left}
    .table td{vertical-align:middle}
    .row-item{background:linear-gradient(180deg, color-mix(in srgb, var(--panel) 90%, transparent), var(--panel)); border:1px solid var(--border); border-radius:14px; transition:transform .08s ease, box-shadow .15s ease}
    .row-item:hover{transform:translateY(-1px); box-shadow:var(--row-shadow); padding:4px 10px; border-radius:999px; border:1px solid var(--border); font-size:.85rem}
    .chip.ok{background:var(--chip-ok)}
    .chip.low{background:var(--chip-low)}
    .chip.full{background:var(--chip-full)}

    .hide-sm{display:none}
    @media (min-width:720px){.hide-sm{display:table-cell}}

    .subtotal{display:flex; align-items:center; justify-content:space-between; padding:8px 12px}

    /* === Hero === */
    .hero{position:relative; overflow:hidden; border-radius:16px; padding:22px; border:1px solid var(--border); background:radial-gradient(1200px 400px at -10% -80%, #60a5fa22, transparent 60%), radial-gradient(900px 300px at 120% 10%, #a78bfa22, transparent 60%),linear-gradient(180deg, color-mix(in srgb, var(--panel) 85%, transparent), var(--panel));}
    .hero h2{margin:0 0 6px 0}
    .hero .sub{opacity:.9}
    .progress{height:12px; border-radius:999px; background:color-mix(in srgb, var(--panel) 60%, transparent); border:1px solid var(--border); overflow:hidden}
    .progress span{display:block; height:100%; width:0; background:linear-gradient(90deg,#22c55e,#84cc16,#eab308); box-shadow:inset 0 0 6px rgba(0,0,0,.1)}

    /* === Tests === */
    .tests{font:12px/1.4 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:8px 12px; margin:8px 12px}
    .test-pass{color:#16a34a}
    .test-fail{color:#dc2626}
    .tag{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); border-radius:999px; padding:6px 10px}

    /* === Inline course modal === */
    body.modal-open{overflow:hidden}
    body.modal-open main{filter: blur(6px); transform: translateZ(0)}
    .modal-scrim{position:fixed; inset:0; background:var(--scrim); display:none; align-items:center; justify-content:center; z-index:999; backdrop-filter:saturate(120%) blur(2px)}
    .modal{width:min(720px, 92vw); border:1px solid var(--border); border-radius:16px; background:linear-gradient(180deg, color-mix(in srgb, var(--panel) 90%, transparent), var(--panel)); box-shadow:0 30px 80px rgba(0,0,0,.25); overflow:hidden}
    .modal-head{display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border)}
    .modal-title{font-weight:700}
    .modal-body{padding:16px; display:grid; gap:10px}
    .modal-row{display:flex; align-items:center; gap:10px}
    .modal-foot{display:flex; justify-content:flex-end; gap:10px; padding:14px 16px; border-top:1px solid var(--border)}
    .modal-scrim.open{display:flex}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>
  </header>

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

          <button type="submit">Apply Filters</button>
        </form>
        <p>Click ID to pull up requirements</p>
    </div>

      <div class="table-wrap">
        <table id="programsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Program ID</th><th>Program Name</th><th>Department Name</th><th>Degree Level</th><th>Credits</th><th>Department Email</th></tr></thead>
            <tbody id="programsBody">
              <?php if (!empty($programs)): ?>
                <?php foreach ($programs as $p): ?>
                  <tr>
                    <td><a href="ViewProgramRequirements.php?programID=<?= urlencode($p['ProgramID']) ?>">
                      <?= htmlspecialchars($p['ProgramID']) ?> </a></td>
                    <td><?= htmlspecialchars($p['ProgramName']) ?></td>
                    <td><?= htmlspecialchars($p['DeptName']) ?></td>
                    <td><?= htmlspecialchars($p['DegreeLevel']) ?></td>
                    <td><?= htmlspecialchars($p['CreditsRequired']) ?></td>
                    <td><?= htmlspecialchars($p['Email']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="6">No Programs found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

  <script>
      lucide.createIcons();
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
