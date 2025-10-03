document.addEventListener("DOMContentLoaded", () => {
  // File System Access API support check
  let dataFolderHandle = null;
  const supportsFileSystem = 'showDirectoryPicker' in window;

  // Navbar
  function updateNavbar() {
    const navbar = document.querySelector(".navbar-nav");
    const signupBtnContainer = document.querySelector(".d-flex");
    if (!navbar) return;

    if (localStorage.getItem("isLoggedIn") === "true") {
      navbar.innerHTML = `
        <li class="nav-item"><a class="nav-link" href="/index.html">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="Team.html">Teams</a></li>
        <li class="nav-item"><a class="nav-link" href="./Pages/Dash.html">Dashboard</a></li>
      `;
      if (signupBtnContainer) {
        signupBtnContainer.innerHTML = `<a class="btn btn-sm btn-secondary logout-btn" href="#" style="min-width:110px">Logout</a>`;
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
        <li class="nav-item"><a class="nav-link" href="./Pages/Team.html">Teams</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Register</a></li>
        <li class="nav-item"><a class="nav-link" href="./Pages/SignUp.html">Login</a></li>
      `;
      if (signupBtnContainer) {
        signupBtnContainer.innerHTML = `<a class="btn btn-sm btn-primary-acc" href="./Pages/SignUp.html" style="min-width:110px">Sign Up</a>`;
      }
    }
  }
  updateNavbar();

  // ---------------- Teams -----------------
  const addTeamBtn = document.getElementById("addTeamBtn");
  const teamForm = document.getElementById("teamForm");
  const teamContainer = document.getElementById("teamContainer");

  // Setup data folder access
  async function setupDataFolder() {
    if (!supportsFileSystem) {
      showMessage("Your browser doesn't support direct file saving. Using localStorage + download instead.", "info");
      return false;
    }

    try {
      const folderButton = document.getElementById("selectFolderBtn");
      if (folderButton) {
        folderButton.addEventListener("click", async () => {
          try {
            dataFolderHandle = await window.showDirectoryPicker();
            updateFolderStatus(dataFolderHandle.name);
            showMessage(`Connected to folder: ${dataFolderHandle.name}`, "success");
          } catch (error) {
            if (error.name !== 'AbortError') {
              showMessage("Failed to select folder", "error");
            }
          }
        });
      }
      return true;
    } catch (error) {
      console.error("File system setup error:", error);
      return false;
    }
  }

  // Update folder connection status
  function updateFolderStatus(folderName) {
    const statusElement = document.getElementById("folderStatus");
    if (statusElement) {
      statusElement.innerHTML = `
        <i class="fas fa-folder-open text-success me-1"></i>
        Connected to: <strong>${folderName}</strong>
      `;
      statusElement.className = "text-success small";
    }
  }

  // Save team to file
  async function saveTeamToFile(team) {
    if (!dataFolderHandle) return false;

    try {
      // Save individual team file
      const teamFileName = `team_${team.id}_${team.name.replace(/[^a-zA-Z0-9]/g, '_')}.json`;
      const teamFileHandle = await dataFolderHandle.getFileHandle(teamFileName, { create: true });
      const teamWritable = await teamFileHandle.createWritable();
      await teamWritable.write(JSON.stringify(team, null, 2));
      await teamWritable.close();

      // Update master teams file
      const teams = getTeamsFromStorage();
      const teamsFileHandle = await dataFolderHandle.getFileHandle('all_teams.json', { create: true });
      const teamsWritable = await teamsFileHandle.createWritable();
      await teamsWritable.write(JSON.stringify(teams, null, 2));
      await teamsWritable.close();

      return true;
    } catch (error) {
      console.error("Error saving to file:", error);
      return false;
    }
  }

  // Delete team file
  async function deleteTeamFile(teamId, teamName) {
    if (!dataFolderHandle) return;

    try {
      const teamFileName = `team_${teamId}_${teamName.replace(/[^a-zA-Z0-9]/g, '_')}.json`;
      await dataFolderHandle.removeEntry(teamFileName);
      
      // Update master teams file
      const teams = getTeamsFromStorage();
      const teamsFileHandle = await dataFolderHandle.getFileHandle('all_teams.json', { create: true });
      const teamsWritable = await teamsFileHandle.createWritable();
      await teamsWritable.write(JSON.stringify(teams, null, 2));
      await teamsWritable.close();
    } catch (error) {
      console.error("Error deleting file:", error);
    }
  }

  // Show modal
  addTeamBtn.addEventListener("click", () => {
    const modal = new bootstrap.Modal(document.getElementById("addTeamModal"));
    modal.show();
  });

  // Handle form submit
  teamForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const formData = new FormData(teamForm);
    const logoFile = formData.get("logo");

    if (!logoFile || logoFile.size === 0) {
      showMessage("Please select a team logo", "error");
      return;
    }

    const reader = new FileReader();
    reader.onload = async function(event) {
      const newTeam = {
        id: Date.now(),
        name: formData.get("name").trim(),
        captain: formData.get("captain").trim(),
        description: formData.get("description").trim(),
        logo: event.target.result, // base64 image
        createdAt: new Date().toISOString(),
        createdDate: new Date().toLocaleDateString()
      };

      // Validate team name is unique
      const teams = getTeamsFromStorage();
      const existingTeam = teams.find(team => team.name.toLowerCase() === newTeam.name.toLowerCase());
      if (existingTeam) {
        showMessage(`Team "${newTeam.name}" already exists!`, "error");
        return;
      }

      // Save to localStorage
      teams.push(newTeam);
      saveTeamsToStorage(teams);

      // Try to save to file system
      const fileSaved = await saveTeamToFile(newTeam);
      
      renderTeam(newTeam);
      
      if (fileSaved) {
        showMessage(`Team "${newTeam.name}" saved to localStorage and data folder!`, "success");
      } else if (dataFolderHandle) {
        showMessage(`Team "${newTeam.name}" saved to localStorage (file save failed)`, "warning");
      } else {
        showMessage(`Team "${newTeam.name}" saved to localStorage (connect data folder to save files)`, "info");
      }

      // Reset form and close modal
      teamForm.reset();
      bootstrap.Modal.getInstance(document.getElementById("addTeamModal")).hide();
      updateTeamCount();
    };

    reader.readAsDataURL(logoFile);
  });

  // Helper functions for localStorage
  function getTeamsFromStorage() {
    try {
      const teams = localStorage.getItem("teams");
      return teams ? JSON.parse(teams) : [];
    } catch (error) {
      console.error("Error reading teams from localStorage:", error);
      return [];
    }
  }

  function saveTeamsToStorage(teams) {
    try {
      localStorage.setItem("teams", JSON.stringify(teams));
    } catch (error) {
      console.error("Error saving teams to localStorage:", error);
      showMessage("Error saving team data", "error");
    }
  }

  // Load teams from localStorage
  function loadTeams() {
    const teams = getTeamsFromStorage();
    teams.forEach(renderTeam);
    updateTeamCount();
    
    if (teams.length === 0) {
      showEmptyState();
    }
  }

  // Show empty state when no teams
  function showEmptyState() {
    const emptyDiv = document.createElement("div");
    emptyDiv.className = "col-12 text-center";
    emptyDiv.innerHTML = `
      <div class="py-5">
        <h4 style="color: var(--muted); margin-bottom: 1rem;">No teams added yet</h4>
        <p style="color: var(--muted);">Click the + button to add your first team</p>
      </div>
    `;
    teamContainer.appendChild(emptyDiv);
  }

  // Update team count
  function updateTeamCount() {
    const teams = getTeamsFromStorage();
    const countElement = document.getElementById("teamCount");
    if (countElement) {
      countElement.textContent = `${teams.length} Team${teams.length !== 1 ? 's' : ''}`;
    }
  }

  // Delete team function
  async function deleteTeam(teamId) {
    const teams = getTeamsFromStorage();
    const teamIndex = teams.findIndex(team => team.id === teamId);
    
    if (teamIndex > -1) {
      const team = teams[teamIndex];
      const teamName = team.name;
      
      teams.splice(teamIndex, 1);
      saveTeamsToStorage(teams);
      
      // Delete from file system
      await deleteTeamFile(teamId, teamName);
      
      // Remove from DOM
      const teamCard = document.querySelector(`[data-team-id="${teamId}"]`);
      if (teamCard) {
        teamCard.closest('.col-md-6').remove();
      }
      
      showMessage(`Team "${teamName}" deleted from localStorage and files`, "success");
      updateTeamCount();
      
      // Show empty state if no teams left
      if (teams.length === 0) {
        teamContainer.innerHTML = '';
        showEmptyState();
      }
    }
  }

  // Function to view team (navigate to TeamForm.html)
  function viewTeam(teamId) {
    window.location.href = `TeamForm.html?teamId=${teamId}`;
  }

  // Render a team card
  function renderTeam(team) {
    const col = document.createElement("div");
    col.className = "col-md-6 col-lg-4 mb-4";
    col.innerHTML = `
      <div class="card h-100 d-flex flex-row align-items-center position-relative" data-team-id="${team.id}">
        <img src="${team.logo}" class="team-logo me-3" alt="Team Logo" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22><rect width=%2260%22 height=%2260%22 fill=%22%23fcb852%22/><text x=%2230%22 y=%2235%22 text-anchor=%22middle%22 fill=%22%23000%22 font-size=%2220%22>T</text></svg>'">
        <div class="card-body p-2" style="color:#c5c6c7;">
          <h5 class="card-title" style="color: var(--accent); margin-bottom: 0.25rem;">${team.name}</h5>
          <p class="card-text" style="color:#c5c6c7; margin-bottom: 0.25rem;">Captain: ${team.captain}</p>
          <p class="card-text" style="color:#c5c6c7; font-size: 0.9rem;">${team.description}</p>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-primary-acc" onclick="viewTeam(${team.id})">View Team</button>
            <button class="btn btn-sm btn-outline-danger delete-btn" onclick="confirmDelete(${team.id})">
              <i class="fas fa-trash"></i>
            </button>
          </div>
          ${team.createdDate ? `<small class="text-muted" style="font-size: 0.75rem;">Added: ${team.createdDate}</small>` : ''}
        </div>
        <button class="position-absolute top-0 end-0 btn btn-sm text-muted p-1" style="background: none; border: none;" onclick="confirmDelete(${team.id})">
          ×
        </button>
      </div>
    `;
    teamContainer.appendChild(col);
  }

  // Make viewTeam function globally accessible
  window.viewTeam = viewTeam;

  // Confirm delete function (global scope)
  window.confirmDelete = function(teamId) {
    const teams = getTeamsFromStorage();
    const team = teams.find(t => t.id === teamId);
    if (team && confirm(`Are you sure you want to delete "${team.name}"?\n\nThis action cannot be undone.`)) {
      deleteTeam(teamId);
    }
  };

  // Export teams function
  window.exportTeams = function() {
    const teams = getTeamsFromStorage();
    const dataStr = JSON.stringify(teams, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `cricket_teams_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    showMessage('Teams data exported successfully!', 'success');
  };

  // Clear all teams
  window.clearAllTeams = async function() {
    if (confirm('Are you sure you want to delete ALL teams?\n\nThis action cannot be undone!')) {
      if (confirm('This will permanently delete all team data. Are you absolutely sure?')) {
        localStorage.removeItem('teams');
        
        // Clear files if connected
        if (dataFolderHandle) {
          try {
            // This would require iterating through files - simplified for demo
            showMessage('Teams cleared from localStorage. Manually delete files from data folder if needed.', 'warning');
          } catch (error) {
            console.error("Error clearing files:", error);
          }
        }
        
        teamContainer.innerHTML = '';
        showEmptyState();
        updateTeamCount();
        showMessage('All teams deleted successfully', 'success');
      }
    }
  };

  // Show message to user
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

  // Initialize
  setupDataFolder();
  loadTeams();
});