<?php
// Start session
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';

$database = new Database();
$db = $database->getConnection();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: SignUp.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Handle new match reset
if (isset($_GET['action']) && $_GET['action'] === 'new_match') {
    if (isset($_SESSION['current_match_id'])) {
        $match_id = $_SESSION['current_match_id'];
        
        // Delete current match scores
        $stmt = $db->prepare("DELETE FROM match_scores WHERE match_id = ?");
        $stmt->execute([$match_id]);
        
        // Reset match to setup status
        $stmt = $db->prepare("UPDATE matches SET status = 'setup', result_text = NULL, winner_id = NULL, innings1_data = NULL, innings2_data = NULL, completed_at = NULL WHERE id = ?");
        $stmt->execute([$match_id]);
    }
    header('Location: Match.php');
    exit;
}

// Check if match exists
if (!isset($_SESSION['current_match_id'])) {
    header('Location: Match.php');
    exit;
}

$match_id = $_SESSION['current_match_id'];

// Fetch match details from database
$stmt = $db->prepare("
    SELECT m.*, 
           t1.id as team1_id, t1.name as team1_name, t1.logo as team1_logo, t1.captain as team1_captain,
           t2.id as team2_id, t2.name as team2_name, t2.logo as team2_logo, t2.captain as team2_captain,
           bf.id as batting_first_id, bf.name as batting_first_name, bf.logo as batting_first_logo
    FROM matches m
    JOIN teams t1 ON m.team1_id = t1.id
    JOIN teams t2 ON m.team2_id = t2.id
    JOIN teams bf ON m.batting_first_id = bf.id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$matchData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$matchData) {
    unset($_SESSION['current_match_id']);
    header('Location: Match.php');
    exit;
}

// Convert database format to expected array structure
$currentMatch = [
    'id' => $matchData['id'],
    'team1' => [
        'id' => $matchData['team1_id'],
        'name' => $matchData['team1_name'],
        'logo' => $matchData['team1_logo'],
        'captain' => $matchData['team1_captain']
    ],
    'team2' => [
        'id' => $matchData['team2_id'],
        'name' => $matchData['team2_name'],
        'logo' => $matchData['team2_logo'],
        'captain' => $matchData['team2_captain']
    ],
    'battingFirst' => [
        'id' => $matchData['batting_first_id'],
        'name' => $matchData['batting_first_name'],
        'logo' => $matchData['batting_first_logo']
    ],
    'status' => $matchData['status']
];

