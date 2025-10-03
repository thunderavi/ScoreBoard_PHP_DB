<?php
// Start session
session_start();

require_once __DIR__ . '/../config/database.php';

// Page configuration
$page_title = "Login / Sign Up - ScoreBoard";
$brand_name = "🏏 ScoreBoard";

// Check if already logged in
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Redirect to dashboard if already logged in
if ($is_logged_in && !isset($_GET['action'])) {
    header('Location: Dash.php');
    exit;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: SignUp.php');
    exit;
}

// Initialize error/success messages
$error_message = '';
$success_message = '';
$form_type = 'login'; // default form type

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($action === 'signup') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        if (empty($name)) {
            $errors['name'] = 'Name cannot be empty.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/', $email)) {
            $errors['email'] = 'Enter a valid Gmail address.';
        }
        
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
            $errors['password'] = 'Password must be 8+ chars with letters & numbers.';
        }
        
        if (empty($errors)) {
            // Check if user exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'An account with this email already exists.';
                $form_type = 'signup';
            } else {
                // Create new user
                $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                if ($stmt->execute([$name, $email, $hashed_password])) {
                    $user_id = $db->lastInsertId();
                    
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    session_regenerate_id(true);
                    header('Location: Dash.php');
                    exit;
                }
            }
        } else {
            $error_message = implode('<br>', $errors);
            $form_type = 'signup';
        }
        
    } elseif ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/', $email)) {
            $errors['email'] = 'Enter a valid Gmail address.';
        }
        
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    
                    session_regenerate_id(true);
                    header('Location: Dash.php');
                    exit;
                }
            }
            
            $error_message = 'Invalid email or password.';
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Determine which form to show
if (isset($_GET['mode']) && $_GET['mode'] === 'signup') {
    $form_type = 'signup';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../style/sign.css">
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../index.php"><?php echo $brand_name; ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLinks">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navLinks">
        <ul class="navbar-nav ms-auto align-items-center me-3">
          <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="Team.php">Teams</a></li>
          <li class="nav-item"><a class="nav-link" href="Match.php">Match</a></li>
          <li class="nav-item"><a class="nav-link" href="SignUp.php">Login</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- FORM -->
  <div class="form-container">
    <div class="container-box" id="form-box">
      <h2 id="form-title"><?php echo $form_type === 'signup' ? 'Sign Up' : 'Login'; ?></h2>
      
      <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert" style="font-size: 14px; padding: 10px; margin-bottom: 15px;">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>
      
      <?php if ($success_message): ?>
        <div class="alert alert-success" role="alert" style="font-size: 14px; padding: 10px; margin-bottom: 15px;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="SignUp.php" id="auth-form">
        <input type="hidden" name="action" value="<?php echo $form_type; ?>">
        
        <?php if ($form_type === 'signup'): ?>
          <!-- Sign Up Form -->
          <div class="input-group">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="Enter your name" 
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                   required>
          </div>
        <?php endif; ?>
        
        <div class="input-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="Enter your email" 
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                 required>
        </div>
        
        <div class="input-group">
          <label>Password</label>
          <input type="password" name="password" 
                 placeholder="<?php echo $form_type === 'signup' ? 'Create a password' : 'Enter your password'; ?>" 
                 required>
          <?php if ($form_type === 'signup'): ?>
            <small style="color: #9ca3af; display: block; margin-top: 4px; font-size: 12px;">
              Must be 8+ characters with letters & numbers
            </small>
          <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary">
          <?php echo $form_type === 'signup' ? 'Sign Up' : 'Login'; ?>
        </button>
        
        <button type="button" class="btn btn-secondary" onclick="alert('Google Sign-in not implemented yet')">
          Sign <?php echo $form_type === 'signup' ? 'up' : 'in'; ?> with Google
        </button>
      </form>
      
      <p class="switch" id="switch-text">
        <?php if ($form_type === 'signup'): ?>
          Already have an account? <a href="SignUp.php?mode=login">Login</a>
        <?php else: ?>
          Don't have an account? <a href="SignUp.php?mode=signup">Sign Up</a>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Client-side validation for better UX (server-side is primary)
  document.getElementById('auth-form').addEventListener('submit', function(e) {
    const email = document.querySelector('input[name="email"]');
    const password = document.querySelector('input[name="password"]');
    const formType = document.querySelector('input[name="action"]').value;
    
    let isValid = true;
    
    // Email validation
    const emailPattern = /^[^\s@]+@gmail\.com$/;
    if (!emailPattern.test(email.value)) {
      alert('Please enter a valid Gmail address');
      email.focus();
      e.preventDefault();
      return;
    }
    
    // Password validation
    if (formType === 'signup') {
      const pwdPattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
      if (!pwdPattern.test(password.value)) {
        alert('Password must be 8+ characters with letters & numbers');
        password.focus();
        e.preventDefault();
        return;
      }
    } else {
      if (password.value.length < 6) {
        alert('Password must be at least 6 characters');
        password.focus();
        e.preventDefault();
        return;
      }
    }
    
    // Name validation for signup
    if (formType === 'signup') {
      const name = document.querySelector('input[name="name"]');
      if (!name.value.trim()) {
        alert('Please enter your name');
        name.focus();
        e.preventDefault();
        return;
      }
    }
  });

  // Sync with localStorage for backward compatibility
  document.addEventListener('DOMContentLoaded', function() {
    <?php if ($is_logged_in): ?>
      // Update localStorage when logged in via PHP
      localStorage.setItem('isLoggedIn', 'true');
      localStorage.setItem('userName', <?php echo json_encode($_SESSION['user_name']); ?>);
      localStorage.setItem('userEmail', <?php echo json_encode($_SESSION['user_email']); ?>);
    <?php endif; ?>
  });
  </script>

  <style>
  /* Additional styles for alerts */
  .alert {
    border-radius: 10px;
    border: none;
  }
  .alert-danger {
    background: rgba(248, 113, 113, 0.1);
    color: #f87171;
    border-left: 3px solid #f87171;
  }
  .alert-success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border-left: 3px solid #22c55e;
  }
  
  /* Loading state */
  .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  </style>

</body>
</html>