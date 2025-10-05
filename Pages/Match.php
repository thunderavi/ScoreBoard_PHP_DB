<?php
// Start session
session_start();


require_once __DIR__ . '/auth_check.php';

// Page configuration
$page_title = "Match Setup – Live Cricket Scoreboard";
$brand_name = "🏏 ScoreBoard";

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_match':
            $team1Id = intval($_POST['team1_id'] ?? 0);
            $team2Id = intval($_POST['team2_id'] ?? 0);
            $tossWinnerId = intval($_POST['toss_winner_id'] ?? 0);
            $coinResult = $_POST['coin_result'] ?? '';
            $tossChoice = $_POST['toss_choice'] ?? '';
            $battingFirstId = intval($_POST['batting_first_id'] ?? 0);
            $fieldingFirstId = intval($_POST['fielding_first_id'] ?? 0);
            
            // Get team details from database
            $stmt = $db->prepare("SELECT * FROM teams WHERE id IN (?, ?)");
            $stmt->execute([$team1Id, $team2Id]);
            $teams = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);
            
            $team1 = $teams[$team1Id] ?? null;
            $team2 = $teams[$team2Id] ?? null;
            
            if (!$team1 || !$team2) {
                echo json_encode(['success' => false, 'message' => 'Invalid teams selected']);
                exit;
            }
            
            // Insert match
            $stmt = $db->prepare("INSERT INTO matches (team1_id, team2_id, toss_winner_id, coin_result, toss_choice, batting_first_id, fielding_first_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'setup')");
            
            if ($stmt->execute([$team1Id, $team2Id, $tossWinnerId, $coinResult, $tossChoice, $battingFirstId, $fieldingFirstId])) {
                $match_id = $db->lastInsertId();
                
                // Store match ID in session for Board.php
                $_SESSION['current_match_id'] = $match_id;
                
                echo json_encode(['success' => true, 'message' => 'Match setup saved successfully', 'match_id' => $match_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save match']);
            }
            exit;
    }
}

// ===== MOVED OUTSIDE POST HANDLER =====
// Get teams from database with player counts
$stmt = $db->query("SELECT t.*, COUNT(p.id) as playerCount FROM teams t LEFT JOIN players p ON t.id = p.team_id GROUP BY t.id ORDER BY t.name");
$teams = $stmt->fetchAll();

