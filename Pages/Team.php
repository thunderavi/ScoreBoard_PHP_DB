<?php
// Start session
session_start();

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/auth_check.php';
// Page configuration
$page_title = "Teams – Live Cricket Scoreboard";
$brand_name = "🏏 ScoreBoard";

// Database connection
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_team':
            $name = trim($_POST['name'] ?? '');
            $captain = trim($_POST['captain'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $logo = $_POST['logo'] ?? '';
            
            if (empty($name) || empty($captain) || empty($description) || empty($logo)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            // Check duplicate
            $stmt = $db->prepare("SELECT id FROM teams WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => "Team \"$name\" already exists!"]);
                exit;
            }
            
            // Insert team
            $stmt = $db->prepare("INSERT INTO teams (name, captain, description, logo, created_by) VALUES (?, ?, ?, ?, ?)");
            $created_by = $_SESSION['user_id'] ?? null;
            
            if ($stmt->execute([$name, $captain, $description, $logo, $created_by])) {
                $team_id = $db->lastInsertId();
                
                $newTeam = [
                    'id' => $team_id,
                    'name' => $name,
                    'captain' => $captain,
                    'description' => $description,
                    'logo' => $logo,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'createdDate' => date('M d, Y')
                ];
                
                echo json_encode(['success' => true, 'message' => "Team \"$name\" created successfully!", 'team' => $newTeam]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create team']);
            }
            exit;
            
        case 'delete_team':
            $teamId = $_POST['team_id'] ?? '';
            
            $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);
            $team = $stmt->fetch();
            
            if ($team) {
                $stmt = $db->prepare("DELETE FROM teams WHERE id = ?");
                if ($stmt->execute([$teamId])) {
                    echo json_encode(['success' => true, 'message' => "Team \"{$team['name']}\" deleted successfully"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete team']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Team not found']);
            }
            exit;
            
        case 'export_teams':
            $stmt = $db->query("SELECT id, name, captain, description, logo, created_at FROM teams ORDER BY created_at DESC");
            $teams = $stmt->fetchAll();
            echo json_encode(['success' => true, 'teams' => $teams]);
            exit;
            
        case 'clear_all':
            $stmt = $db->prepare("DELETE FROM teams");
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'All teams deleted successfully']);
            }
            exit;
    }
}

// Get teams from database
$stmt = $db->query("SELECT t.*, COUNT(p.id) as playerCount FROM teams t LEFT JOIN players p ON t.id = p.team_id GROUP BY t.id ORDER BY t.created_at DESC");
$teams = $stmt->fetchAll();

foreach ($teams as &$team) {
    $team['createdDate'] = date('M d, Y', strtotime($team['created_at']));
}
unset($team);

