document.getElementById("loginForm").addEventListener("submit", function(event) {
  let username = document.getElementById("username").value.trim();
  let password = document.getElementById("password").value.trim();
  
  if (username === "" || password === "") {
    event.preventDefault();
    document.getElementById("error-message").textContent = "Both fields are required.";
  }
});

function showPassword() {
  var x = document.getElementById("myInput");
  if (x.type === "password") {
    x.type = "text";
  } else {
    x.type = "password";
    }
}
