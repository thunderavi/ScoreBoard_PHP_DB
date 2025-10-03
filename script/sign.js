const formTitle = document.getElementById("form-title");
const authForm = document.getElementById("auth-form");
const switchText = document.getElementById("switch-text");

// Function to update navbar based on login state
function updateNavbar() {
  const navbar = document.querySelector(".navbar-nav");
  const signupBtnContainer = document.querySelector(".d-flex");

  if (!navbar) return; // in case navbar is missing

  if (localStorage.getItem("isLoggedIn") === "true") {
    navbar.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="/index.html">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="./Team.html">Teams</a></li>
      <li class="nav-item"><a class="nav-link logout-btn" href="#">Logout</a></li>
    `;
    if (signupBtnContainer) {
      signupBtnContainer.innerHTML = `
        <a class="btn btn-sm btn-secondary logout-btn" href="#" style="min-width:110px">Logout</a>
      `;
    }
    document.querySelectorAll(".logout-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        localStorage.removeItem("isLoggedIn");
        window.location.href = "./Pages/SignUp.html";
      });
    });
  } else {
    navbar.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="/index.html">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="./Team.html">Teams</a></li>
      <li class="nav-item"><a class="nav-link" href="#">Register</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/SignUp.html">Login</a></li>
    `;
    if (signupBtnContainer) {
      signupBtnContainer.innerHTML = `
        <a class="btn btn-sm btn-primary-acc" href="./Pages/SignUp.html" style="min-width:110px">Sign Up</a>
      `;
    }
  }
}

// Call it on page load
document.addEventListener("DOMContentLoaded", updateNavbar);


// Render form dynamically
function renderForm(type) {
  if (type === "signup") {
    formTitle.textContent = "Sign Up";
    authForm.innerHTML = `
      <div class="input-group">
        <label>Full Name</label>
        <input type="text" id="name" placeholder="Enter your name" required>
        <small class="error-msg" style="color:#f87171; display:none;"></small>
      </div>
      <div class="input-group">
        <label>Email</label>
        <input type="email" id="email" placeholder="Enter your email" required>
        <small class="error-msg" style="color:#f87171; display:none;"></small>
      </div>
      <div class="input-group">
        <label>Password</label>
        <input type="password" id="password" placeholder="Create a password" required>
        <small class="error-msg" style="color:#f87171; display:none;"></small>
      </div>
      <button type="submit" class="btn btn-primary">Sign Up</button>
      <button type="button" class="btn btn-secondary">Sign up with Google</button>
    `;
    switchText.innerHTML = `Already have an account? <a href="#" id="toggle">Login</a>`;
  } else {
    formTitle.textContent = "Login";
    authForm.innerHTML = `
      <div class="input-group">
        <label>Email</label>
        <input type="email" id="email" placeholder="Enter your email" required>
        <small class="error-msg" style="color:#f87171; display:none;"></small>
      </div>
      <div class="input-group">
        <label>Password</label>
        <input type="password" id="password" placeholder="Enter your password" required>
        <small class="error-msg" style="color:#f87171; display:none;"></small>
      </div>
      <button type="submit" class="btn btn-primary">Login</button>
      <button type="button" class="btn btn-secondary">Sign in with Google</button>
    `;
    switchText.innerHTML = `Don't have an account? <a href="#" id="toggle">Sign Up</a>`;
  }

  // Toggle link
  document.getElementById("toggle").addEventListener("click", (e) => {
    e.preventDefault();
    renderForm(type === "signup" ? "login" : "signup");
  });

  // Submit handler
  authForm.addEventListener("submit", (e) => {
    e.preventDefault();
    if (validateForm(type)) {
      localStorage.setItem("isLoggedIn", "true");
      window.location.href = "../Pages/dash.html"; // redirect to dashboard
    }
  });
}

// Validation function
function validateForm(type) {
  let valid = true;
  const email = document.getElementById("email");
  const password = document.getElementById("password");
  const name = type === "signup" ? document.getElementById("name") : null;

  document.querySelectorAll(".error-msg").forEach(e => e.style.display = "none");

  if (type === "signup" && !name.value.trim()) {
    name.nextElementSibling.textContent = "Name cannot be empty.";
    name.nextElementSibling.style.display = "block";
    valid = false;
  }

  const emailPattern = /^[^\s@]+@gmail\.com$/;
  if (!emailPattern.test(email.value)) {
    email.nextElementSibling.textContent = "Enter a valid Gmail address.";
    email.nextElementSibling.style.display = "block";
    valid = false;
  }

  if (type === "login") {
    if (password.value.length < 6) {
      password.nextElementSibling.textContent = "Password must be at least 6 characters.";
      password.nextElementSibling.style.display = "block";
      valid = false;
    }
  } else {
    const pwdPattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
    if (!pwdPattern.test(password.value)) {
      password.nextElementSibling.textContent = "Password must be 8+ chars with letters & numbers.";
      password.nextElementSibling.style.display = "block";
      valid = false;
    }
  }

  return valid;
}

// Check login state on page load
if (localStorage.getItem("isLoggedIn") === "true") {
  if (!window.location.href.includes("dash.html")) {
    window.location.href = "../Pages/dash.html";
  }
} else {
  renderForm("login");
  updateNavbar();
}
