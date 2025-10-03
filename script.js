// ---------------- Slider animation -----------------
const speedSeconds = 20;
document.querySelectorAll('.slider-track').forEach(track => {
  track.style.animationDuration = speedSeconds + 's';
});

const viewport = document.querySelector('.slider-viewport');
if (viewport) {
  viewport.addEventListener('touchstart', () => {
    document.querySelectorAll('.slider-track').forEach(t => t.style.animationPlayState = 'paused');
  });
  viewport.addEventListener('touchend', () => {
    document.querySelectorAll('.slider-track').forEach(t => t.style.animationPlayState = 'running');
  });
}

// ---------------- Navbar login/logout -----------------
function updateNavbar() {
  const navbar = document.querySelector(".navbar-nav");
  const signupBtnContainer = document.querySelector(".d-flex");

  if (!navbar) return;

  // Check if using PHP session (preferred) or localStorage (fallback)
  const isLoggedInPHP = window.phpSession && window.phpSession.isLoggedIn;
  const isLoggedInLocal = localStorage.getItem("isLoggedIn") === "true";
  const isLoggedIn = isLoggedInPHP || isLoggedInLocal;

  if (isLoggedIn) {
    // Logged in state - show Dashboard instead of Match/Register
    navbar.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/Team.php">Teams</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/Dash.php">Dashboard</a></li>
    `;

    if (signupBtnContainer) {
      signupBtnContainer.innerHTML = `
        <a class="btn btn-sm btn-secondary logout-btn" href="index.php?action=logout" style="min-width:110px">Logout</a>
      `;
    }

    // Bind logout action to all logout buttons
    document.querySelectorAll(".logout-btn").forEach(btn => {
      btn.addEventListener("click", (e) => {
        // If using localStorage, clear it
        if (isLoggedInLocal) {
          e.preventDefault();
          localStorage.removeItem("isLoggedIn");
          localStorage.removeItem("userName");
          localStorage.removeItem("userEmail");
          window.location.href = "./Pages/SignUp.php";
        }
        // Otherwise let PHP handle the logout via GET parameter
      });
    });

  } else {
    // Logged out state - show Match and Login options
    navbar.innerHTML = `
      <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/Team.php">Teams</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/Match.php">Match</a></li>
      <li class="nav-item"><a class="nav-link" href="./Pages/SignUp.php">Login</a></li>
    `;

    if (signupBtnContainer) {
      signupBtnContainer.innerHTML = `
        <a class="btn btn-sm btn-primary-acc" href="./Pages/SignUp.php" style="min-width:110px">Sign Up</a>
      `;
    }
  }
}

// Call this on every page load
document.addEventListener("DOMContentLoaded", updateNavbar);

// Helper function to sync login state between localStorage and PHP session
function syncLoginState(userData) {
  localStorage.setItem("isLoggedIn", "true");
  if (userData.name) localStorage.setItem("userName", userData.name);
  if (userData.email) localStorage.setItem("userEmail", userData.email);
  
  // Also sync to PHP session
  fetch('index.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `sync_login=1&user_name=${encodeURIComponent(userData.name || 'User')}&user_email=${encodeURIComponent(userData.email || '')}`
  }).then(() => {
    updateNavbar();
  });
}

// Helper function to check login status
function checkLoginStatus() {
  return (window.phpSession && window.phpSession.isLoggedIn) || 
         localStorage.getItem("isLoggedIn") === "true";
}

// Export functions for use in other pages
window.cricketApp = {
  updateNavbar,
  syncLoginState,
  checkLoginStatus
};