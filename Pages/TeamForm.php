<?php
// Start session
session_start();
require_once __DIR__ . '/../config/database.php';

// Page configuration
$page_title = "Team Players – Live Cricket Scoreboard";
$brand_name = "🏏 ScoreBoard";

$database = new Database();
$db = $database->getConnection();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Get team from database
$selected_team_id = isset($_GET['teamId']) ? intval($_GET['teamId']) : null;
$current_team = null;

if ($selected_team_id) {
    $stmt = $db->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$selected_team_id]);
    $current_team = $stmt->fetch();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_player':
            $teamId = intval($_POST['team_id'] ?? 0);
            $playerName = trim($_POST['player_name'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $photo = $_POST['photo'] ?? '';
            
            if (empty($teamId) || empty($playerName) || empty($position) || empty($photo)) {
                echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
                exit;
            }
            
            // Check duplicate
            $stmt = $db->prepare("SELECT id FROM players WHERE team_id = ? AND player_name = ?");
            $stmt->execute([$teamId, $playerName]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => "Player \"$playerName\" already exists in this team!"]);
                exit;
            }
            
            // Insert player
            $stmt = $db->prepare("INSERT INTO players (team_id, player_name, position, contact, email, description, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$teamId, $playerName, $position, $contact, $email, $description, $photo])) {
                $player_id = $db->lastInsertId();
                
                // Get team name
                $stmtTeam = $db->prepare("SELECT name FROM teams WHERE id = ?");
                $stmtTeam->execute([$teamId]);
                $team = $stmtTeam->fetch();
                
                $newPlayer = [
                    'id' => $player_id,
                    'teamId' => $teamId,
                    'teamName' => $team['name'],
                    'playerName' => $playerName,
                    'position' => $position,
                    'contact' => $contact,
                    'email' => $email,
                    'description' => $description,
                    'photo' => $photo,
                    'createdDate' => date('M d, Y')
                ];
                
                echo json_encode(['success' => true, 'message' => "Player \"$playerName\" added successfully!", 'player' => $newPlayer]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add player']);
            }
            exit;
            
        case 'update_player':
            $playerId = intval($_POST['player_id'] ?? 0);
            $playerName = trim($_POST['player_name'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $photo = $_POST['photo'] ?? null;
            
            if ($photo !== null) {
                $stmt = $db->prepare("UPDATE players SET player_name = ?, position = ?, contact = ?, email = ?, description = ?, photo = ? WHERE id = ?");
                $params = [$playerName, $position, $contact, $email, $description, $photo, $playerId];
            } else {
                $stmt = $db->prepare("UPDATE players SET player_name = ?, position = ?, contact = ?, email = ?, description = ? WHERE id = ?");
                $params = [$playerName, $position, $contact, $email, $description, $playerId];
            }
            
            if ($stmt->execute($params)) {
                echo json_encode(['success' => true, 'message' => "Player \"$playerName\" updated successfully!"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update player']);
            }
            exit;
            
        case 'delete_player':
            $playerId = intval($_POST['player_id'] ?? 0);
            
            $stmt = $db->prepare("SELECT player_name FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            $player = $stmt->fetch();
            
            if ($player) {
                $stmt = $db->prepare("DELETE FROM players WHERE id = ?");
                if ($stmt->execute([$playerId])) {
                    echo json_encode(['success' => true, 'message' => "Player \"{$player['player_name']}\" deleted successfully"]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Player not found']);
            }
            exit;
            
        case 'export_players':
            $teamId = intval($_POST['team_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM players WHERE team_id = ?");
            $stmt->execute([$teamId]);
            $players = $stmt->fetchAll();
            echo json_encode(['success' => true, 'players' => $players]);
            exit;
            
        case 'clear_all_players':
            $teamId = intval($_POST['team_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM players WHERE team_id = ?");
            if ($stmt->execute([$teamId])) {
                echo json_encode(['success' => true, 'message' => 'All players cleared successfully']);
            }
            exit;
    }
}

// Get players for current team
$team_players = [];
$player_count = 0;

if ($current_team) {
    $stmt = $db->prepare("SELECT * FROM players WHERE team_id = ? ORDER BY created_at DESC");
    $stmt->execute([$current_team['id']]);
    $team_players = $stmt->fetchAll();
    
    foreach ($team_players as &$player) {
        $player['createdDate'] = date('M d, Y', strtotime($player['created_at']));
    }
    unset($player);
    
    $player_count = count($team_players);
}

// Get all teams for dropdown
$stmtTeams = $db->query("SELECT * FROM teams ORDER BY name");
$all_teams = $stmtTeams->fetchAll();

// Navigation items
if ($is_logged_in) {
    $nav_items = [
        ['text' => 'Home', 'href' => '../index.php'],
        ['text' => 'Teams', 'href' => 'Team.php'],
        ['text' => 'Dashboard', 'href' => 'Dash.php']
    ];
    $cta_button = [
        'text' => 'Logout',
        'href' => 'TeamForm.php?action=logout',
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

  <link rel="stylesheet" href="../style/TeamForm.css">
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

  <!-- TEAM HEADER SECTION -->
  <section class="container py-4">
    <div class="row align-items-center mb-4">
      <div class="col-auto">
        <a href="Team.php" class="btn btn-outline-light btn-sm">
          <i class="fas fa-arrow-left"></i> Back to Teams
        </a>
      </div>
      <div class="col">
        <div class="d-flex align-items-center">
          <?php if ($current_team): ?>
            <img src="<?php echo htmlspecialchars($current_team['logo']); ?>" 
                 alt="Team Logo" 
                 class="team-header-logo me-3">
            <div>
              <h2 class="mb-1" style="color:var(--accent); font-weight:700;">
                <?php echo htmlspecialchars($current_team['name']); ?>
              </h2>
              <p class="mb-0 text-muted">
                <?php echo htmlspecialchars($current_team['description']); ?> | 
                Captain: <?php echo htmlspecialchars($current_team['captain']); ?>
              </p>
            </div>
          <?php else: ?>
            <div>
              <h2 class="mb-1" style="color:var(--accent); font-weight:700;">Select Team</h2>
              <p class="mb-0 text-muted">Team players and management</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-auto">
        <span id="playerCount" class="badge bg-secondary">
          <?php echo $player_count; ?> Player<?php echo $player_count !== 1 ? 's' : ''; ?>
        </span>
      </div>
    </div>

    <!-- Team Selection -->
    <?php if (!$current_team): ?>
    <div class="row mb-4">
      <div class="col-md-6">
        <label class="form-label" style="color: var(--accent);">
          <i class="fas fa-users me-1"></i>
          Select Team
        </label>
        <select id="teamSelect" class="form-select" onchange="if(this.value) window.location.href='TeamForm.php?teamId='+this.value">
          <option value="">Choose a team...</option>
          <?php foreach ($all_teams as $team): ?>
            <option value="<?php echo $team['id']; ?>">
              <?php echo htmlspecialchars($team['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- PLAYERS SECTION -->
  <?php if ($current_team): ?>
  <section class="container pb-5">
    <div class="row align-items-center mb-4">
      <div class="col-md-8">
        <h4 style="color:var(--accent); font-weight:600;">Team Players</h4>
        <p class="text-muted mb-0">Manage team roster and player details</p>
      </div>
      <div class="col-md-4 text-end">
        <div class="dropdown">
          <button class="btn btn-outline-light dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-cog"></i> Manage
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="exportPlayers(); return false;">
              <i class="fas fa-download"></i> Export Players
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="clearAllPlayers(); return false;">
              <i class="fas fa-trash"></i> Clear All Players
            </a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Players Table -->
    <?php if (!empty($team_players)): ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover">
        <thead>
          <tr style="color: var(--accent);">
            <th><i class="fas fa-image me-1"></i> Photo</th>
            <th><i class="fas fa-user me-1"></i> Name</th>
            <th><i class="fas fa-baseball-ball me-1"></i> Position</th>
            <th><i class="fas fa-phone me-1"></i> Contact</th>
            <th><i class="fas fa-info-circle me-1"></i> Description</th>
            <th><i class="fas fa-calendar me-1"></i> Added</th>
            <th><i class="fas fa-cogs me-1"></i> Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($team_players as $player): ?>
          <tr>
            <td>
              <img src="<?php echo htmlspecialchars($player['photo']); ?>" 
                   alt="<?php echo htmlspecialchars($player['player_name']); ?>" 
                   class="player-photo" 
                   onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22 viewBox=%220 0 50 50%22><circle cx=%2225%22 cy=%2225%22 r=%2225%22 fill=%22%23fcb852%22/><text x=%2225%22 y=%2230%22 text-anchor=%22middle%22 fill=%22%23000%22 font-size=%2220%22><?php echo substr($player['player_name'], 0, 1); ?></text></svg>'">
            </td>
            <td>
              <div class="player-name"><?php echo htmlspecialchars($player['player_name']); ?></div>
              <?php if (!empty($player['email'])): ?>
                <small class="text-muted"><?php echo htmlspecialchars($player['email']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span class="position-badge"><?php echo htmlspecialchars($player['position']); ?></span>
            </td>
            <td>
              <span class="contact-text">
                <?php echo !empty($player['contact']) ? htmlspecialchars($player['contact']) : 'N/A'; ?>
              </span>
            </td>
            <td>
              <span class="description-text" title="<?php echo htmlspecialchars($player['description']); ?>">
                <?php echo !empty($player['description']) ? htmlspecialchars($player['description']) : 'No description'; ?>
              </span>
            </td>
            <td>
              <small class="text-muted"><?php echo htmlspecialchars($player['createdDate']); ?></small>
            </td>
            <td>
              <button class="btn action-btn btn-edit" 
                      onclick='editPlayer(<?php echo json_encode($player); ?>)' 
                      title="Edit Player">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn action-btn btn-delete" 
                      onclick="confirmDeletePlayer(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars($player['player_name'], ENT_QUOTES); ?>')" 
                      title="Delete Player">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="text-center py-5">
      <i class="fas fa-users fa-3x text-muted mb-3"></i>
      <h5 class="text-muted">No players added yet</h5>
      <p class="text-muted">Click the + button to add your first player to this team</p>
    </div>
    <?php endif; ?>

    <!-- Floating + Button -->
    <button class="btn btn-accent rounded-circle shadow-lg" 
            id="addPlayerBtn"
            data-bs-toggle="modal" 
            data-bs-target="#addPlayerModal"
            onclick="openPlayerModal()"
            title="Add New Player">
      <i class="fas fa-plus"></i>
    </button>
  </section>
  <?php endif; ?>

  <!-- Add Player Modal -->
  <div class="modal fade" id="addPlayerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <form id="playerForm" class="modal-content">
        <input type="hidden" id="editingPlayerId" name="player_id" value="">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-user-plus me-2" style="color: var(--accent);"></i>
            <span id="modalTitle">Add New Player</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-user me-1"></i>
                  Player Name *
                </label>
                <input type="text" name="playerName" id="playerName" class="form-control" required maxlength="50" 
                       placeholder="Enter player name">
              </div>
              
              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-baseball-ball me-1"></i>
                  Position/Role *
                </label>
                <select name="position" id="position" class="form-select" required>
                  <option value="">Select position...</option>
                  <option value="Batsman">Batsman</option>
                  <option value="Bowler">Bowler</option>
                  <option value="All-rounder">All-rounder</option>
                  <option value="Wicket-keeper">Wicket-keeper</option>
                  <option value="Captain">Captain</option>
                  <option value="Vice-Captain">Vice-Captain</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-phone me-1"></i>
                  Contact Number
                </label>
                <input type="tel" name="contact" id="contact" class="form-control" maxlength="15"
                       placeholder="Enter contact number">
              </div>

              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-envelope me-1"></i>
                  Email (Optional)
                </label>
                <input type="email" name="email" id="email" class="form-control"
                       placeholder="Enter email address">
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-image me-1"></i>
                  Player Photo *
                </label>
                <input type="file" name="playerPhoto" id="playerPhoto" class="form-control" accept="image/*" required>
                <div class="form-text text-muted">Supported formats: JPG, PNG, GIF (Max: 5MB)</div>
                
                <!-- Photo Preview -->
                <div id="photoPreview" class="mt-2" style="display: none;">
                  <img id="previewImage" src="" alt="Preview" class="preview-img">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">
                  <i class="fas fa-info-circle me-1"></i>
                  Description/Notes
                </label>
                <textarea name="description" id="description" class="form-control" maxlength="200" rows="4"
                          placeholder="Brief description about the player (achievements, playing style, etc.)"></textarea>
                <div class="form-text text-muted">Maximum 200 characters</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-acc">
            <i class="fas fa-save me-1"></i>
            <span id="submitBtnText">Save Player</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Pass PHP data to JavaScript
  window.currentTeam = <?php echo $current_team ? json_encode($current_team) : 'null'; ?>;
  window.teamPlayers = <?php echo json_encode($team_players); ?>;

  let editingPlayer = null;

  // Photo preview handler
  document.getElementById('playerPhoto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(event) {
        document.getElementById('previewImage').src = event.target.result;
        document.getElementById('photoPreview').style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      document.getElementById('photoPreview').style.display = 'none';
    }
  });

  // Open modal for adding/editing
  function openPlayerModal(player = null) {
    editingPlayer = player;
    const modalTitle = document.getElementById('modalTitle');
    const submitBtnText = document.getElementById('submitBtnText');
    const photoInput = document.getElementById('playerPhoto');
    
    if (player) {
      // Editing
      modalTitle.textContent = 'Edit Player';
      submitBtnText.textContent = 'Update Player';
      
      document.getElementById('editingPlayerId').value = player.id;
      document.getElementById('playerName').value = player.player_name;
      document.getElementById('position').value = player.position;
      document.getElementById('contact').value = player.contact || '';
      document.getElementById('email').value = player.email || '';
      document.getElementById('description').value = player.description || '';
      
      document.getElementById('previewImage').src = player.photo;
      document.getElementById('photoPreview').style.display = 'block';
      
      photoInput.removeAttribute('required');
    } else {
      // Adding
      modalTitle.textContent = 'Add New Player';
      submitBtnText.textContent = 'Save Player';
      
      document.getElementById('playerForm').reset();
      document.getElementById('editingPlayerId').value = '';
      document.getElementById('photoPreview').style.display = 'none';
      
      photoInput.setAttribute('required', 'required');
    }
  }

  // Edit player
  function editPlayer(player) {
    openPlayerModal(player);
    const modal = new bootstrap.Modal(document.getElementById('addPlayerModal'));
    modal.show();
  }

  // Delete player
  async function confirmDeletePlayer(playerId, playerName) {
    if (confirm(`Are you sure you want to delete "${playerName}"?\n\nThis action cannot be undone.`)) {
      const response = await fetch('TeamForm.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'delete_player',
          player_id: playerId
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        showMessage(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showMessage(result.message, 'error');
      }
    }
  }

  // Form submission
  document.getElementById('playerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const photoFile = formData.get('playerPhoto');
    const editingPlayerId = document.getElementById('editingPlayerId').value;
    
    // Check if photo is required
    if (!editingPlayerId && (!photoFile || photoFile.size === 0)) {
      showMessage('Please select a player photo', 'error');
      return;
    }
    
    if (photoFile && photoFile.size > 5 * 1024 * 1024) {
      showMessage('Photo file size must be less than 5MB', 'error');
      return;
    }
    
    const processForm = async (photoBase64 = null) => {
      const params = {
        action: editingPlayerId ? 'update_player' : 'add_player',
        team_id: window.currentTeam.id,
        player_name: formData.get('playerName'),
        position: formData.get('position'),
        contact: formData.get('contact'),
        email: formData.get('email'),
        description: formData.get('description')
      };
      
      if (editingPlayerId) {
        params.player_id = editingPlayerId;
      }
      
      if (photoBase64) {
        params.photo = photoBase64;
      } else if (!editingPlayerId) {
        showMessage('Photo is required', 'error');
        return;
      }
      
      const response = await fetch('TeamForm.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(params)
      });
      
      const result = await response.json();
      
      if (result.success) {
        showMessage(result.message, 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showMessage(result.message, 'error');
      }
    };
    
    if (photoFile && photoFile.size > 0) {
      const reader = new FileReader();
      reader.onload = function(event) {
        processForm(event.target.result);
      };
      reader.readAsDataURL(photoFile);
    } else {
      processForm();
    }
  });

  // Export players
  async function exportPlayers() {
    if (!window.currentTeam) return;
    
    const response = await fetch('TeamForm.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'export_players',
        team_id: window.currentTeam.id
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      const dataStr = JSON.stringify(result.players, null, 2);
      const dataBlob = new Blob([dataStr], {type: 'application/json'});
      const url = URL.createObjectURL(dataBlob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `${window.currentTeam.name}_players_${new Date().toISOString().split('T')[0]}.json`;
      link.click();
      URL.revokeObjectURL(url);
      showMessage('Players data exported successfully!', 'success');
    }
  }

  // Clear all players
  async function clearAllPlayers() {
    if (!window.currentTeam) return;
    
    if (confirm(`Are you sure you want to delete ALL players from "${window.currentTeam.name}"?\n\nThis action cannot be undone!`)) {
      const response = await fetch('TeamForm.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'clear_all_players',
          team_id: window.currentTeam.id
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        showMessage(result.message, 'success');
        setTimeout(() => location.reload(), 1000);
      }
    }
  }

  // Show message
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
            <i class="fas fa-database me-1"></i>
            <strong>Player Management:</strong> Add, edit, and manage individual players for each team.
          </small>
        </div>
        <div class="col-md-4 text-end">
          <small class="text-muted">
            <i class="fas fa-server me-1"></i>
            MySQL Database Storage
          </small>
        </div>
      </div>
    </div>
  </div>

</body>
</html>