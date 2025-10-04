<?php
// Start session
session_start();

// Page configuration
$page_title = "Live Cricket Scoreboard – Landing";
$brand_name = "🏏 ScoreBoard";

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Handle logout - CLEAR BOTH SESSION AND LOCALSTORAGE
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    // Set a flag to clear localStorage
    setcookie('clear_storage', '1', time() + 5, '/');
    header('Location: index.php');
    exit;
}

// Handle login sync from localStorage
if (isset($_POST['sync_login'])) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_name'] = $_POST['user_name'] ?? 'User';
    $_SESSION['user_email'] = $_POST['user_email'] ?? '';
    echo json_encode(['status' => 'success']);
    exit;
}

// Navigation items based on login status
if ($is_logged_in) {
    $nav_items = [
        ['text' => 'Home', 'href' => 'index.php', 'active' => true],
        ['text' => 'Teams', 'href' => 'Pages/Team.php', 'active' => false],
        ['text' => 'Match', 'href' => 'Pages/Match.php', 'active' => false],
        ['text' => 'Dashboard', 'href' => 'Pages/Dash.php', 'active' => false]
    ];
    $cta_button = [
        'text' => 'Logout',
        'href' => 'index.php?action=logout',
        'class' => 'btn-secondary'
    ];
} else {
    $nav_items = [
        ['text' => 'Home', 'href' => 'index.php', 'active' => true],
        ['text' => 'Teams', 'href' => 'Pages/Team.php', 'active' => false],
        ['text' => 'Match', 'href' => 'Pages/Match.php', 'active' => false],
        ['text' => 'Login', 'href' => 'Pages/SignUp.php', 'active' => false]
    ];
    $cta_button = [
        'text' => 'Sign Up',
        'href' => 'Pages/SignUp.php',
        'class' => 'btn-primary-acc'
    ];
}

// Slider images
$slider_images = [
    ['src' => 'image/stadium.jpeg', 'alt' => 'Cricket stadium', 'badge' => 'Stadium'],
    ['src' => 'image/batsman.jpeg', 'alt' => 'Cricket player', 'badge' => 'Batsman'],
    ['src' => 'image/action.jpeg', 'alt' => 'Match action', 'badge' => 'Match Day'],
    ['src' => 'image/match.webp', 'alt' => 'Cricket ball', 'badge' => 'Perfect Shot']
];