$team_count = count($teams);

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
        'href' => 'Team.php?action=logout',
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

  <link rel="stylesheet" href="../style/Team.css">
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

  <!-- TEAM SECTION -->
  <section class="container py-5 position-relative">
    <div class="row align-items-center mb-4">
      <div class="col-md-6">
        <h2 class="mb-2" style="color:var(--accent); font-weight:700;">Our Cricket Teams</h2>
        <p class="mb-0" style="color:var(--muted);">
          Explore all registered teams, their players, and match stats. 
          <span id="teamCount" class="badge bg-secondary ms-2"><?php echo $team_count; ?> Team<?php echo $team_count !== 1 ? 's' : ''; ?></span>
        </p>
      </div>
      <div class="col-md-6 text-end">
        <div class="dropdown d-inline-block">
          <button class="btn btn-outline-light dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
            <i class="fas fa-cog"></i> Manage
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" onclick="exportTeams(); return false;">
              <i class="fas fa-download"></i> Export Teams
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="clearAllTeams(); return false;">
              <i class="fas fa-trash"></i> Clear All Teams
            </a></li>
          </ul>
        </div>
      </div>
    </div>

    <div class="row g-4" id="teamContainer">
      <?php if (empty($teams)): ?>
        <div class="col-12 text-center">
          <div class="py-5">
            <h4 style="color: var(--muted); margin-bottom: 1rem;">No teams added yet</h4>
            <p style="color: var(--muted);">Click the + button to add your first team</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($teams as $team): ?>
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 d-flex flex-row align-items-center position-relative" data-team-id="<?php echo $team['id']; ?>">
              <img src="<?php echo htmlspecialchars($team['logo']); ?>" 
                   class="team-logo me-3" 
                   alt="Team Logo" 
                   onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22><rect width=%2260%22 height=%2260%22 fill=%22%23fcb852%22/><text x=%2230%22 y=%2235%22 text-anchor=%22middle%22 fill=%22%23000%22 font-size=%2220%22>T</text></svg>'">
              <div class="card-body p-2" style="color:#c5c6c7;">
                <h5 class="card-title" style="color: var(--accent); margin-bottom: 0.25rem;">
                  <?php echo htmlspecialchars($team['name']); ?>
                </h5>
                <p class="card-text" style="color:#c5c6c7; margin-bottom: 0.25rem;">
                  Captain: <?php echo htmlspecialchars($team['captain']); ?>
                </p>
                <p class="card-text" style="color:#c5c6c7; font-size: 0.9rem;">
                  <?php echo htmlspecialchars($team['description']); ?>
                </p>
                <div class="d-flex gap-2 mt-2">
                  <button class="btn btn-sm btn-primary-acc" onclick="viewTeam(<?php echo $team['id']; ?>)">
                    View Team
                  </button>
                  <button class="btn btn-sm btn-outline-danger delete-btn" onclick="confirmDelete(<?php echo $team['id']; ?>)">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <?php if (!empty($team['createdDate'])): ?>
                  <small class="text-muted" style="font-size: 0.75rem;">
                    Added: <?php echo htmlspecialchars($team['createdDate']); ?>
                  </small>
                <?php endif; ?>
              </div>
              <button class="position-absolute top-0 end-0 btn btn-sm text-muted p-1" 
                      style="background: none; border: none;" 
                      onclick="confirmDelete(<?php echo $team['id']; ?>)">
                ×
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Floating + Button -->
    <button id="addTeamBtn" class="btn btn-accent rounded-circle shadow-lg" 
            title="Add New Team" 
            data-bs-toggle="modal" 
            data-bs-target="#addTeamModal">
      <i class="fas fa-plus"></i>
    </button>
  </section>

  <!-- Add Team Modal -->
  <div class="modal fade" id="addTeamModal" tabindex="-1">
    <div class="modal-dialog">
      <form id="teamForm" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="fas fa-plus-circle me-2" style="color: var(--accent);"></i>
            Add New Team
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-users me-1"></i>
              Team Name *
            </label>
            <input type="text" name="name" class="form-control" required maxlength="50" 
                   placeholder="Enter team name">
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-crown me-1"></i>
              Captain *
            </label>
            <input type="text" name="captain" class="form-control" required maxlength="50"
                   placeholder="Enter captain name">
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-info-circle me-1"></i>
              Description *
            </label>
            <textarea name="description" class="form-control" required maxlength="200" rows="3"
                      placeholder="Brief description about the team"></textarea>
            <div class="form-text text-muted">Maximum 200 characters</div>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <i class="fas fa-image me-1"></i>
              Team Logo *
            </label>
            <input type="file" name="logo" id="logoInput" class="form-control" accept="image/*" required>
            <div class="form-text text-muted">Supported formats: JPG, PNG, GIF (Max: 5MB)</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-acc">
            <i class="fas fa-save me-1"></i>
            Save Team
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Pass PHP data to JavaScript
  window.phpTeams = <?php echo json_encode($teams); ?>;
  window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

  // Team form submission
  document.getElementById('teamForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const logoFile = formData.get('logo');
    
    if (!logoFile || logoFile.size === 0) {
      showMessage('Please select a team logo', 'error');
      return;
    }
    
    // Check file size (5MB max)
    if (logoFile.size > 5 * 1024 * 1024) {
      showMessage('Logo file size must be less than 5MB', 'error');
      return;
    }
    
    // Convert logo to base64
    const reader = new FileReader();
    reader.onload = async function(event) {
      const logoBase64 = event.target.result;
      
      // Send to server
      const response = await fetch('Team.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'add_team',
          name: formData.get('name'),
          captain: formData.get('captain'),
          description: formData.get('description'),
          logo: logoBase64
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        showMessage(result.message, 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showMessage(result.message, 'error');
      }
    };
    
    reader.readAsDataURL(logoFile);
  });

  // View team function
  function viewTeam(teamId) {
    window.location.href = `TeamForm.php?teamId=${teamId}`;
  }

  // Confirm delete
  async function confirmDelete(teamId) {
    const teams = window.phpTeams;
    const team = teams.find(t => t.id == teamId);
    
    if (team && confirm(`Are you sure you want to delete "${team.name}"?\n\nThis action cannot be undone.`)) {
      const response = await fetch('Team.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'delete_team',
          team_id: teamId
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

  // Export teams
  async function exportTeams() {
    const response = await fetch('Team.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=export_teams'
    });
    
    const result = await response.json();
    
    if (result.success) {
      const dataStr = JSON.stringify(result.teams, null, 2);
      const dataBlob = new Blob([dataStr], {type: 'application/json'});
      const url = URL.createObjectURL(dataBlob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `cricket_teams_${new Date().toISOString().split('T')[0]}.json`;
      link.click();
      URL.revokeObjectURL(url);
      showMessage('Teams data exported successfully!', 'success');
    }
  }

  // Clear all teams
  async function clearAllTeams() {
    if (confirm('Are you sure you want to delete ALL teams?\n\nThis action cannot be undone!')) {
      if (confirm('This will permanently delete all team data. Are you absolutely sure?')) {
        const response = await fetch('Team.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'action=clear_all'
        });
        
        const result = await response.json();
        
        if (result.success) {
          showMessage(result.message, 'success');
          setTimeout(() => location.reload(), 1000);
        }
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
            <strong>Database Storage Mode:</strong> Teams are stored in MySQL database permanently.
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