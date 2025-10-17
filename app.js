// Northport University — Vanilla JS for Dashboard
const data = {
  gpaTrend: [
    { term: "F23", gpa: 3.1 },
    { term: "S24", gpa: 3.3 },
    { term: "Su24", gpa: 3.4 },
    { term: "F24", gpa: 3.5 },
    { term: "S25", gpa: 3.6 },
  ],
  schedule: [
    { crn: 10423, course: "CS 301 – Networks", days: "MW", time: "10:30–11:45 AM", location: "ENG 210" },
    { crn: 11802, course: "CS 340 – DB Systems", days: "TR", time: "1:00–2:15 PM", location: "SCI 132" },
    { crn: 12544, course: "MATH 245 – Stats", days: "MWF", time: "2:30–3:20 PM", location: "MAT 108" },
    { crn: 13001, course: "HUM 210 – Ethics", days: "TR", time: "3:30–4:45 PM", location: "LIB 204" },
  ],
  tasks: [
    { id: 1, title: "Networks HW#3", due: "Oct 15, 11:59 PM", status: "due-soon" },
    { id: 2, title: "DB Project ERD", due: "Oct 17, 5:00 PM", status: "in-progress" },
    { id: 3, title: "Stats Quiz 4", due: "Oct 18, 9:00 AM", status: "scheduled" },
  ],
  announcements: [
    { id: 1, tag: "Registrar", text: "Spring 2026 registration opens Nov 5.", date: "Oct 10" },
    { id: 2, tag: "IT", text: "Portal maintenance Oct 20, 1–3 AM.", date: "Oct 12" },
    { id: 3, tag: "Career", text: "Tech Career Fair Oct 21 @ Student Center.", date: "Oct 13" },
  ],
  messages: [
    { from: "Dr. Patel", subject: "Project milestones feedback", at: "Today 10:12 AM" },
    { from: "Bursar", subject: "Statement available", at: "Yesterday" },
  ],
  quickLinks: [
    { icon: "book-open", label: "Courses" },
    { icon: "clipboard-list", label: "To‑Dos" },
    { icon: "credit-card", label: "Billing" },
    { icon: "graduation-cap", label: "Degree Plan" },
    { icon: "calendar-days", label: "Calendar" },
    { icon: "settings", label: "Settings" },
  ],
};

// Fill current year
document.getElementById("year").textContent = new Date().getFullYear();

// Render schedule
const scheduleBody = document.getElementById("scheduleBody");
scheduleBody.innerHTML = data.schedule.map(r => `
  <tr>
    <td class="font-medium">${r.crn}</td>
    <td>${r.course}</td>
    <td>${r.days}</td>
    <td>${r.time}</td>
    <td>${r.location}</td>
  </tr>
`).join("");

// Render quick links
const ql = document.getElementById("quickLinks");
ql.innerHTML = data.quickLinks.map(q => `
  <button class="ql"><i data-lucide="${q.icon}"></i><span>${q.label}</span></button>
`).join("");

// Render tasks
const tasksList = document.getElementById("tasksList");
tasksList.innerHTML = data.tasks.map(t => `
  <div class="row gap card" style="padding:12px">
    <i data-lucide="check-circle-2" class="${t.status === 'due-soon' ? 'text-amber' : t.status === 'in-progress' ? 'text-blue' : 'text-muted'}"></i>
    <div class="vstack" style="gap:4px">
      <div style="font-size:14px; font-weight:600">${t.title}</div>
      <div class="muted" style="font-size:12px">Due ${t.due}</div>
    </div>
    <div style="margin-left:auto">
      <button class="btn outline">Mark done</button>
    </div>
  </div>
`).join("");

// Render announcements
const ann = document.getElementById("annList");
ann.innerHTML = data.announcements.map(a => `
  <div class="card" style="padding:12px">
    <div class="row gap muted small">
      <span class="badge">${a.tag}</span><span>•</span><span>${a.date}</span>
    </div>
    <div style="margin-top:6px; font-size:14px">${a.text}</div>
  </div>
`).join("");

// Render messages
const msgList = document.getElementById("msgList");
msgList.innerHTML = data.messages.map(m => {
  const initials = m.from.split(" ").map(s => s[0]).join("");
  return `
  <div class="row gap">
    <div class="avatar" style="background:var(--pill); display:grid; place-items:center; color:var(--text)">${initials}</div>
    <div class="vstack" style="gap:2px">
      <div style="font-size:14px; font-weight:600">${m.from}</div>
      <div class="muted" style="font-size:12px">${m.subject}</div>
    </div>
    <span class="muted small" style="margin-left:auto">${m.at}</span>
  </div>`;
}).join("");

// Tabs logic
document.querySelectorAll(".tab").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".tab").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    const target = btn.dataset.tab;
    document.querySelectorAll(".tab-panel").forEach(p => p.classList.remove("active"));
    document.getElementById(`panel-${target}`).classList.add("active");
  });
});

// Theme toggle
const themeToggle = document.getElementById("themeToggle");
themeToggle.addEventListener("click", () => {
  const html = document.documentElement;
  const next = html.getAttribute("data-theme") === "light" ? "dark" : "light";
  html.setAttribute("data-theme", next);
  // swap icon
  const i = themeToggle.querySelector("i");
  i.setAttribute("data-lucide", next === "light" ? "moon" : "sun");
  lucide.createIcons();
});

// GPA Chart (Chart.js)
const ctx = document.getElementById("gpaChart");
const labels = data.gpaTrend.map(d => d.term);
const values = data.gpaTrend.map(d => d.gpa);
const chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'GPA',
      data: values,
      tension: 0.35,
      pointRadius: 3,
      borderWidth: 2
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { min: 0, max: 4, ticks: { stepSize: 1 } }
    },
    plugins: {
      legend: { display: false },
      tooltip: { intersect: false, mode: 'index' }
    }
  }
});

// Init icons
lucide.createIcons();
