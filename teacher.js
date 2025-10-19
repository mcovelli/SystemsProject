 const facultyData = {
 facultySchedule: [
    { crn: 10423, course: "CS 301 – Networks", days: "MW", time: "9:50 - 11:30 AM", location: "ENG 210" },
    { crn: 10116, course: "CS 101 – DB Systems", days: "TR", time: "2:40 - 4:20 PM", location: "SCI 132" },
  ],
  facultyMessages: [
    { from: "Mike B.", subject: "ChatGPT okay for help", at: "Wednesday 12:52 PM" },
    { from: "Dean Berbari", subject: "Meeting about possible program", at: "Yesterday" },
  ],
  facultyQuickLinks: [
    { icon: "book-open", label: "Courses" },
    { icon: "brain", label: "Student Grades" },
    { icon: "check-circle", label: "Attendance" },
    { icon: "upload", label: "Create Assignment" },
    { icon: "calendar-days", label: "Calendar" },
    { icon: "settings", label: "Settings" },
  ],
}

//Render teacherSchedule
const facultyScheduleBody = document.getElementById("facultyScheduleBody");
facultyScheduleBody.innerHTML = studentData.teacherSchedule.map(r => `
  <tr>
    <td class="font-medium">${r.crn}</td>
    <td>${r.course}</td>
    <td>${r.days}</td>
    <td>${r.time}</td>
    <td>${r.location}</td>
  </tr>
`).join("");

const qlFaculty = document.getElementById("facultyQuickLinks");
qlFaculty.innerHTML = studentData.facultyQuickLinks.map(q => `
  <button class="ql"><i data-lucide="${q.icon}"></i><span>${q.label}</span></button>
`).join("");

const facultyMsg = document.getElementById("facultyMsgList");
facultyMsg.innerHTML = studentData.teacherMessages.map(m => {
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

// Init icons
lucide.createIcons();