// Navigation items based on login status
if ($is_logged_in) {
    $nav_items = [
        ['text' => 'Home', 'href' => '../index.php'],
        ['text' => 'Teams', 'href' => 'Team.php'],
        ['text' => 'Match', 'href' => 'Match.php'],
        ['text' => 'Dashboard', 'href' => 'Dash.php']
    ];
    $cta_button = [
        'text' => 'Logout',
        'href' => 'Match.php?action=logout',
        'class' => 'btn-secondary'
    ];
} else {
    $nav_items = [
        ['text' => 'Home', 'href' => '../index.php'],
        ['text' => 'Teams', 'href' => 'Team.php'],
        ['text' => 'Match', 'href' => 'Match.php'],
        ['text' => 'Login', 'href' => 'SignUp.php']
    ];
    $cta_button = [
        'text' => 'Sign Up',
        'href' => 'SignUp.php',
        'class' => 'btn-primary-acc'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../style/Match.css">
</head>
<body>

  <!-- NAV -->
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

  <!-- MATCH SETUP SECTION -->
  <section class="container py-5">
    <div class="row align-items-center mb-4">
      <div class="col-auto">
        <a href="Team.php" class="btn btn-outline-light btn-sm">
          <i class="fas fa-arrow-left"></i> Back to Teams
        </a>
      </div>
      <div class="col">
        <h2 class="mb-1" style="color:var(--accent); font-weight:700;">Match Setup</h2>
        <p class="mb-0 text-muted">Select teams, conduct toss, and start your cricket match</p>
      </div>
    </div>

    <?php if (empty($teams)): ?>
      <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        No teams available. Please <a href="Team.php" class="alert-link">create teams</a> first.
      </div>
    <?php else: ?>

    <!-- Team Selection -->
    <div class="row mb-5" id="teamSelectionSection">
      <div class="col-lg-6 mb-4">
        <div class="team-card">
          <div class="team-card-header">
            <h5><i class="fas fa-users me-2"></i>Team 1</h5>
          </div>
          <div class="team-card-body">
            <select id="team1Select" class="form-select mb-3">
              <option value="">Choose Team 1...</option>
              <?php foreach ($teams as $team): ?>
                <option value="<?php echo $team['id']; ?>" 
                        data-logo="<?php echo htmlspecialchars($team['logo']); ?>"
                        data-name="<?php echo htmlspecialchars($team['name']); ?>"
                        data-captain="<?php echo htmlspecialchars($team['captain']); ?>"
                        data-players="<?php echo $team['playerCount']; ?>">
                  <?php echo htmlspecialchars($team['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="team1Info" class="team-info" style="display: none;">
              <img id="team1Logo" src="" alt="Team 1 Logo" class="team-logo">
              <div class="team-details">
                <h6 id="team1Name" class="team-name">Team Name</h6>
                <p id="team1Captain" class="team-captain">Captain: </p>
                <p id="team1Players" class="team-players">Players: 0</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 mb-4">
        <div class="team-card">
          <div class="team-card-header">
            <h5><i class="fas fa-users me-2"></i>Team 2</h5>
          </div>
          <div class="team-card-body">
            <select id="team2Select" class="form-select mb-3">
              <option value="">Choose Team 2...</option>
              <?php foreach ($teams as $team): ?>
                <option value="<?php echo $team['id']; ?>"
                        data-logo="<?php echo htmlspecialchars($team['logo']); ?>"
                        data-name="<?php echo htmlspecialchars($team['name']); ?>"
                        data-captain="<?php echo htmlspecialchars($team['captain']); ?>"
                        data-players="<?php echo $team['playerCount']; ?>">
                  <?php echo htmlspecialchars($team['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="team2Info" class="team-info" style="display: none;">
              <img id="team2Logo" src="" alt="Team 2 Logo" class="team-logo">
              <div class="team-details">
                <h6 id="team2Name" class="team-name">Team Name</h6>
                <p id="team2Captain" class="team-captain">Captain: </p>
                <p id="team2Players" class="team-players">Players: 0</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toss Section -->
    <div class="row" id="tossSection" style="display: none;">
      <div class="col-12">
        <div class="toss-card">
          <div class="text-center mb-4">
            <h4 style="color: var(--accent); font-weight: 600;">
              <i class="fas fa-coins me-2"></i>Toss Time
            </h4>
            <p class="text-muted">Choose your call and flip the coin!</p>
          </div>

          <div class="row align-items-center">
            <!-- Toss Options -->
            <div class="col-lg-4">
              <div class="toss-options">
                <h6 class="mb-3" style="color: var(--accent);">Choose Your Call:</h6>
                <div class="btn-group-vertical d-grid gap-2" role="group">
                  <input type="radio" class="btn-check" name="tossCall" id="headsRadio" value="heads" autocomplete="off">
                  <label class="btn btn-outline-accent" for="headsRadio">
                    <i class="fas fa-circle me-2"></i>Heads
                  </label>

                  <input type="radio" class="btn-check" name="tossCall" id="tailsRadio" value="tails" autocomplete="off">
                  <label class="btn btn-outline-accent" for="tailsRadio">
                    <i class="fas fa-circle me-2"></i>Tails
                  </label>
                </div>

                <div class="mt-3">
                  <h6 class="mb-2" style="color: var(--accent);">If Won, Choose:</h6>
                  <div class="btn-group-vertical d-grid gap-2" role="group">
                    <input type="radio" class="btn-check" name="tossChoice" id="battingRadio" value="batting" autocomplete="off" checked>
                    <label class="btn btn-outline-light btn-sm" for="battingRadio">
                      <i class="fas fa-baseball-ball me-2"></i>Batting
                    </label>

                    <input type="radio" class="btn-check" name="tossChoice" id="fieldingRadio" value="fielding" autocomplete="off">
                    <label class="btn btn-outline-light btn-sm" for="fieldingRadio">
                      <i class="fas fa-running me-2"></i>Fielding
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Coin Animation -->
            <div class="col-lg-4 text-center">
              <div class="coin-container">
                <div id="coin" class="coin">
                  <div class="coin-side coin-heads">H</div>
                  <div class="coin-side coin-tails">T</div>
                </div>
              </div>
              
              <button id="flipCoinBtn" class="btn btn-accent btn-lg mt-4" disabled>
                <i class="fas fa-hand-paper me-2"></i>Flip Coin
              </button>
            </div>

            <!-- Toss Result -->
            <div class="col-lg-4">
              <div id="tossResult" class="toss-result" style="display: none;">
                <div class="text-center">
                  <div id="tossResultIcon" class="toss-result-icon mb-3">
                    <i class="fas fa-trophy"></i>
                  </div>
                  <h5 id="tossWinnerText" style="color: var(--accent);">Toss Winner</h5>
                  <p id="tossDecisionText" class="text-muted">Decision</p>
                  
                  <div class="match-order mt-3">
                    <div class="batting-team mb-2">
                      <small class="text-muted">BATTING FIRST</small>
                      <div id="battingFirstTeam" class="team-badge">Team Name</div>
                    </div>
                    <div class="fielding-team">
                      <small class="text-muted">FIELDING FIRST</small>
                      <div id="fieldingFirstTeam" class="team-badge">Team Name</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Next Button -->
    <div class="row mt-4" id="nextButtonSection" style="display: none;">
      <div class="col-12 text-center">
        <button id="nextBtn" class="btn btn-primary-acc btn-lg">
          <i class="fas fa-arrow-right me-2"></i>Start Match
        </button>
      </div>
    </div>

    <?php endif; ?>
  </section>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Pass PHP data to JavaScript
  window.teams = <?php echo json_encode($teams); ?>;

  let selectedTeam1 = null;
  let selectedTeam2 = null;
  let matchData = null;

  // DOM Elements
  const team1Select = document.getElementById("team1Select");
  const team2Select = document.getElementById("team2Select");
  const team1Info = document.getElementById("team1Info");
  const team2Info = document.getElementById("team2Info");
  const tossSection = document.getElementById("tossSection");
  const flipCoinBtn = document.getElementById("flipCoinBtn");
  const coin = document.getElementById("coin");
  const tossResult = document.getElementById("tossResult");
  const nextButtonSection = document.getElementById("nextButtonSection");

  // Team selection handlers
  team1Select.addEventListener("change", function() {
    handleTeamSelection(this.value, 1);
  });

  team2Select.addEventListener("change", function() {
    handleTeamSelection(this.value, 2);
  });

  // Toss call radio buttons
  document.querySelectorAll('input[name="tossCall"]').forEach(radio => {
    radio.addEventListener("change", updateFlipButtonState);
  });

  // Flip coin button
  flipCoinBtn.addEventListener("click", flipCoin);

  // Next button
  document.getElementById("nextBtn").addEventListener("click", async function() {
    if (!matchData) {
      showMessage("Match data not properly saved. Please try the toss again.", "error");
      return;
    }
    
    // Save match data to server
    const response = await fetch('Match.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'save_match',
        team1_id: matchData.team1.id,
        team2_id: matchData.team2.id,
        toss_winner_id: matchData.tossWinner.id,
        coin_result: matchData.coinResult,
        toss_choice: matchData.tossChoice,
        batting_first_id: matchData.battingFirst.id,
        fielding_first_id: matchData.fieldingFirst.id
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      // Also save to localStorage for backward compatibility
      localStorage.setItem('currentMatch', JSON.stringify(result.match));
      
      showMessage("Redirecting to scoreboard...", "success");
      setTimeout(() => {
        window.location.href = "Board.php";
      }, 1000);
    } else {
      showMessage(result.message, "error");
    }
  });

  function handleTeamSelection(teamId, teamNumber) {
    if (!teamId) {
      if (teamNumber === 1) {
        selectedTeam1 = null;
        team1Info.style.display = "none";
      } else {
        selectedTeam2 = null;
        team2Info.style.display = "none";
      }
      updateTossVisibility();
      return;
    }

    const select = teamNumber === 1 ? team1Select : team2Select;
    const option = select.options[select.selectedIndex];
    
    const team = {
      id: parseInt(teamId),
      name: option.getAttribute('data-name'),
      captain: option.getAttribute('data-captain'),
      logo: option.getAttribute('data-logo'),
      playerCount: parseInt(option.getAttribute('data-players'))
    };

    // Check if same team is selected for both
    if (teamNumber === 1 && selectedTeam2 && selectedTeam2.id === team.id) {
      showMessage("Please select different teams", "error");
      team1Select.value = "";
      selectedTeam1 = null;
      team1Info.style.display = "none";
      updateTossVisibility();
      return;
    }
    if (teamNumber === 2 && selectedTeam1 && selectedTeam1.id === team.id) {
      showMessage("Please select different teams", "error");
      team2Select.value = "";
      selectedTeam2 = null;
      team2Info.style.display = "none";
      updateTossVisibility();
      return;
    }

    if (teamNumber === 1) {
      selectedTeam1 = team;
      updateTeamInfo(1, selectedTeam1);
      team1Info.style.display = "block";
      team1Info.classList.add("fade-in");
    } else {
      selectedTeam2 = team;
      updateTeamInfo(2, selectedTeam2);
      team2Info.style.display = "block";
      team2Info.classList.add("fade-in");
    }

    updateTossVisibility();
  }

  function updateTeamInfo(teamNumber, team) {
    document.getElementById(`team${teamNumber}Logo`).src = team.logo;
    document.getElementById(`team${teamNumber}Name`).textContent = team.name;
    document.getElementById(`team${teamNumber}Captain`).textContent = `Captain: ${team.captain}`;
    document.getElementById(`team${teamNumber}Players`).textContent = `Players: ${team.playerCount}`;
  }

  function updateTossVisibility() {
    if (selectedTeam1 && selectedTeam2) {
      tossSection.style.display = "block";
      tossSection.classList.add("fade-in");
      updateFlipButtonState();
    } else {
      tossSection.style.display = "none";
      tossResult.style.display = "none";
      nextButtonSection.style.display = "none";
    }
  }

  function updateFlipButtonState() {
    const tossCallSelected = document.querySelector('input[name="tossCall"]:checked');
    flipCoinBtn.disabled = !tossCallSelected;
  }

  function flipCoin() {
    const tossCall = document.querySelector('input[name="tossCall"]:checked').value;
    const tossChoice = document.querySelector('input[name="tossChoice"]:checked').value;

    flipCoinBtn.disabled = true;
    coin.classList.add("flipping");
    tossResult.style.display = "none";

    setTimeout(() => {
      const coinResult = Math.random() < 0.5 ? 'heads' : 'tails';
      
      coin.classList.remove("flipping");
      if (coinResult === 'heads') {
        coin.classList.add("show-heads");
        coin.classList.remove("show-tails");
      } else {
        coin.classList.add("show-tails");
        coin.classList.remove("show-heads");
      }

      const tossWon = tossCall === coinResult;
      let tossWinner, tossLoser;
      
      if (tossWon) {
        tossWinner = selectedTeam1;
        tossLoser = selectedTeam2;
      } else {
        tossWinner = selectedTeam2;
        tossLoser = selectedTeam1;
      }

      let battingFirst, fieldingFirst;
      if (tossChoice === 'batting') {
        battingFirst = tossWinner;
        fieldingFirst = tossLoser;
      } else {
        battingFirst = tossLoser;
        fieldingFirst = tossWinner;
      }

      displayTossResult(coinResult, tossWinner, tossChoice, battingFirst, fieldingFirst);

      matchData = {
        team1: selectedTeam1,
        team2: selectedTeam2,
        tossWinner: tossWinner,
        coinResult: coinResult,
        tossChoice: tossChoice,
        battingFirst: battingFirst,
        fieldingFirst: fieldingFirst,
        createdAt: new Date().toISOString()
      };

      setTimeout(() => {
        nextButtonSection.style.display = "block";
        nextButtonSection.classList.add("slide-in-right");
      }, 500);

    }, 2000);
  }

  function displayTossResult(coinResult, tossWinner, tossChoice, battingFirst, fieldingFirst) {
    document.getElementById("tossWinnerText").textContent = `${tossWinner.name} Wins Toss!`;
    document.getElementById("tossDecisionText").textContent = 
      `Coin: ${coinResult.charAt(0).toUpperCase() + coinResult.slice(1)} | Chooses to ${tossChoice} first`;
    document.getElementById("battingFirstTeam").textContent = battingFirst.name;
    document.getElementById("fieldingFirstTeam").textContent = fieldingFirst.name;

    tossResult.style.display = "block";
    tossResult.classList.add("fade-in");
  }

  function showMessage(message, type) {
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
    
    setTimeout(() => {
      if (messageDiv.parentNode) {
        messageDiv.remove();
      }
    }, 5000);
  }
  </script>

  <!-- Info Footer -->
  <div class="container-fluid mt-5 py-3" style="background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.1);">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-8">
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Match Setup:</strong> Select two teams, conduct the toss, and proceed to match scoring.
          </small>
        </div>
        <div class="col-md-4 text-end">
          <small class="text-muted">
            <i class="fas fa-trophy me-1"></i>
            PHP Session Storage
          </small>
        </div>
      </div>
    </div>
  </div>

</body>
</html>