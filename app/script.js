document.getElementById("loginForm").addEventListener("submit", function(event) {
  let username = document.getElementById("username").value.trim();
  let password = document.getElementById("password").value.trim();
  
  if (username === "" || password === "") {
    event.preventDefault();
    document.getElementById("error-message").textContent = "Both fields are required.";
  }
});

function showPassword() {
  let x = document.getElementById("password");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
  }
}
