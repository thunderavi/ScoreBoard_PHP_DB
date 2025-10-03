document.addEventListener("DOMContentLoaded", () => {
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

  // Initialize navbar
  updateNavbar();

  // Load teams dropdown
  loadTeamsDropdown();

  // Team selection handlers
  team1Select.addEventListener("change", (e) => {
    handleTeamSelection(e.target.value, 1);
  });

  team2Select.addEventListener("change", (e) => {
    handleTeamSelection(e.target.value, 2);
  });

  // Toss call radio buttons
  document.querySelectorAll('input[name="tossCall"]').forEach(radio => {
    radio.addEventListener("change", updateFlipButtonState);
  });

  // Flip coin button
  flipCoinBtn.addEventListener("click", flipCoin);

  // Next button
  document.getElementById("nextBtn").addEventListener("click", () => {
    if (!matchData) {
      showMessage("Match data not properly saved. Please try the toss again.", "error");
      return;
    }
    
    // Save match data to localStorage before navigating
    localStorage.setItem('currentMatch', JSON.stringify(matchData));
    console.log("Saving match data:", matchData); // Debug log
    
    showMessage("Redirecting to scoreboard...", "success");
    setTimeout(() => {
      window.location.href = "Board.html";
    }, 1000);
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
        <li class="nav-item"><a class="nav-link" href="Match.html">Match</a></li>
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
        <li class="nav-item"><a class="nav-link" href="./Pages/Match.html">Match</a></li>
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
      const teams = getTeamsFromStorage();
      
      team1Select.innerHTML = '<option value="">Choose Team 1...</option>';
      team2Select.innerHTML = '<option value="">Choose Team 2...</option>';
      
      teams.forEach(team => {
        const option1 = document.createElement("option");
        option1.value = team.id;
        option1.textContent = team.name;
        team1Select.appendChild(option1);

        const option2 = document.createElement("option");
        option2.value = team.id;
        option2.textContent = team.name;
        team2Select.appendChild(option2);
      });
    } catch (error) {
      console.error("Error loading teams:", error);
      showMessage("Error loading teams", "error");
    }
  }

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

    const teams = getTeamsFromStorage();
    const team = teams.find(t => t.id === parseInt(teamId));
    
    if (!team) {
      showMessage("Team not found", "error");
      return;
    }

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

    // Get player count for the team
    const players = getPlayersFromStorage();
    const teamPlayers = players.filter(p => p.teamId === team.id);

    if (teamNumber === 1) {
      selectedTeam1 = { ...team, playerCount: teamPlayers.length };
      updateTeamInfo(1, selectedTeam1);
      team1Info.style.display = "block";
      team1Info.classList.add("fade-in");
    } else {
      selectedTeam2 = { ...team, playerCount: teamPlayers.length };
      updateTeamInfo(2, selectedTeam2);
      team2Info.style.display = "block";
      team2Info.classList.add("fade-in");
    }

    updateTossVisibility();
  }

  function updateTeamInfo(teamNumber, team) {
    const logoElement = document.getElementById(`team${teamNumber}Logo`);
    const nameElement = document.getElementById(`team${teamNumber}Name`);
    const captainElement = document.getElementById(`team${teamNumber}Captain`);
    const playersElement = document.getElementById(`team${teamNumber}Players`);

    logoElement.src = team.logo;
    logoElement.alt = `${team.name} Logo`;
    nameElement.textContent = team.name;
    captainElement.textContent = `Captain: ${team.captain}`;
    playersElement.textContent = `Players: ${team.playerCount}`;
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

    // Disable button and show flipping animation
    flipCoinBtn.disabled = true;
    coin.classList.add("flipping");
    
    // Hide previous result
    tossResult.style.display = "none";

    setTimeout(() => {
      // Random coin result
      const coinResult = Math.random() < 0.5 ? 'heads' : 'tails';
      
      // Show coin result
      coin.classList.remove("flipping");
      if (coinResult === 'heads') {
        coin.classList.add("show-heads");
        coin.classList.remove("show-tails");
      } else {
        coin.classList.add("show-tails");
        coin.classList.remove("show-heads");
      }

      // Determine toss winner
      const tossWon = tossCall === coinResult;
      let tossWinner, tossLoser;
      
      if (tossWon) {
        tossWinner = selectedTeam1;
        tossLoser = selectedTeam2;
      } else {
        tossWinner = selectedTeam2;
        tossLoser = selectedTeam1;
      }

      // Determine batting/fielding order based on toss winner's choice
      let battingFirst, fieldingFirst;
      if (tossChoice === 'batting') {
        battingFirst = tossWinner;
        fieldingFirst = tossLoser;
      } else {
        battingFirst = tossLoser;
        fieldingFirst = tossWinner;
      }

      // Update result display
      displayTossResult(coinResult, tossWinner, tossChoice, battingFirst, fieldingFirst);

      // Store match data
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

      // Save to localStorage for next page
      localStorage.setItem('currentMatch', JSON.stringify(matchData));

      // Show next button
      setTimeout(() => {
        nextButtonSection.style.display = "block";
        nextButtonSection.classList.add("slide-in-right");
      }, 500);

    }, 2000); // Match animation duration
  }

  function displayTossResult(coinResult, tossWinner, tossChoice, battingFirst, fieldingFirst) {
    const tossWinnerText = document.getElementById("tossWinnerText");
    const tossDecisionText = document.getElementById("tossDecisionText");
    const battingFirstTeam = document.getElementById("battingFirstTeam");
    const fieldingFirstTeam = document.getElementById("fieldingFirstTeam");

    tossWinnerText.textContent = `${tossWinner.name} Wins Toss!`;
    tossDecisionText.textContent = `Coin: ${coinResult.charAt(0).toUpperCase() + coinResult.slice(1)} | Chooses to ${tossChoice} first`;
    
    battingFirstTeam.textContent = battingFirst.name;
    fieldingFirstTeam.textContent = fieldingFirst.name;

    tossResult.style.display = "block";
    tossResult.classList.add("fade-in");
  }

  // Helper functions
  function getTeamsFromStorage() {
    try {
      const teams = localStorage.getItem("teams");
      return teams ? JSON.parse(teams) : [];
    } catch (error) {
      console.error("Error reading teams from localStorage:", error);
      return [];
    }
  }

  function getPlayersFromStorage() {
    try {
      const players = localStorage.getItem("teamPlayers");
      return players ? JSON.parse(players) : [];
    } catch (error) {
      console.error("Error reading players from localStorage:", error);
      return [];
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