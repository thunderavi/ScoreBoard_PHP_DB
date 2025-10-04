<?php
// Start session
session_start();

require_once __DIR__ . '/../config/database.php';

// Page configuration
$page_title = "Dashboard - ScoreBoard";
$brand_name = "🏏 ScoreBoard";

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Player';

// Redirect to login if not logged in
if (!$is_logged_in) {
    header('Location: SignUp.php');
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Get user statistics
$user_id = $_SESSION['user_id'] ?? null;

// Count teams created by user
$stmt = $db->prepare("SELECT COUNT(*) as team_count FROM teams WHERE created_by = ?");
$stmt->execute([$user_id]);
$team_stats = $stmt->fetch();
$team_count = $team_stats['team_count'] ?? 0;

// Count total matches
$stmt = $db->query("SELECT COUNT(*) as match_count FROM matches");
$match_stats = $stmt->fetch();
$match_count = $match_stats['match_count'] ?? 0;

// Get recent matches
$stmt = $db->prepare("
    SELECT m.*, 
           t1.name as team1_name, t1.logo as team1_logo,
           t2.name as team2_name, t2.logo as team2_logo
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_matches = $stmt->fetchAll();

// Navigation items
$nav_items = [
    ['text' => 'Home', 'href' => '../index.php'],
    ['text' => 'Teams', 'href' => 'Team.php'],
    ['text' => 'Match', 'href' => 'Match.php'],
    ['text' => 'Dashboard', 'href' => 'Dash.php']
];
$cta_button = [
    'text' => 'Logout',
    'href' => 'Dash.php?action=logout'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #0b0f19;
      color: white;
      margin: 0;
      padding: 0;
    }
    /* Navbar */
    .navbar {
      background: #0b0f19;
      border-bottom: 1px solid rgba(252, 184, 82, 0.3);
    }
    .navbar-brand {
      color: #fcb852 !important;
      font-weight: bold;
      font-size: 1.5rem;
    }
    .nav-link {
      color: white !important;
      transition: 0.3s;
    }
    .nav-link:hover {
      color: #fcb852 !important;
    }

    /* Stats Card */
    .stats-card {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 20px;
      border: 1px solid rgba(252, 184, 82, 0.2);
      transition: all 0.3s ease-in-out;
      box-shadow: 0 0 0 rgba(252,184,82,0);
    }
    .stats-card:hover {
      transform: translateY(-5px) scale(1.02);
      box-shadow: 0 0 20px rgba(252,184,82,0.4);
      border-color: #fcb852;
    }
    .stats-card h3 {
      color: #fcb852;
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    /* Match Card */
    .match-card {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s ease-in-out;
    }
    .match-card:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: #fcb852;
      transform: scale(1.01);
    }

    /* Quick Actions */
    .quick-action {
      transition: 0.3s;
    }
    .quick-action:hover {
      background: #fcb852 !important;
      color: #0b0f19 !important;
      transform: translateY(-3px) scale(1.03);
    }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
      <a class="navbar-brand" href="../index.php"><?php echo $brand_name; ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLinks">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navLinks">
        <ul class="navbar-nav ms-auto align-items-center me-3">
          <?php foreach ($nav_items as $item): ?>
            <li class="nav-item">
              <a class="nav-link px-3" href="<?php echo htmlspecialchars($item['href']); ?>">
                <?php echo htmlspecialchars($item['text']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex">
          <a class="btn btn-outline-warning btn-sm px-4 fw-bold" 
             href="<?php echo htmlspecialchars($cta_button['href']); ?>">
            <?php echo htmlspecialchars($cta_button['text']); ?>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- DASHBOARD CONTENT -->
  <div class="container py-5">
    <div class="row mb-4">
      <div class="col-12">
        <h2 style="color: #fcb852; font-weight: 700;">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p class="text-muted">Your cricket scoreboard dashboard</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="stats-card text-center">
          <i class="fas fa-users fa-2x mb-3" style="color: #fcb852;"></i>
          <h3><?php echo $team_count; ?></h3>
          <p class="text-muted">Teams Created</p>
          <a href="Team.php" class="btn btn-sm btn-outline-light mt-2">View Teams</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stats-card text-center">
          <i class="fas fa-trophy fa-2x mb-3" style="color: #fcb852;"></i>
          <h3><?php echo $match_count; ?></h3>
          <p class="text-muted">Total Matches</p>
          <a href="Match.php" class="btn btn-sm btn-outline-light mt-2">New Match</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stats-card text-center">
          <i class="fas fa-user fa-2x mb-3" style="color: #fcb852;"></i>
          <h3>Active</h3>
          <p class="text-muted">Account Status</p>
          <a href="SignUp.php" class="btn btn-sm btn-outline-light mt-2">Profile</a>
        </div>
      </div>
    </div>
     <!-- Quick Actions -->
    <div class="row mt-5">
      <div class="col-12">
        <h4 style="color: #fcb852; font-weight: 600;" class="mb-3">
          <i class="fas fa-bolt me-2"></i>Quick Actions
        </h4>
        <div class="row">
          <div class="col-md-3 mb-3">
            <a href="Team.php" class="btn btn-outline-light w-100 py-3 quick-action">
              <i class="fas fa-users d-block mb-2" style="font-size: 2rem;"></i>
              Manage Teams
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="Match.php" class="btn btn-outline-light w-100 py-3 quick-action">
              <i class="fas fa-plus-circle d-block mb-2" style="font-size: 2rem;"></i>
              New Match
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="TeamForm.php" class="btn btn-outline-light w-100 py-3 quick-action">
              <i class="fas fa-user-plus d-block mb-2" style="font-size: 2rem;"></i>
              Add Players
            </a>
          </div>
          <div class="col-md-3 mb-3">
            <a href="Board.php" class="btn btn-outline-light w-100 py-3 quick-action">
              <i class="fas fa-chart-line d-block mb-2" style="font-size: 2rem;"></i>
              Live Score
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Matches -->
    <div class="row">
      <div class="col-12">
        <h4 style="color: #fcb852; font-weight: 600;" class="mb-3">
          <i class="fas fa-history me-2"></i>Recent Matches
        </h4>
        
        <?php if (empty($recent_matches)): ?>
          <div class="text-center py-5">
            <i class="fas fa-cricket fa-3x text-muted mb-3"></i>
            <p class="text-muted">No matches played yet</p>
            <a href="Match.php" class="btn btn-primary">Start Your First Match</a>
          </div>
        <?php else: ?>
          <?php foreach ($recent_matches as $match): ?>
            <div class="match-card">
              <div class="row align-items-center">
                <div class="col-md-4">
                  <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($match['team1_logo'] ?? '../assets/default-logo.png'); ?>" 
                         alt="Team 1" 
                         style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo htmlspecialchars($match['team1_name']); ?></span>
                  </div>
                </div>
                <div class="col-md-1 text-center">
                  <strong style="color: #fcb852;">VS</strong>
                </div>
                <div class="col-md-4">
                  <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($match['team2_logo'] ?? '../assets/default-logo.png'); ?>" 
                         alt="Team 2" 
                         style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                    <span><?php echo htmlspecialchars($match['team2_name']); ?></span>
                  </div>
                </div>
                <div class="col-md-3 text-end">
                  <span class="badge <?php echo $match['status'] === 'completed' ? 'bg-success' : 'bg-warning'; ?>">
                    <?php echo ucfirst($match['status']); ?>
                  </span>
                  <br>
                  <small class="text-muted">
                    <?php echo date('M d, Y', strtotime($match['created_at'])); ?>
                  </small>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

   
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
