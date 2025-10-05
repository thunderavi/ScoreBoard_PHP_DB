<?php
// Start session
session_start();

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/auth_check.php';
// Page configuration
$page_title = "Match Details - ScoreBoard";
$brand_name = "🏏 ScoreBoard";

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Redirect to login if not logged in
if (!$is_logged_in) {
    header('Location: SignUp.php');
    exit;
}

// Get match ID from URL
$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($match_id === 0) {
    header('Location: Dash.php');
    exit;
}

// Get match details with scores
$stmt = $db->prepare("
    SELECT m.*, 
           t1.name as team1_name, t1.logo as team1_logo,
           t2.name as team2_name, t2.logo as team2_logo,
           ms1.runs as team1_runs, ms1.wickets as team1_wickets, ms1.balls as team1_balls,
           ms2.runs as team2_runs, ms2.wickets as team2_wickets, ms2.balls as team2_balls
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    LEFT JOIN match_scores ms1 ON m.id = ms1.match_id AND ms1.batting_team_id = t1.id
    LEFT JOIN match_scores ms2 ON m.id = ms2.match_id AND ms2.batting_team_id = t2.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: Dash.php');
    exit;
}

// Get Team 1 players
$stmt = $db->prepare("
    SELECT p.*
    FROM players p
    WHERE p.team_id = ?
    ORDER BY p.id
");
$stmt->execute([$match['team1_id']]);
$team1_players = $stmt->fetchAll();

// Get Team 2 players
$stmt->execute([$match['team2_id']]);
$team2_players = $stmt->fetchAll();

// Parse innings data from JSON
$innings1_data = !empty($match['innings1_data']) ? json_decode($match['innings1_data'], true) : [];
$innings2_data = !empty($match['innings2_data']) ? json_decode($match['innings2_data'], true) : [];

// Function to get player stats from innings data
function getPlayerStats($player_id, $innings_data) {
    if (empty($innings_data) || !isset($innings_data['player_stats'])) {
        return [
            'runs' => 0,
            'balls' => 0,
            'fours' => 0,
            'sixes' => 0,
            'is_out' => false,
            'dismissal' => 'Not Out',
            'overs' => 0,
            'runs_conceded' => 0,
            'wickets' => 0
        ];
    }
    
    foreach ($innings_data['player_stats'] as $stat) {
        if ($stat['player_id'] == $player_id) {
            return $stat;
        }
    }
    
    return [
        'runs' => 0,
        'balls' => 0,
        'fours' => 0,
        'sixes' => 0,
        'is_out' => false,
        'dismissal' => 'Not Out',
        'overs' => 0,
        'runs_conceded' => 0,
        'wickets' => 0
    ];
}

// Determine which team batted first
$team1_batted_first = ($match['batting_first_id'] == $match['team1_id']);

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

    /* Match Header */
    .match-header {
      background: linear-gradient(135deg, rgba(252, 184, 82, 0.1), rgba(252, 184, 82, 0.05));
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      border: 1px solid rgba(252, 184, 82, 0.3);
    }

    .team-badge {
      text-align: center;
    }

    .team-badge img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 3px solid #fcb852;
      margin-bottom: 10px;
      object-fit: cover;
    }

    .vs-badge {
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      color: #fcb852;
    }

    .score-display {
      font-size: 2rem;
      font-weight: 700;
      color: #fcb852;
    }

    .overs-display {
      font-size: 0.9rem;
      color: #aaa;
    }

    /* Player Table */
    .player-table {
      background: rgba(255, 255, 255, 0.05);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 30px;
      border: 1px solid rgba(252, 184, 82, 0.2);
    }

    .player-table h4 {
      color: #fcb852;
      margin-bottom: 20px;
      font-weight: 600;
    }

    .table {
      color: white;
    }

    .table thead th {
      background: rgba(252, 184, 82, 0.2);
      color: #fcb852;
      border: none;
      font-weight: 600;
    }

    .table tbody tr {
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      transition: all 0.3s;
    }

    .table tbody tr:hover {
      background: rgba(252, 184, 82, 0.1);
    }

    .table td {
      border: none;
      padding: 15px 10px;
    }

    .player-photo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
    }

    .status-badge {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .strike-rate {
      color: #28a745;
      font-weight: 600;
    }

    .out-badge {
      background: rgba(220, 53, 69, 0.2);
      color: #dc3545;
    }

    .not-out-badge {
      background: rgba(40, 167, 69, 0.2);
      color: #28a745;
    }

    .back-btn {
      transition: all 0.3s;
    }

    .back-btn:hover {
      transform: translateX(-5px);
    }

    .no-data {
      text-align: center;
      padding: 40px;
      color: #aaa;
    }

    .innings-badge {
      display: inline-block;
      padding: 5px 15px;
      background: rgba(252, 184, 82, 0.2);
      border-radius: 20px;
      font-size: 0.9rem;
      margin-left: 10px;
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

  <!-- MATCH DETAILS CONTENT -->
  <div class="container py-5">
    <!-- Back Button -->
    <div class="mb-4">
      <a href="Dash.php" class="btn btn-outline-light back-btn">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
      </a>
    </div>

    <!-- Match Header -->
    <div class="match-header">
      <div class="row align-items-center">
        <div class="col-md-4">
          <div class="team-badge">
            <img src="<?php echo htmlspecialchars($match['team1_logo']); ?>" 
                 alt="<?php echo htmlspecialchars($match['team1_name']); ?>"
                 onerror="this.src='../assets/default-logo.png'">
            <h5><?php echo htmlspecialchars($match['team1_name']); ?></h5>
            <div class="score-display">
              <?php echo $match['team1_runs'] ?? 0; ?>/<?php echo $match['team1_wickets'] ?? 0; ?>
            </div>
            <?php if (isset($match['team1_balls'])): ?>
              <div class="overs-display">
                (<?php echo floor($match['team1_balls'] / 6) . '.' . ($match['team1_balls'] % 6); ?> overs)
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="vs-badge">VS</div>
          <div class="text-center mt-3">
            <span class="badge <?php echo $match['status'] === 'completed' ? 'bg-success' : ($match['status'] === 'live' ? 'bg-danger' : 'bg-warning'); ?>">
              <?php echo ucfirst($match['status']); ?>
            </span>
            <br>
            <small class="text-muted">
              <?php echo date('F d, Y', strtotime($match['created_at'])); ?>
            </small>
            <?php if (!empty($match['result_text'])): ?>
              <div class="mt-2">
                <strong style="color: #fcb852;"><?php echo htmlspecialchars($match['result_text']); ?></strong>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="team-badge">
            <img src="<?php echo htmlspecialchars($match['team2_logo']); ?>" 
                 alt="<?php echo htmlspecialchars($match['team2_name']); ?>"
                 onerror="this.src='../assets/default-logo.png'">
            <h5><?php echo htmlspecialchars($match['team2_name']); ?></h5>
            <div class="score-display">
              <?php echo $match['team2_runs'] ?? 0; ?>/<?php echo $match['team2_wickets'] ?? 0; ?>
            </div>
            <?php if (isset($match['team2_balls'])): ?>
              <div class="overs-display">
                (<?php echo floor($match['team2_balls'] / 6) . '.' . ($match['team2_balls'] % 6); ?> overs)
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Team 1 Players -->
    <div class="player-table">
      <h4>
        <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($match['team1_name']); ?> - Squad
        <?php if ($team1_batted_first): ?>
          <span class="innings-badge">1st Innings</span>
        <?php else: ?>
          <span class="innings-badge">2nd Innings</span>
        <?php endif; ?>
      </h4>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Player</th>
              <th>Position</th>
              <th>Contact</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($team1_players as $player): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($player['photo']); ?>" 
                         alt="<?php echo htmlspecialchars($player['player_name']); ?>"
                         class="player-photo"
                         onerror="this.src='../assets/default-avatar.png'">
                    <strong><?php echo htmlspecialchars($player['player_name']); ?></strong>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($player['position']); ?></td>
                <td><?php echo htmlspecialchars($player['contact'] ?? 'N/A'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Team 2 Players -->
    <div class="player-table">
      <h4>
        <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($match['team2_name']); ?> - Squad
        <?php if (!$team1_batted_first): ?>
          <span class="innings-badge">1st Innings</span>
        <?php else: ?>
          <span class="innings-badge">2nd Innings</span>
        <?php endif; ?>
      </h4>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Player</th>
              <th>Position</th>
              <th>Contact</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($team2_players as $player): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="<?php echo htmlspecialchars($player['photo']); ?>" 
                         alt="<?php echo htmlspecialchars($player['player_name']); ?>"
                         class="player-photo"
                         onerror="this.src='../assets/default-avatar.png'">
                    <strong><?php echo htmlspecialchars($player['player_name']); ?></strong>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($player['position']); ?></td>
                <td><?php echo htmlspecialchars($player['contact'] ?? 'N/A'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Match Info -->
    <div class="player-table">
      <h4><i class="fas fa-info-circle me-2"></i>Match Information</h4>
      <div class="row">
        <div class="col-md-6">
          <p><strong>Toss Winner:</strong> 
            <?php 
              $toss_winner_name = ($match['toss_winner_id'] == $match['team1_id']) 
                ? $match['team1_name'] 
                : $match['team2_name'];
              echo htmlspecialchars($toss_winner_name);
            ?>
          </p>
          <p><strong>Toss Decision:</strong> <?php echo ucfirst($match['toss_choice']); ?></p>
          <p><strong>Coin Result:</strong> <?php echo ucfirst($match['coin_result']); ?></p>
        </div>
        <div class="col-md-6">
          <p><strong>Batting First:</strong> 
            <?php 
              $batting_first_name = ($match['batting_first_id'] == $match['team1_id']) 
                ? $match['team1_name'] 
                : $match['team2_name'];
              echo htmlspecialchars($batting_first_name);
            ?>
          </p>
          <p><strong>Match Status:</strong> 
            <span class="badge <?php echo $match['status'] === 'completed' ? 'bg-success' : ($match['status'] === 'live' ? 'bg-danger' : 'bg-warning'); ?>">
              <?php echo ucfirst($match['status']); ?>
            </span>
          </p>
          <?php if ($match['status'] === 'completed' && $match['completed_at']): ?>
            <p><strong>Completed:</strong> <?php echo date('F d, Y g:i A', strtotime($match['completed_at'])); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>