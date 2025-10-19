// Northport University — Vanilla JS for Dashboard
const data = {
  gpaTrend: [
    { term: "F23", gpa: 3.1 },
    { term: "S24", gpa: 3.3 },
    { term: "Su24", gpa: 3.4 },
    { term: "F24", gpa: 3.5 },
    { term: "S25", gpa: 3.6 },
  ],
  studentSchedule: [
    { crn: 10423, course: "CS 301 – Networks", days: "MW", time: "9:50 - 11:30 AM", location: "ENG 210" },
    { crn: 11802, course: "CS 340 – DB Systems", days: "TR", time: "2:40 - 4:20 PM", location: "SCI 132" },
    { crn: 12544, course: "MATH 245 – Stats", days: "MW", time: "2:40 - 4:20 PM", location: "MAT 108" },
    { crn: 13001, course: "HUM 210 – Ethics", days: "TR", time: "4:30 - 6:10 PM", location: "LIB 204" },
  ],
  teacherSchedule: [
    { crn: 10423, course: "CS 301 – Networks", days: "MW", time: "9:50 - 11:30 AM", location: "ENG 210" },
    { crn: 10116, course: "CS 101 – DB Systems", days: "TR", time: "2:40 - 4:20 PM", location: "SCI 132" },
  ],
  studentTasks: [
    { id: 1, title: "Networks HW#3", due: "Oct 15, 11:59 PM", status: "due-soon" },
    { id: 2, title: "DB Project ERD", due: "Oct 17, 5:00 PM", status: "in-progress" },
    { id: 3, title: "Stats Quiz 4", due: "Oct 18, 9:00 AM", status: "scheduled" },
  ],
  studentAnnouncements: [
    { id: 1, tag: "Registrar", text: "Spring 2026 registration opens Nov 5.", date: "Oct 10" },
    { id: 2, tag: "IT", text: "Portal maintenance Oct 20, 1–3 AM.", date: "Oct 12" },
    { id: 3, tag: "Career", text: "Tech Career Fair Oct 21 @ Student Center.", date: "Oct 13" },
  ],
  studentMessages: [
    { from: "Dr. Patel", subject: "Project milestones feedback", at: "Today 10:12 AM" },
    { from: "Bursar", subject: "Statement available", at: "Yesterday" },
  ],
  teacherMessages: [
    { from: "Mike B.", subject: "ChatGPT okay for help", at: "Wednesday 12:52 PM" },
    { from: "Dean Berbari", subject: "Meeting about possible program", at: "Yesterday" },
  ],
  studentQuickLinks: [
    { icon: "book-open", label: "Courses" },
    { icon: "clipboard-list", label: "To‑Dos" },
    { icon: "credit-card", label: "Billing" },
    { icon: "graduation-cap", label: "Degree Plan" },
    { icon: "calendar-days", label: "Calendar" },
    { icon: "settings", label: "Settings" },
  ],
  teacherQuickLinks: [
    { icon: "book-open", label: "Courses" },
    { icon: "brain", label: "Student Grades" },
    { icon: "check-circle", label: "Attendance" },
    { icon: "upload", label: "Create Assignment" },
    { icon: "calendar-days", label: "Calendar" },
    { icon: "settings", label: "Settings" },
  ],
};

// Fill current year
document.getElementById("year").textContent = new Date().getFullYear();

// Render student schedule
const studentScheduleBody = document.getElementById("studentScheduleBody");
studentScheduleBody.innerHTML = data.studentSchedule.map(r => `
  <tr>
    <td class="font-medium">${r.crn}</td>
    <td>${r.course}</td>
    <td>${r.days}</td>
    <td>${r.time}</td>
    <td>${r.location}</td>
  </tr>
`).join("");

//Render teacherSchedule
const facultyScheduleBody = document.getElementById("facultyScheduleBody");
facultyScheduleBody.innerHTML = data.teacherSchedule.map(r => `
  <tr>
    <td class="font-medium">${r.crn}</td>
    <td>${r.course}</td>
    <td>${r.days}</td>
    <td>${r.time}</td>
    <td>${r.location}</td>
  </tr>
`).join("");

// Render quick links
const qlStudent = document.getElementById("studentQuickLinks");
qlStudent.innerHTML = data.studentQuickLinks.map(q => `
  <button class="ql"><i data-lucide="${q.icon}"></i><span>${q.label}</span></button>
`).join("");

const qlFaculty = document.getElementById("facultyQuickLinks");
qlFaculty.innerHTML = data.teacherQuickLinks.map(q => `
  <button class="ql"><i data-lucide="${q.icon}"></i><span>${q.label}</span></button>
`).join("");

// Render tasks
const studentTasks = document.getElementById("studentTasksList");
studentTasks.innerHTML = data.studentTasks.map(t => `
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
const studentAnn = document.getElementById("studentAnnList");
studentAnn.innerHTML = data.studentAnnouncements.map(a => `
  <div class="card" style="padding:12px">
    <div class="row gap muted small">
      <span class="badge">${a.tag}</span><span>•</span><span>${a.date}</span>
    </div>
    <div style="margin-top:6px; font-size:14px">${a.text}</div>
  </div>
`).join("");

// Render messages
const studentMsg = document.getElementById("studentMsgList");
studentMsg.innerHTML = data.studentMessages.map(m => {
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

const facultyMsg = document.getElementById("facultyMsgList");
facultyMsg.innerHTML = data.teacherMessages.map(m => {
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
const ctx = document.getElementById("gpaChart").getContext("2d");
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

  document.getElementById("year").textContent = new Date().getFullYear();


// Init icons
lucide.createIcons();
