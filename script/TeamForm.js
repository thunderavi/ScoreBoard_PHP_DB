document.addEventListener("DOMContentLoaded", () => {
  let currentTeam = null;
  let editingPlayerId = null;

  // DOM Elements
  const teamSelect = document.getElementById("teamSelect");
  const teamSelection = document.getElementById("teamSelection");
  const playersSection = document.getElementById("playersSection");
  const teamLogo = document.getElementById("teamLogo");
  const teamName = document.getElementById("teamName");
  const teamDescription = document.getElementById("teamDescription");
  const addPlayerBtn = document.getElementById("addPlayerBtn");
  const playerForm = document.getElementById("playerForm");
  const playersTableBody = document.getElementById("playersTableBody");
  const emptyState = document.getElementById("emptyState");
  const playerCountBadge = document.getElementById("playerCount");
  const modalTitle = document.getElementById("modalTitle");
  const submitBtnText = document.getElementById("submitBtnText");

  // Initialize navbar
  updateNavbar();

  // Initialize teams dropdown
  loadTeamsDropdown();

  // Team selection handler
  teamSelect.addEventListener("change", (e) => {
    const teamId = e.target.value;
    if (teamId) {
      selectTeam(parseInt(teamId));
    } else {
      hidePlayersSection();
    }
  });

  // Add player button handler
  addPlayerBtn.addEventListener("click", () => {
    openPlayerModal();
  });

  // Player form submit handler
  playerForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    await savePlayer();
  });

  // File input preview handler
  const photoInput = document.querySelector('input[name="playerPhoto"]');
  const photoPreview = document.getElementById("photoPreview");
  const previewImage = document.getElementById("previewImage");

  photoInput.addEventListener("change", (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (event) => {
        previewImage.src = event.target.result;
        photoPreview.style.display = "block";
      };
      reader.readAsDataURL(file);
    } else {
      photoPreview.style.display = "none";
    }
  });

  // Functions
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

  function loadTeamsDropdown() {
    try {
      const teams = JSON.parse(localStorage.getItem("teams")) || [];
      teamSelect.innerHTML = '<option value="">Choose a team...</option>';
      
      teams.forEach(team => {
        const option = document.createElement("option");
        option.value = team.id;
        option.textContent = team.name;
        teamSelect.appendChild(option);
      });

      // Check URL parameter for team ID
      const urlParams = new URLSearchParams(window.location.search);
      const teamId = urlParams.get('teamId');
      if (teamId) {
        teamSelect.value = teamId;
        selectTeam(parseInt(teamId));
      }
    } catch (error) {
      console.error("Error loading teams:", error);
      showMessage("Error loading teams", "error");
    }
  }

  function selectTeam(teamId) {
    try {
      const teams = JSON.parse(localStorage.getItem("teams")) || [];
      const team = teams.find(t => t.id === teamId);
      
      if (!team) {
        showMessage("Team not found", "error");
        return;
      }

      currentTeam = team;
      
      // Update team header
      teamLogo.src = team.logo;
      teamLogo.style.display = "block";
      teamName.textContent = team.name;
      teamDescription.textContent = `${team.description} | Captain: ${team.captain}`;
      
      // Show players section
      teamSelection.style.display = "none";
      playersSection.style.display = "block";
      addPlayerBtn.style.display = "block";
      
      // Load players for this team
      loadPlayers();
      
    } catch (error) {
      console.error("Error selecting team:", error);
      showMessage("Error loading team", "error");
    }
  }

  function hidePlayersSection() {
    currentTeam = null;
    teamSelection.style.display = "block";
    playersSection.style.display = "none";
    addPlayerBtn.style.display = "none";
    teamLogo.style.display = "none";
    teamName.textContent = "Select Team";
    teamDescription.textContent = "Team players and management";
  }

  function loadPlayers() {
    if (!currentTeam) return;

    try {
      const players = getPlayersFromStorage();
      const teamPlayers = players.filter(p => p.teamId === currentTeam.id);
      
      playersTableBody.innerHTML = "";
      
      if (teamPlayers.length === 0) {
        emptyState.style.display = "block";
        document.querySelector(".table-responsive").style.display = "none";
      } else {
        emptyState.style.display = "none";
        document.querySelector(".table-responsive").style.display = "block";
        
        teamPlayers.forEach(renderPlayer);
      }
      
      updatePlayerCount(teamPlayers.length);
    } catch (error) {
      console.error("Error loading players:", error);
      showMessage("Error loading players", "error");
    }
  }

  function renderPlayer(player) {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td>
        <img src="${player.photo}" alt="${player.playerName}" class="player-photo" 
             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22 viewBox=%220 0 50 50%22><circle cx=%2225%22 cy=%2225%22 r=%2225%22 fill=%22%23fcb852%22/><text x=%2225%22 y=%2230%22 text-anchor=%22middle%22 fill=%22%23000%22 font-size=%2220%22>${player.playerName.charAt(0)}</text></svg>'">
      </td>
      <td>
        <div class="player-name">${player.playerName}</div>
        ${player.email ? `<small class="text-muted">${player.email}</small>` : ''}
      </td>
      <td>
        <span class="position-badge">${player.position}</span>
      </td>
      <td>
        <span class="contact-text">${player.contact || 'N/A'}</span>
      </td>
      <td>
        <span class="description-text" title="${player.description || ''}">${player.description || 'No description'}</span>
      </td>
      <td>
        <small class="text-muted">${player.createdDate}</small>
      </td>
      <td>
        <button class="btn action-btn btn-edit" onclick="editPlayer(${player.id})" title="Edit Player">
          <i class="fas fa-edit"></i>
        </button>
        <button class="btn action-btn btn-delete" onclick="confirmDeletePlayer(${player.id})" title="Delete Player">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `;
    playersTableBody.appendChild(row);
  }

  function openPlayerModal(player = null) {
    editingPlayerId = player ? player.id : null;
    
    if (player) {
      // Editing existing player
      modalTitle.textContent = "Edit Player";
      submitBtnText.textContent = "Update Player";
      
      // Fill form with player data
      document.querySelector('input[name="playerName"]').value = player.playerName;
      document.querySelector('select[name="position"]').value = player.position;
      document.querySelector('input[name="contact"]').value = player.contact || '';
      document.querySelector('input[name="email"]').value = player.email || '';
      document.querySelector('textarea[name="description"]').value = player.description || '';
      
      // Show photo preview
      previewImage.src = player.photo;
      photoPreview.style.display = "block";
      
      // Make photo input optional for editing
      photoInput.removeAttribute('required');
    } else {
      // Adding new player
      modalTitle.textContent = "Add New Player";
      submitBtnText.textContent = "Save Player";
      
      // Reset form
      playerForm.reset();
      photoPreview.style.display = "none";
      photoInput.setAttribute('required', 'required');
    }
    
    const modal = new bootstrap.Modal(document.getElementById("addPlayerModal"));
    modal.show();
  }

  async function savePlayer() {
    const formData = new FormData(playerForm);
    const photoFile = formData.get("playerPhoto");

    // Validate required fields
    const playerName = formData.get("playerName").trim();
    const position = formData.get("position");

    if (!playerName || !position) {
      showMessage("Please fill in all required fields", "error");
      return;
    }

    // Check if photo is required (new player) or optional (editing)
    if (!editingPlayerId && (!photoFile || photoFile.size === 0)) {
      showMessage("Please select a player photo", "error");
      return;
    }

    const processPlayerData = (photoDataUrl) => {
      const playerData = {
        id: editingPlayerId || Date.now(),
        teamId: currentTeam.id,
        teamName: currentTeam.name,
        playerName: playerName,
        position: position,
        contact: formData.get("contact").trim(),
        email: formData.get("email").trim(),
        description: formData.get("description").trim(),
        photo: photoDataUrl,
        createdAt: new Date().toISOString(),
        createdDate: new Date().toLocaleDateString()
      };

      const players = getPlayersFromStorage();
      
      if (editingPlayerId) {
        // Update existing player
        const playerIndex = players.findIndex(p => p.id === editingPlayerId);
        if (playerIndex > -1) {
          // Keep original creation date for updates
          playerData.createdAt = players[playerIndex].createdAt;
          playerData.createdDate = players[playerIndex].createdDate;
          players[playerIndex] = playerData;
        }
      } else {
        // Check for duplicate player name in the same team
        const existingPlayer = players.find(p => 
          p.teamId === currentTeam.id && 
          p.playerName.toLowerCase() === playerName.toLowerCase()
        );
        
        if (existingPlayer) {
          showMessage(`Player "${playerName}" already exists in this team!`, "error");
          return;
        }
        
        // Add new player
        players.push(playerData);
      }

      // Save to localStorage
      savePlayersToStorage(players);
      
      // Refresh players table
      loadPlayers();
      
      // Show success message
      const action = editingPlayerId ? "updated" : "added";
      showMessage(`Player "${playerName}" ${action} successfully!`, "success");
      
      // Close modal and reset form
      bootstrap.Modal.getInstance(document.getElementById("addPlayerModal")).hide();
      playerForm.reset();
      photoPreview.style.display = "none";
    };

    // Handle photo processing
    if (photoFile && photoFile.size > 0) {
      const reader = new FileReader();
      reader.onload = (event) => {
        processPlayerData(event.target.result);
      };
      reader.readAsDataURL(photoFile);
    } else if (editingPlayerId) {
      // Use existing photo for editing
      const existingPlayers = getPlayersFromStorage();
      const existingPlayer = existingPlayers.find(p => p.id === editingPlayerId);
      processPlayerData(existingPlayer ? existingPlayer.photo : '');
    }
  }

  function updatePlayerCount(count) {
    playerCountBadge.textContent = `${count} Player${count !== 1 ? 's' : ''}`;
  }

  // Global functions for onclick handlers
  window.editPlayer = function(playerId) {
    const players = getPlayersFromStorage();
    const player = players.find(p => p.id === playerId);
    if (player) {
      openPlayerModal(player);
    }
  };

  window.confirmDeletePlayer = function(playerId) {
    const players = getPlayersFromStorage();
    const player = players.find(p => p.id === playerId);
    if (player && confirm(`Are you sure you want to delete "${player.playerName}"?\n\nThis action cannot be undone.`)) {
      deletePlayer(playerId);
    }
  };

  function deletePlayer(playerId) {
    const players = getPlayersFromStorage();
    const playerIndex = players.findIndex(p => p.id === playerId);
    
    if (playerIndex > -1) {
      const playerName = players[playerIndex].playerName;
      players.splice(playerIndex, 1);
      savePlayersToStorage(players);
      loadPlayers();
      showMessage(`Player "${playerName}" deleted successfully`, "success");
    }
  }

  // Export and clear functions
  window.exportPlayers = function() {
    if (!currentTeam) return;
    
    const players = getPlayersFromStorage();
    const teamPlayers = players.filter(p => p.teamId === currentTeam.id);
    const dataStr = JSON.stringify(teamPlayers, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${currentTeam.name}_players_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    showMessage('Players data exported successfully!', 'success');
  };

  window.clearAllPlayers = function() {
    if (!currentTeam) return;
    
    if (confirm(`Are you sure you want to delete ALL players from "${currentTeam.name}"?\n\nThis action cannot be undone!`)) {
      const players = getPlayersFromStorage();
      const filteredPlayers = players.filter(p => p.teamId !== currentTeam.id);
      savePlayersToStorage(filteredPlayers);
      loadPlayers();
      showMessage('All players cleared successfully', 'success');
    }
  };

  // Helper functions
  function getPlayersFromStorage() {
    try {
      const players = localStorage.getItem("teamPlayers");
      return players ? JSON.parse(players) : [];
    } catch (error) {
      console.error("Error reading players from localStorage:", error);
      return [];
    }
  }

  function savePlayersToStorage(players) {
    try {
      localStorage.setItem("teamPlayers", JSON.stringify(players));
    } catch (error) {
      console.error("Error saving players to localStorage:", error);
      showMessage("Error saving player data", "error");
    }
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
});