// Features list
$features = [
    ['title' => 'Real-time updates', 'desc' => 'Enter runs & overs live.'],
    ['title' => 'Milestone alerts', 'desc' => 'Automatic 50 / 100 highlights.'],
    ['title' => 'Win Predictor', 'desc' => 'Simple algorithmic estimate.']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="styles.css">
  
  <style>
    /* Add visual feedback for navigation */
    .nav-link {
      transition: all 0.3s ease;
      position: relative;
    }
    
    .nav-link:hover {
      color: var(--accent, #00d9ff) !important;
      transform: translateY(-2px);
    }
    
    .nav-link.active {
      color: var(--accent, #00d9ff) !important;
      font-weight: 600;
    }
    
    .nav-link.active::after {
      content: '';
      position: absolute;
      bottom: -5px;
      left: 50%;
      transform: translateX(-50%);
      width: 30px;
      height: 2px;
      background: var(--accent, #00d9ff);
    }
  </style>
</head>
<body>

  <!-- NAV -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand brand" href="index.php"><?php echo $brand_name; ?></a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLinks">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navLinks">
        <ul class="navbar-nav ms-auto align-items-center me-3">
          <?php foreach ($nav_items as $item): ?>
            <li class="nav-item">
              <a class="nav-link <?php echo $item['active'] ? 'active' : ''; ?>" 
                 href="<?php echo htmlspecialchars($item['href']); ?>">
                <?php echo htmlspecialchars($item['text']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="d-flex">
          <a class="btn btn-sm <?php echo htmlspecialchars($cta_button['class']); ?>" 
             href="<?php echo htmlspecialchars($cta_button['href']); ?>" 
             style="min-width:110px">
            <?php echo htmlspecialchars($cta_button['text']); ?>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero container-fluid">
    <div class="row w-100 gx-4">
      <!-- LEFT: sliding thumbnails -->
      <div class="col-lg-7 left">
        <div class="slider-viewport">
          <div class="slider-track" id="sliderTrack">
            <?php 
            // Display images twice for smooth looping
            for ($i = 0; $i < 2; $i++):
              foreach ($slider_images as $image): 
            ?>
              <div class="slide">
                <img src="<?php echo htmlspecialchars($image['src']); ?>" 
                     alt="<?php echo htmlspecialchars($image['alt']); ?>">
                <div class="slide-badge"><?php echo htmlspecialchars($image['badge']); ?></div>
              </div>
            <?php 
              endforeach;
            endfor; 
            ?>
          </div>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="col-lg-5 right d-flex flex-column justify-content-center">
        <div style="max-width:520px;">
          <?php if ($is_logged_in): ?>
            <div class="welcome-message mb-3" style="color: var(--accent); font-size: 14px;">
              Welcome back, <?php echo htmlspecialchars($user_name); ?>!
            </div>
          <?php endif; ?>

          <div class="h-title">Live Cricket Match Scoreboard</div>
          <div class="h-sub">
            Futuristic, real-time scoreboard system – update runs, wickets & overs dynamically, highlight milestones,
            and get a simple win-probability estimate. Designed for scorers, coaches, and fans.
          </div>

          <div class="feature-list">
            <?php foreach ($features as $feature): ?>
              <div class="feature">
                <div class="dot"></div>
                <div>
                  <strong><?php echo htmlspecialchars($feature['title']); ?></strong>
                  <div style="color:var(--muted);font-size:13px">
                    <?php echo htmlspecialchars($feature['desc']); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="cta-row">
            <a class="btn btn-primary-acc" href="#demo" role="button">Start Demo</a>
            <a class="btn btn-ghost" href="#features" role="button">Explore Features</a>
          </div>

          <div class="demo-card">
            <small style="color:var(--muted)">Quick demo:</small>
            <div style="display:flex;gap:10px;margin-top:8px;align-items:center">
              <div style="background:#071019;padding:10px 12px;border-radius:8px;font-weight:700">
                Score <span style="color:var(--accent)">0/0</span>
              </div>
              <div style="background:#071019;padding:10px 12px;border-radius:8px">
                Overs <span style="color:var(--accent)">0.0</span>
              </div>
              <div style="background:#071019;padding:10px 12px;border-radius:8px">
                Batsman <span style="color:var(--accent)">0</span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <section id="demo" class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.01), transparent); padding:18px; border-radius:12px; border:1px solid rgba(255,255,255,0.03);">
          <h5 style="color:var(--accent)">Live Scoreboard Demo</h5>
          <p style="color:var(--muted)">
            This is a compact demo. 
            <?php if ($is_logged_in): ?>
              Start a new match from the Teams page or view your Dashboard.
            <?php else: ?>
              Sign up to create teams and start tracking live matches!
            <?php endif; ?>
          </p>
          <div class="d-flex gap-2 flex-wrap">
            <?php if ($is_logged_in): ?>
              <a href="Pages/Team.php" class="btn btn-sm btn-primary-acc">Go to Teams</a>
              <a href="Pages/Dash.php" class="btn btn-sm btn-ghost">View Dashboard</a>
            <?php else: ?>
              <a href="Pages/SignUp.php" class="btn btn-sm btn-primary-acc">Sign Up Now</a>
              <button class="btn btn-sm btn-ghost" onclick="window.scrollTo({top:0, behavior:'smooth'})">Back to Top</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Main Script -->
  <script src="script.js"></script>
  
  <script>
  // Pass PHP session data to JavaScript
  window.phpSession = {
    isLoggedIn: <?php echo $is_logged_in ? 'true' : 'false'; ?>,
    userName: <?php echo json_encode($user_name); ?>,
    userEmail: <?php echo json_encode($user_email); ?>
  };

  // Check if we need to clear localStorage (after logout)
  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
  }

  // Sync localStorage with PHP session on page load
  document.addEventListener("DOMContentLoaded", function() {
    // Check for logout flag
    if (getCookie('clear_storage') === '1') {
      // Clear localStorage
      localStorage.removeItem("isLoggedIn");
      localStorage.removeItem("userName");
      localStorage.removeItem("userEmail");
      // Clear the cookie
      document.cookie = "clear_storage=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
      console.log("Logout: localStorage cleared");
      return; // Don't sync anything
    }

    const localLoggedIn = localStorage.getItem("isLoggedIn") === "true";
    const phpLoggedIn = window.phpSession.isLoggedIn;
    
    // Only sync if localStorage says logged in AND PHP session doesn't
    // This prevents re-login after logout
    if (localLoggedIn && !phpLoggedIn) {
      fetch('index.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'sync_login=1&user_name=' + encodeURIComponent(localStorage.getItem("userName") || 'User') + 
              '&user_email=' + encodeURIComponent(localStorage.getItem("userEmail") || '')
      }).then(() => location.reload());
    }
    
    // If PHP session says logged in, update localStorage
    if (phpLoggedIn) {
      localStorage.setItem("isLoggedIn", "true");
      if (window.phpSession.userName) {
        localStorage.setItem("userName", window.phpSession.userName);
      }
      if (window.phpSession.userEmail) {
        localStorage.setItem("userEmail", window.phpSession.userEmail);
      }
    }
    
    // If neither is logged in, make sure localStorage is clear
    if (!phpLoggedIn && !localLoggedIn) {
      localStorage.removeItem("isLoggedIn");
      localStorage.removeItem("userName");
      localStorage.removeItem("userEmail");
    }
  });
  </script>
</body>
</html>