// Get or create match scores
$stmt = $db->prepare("SELECT * FROM match_scores WHERE match_id = ? ORDER BY innings");
$stmt->execute([$match_id]);
$scoresData = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($scoresData)) {
    // Initialize innings in database
    $batting2_id = ($matchData['batting_first_id'] == $matchData['team1_id']) ? 
        $matchData['team2_id'] : $matchData['team1_id'];
    
    $stmt = $db->prepare("INSERT INTO match_scores (match_id, innings, batting_team_id, runs, wickets, balls, fours, sixes, completed_players, current_player) VALUES (?, ?, ?, 0, 0, 0, 0, 0, '[]', NULL)");
    $stmt->execute([$match_id, 1, $matchData['batting_first_id']]);
    $stmt->execute([$match_id, 2, $batting2_id]);
    
    // Reload scores
    $stmt = $db->prepare("SELECT * FROM match_scores WHERE match_id = ? ORDER BY innings");
    $stmt->execute([$match_id]);
    $scoresData = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Build live score structure from database
$_SESSION['live_score'] = [
    'currentInnings' => 1, // Will be determined below
    'innings1' => [
        'battingTeamId' => $scoresData[0]['batting_team_id'],
        'runs' => $scoresData[0]['runs'],
        'wickets' => $scoresData[0]['wickets'],
        'balls' => $scoresData[0]['balls'],
        'fours' => $scoresData[0]['fours'],
        'sixes' => $scoresData[0]['sixes'],
        'completedPlayers' => json_decode($scoresData[0]['completed_players'], true) ?? []
    ],
    'innings2' => [
        'battingTeamId' => $scoresData[1]['batting_team_id'],
        'runs' => $scoresData[1]['runs'],
        'wickets' => $scoresData[1]['wickets'],
        'balls' => $scoresData[1]['balls'],
        'fours' => $scoresData[1]['fours'],
        'sixes' => $scoresData[1]['sixes'],
        'completedPlayers' => json_decode($scoresData[1]['completed_players'], true) ?? []
    ],
    'currentPlayer' => json_decode($scoresData[0]['current_player'], true) ?? 
                      json_decode($scoresData[1]['current_player'], true)
];

// FIXED: Determine current innings based on match status and scores
if ($matchData['status'] !== 'completed') {
    // Use match status to determine innings
    if ($matchData['status'] === 'live') {
        // Status 'live' means 1st innings is done, we're in 2nd
        $_SESSION['live_score']['currentInnings'] = 2;
    } else {
        // Status 'setup' - check scores to determine
        $innings1HasActivity = ($scoresData[0]['runs'] > 0 || $scoresData[0]['wickets'] > 0);
        $innings2HasActivity = ($scoresData[1]['runs'] > 0 || $scoresData[1]['wickets'] > 0);
        
        if ($innings2HasActivity) {
            $_SESSION['live_score']['currentInnings'] = 2;
        } else {
            $_SESSION['live_score']['currentInnings'] = 1;
        }
    }
}

// If match is completed, load result
if ($matchData['status'] === 'completed') {
    $currentMatch['result'] = [
        'text' => $matchData['result_text']
    ];
    if ($matchData['winner_id']) {
        $stmt = $db->prepare("SELECT id, name, logo FROM teams WHERE id = ?");
        $stmt->execute([$matchData['winner_id']]);
        $currentMatch['result']['winner'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $currentMatch['result']['winner'] = null;
    }
    
    $currentMatch['innings1Score'] = $_SESSION['live_score']['innings1'];
    $currentMatch['innings2Score'] = $_SESSION['live_score']['innings2'];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $currentInnings = $_SESSION['live_score']['currentInnings'];
    $innings = $currentInnings === 1 ? 'innings1' : 'innings2';
    
    switch ($action) {
        case 'select_player':
            $playerId = intval($_POST['player_id'] ?? 0);
            
            // Get player from database
            $stmt = $db->prepare("SELECT * FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player) {
                echo json_encode(['success' => false, 'message' => 'Player not found']);
                exit;
            }
            
            // Verify player belongs to current batting team
            $battingTeamId = $_SESSION['live_score'][$innings]['battingTeamId'];
            
            if ($player['team_id'] != $battingTeamId) {
                echo json_encode(['success' => false, 'message' => 'Player does not belong to batting team']);
                exit;
            }
            
            // Check if player already batted
            $alreadyBatted = false;
            foreach ($_SESSION['live_score'][$innings]['completedPlayers'] as $cp) {
                if ($cp['player']['id'] == $playerId) {
                    $alreadyBatted = true;
                    break;
                }
            }
            
            if ($alreadyBatted) {
                echo json_encode(['success' => false, 'message' => 'This player has already batted in this innings']);
                exit;
            }
            
            // Set current player
            $currentPlayerData = [
                'player' => [
                    'id' => $player['id'],
                    'playerName' => $player['player_name'],
                    'position' => $player['position'],
                    'photo' => $player['photo'],
                    'teamId' => $player['team_id']
                ],
                'stats' => [
                    'runs' => 0,
                    'balls' => 0,
                    'fours' => 0,
                    'sixes' => 0
                ]
            ];
            
            $_SESSION['live_score']['currentPlayer'] = $currentPlayerData;
            
            // Update database
            $stmt = $db->prepare("UPDATE match_scores SET current_player = ? WHERE match_id = ? AND innings = ?");
            $stmt->execute([json_encode($currentPlayerData), $match_id, $currentInnings]);
            
            echo json_encode(['success' => true, 'player' => $currentPlayerData['player']]);
            exit;
            
        case 'score_runs':
            $runs = intval($_POST['runs'] ?? 0);
            
            if (!isset($_SESSION['live_score']['currentPlayer'])) {
                echo json_encode(['success' => false, 'message' => 'No player selected']);
                exit;
            }
            
            // Update player stats
            $_SESSION['live_score']['currentPlayer']['stats']['runs'] += $runs;
            $_SESSION['live_score']['currentPlayer']['stats']['balls'] += 1;
            
            if ($runs === 4) $_SESSION['live_score']['currentPlayer']['stats']['fours'] += 1;
            if ($runs === 6) $_SESSION['live_score']['currentPlayer']['stats']['sixes'] += 1;
            
            // Update team stats
            $_SESSION['live_score'][$innings]['runs'] += $runs;
            $_SESSION['live_score'][$innings]['balls'] += 1;
            
            if ($runs === 4) $_SESSION['live_score'][$innings]['fours'] += 1;
            if ($runs === 6) $_SESSION['live_score'][$innings]['sixes'] += 1;
            
            // Update database
            $stmt = $db->prepare("UPDATE match_scores SET runs = ?, balls = ?, fours = ?, sixes = ?, current_player = ? WHERE match_id = ? AND innings = ?");
            $stmt->execute([
                $_SESSION['live_score'][$innings]['runs'],
                $_SESSION['live_score'][$innings]['balls'],
                $_SESSION['live_score'][$innings]['fours'],
                $_SESSION['live_score'][$innings]['sixes'],
                json_encode($_SESSION['live_score']['currentPlayer']),
                $match_id,
                $currentInnings
            ]);
            
            echo json_encode([
                'success' => true,
                'teamStats' => $_SESSION['live_score'][$innings],
                'playerStats' => $_SESSION['live_score']['currentPlayer']['stats']
            ]);
            exit;
            
        case 'score_wide':
            $_SESSION['live_score'][$innings]['runs'] += 1;
            
            $stmt = $db->prepare("UPDATE match_scores SET runs = ? WHERE match_id = ? AND innings = ?");
            $stmt->execute([$_SESSION['live_score'][$innings]['runs'], $match_id, $currentInnings]);
            
            echo json_encode(['success' => true, 'teamStats' => $_SESSION['live_score'][$innings]]);
            exit;
            
        case 'score_noball':
            $_SESSION['live_score'][$innings]['runs'] += 1;
            
            $stmt = $db->prepare("UPDATE match_scores SET runs = ? WHERE match_id = ? AND innings = ?");
            $stmt->execute([$_SESSION['live_score'][$innings]['runs'], $match_id, $currentInnings]);
            
            echo json_encode(['success' => true, 'teamStats' => $_SESSION['live_score'][$innings]]);
            exit;
            
        case 'score_bye':
            $byeRuns = intval($_POST['bye_runs'] ?? 0);
            $_SESSION['live_score'][$innings]['runs'] += $byeRuns;
            $_SESSION['live_score'][$innings]['balls'] += 1;
            
            $stmt = $db->prepare("UPDATE match_scores SET runs = ?, balls = ? WHERE match_id = ? AND innings = ?");
            $stmt->execute([
                $_SESSION['live_score'][$innings]['runs'],
                $_SESSION['live_score'][$innings]['balls'],
                $match_id,
                $currentInnings
            ]);
            
            echo json_encode(['success' => true, 'teamStats' => $_SESSION['live_score'][$innings]]);
            exit;
            
        case 'player_out':
            if (!isset($_SESSION['live_score']['currentPlayer'])) {
                echo json_encode(['success' => false, 'message' => 'No player selected']);
                exit;
            }
            
            // Save completed player
            $completedPlayer = $_SESSION['live_score']['currentPlayer'];
            $_SESSION['live_score'][$innings]['completedPlayers'][] = $completedPlayer;
            
            // Update team stats
            $_SESSION['live_score'][$innings]['wickets'] += 1;
            $_SESSION['live_score'][$innings]['balls'] += 1;
            
            // Clear current player
            $_SESSION['live_score']['currentPlayer'] = null;
            
            // Update database
            $stmt = $db->prepare("UPDATE match_scores SET wickets = ?, balls = ?, completed_players = ?, current_player = NULL WHERE match_id = ? AND innings = ?");
            $stmt->execute([
                $_SESSION['live_score'][$innings]['wickets'],
                $_SESSION['live_score'][$innings]['balls'],
                json_encode($_SESSION['live_score'][$innings]['completedPlayers']),
                $match_id,
                $currentInnings
            ]);
            
            // Check if innings should end
            $shouldEndInnings = false;
            $endReason = null;
            $wickets = $_SESSION['live_score'][$innings]['wickets'];
            
            if ($wickets >= 10) {
                $shouldEndInnings = true;
                $endReason = 'All out - 10 wickets fallen';
            }
            
            // Check remaining players
            $battingTeamId = $_SESSION['live_score'][$innings]['battingTeamId'];
            $stmtPlayers = $db->prepare("SELECT COUNT(*) as count FROM players WHERE team_id = ?");
            $stmtPlayers->execute([$battingTeamId]);
            $totalPlayers = $stmtPlayers->fetch(PDO::FETCH_ASSOC)['count'];
            
            $completedCount = count($_SESSION['live_score'][$innings]['completedPlayers']);
            $remainingPlayers = $totalPlayers - $completedCount;
            
            if ($remainingPlayers <= 0) {
                $shouldEndInnings = true;
                $endReason = 'No more players available';
            }
            
            // For 2nd innings, check if target achieved
            if ($currentInnings === 2) {
                $targetRuns = $_SESSION['live_score']['innings1']['runs'];
                if ($_SESSION['live_score']['innings2']['runs'] > $targetRuns) {
                    $shouldEndInnings = true;
                    $endReason = 'Target achieved';
                }
            }
            
            echo json_encode([
                'success' => true,
                'teamStats' => $_SESSION['live_score'][$innings],
                'shouldEndInnings' => $shouldEndInnings,
                'endReason' => $endReason,
                'remainingPlayers' => $remainingPlayers
            ]);
            exit;
            
        case 'end_innings':
            if ($currentInnings === 1) {
                // FIXED: Transition to 2nd innings
                $_SESSION['live_score']['currentInnings'] = 2;
                $_SESSION['live_score']['currentPlayer'] = null;
                
                // CRITICAL: Update match status to 'live'
                $stmt = $db->prepare("UPDATE matches SET status = 'live' WHERE id = ?");
                $stmt->execute([$match_id]);
                
                // Clear current player for innings 2
                $stmt = $db->prepare("UPDATE match_scores SET current_player = NULL WHERE match_id = ? AND innings = 2");
                $stmt->execute([$match_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => '1st Innings Complete! Starting 2nd Innings...',
                    'newInnings' => 2
                ]);
            } else {
                // Calculate result
                $result = calculateMatchResult($_SESSION['live_score'], $currentMatch);
                
                // Update match in database
                $winnerId = $result['winner'] ? $result['winner']['id'] : null;
                $stmt = $db->prepare("UPDATE matches SET status = 'completed', result_text = ?, winner_id = ?, innings1_data = ?, innings2_data = ?, completed_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $result['text'],
                    $winnerId,
                    json_encode($_SESSION['live_score']['innings1']),
                    json_encode($_SESSION['live_score']['innings2']),
                    $match_id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Match Complete!',
                    'matchComplete' => true,
                    'result' => $result
                ]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

function calculateMatchResult($liveScore, $match) {
    $innings1 = $liveScore['innings1'];
    $innings2 = $liveScore['innings2'];
    $team1 = $match['team1'];
    $team2 = $match['team2'];
    
    // Determine which team batted first
    $team1BattedFirst = ($match['battingFirst']['id'] === $team1['id']);
    
    $team1Runs = $team1BattedFirst ? $innings1['runs'] : $innings2['runs'];
    $team2Runs = $team1BattedFirst ? $innings2['runs'] : $innings1['runs'];
    $team2Wickets = $team1BattedFirst ? $innings2['wickets'] : $innings1['wickets'];
    
    if ($team1Runs > $team2Runs) {
        $margin = $team1Runs - $team2Runs;
        return [
            'winner' => $team1,
            'text' => "{$team1['name']} wins by {$margin} runs"
        ];
    } elseif ($team2Runs > $team1Runs) {
        $wicketsLeft = 10 - $team2Wickets;
        return [
            'winner' => $team2,
            'text' => "{$team2['name']} wins by {$wicketsLeft} wickets"
        ];
    } else {
        return [
            'winner' => null,
            'text' => 'Match Tied!'
        ];
    }
}

// Get current innings and batting team
$currentInnings = $_SESSION['live_score']['currentInnings'];
$innings = $currentInnings === 1 ? 'innings1' : 'innings2';
$battingTeamId = $_SESSION['live_score'][$innings]['battingTeamId'];
$currentBattingTeam = ($battingTeamId === $currentMatch['team1']['id']) ? 
    $currentMatch['team1'] : $currentMatch['team2'];

// Get available players from database
$completedPlayerIds = array_column($_SESSION['live_score'][$innings]['completedPlayers'], 'player');
$completedPlayerIds = array_column($completedPlayerIds, 'id');

$query = "SELECT id, player_name as playerName, position, photo, team_id as teamId FROM players WHERE team_id = ?";
$params = [$battingTeamId];

if (!empty($completedPlayerIds)) {
    $placeholders = str_repeat('?,', count($completedPlayerIds) - 1) . '?';
    $query .= " AND id NOT IN ($placeholders)";
    $params = array_merge($params, $completedPlayerIds);
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$availablePlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$matchComplete = isset($currentMatch['status']) && $currentMatch['status'] === 'completed';
$hasCurrentPlayer = isset($_SESSION['live_score']['currentPlayer']) && $_SESSION['live_score']['currentPlayer'] !== null;

// Navigation setup
$page_title = "Live Scoreboard";
$brand_name = "🏏 ScoreBoard";

if ($is_logged_in) {
    $nav_items = [
        ['text' => 'Home', 'href' => '../index.php'],
        ['text' => 'Teams', 'href' => 'Team.php'],
        ['text' => 'Match', 'href' => 'Match.php'],
        ['text' => 'Dashboard', 'href' => 'Dash.php']
    ];
    $cta_button = ['text' => 'Logout', 'href' => 'Board.php?action=logout', 'class' => 'btn-secondary'];
} else {
    $nav_items = [
        ['text' => 'Home', 'href' => '../index.php'],
        ['text' => 'Teams', 'href' => 'Team.php'],
        ['text' => 'Match', 'href' => 'Match.php'],
        ['text' => 'Login', 'href' => 'SignUp.php']
    ];
    $cta_button = ['text' => 'Sign Up', 'href' => 'SignUp.php', 'class' => 'btn-primary-acc'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style/Board.css">
</head>
<body>

  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand brand" href="../index.php"><?php echo $brand_name; ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLinks">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navLinks">
        <ul class="navbar-nav ms-auto align-items-center me-3">
          <?php foreach ($nav_items as $item): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo htmlspecialchars($item['href']); ?>">
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

  <div class="match-info-bar">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-4">
          <div class="team-info">
            <img src="<?php echo htmlspecialchars($currentBattingTeam['logo']); ?>" alt="Team Logo" class="team-logo-small">
            <span><?php echo htmlspecialchars($currentBattingTeam['name']); ?></span>
          </div>
        </div>
        <div class="col-md-4 text-center">
          <div class="match-status">
            <span><?php echo $currentInnings === 1 ? '1st' : '2nd'; ?> Innings</span>
          </div>
        </div>
        <div class="col-md-4 text-end">
          <div class="quick-stats">
            <span>
              <?php 
              $stats = $_SESSION['live_score'][$innings];
              echo "{$stats['runs']}/{$stats['wickets']} (" . number_format($stats['balls'] / 6, 1) . " overs)";
              ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <section class="container py-4">
    
    <?php if (!$matchComplete): ?>
    
    <!-- Player Selection Section - Shows when no current player -->
    <div id="playerSelectionSection" class="row mb-4" style="display: <?php echo !$hasCurrentPlayer ? 'block' : 'none'; ?>;">
      <div class="col-12">
        <div class="selection-card">
          <h5 class="mb-3">
            <i class="fas fa-user-check me-2"></i>
            Select Next Batsman from <?php echo htmlspecialchars($currentBattingTeam['name']); ?>
          </h5>
          
          <?php if (count($availablePlayers) > 0): ?>
          <select id="playerSelect" class="form-select mb-3">
            <option value="">Choose player...</option>
            <?php foreach ($availablePlayers as $player): ?>
              <option value="<?php echo $player['id']; ?>" 
                      data-photo="<?php echo htmlspecialchars($player['photo']); ?>"
                      data-position="<?php echo htmlspecialchars($player['position']); ?>"
                      data-name="<?php echo htmlspecialchars($player['playerName']); ?>">
                <?php echo htmlspecialchars($player['playerName']); ?> (<?php echo htmlspecialchars($player['position']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button id="confirmPlayerBtn" class="btn btn-accent" disabled>
            <i class="fas fa-check me-2"></i>Confirm Player
          </button>
          
          <?php else: ?>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No more players available from <?php echo htmlspecialchars($currentBattingTeam['name']); ?>. 
            Please end this innings.
          </div>
          <button id="forceEndInningsBtn" class="btn btn-outline-light w-100">
            <i class="fas fa-exchange-alt me-2"></i>End Innings
          </button>
          <?php endif; ?>
          
          <!-- Show completed players -->
          <?php if (count($_SESSION['live_score'][$innings]['completedPlayers']) > 0): ?>
          <div class="mt-4">
            <h6 class="text-muted">Players Already Batted:</h6>
            <div class="list-group">
              <?php foreach ($_SESSION['live_score'][$innings]['completedPlayers'] as $cp): ?>
              <div class="list-group-item bg-dark text-white border-secondary">
                <?php echo htmlspecialchars($cp['player']['playerName']); ?> - 
                <?php echo $cp['stats']['runs']; ?> runs 
                (<?php echo $cp['stats']['balls']; ?> balls)
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Scoreboard Section - Shows when player is selected -->
    <div id="scoreboardSection" class="row mb-4" style="display: <?php echo $hasCurrentPlayer ? 'block' : 'none'; ?>;">
      <div class="col-lg-8">
        <div class="score-display">
          <div class="row">
            <div class="col-md-6">
              <div class="team-score-card">
                <div class="team-header">
                  <img src="<?php echo htmlspecialchars($currentBattingTeam['logo']); ?>" alt="Team Logo" class="team-logo">
                  <div class="team-details">
                    <h4><?php echo htmlspecialchars($currentBattingTeam['name']); ?></h4>
                    <span class="team-status">BATTING</span>
                  </div>
                </div>
                <div class="score-stats">
                  <div class="main-score">
                    <span id="teamScore"><?php echo $_SESSION['live_score'][$innings]['runs']; ?></span>/<span id="teamWickets"><?php echo $_SESSION['live_score'][$innings]['wickets']; ?></span>
                  </div>
                  <div class="over-info">
                    (<span id="teamOvers"><?php echo number_format($_SESSION['live_score'][$innings]['balls'] / 6, 1); ?></span> overs)
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="stats-grid">
                <div class="stat-box">
                  <div class="stat-number" id="totalFours"><?php echo $_SESSION['live_score'][$innings]['fours']; ?></div>
                  <div class="stat-label">Fours</div>
                </div>
                <div class="stat-box">
                  <div class="stat-number" id="totalSixes"><?php echo $_SESSION['live_score'][$innings]['sixes']; ?></div>
                  <div class="stat-label">Sixes</div>
                </div>
                <div class="stat-box">
                  <div class="stat-number" id="runRate">
                    <?php 
                    $balls = $_SESSION['live_score'][$innings]['balls'];
                    $runs = $_SESSION['live_score'][$innings]['runs'];
                    echo $balls > 0 ? number_format(($runs / $balls) * 6, 2) : '0.00';
                    ?>
                  </div>
                  <div class="stat-label">Run Rate</div>
                </div>
                <div class="stat-box">
                  <div class="stat-number" id="ballsFaced"><?php echo $_SESSION['live_score'][$innings]['balls']; ?></div>
                  <div class="stat-label">Balls</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php if ($hasCurrentPlayer): 
          $currentPlayer = $_SESSION['live_score']['currentPlayer'];
        ?>
        <div class="player-card mt-3">
          <div class="player-header">
            <img id="currentPlayerPhoto" src="<?php echo htmlspecialchars($currentPlayer['player']['photo']); ?>" alt="Player Photo" class="player-photo">
            <div class="player-info">
              <h5 id="currentPlayerName"><?php echo htmlspecialchars($currentPlayer['player']['playerName']); ?></h5>
              <span id="currentPlayerPosition"><?php echo htmlspecialchars($currentPlayer['player']['position']); ?></span>
            </div>
          </div>
          <div class="player-stats">
            <div class="player-stat">
              <span class="stat-label">Runs</span>
              <span id="playerRuns" class="stat-value"><?php echo $currentPlayer['stats']['runs']; ?></span>
            </div>
            <div class="player-stat">
              <span class="stat-label">Balls</span>
              <span id="playerBalls" class="stat-value"><?php echo $currentPlayer['stats']['balls']; ?></span>
            </div>
            <div class="player-stat">
              <span class="stat-label">4s</span>
              <span id="playerFours" class="stat-value"><?php echo $currentPlayer['stats']['fours']; ?></span>
            </div>
            <div class="player-stat">
              <span class="stat-label">6s</span>
              <span id="playerSixes" class="stat-value"><?php echo $currentPlayer['stats']['sixes']; ?></span>
            </div>
            <div class="player-stat">
              <span class="stat-label">S/R</span>
              <span id="playerStrikeRate" class="stat-value">
                <?php 
                $balls = $currentPlayer['stats']['balls'];
                $runs = $currentPlayer['stats']['runs'];
                echo $balls > 0 ? number_format(($runs / $balls) * 100, 2) : '0.00';
                ?>
              </span>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="scoring-panel">
          <h6 class="mb-3" style="color: var(--accent);">
            <i class="fas fa-calculator me-2"></i>Score Runs
          </h6>
          
          <div class="score-buttons">
            <button class="score-btn" data-runs="0">0</button>
            <button class="score-btn" data-runs="1">1</button>
            <button class="score-btn" data-runs="2">2</button>
            <button class="score-btn" data-runs="3">3</button>
            <button class="score-btn score-four" data-runs="4">4</button>
            <button class="score-btn score-six" data-runs="6">6</button>
          </div>

          <div class="special-buttons mt-3">
            <button id="wideBtn" class="btn btn-warning btn-sm">
              <i class="fas fa-arrow-right me-1"></i>Wide
            </button>
            <button id="noBallBtn" class="btn btn-info btn-sm">
              <i class="fas fa-ban me-1"></i>No Ball
            </button>
            <button id="byeBtn" class="btn btn-secondary btn-sm">
              <i class="fas fa-running me-1"></i>Bye
            </button>
          </div>

          <div class="out-section mt-4">
            <button id="outBtn" class="btn btn-danger btn-lg w-100">
              <i class="fas fa-times me-2"></i>OUT
            </button>
          </div>

          <div class="match-controls mt-4">
            <button id="endInningsBtn" class="btn btn-outline-light w-100">
              <i class="fas fa-exchange-alt me-2"></i>End Innings
            </button>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    
    <!-- Match Summary -->
    <div id="matchSummarySection" class="row">
      <div class="col-12">
        <div class="match-summary-card">
          <div class="text-center mb-4">
            <h3 style="color: var(--accent);">
              <i class="fas fa-trophy me-2"></i>Match Result
            </h3>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="team-final-score">
                <div class="team-name"><?php echo htmlspecialchars($currentMatch['team1']['name']); ?></div>
                <div class="final-score">
                  <?php 
                  $team1Score = $currentMatch['innings1Score'] ?? $_SESSION['live_score']['innings1'];
                  echo "{$team1Score['runs']}/{$team1Score['wickets']} (" . number_format($team1Score['balls'] / 6, 1) . " overs)";
                  ?>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="team-final-score">
                <div class="team-name"><?php echo htmlspecialchars($currentMatch['team2']['name']); ?></div>
                <div class="final-score">
                  <?php 
                  $team2Score = $currentMatch['innings2Score'] ?? $_SESSION['live_score']['innings2'];
                  echo "{$team2Score['runs']}/{$team2Score['wickets']} (" . number_format($team2Score['balls'] / 6, 1) . " overs)";
                  ?>
                </div>
              </div>
            </div>
          </div>

          <div class="match-result mt-4 text-center">
            <h4 style="color: var(--accent);"><?php echo htmlspecialchars($currentMatch['result']['text']); ?></h4>
            <p class="text-muted">
              <?php 
              if (isset($currentMatch['result']['winner']) && $currentMatch['result']['winner']) {
                echo "Congratulations to " . htmlspecialchars($currentMatch['result']['winner']['name']) . " on their victory!";
              } else {
                echo "An exciting match that ended in a tie!";
              }?>
            </p>
          </div>

          <div class="match-actions mt-4 text-center">
            <a href="Board.php?action=new_match" class="btn btn-accent me-3">
              <i class="fas fa-plus me-2"></i>New Match (Same Teams)
            </a>
            <a href="Match.php" class="btn btn-outline-light me-3">
              <i class="fas fa-refresh me-2"></i>Different Teams
            </a>
            <a href="Dash.php" class="btn btn-outline-light">
              <i class="fas fa-chart-bar me-2"></i>Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <?php endif; ?>
    
  </section>

  <!-- Out Modal -->
  <div class="modal fade" id="outModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-times-circle me-2" style="color: #dc3545;"></i>
            Player Out
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure <strong id="outPlayerName">Player Name</strong> is out?</p>
          <div class="player-final-stats">
            <div class="row text-center">
              <div class="col-3">
                <div class="stat-number" id="outPlayerRuns">0</div>
                <div class="stat-label">Runs</div>
              </div>
              <div class="col-3">
                <div class="stat-number" id="outPlayerBalls">0</div>
                <div class="stat-label">Balls</div>
              </div>
              <div class="col-3">
                <div class="stat-number" id="outPlayerFours">0</div>
                <div class="stat-label">4s</div>
              </div>
              <div class="col-3">
                <div class="stat-number" id="outPlayerSixes">0</div>
                <div class="stat-label">6s</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmOutBtn" class="btn btn-danger">
            <i class="fas fa-check me-2"></i>Confirm Out
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // AJAX helper function
  async function ajaxRequest(action, data = {}) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    for (let key in data) {
      formData.append(key, data[key]);
    }
    
    try {
      const response = await fetch('Board.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      return await response.json();
    } catch (error) {
      console.error('AJAX Error:', error);
      showMessage('Network error occurred. Please try again.', 'error');
      return { success: false, message: 'Network error' };
    }
  }

  // Player selection
  const playerSelect = document.getElementById("playerSelect");
  const confirmPlayerBtn = document.getElementById("confirmPlayerBtn");
  
  if (playerSelect && confirmPlayerBtn) {
    playerSelect.addEventListener("change", function() {
      confirmPlayerBtn.disabled = !this.value;
    });

    confirmPlayerBtn.addEventListener("click", async function() {
      const playerId = playerSelect.value;
      const selectedOption = playerSelect.options[playerSelect.selectedIndex];
      
      if (!playerId) {
        showMessage('Please select a player', 'warning');
        return;
      }
      
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
      
      const result = await ajaxRequest('select_player', { player_id: playerId });
      
      if (result.success) {
        showMessage('Player selected successfully!', 'success');
        
        // Reload page to show scoreboard with selected player
        setTimeout(() => {
          window.location.reload();
        }, 500);
      } else {
        showMessage(result.message || 'Failed to select player', 'error');
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Player';
      }
    });
  }

  // Score runs buttons
  document.querySelectorAll('.score-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const runs = parseInt(this.dataset.runs);
      
      this.disabled = true;
      const result = await ajaxRequest('score_runs', { runs: runs });
      this.disabled = false;
      
      if (result.success) {
        updateDisplays(result.teamStats, result.playerStats);
        flashScore(runs);
        
        if (runs === 4) {
          showMessage('Boundary! +4 runs', 'success');
        } else if (runs === 6) {
          showMessage('Six! +6 runs', 'success');
        } else if (runs > 0) {
          showMessage(`+${runs} run${runs > 1 ? 's' : ''}`, 'info');
        } else {
          showMessage('Dot ball', 'info');
        }
      } else {
        showMessage(result.message || 'Failed to score runs', 'error');
      }
    });
  });

  // Wide button
  const wideBtn = document.getElementById('wideBtn');
  if (wideBtn) {
    wideBtn.addEventListener('click', async function() {
      this.disabled = true;
      const result = await ajaxRequest('score_wide');
      this.disabled = false;
      
      if (result.success) {
        updateDisplays(result.teamStats);
        showMessage('Wide! +1 run (no ball counted)', 'warning');
      } else {
        showMessage(result.message || 'Failed to score wide', 'error');
      }
    });
  }

  // No ball button
  const noBallBtn = document.getElementById('noBallBtn');
  if (noBallBtn) {
    noBallBtn.addEventListener('click', async function() {
      this.disabled = true;
      const result = await ajaxRequest('score_noball');
      this.disabled = false;
      
      if (result.success) {
        updateDisplays(result.teamStats);
        showMessage('No Ball! +1 run (no ball counted)', 'info');
      } else {
        showMessage(result.message || 'Failed to score no ball', 'error');
      }
    });
  }

  // Bye button
  const byeBtn = document.getElementById('byeBtn');
  if (byeBtn) {
    byeBtn.addEventListener('click', async function() {
      const byeRuns = prompt('How many bye runs? (1-6)');
      
      if (byeRuns === null) return; // User cancelled
      
      const runs = parseInt(byeRuns);
      if (isNaN(runs) || runs < 1 || runs > 6) {
        showMessage('Please enter a valid number between 1 and 6', 'warning');
        return;
      }
      
      this.disabled = true;
      const result = await ajaxRequest('score_bye', { bye_runs: runs });
      this.disabled = false;
      
      if (result.success) {
        updateDisplays(result.teamStats);
        showMessage(`Bye! +${runs} runs (counted as ball)`, 'info');
      } else {
        showMessage(result.message || 'Failed to score bye', 'error');
      }
    });
  }

  // Out button
  const outBtn = document.getElementById('outBtn');
  if (outBtn) {
    outBtn.addEventListener('click', function() {
      const playerName = document.getElementById('currentPlayerName').textContent;
      const playerRuns = document.getElementById('playerRuns').textContent;
      const playerBalls = document.getElementById('playerBalls').textContent;
      const playerFours = document.getElementById('playerFours').textContent;
      const playerSixes = document.getElementById('playerSixes').textContent;
      
      document.getElementById('outPlayerName').textContent = playerName;
      document.getElementById('outPlayerRuns').textContent = playerRuns;
      document.getElementById('outPlayerBalls').textContent = playerBalls;
      document.getElementById('outPlayerFours').textContent = playerFours;
      document.getElementById('outPlayerSixes').textContent = playerSixes;
      
      const modal = new bootstrap.Modal(document.getElementById('outModal'));
      modal.show();
    });
  }

  // Confirm out button
  const confirmOutBtn = document.getElementById('confirmOutBtn');
  if (confirmOutBtn) {
    confirmOutBtn.addEventListener('click', async function() {
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
      
      const result = await ajaxRequest('player_out');
      
      if (result.success) {
        updateDisplays(result.teamStats);
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('outModal'));
        if (modal) modal.hide();
        
        showMessage('Player is out!', 'info');
        
        if (result.shouldEndInnings) {
          showMessage(`${result.endReason}. Please end innings.`, 'warning');
          setTimeout(() => {
            if (confirm(`${result.endReason}. End innings now?`)) {
              endInnings();
            }
          }, 1500);
        } else {
          showMessage(`${result.remainingPlayers} player(s) remaining. Select next batsman.`, 'success');
          
          // Reload to show player selection
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        }
      } else {
        showMessage(result.message || 'Failed to mark player out', 'error');
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Out';
      }
    });
  }

  // End innings button
  const endInningsBtn = document.getElementById('endInningsBtn');
  if (endInningsBtn) {
    endInningsBtn.addEventListener('click', function() {
      endInnings();
    });
  }

  // Force end innings button (when no players available)
  const forceEndInningsBtn = document.getElementById('forceEndInningsBtn');
  if (forceEndInningsBtn) {
    forceEndInningsBtn.addEventListener('click', function() {
      endInnings();
    });
  }

  // End innings function
  async function endInnings() {
    const currentInnings = <?php echo $currentInnings; ?>;
    const confirmMessage = currentInnings === 1 ? 
      'Are you sure you want to end the 1st innings?' : 
      'Are you sure you want to end the 2nd innings and complete the match?';
    
    if (!confirm(confirmMessage)) {
      return;
    }
    
    const result = await ajaxRequest('end_innings');
    
    if (result.success) {
      showMessage(result.message, 'success');
      
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    } else {
      showMessage(result.message || 'Failed to end innings', 'error');
    }
  }

  // Update displays
  function updateDisplays(teamStats, playerStats) {
    if (teamStats) {
      document.getElementById('teamScore').textContent = teamStats.runs;
      document.getElementById('teamWickets').textContent = teamStats.wickets;
      document.getElementById('teamOvers').textContent = (teamStats.balls / 6).toFixed(1);
      document.getElementById('totalFours').textContent = teamStats.fours;
      document.getElementById('totalSixes').textContent = teamStats.sixes;
      document.getElementById('ballsFaced').textContent = teamStats.balls;
      
      const runRate = teamStats.balls > 0 ? ((teamStats.runs / teamStats.balls) * 6).toFixed(2) : '0.00';
      document.getElementById('runRate').textContent = runRate;
      
      // Update quick score in top bar
      const quickScore = document.querySelector('.quick-stats span');
      if (quickScore) {
        quickScore.textContent = `${teamStats.runs}/${teamStats.wickets} (${(teamStats.balls / 6).toFixed(1)} overs)`;
      }
    }
    
    if (playerStats) {
      document.getElementById('playerRuns').textContent = playerStats.runs;
      document.getElementById('playerBalls').textContent = playerStats.balls;
      document.getElementById('playerFours').textContent = playerStats.fours;
      document.getElementById('playerSixes').textContent = playerStats.sixes;
      
      const strikeRate = playerStats.balls > 0 ? 
        ((playerStats.runs / playerStats.balls) * 100).toFixed(2) : '0.00';
      document.getElementById('playerStrikeRate').textContent = strikeRate;
    }
  }

  // Flash score animation
  function flashScore(runs) {
    const scoreElement = document.getElementById('teamScore');
    if (scoreElement) {
      scoreElement.classList.add('score-update');
      setTimeout(() => {
        scoreElement.classList.remove('score-update');
      }, 600);
    }
  }

  // Show message function
  function showMessage(message, type) {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.alert.position-fixed');
    existingMessages.forEach(msg => msg.remove());

    const alertClass = {
      'success': 'alert-success',
      'error': 'alert-danger',
      'warning': 'alert-warning',
      'info': 'alert-info'
    }[type] || 'alert-info';

    const messageDiv = document.createElement('div');
    messageDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    messageDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
    messageDiv.innerHTML = `
      <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.remove();
      }
    }, 5000);
  }
  </script>

</body>
</html>