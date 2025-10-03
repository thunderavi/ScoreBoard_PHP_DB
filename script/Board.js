document.addEventListener("DOMContentLoaded", () => {
  let matchData = null;
  let currentInnings = 1;
  let currentPlayer = null;
  let currentPlayerStats = {
    runs: 0,
    balls: 0,
    fours: 0,
    sixes: 0
  };
  let teamStats = {
    runs: 0,
    wickets: 0,
    balls: 0,
    fours: 0,
    sixes: 0
  };
  let playersData = [];
  let completedPlayers = [];
  let currentBattingTeam = null; // Add this to track current batting team

  // DOM Elements
  const playerSelectionSection = document.getElementById("playerSelectionSection");
  const scoreboardSection = document.getElementById("scoreboardSection");
  const matchSummarySection = document.getElementById("matchSummarySection");
  const playerSelect = document.getElementById("playerSelect");
  const confirmPlayerBtn = document.getElementById("confirmPlayerBtn");

  // Initialize
  updateNavbar();
  loadMatchData();

  // Event Listeners
  playerSelect.addEventListener("change", handlePlayerSelection);
  confirmPlayerBtn.addEventListener("click", confirmPlayer);
  
  // Score buttons
  document.querySelectorAll('.score-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const runs = parseInt(e.target.dataset.runs);
      scoreRuns(runs);
    });
  });

  // Special buttons
  document.getElementById('wideBtn').addEventListener('click', () => scoreWide());
  document.getElementById('noBallBtn').addEventListener('click', () => scoreNoBall());
  document.getElementById('byeBtn').addEventListener('click', () => scoreBye());
  document.getElementById('outBtn').addEventListener('click', () => playerOut());
  document.getElementById('endInningsBtn').addEventListener('click', () => endInnings());
  document.getElementById('confirmOutBtn').addEventListener('click', () => confirmPlayerOut());
  document.getElementById('exportMatchBtn').addEventListener('click', () => exportMatchData());

  // Functions
  function updateNavbar() {
    const navbar = document.querySelector(".navbar-nav");
    const signupBtnContainer = document.querySelector(".d-flex");
    if (!navbar) return;

    if (localStorage.getItem("isLoggedIn") === "true") {
      navbar.innerHTML = `
        <li class="nav-item"><a class="nav-link" href="/index.html">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="../Pages/Team.html">Teams</a></li>
        <li class="nav-item"><a class="nav-link" href="../Pages/Match.html">Match</a></li>
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
        <li class="nav-item"><a class="nav-link" href="../Pages/Team.html">Teams</a></li>
        <li class="nav-item"><a class="nav-link" href="./Pages/Match.html">Match</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Register</a></li>
        <li class="nav-item"><a class="nav-link" href="./Pages/SignUp.html">Login</a></li>
      `;
      if (signupBtnContainer) {
        signupBtnContainer.innerHTML = `<a class="btn btn-sm btn-primary-acc" href="./Pages/SignUp.html" style="min-width:110px">Sign Up</a>`;
      }
    }
  }

  function loadMatchData() {
    try {
      const matchDataStr = localStorage.getItem('currentMatch');
      if (!matchDataStr) {
        showMessage("No match data found. Please start a new match.", "error");
        setTimeout(() => {
          window.location.href = "Match.html";
        }, 3000);
        return;
      }

      matchData = JSON.parse(matchDataStr);
      console.log("Loaded match data:", matchData); // Debug log
      
      // Validate match data structure
      if (!matchData.team1 || !matchData.team2 || !matchData.battingFirst) {
        showMessage("Invalid match data. Please start a new match.", "error");
        setTimeout(() => {
          window.location.href = "Match.html";
        }, 3000);
        return;
      }
      
      playersData = getPlayersFromStorage();
      console.log("Loaded players data:", playersData); // Debug log
      
      // Initialize first innings
      initializeInnings();
      
    } catch (error) {
      console.error("Error loading match data:", error);
      showMessage("Error loading match data. Please start a new match.", "error");
      setTimeout(() => {
        window.location.href = "Match.html";
      }, 3000);
    }
  }

  function initializeInnings() {
    // Determine batting team for current innings
    currentBattingTeam = currentInnings === 1 ? matchData.battingFirst : 
                        (matchData.battingFirst.id === matchData.team1.id ? matchData.team2 : matchData.team1);
    
    console.log(`Innings ${currentInnings}: ${currentBattingTeam.name} is batting`); // Debug log
    
    // Update match info bar
    document.getElementById('currentTeamLogo').src = currentBattingTeam.logo;
    document.getElementById('currentTeamName').textContent = currentBattingTeam.name;
    document.getElementById('matchPhase').textContent = `${currentInnings === 1 ? '1st' : '2nd'} Innings`;
    
    // Update batting team display
    document.getElementById('battingTeamLogo').src = currentBattingTeam.logo;
    document.getElementById('battingTeamName').textContent = currentBattingTeam.name;
    
    // Reset team stats for new innings
    teamStats = {
      runs: 0,
      wickets: 0,
      balls: 0,
      fours: 0,
      sixes: 0
    };
    
    // Reset completed players for new innings
    completedPlayers = [];
    
    // Load players for current batting team
    loadCurrentTeamPlayers();
    updateDisplays();
  }

  function loadCurrentTeamPlayers() {
    // Make sure we're using the correct batting team for current innings
    const battingTeam = currentBattingTeam;
    
    console.log(`Loading players for team: ${battingTeam.name} (ID: ${battingTeam.id})`); // Debug log
    
    // Filter players for the current batting team only
    const teamPlayers = playersData.filter(p => p.teamId === battingTeam.id);
    console.log(`Found ${teamPlayers.length} players for this team`); // Debug log
    
    // Clear and populate player selection dropdown
    playerSelect.innerHTML = '<option value="">Choose player...</option>';
    
    teamPlayers.forEach(player => {
      // Only show players who haven't completed their innings yet
      if (!completedPlayers.find(cp => cp.id === player.id)) {
        const option = document.createElement('option');
        option.value = player.id;
        option.textContent = `${player.playerName} (${player.position})`;
        playerSelect.appendChild(option);
        console.log(`Added player: ${player.playerName}`); // Debug log
      }
    });

    if (teamPlayers.length === 0) {
      showMessage("No players found for this team. Please add players first.", "warning");
    }
    
    // Reset player selection
    confirmPlayerBtn.disabled = true;
  }

  function handlePlayerSelection() {
    const playerId = playerSelect.value;
    confirmPlayerBtn.disabled = !playerId;
  }

  function confirmPlayer() {
    const playerId = parseInt(playerSelect.value);
    const player = playersData.find(p => p.id === playerId);
    
    if (!player) {
      showMessage("Player not found", "error");
      return;
    }

    // Verify player belongs to current batting team
    if (player.teamId !== currentBattingTeam.id) {
      showMessage("Selected player does not belong to the current batting team", "error");
      return;
    }

    currentPlayer = player;
    currentPlayerStats = {
      runs: 0,
      balls: 0,
      fours: 0,
      sixes: 0
    };

    console.log(`Selected player: ${currentPlayer.playerName} from team: ${currentBattingTeam.name}`); // Debug log

    // Hide player selection and show scoreboard
    playerSelectionSection.style.display = "none";
    scoreboardSection.style.display = "block";
    scoreboardSection.classList.add("fade-in");

    // Update player display
    updateCurrentPlayerDisplay();
    updateDisplays();
  }

  function updateCurrentPlayerDisplay() {
    if (!currentPlayer) return;

    document.getElementById('currentPlayerPhoto').src = currentPlayer.photo;
    document.getElementById('currentPlayerName').textContent = currentPlayer.playerName;
    document.getElementById('currentPlayerPosition').textContent = currentPlayer.position;
  }

  function scoreRuns(runs) {
    if (!currentPlayer) return;

    // Update player stats
    currentPlayerStats.runs += runs;
    currentPlayerStats.balls += 1;
    
    if (runs === 4) currentPlayerStats.fours += 1;
    if (runs === 6) currentPlayerStats.sixes += 1;

    // Update team stats
    teamStats.runs += runs;
    teamStats.balls += 1;
    
    if (runs === 4) teamStats.fours += 1;
    if (runs === 6) teamStats.sixes += 1;

    // Visual feedback
    flashScore(runs);
    
    updateDisplays();
  }

  function scoreWide() {
    teamStats.runs += 1;
    // Wide doesn't count as a ball for the batsman
    updateDisplays();
    showMessage("Wide! +1 run", "info");
  }

  function scoreNoBall() {
    teamStats.runs += 1;
    // No ball doesn't count as a legal ball
    updateDisplays();
    showMessage("No Ball! +1 run", "info");
  }

  function scoreBye() {
    const runs = prompt("How many bye runs?");
    if (runs && !isNaN(runs)) {
      const byeRuns = parseInt(runs);
      teamStats.runs += byeRuns;
      teamStats.balls += 1;
      updateDisplays();
      showMessage(`Bye! +${byeRuns} runs`, "info");
    }
  }

  function playerOut() {
    if (!currentPlayer) return;

    // Show confirmation modal
    document.getElementById('outPlayerName').textContent = currentPlayer.playerName;
    document.getElementById('outPlayerRuns').textContent = currentPlayerStats.runs;
    document.getElementById('outPlayerBalls').textContent = currentPlayerStats.balls;
    document.getElementById('outPlayerFours').textContent = currentPlayerStats.fours;
    document.getElementById('outPlayerSixes').textContent = currentPlayerStats.sixes;

    const modal = new bootstrap.Modal(document.getElementById('outModal'));
    modal.show();
  }

  function confirmPlayerOut() {
    if (!currentPlayer) return;

    // Add to completed players with team information
    completedPlayers.push({
      ...currentPlayer,
      stats: { ...currentPlayerStats },
      teamId: currentPlayer.teamId // Ensure team ID is preserved
    });

    console.log(`Player ${currentPlayer.playerName} is out. Completed players:`, completedPlayers.length); // Debug log

    // Update team wickets
    teamStats.wickets += 1;
    teamStats.balls += 1; // Out counts as a ball

    updateDisplays();
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('outModal')).hide();

    // Check if innings should end
    checkInningsEnd();
  }

  function checkInningsEnd() {
    // Get players from current batting team only
    const teamPlayers = playersData.filter(p => p.teamId === currentBattingTeam.id);
    const remainingPlayers = teamPlayers.length - completedPlayers.length;

    console.log(`Team players: ${teamPlayers.length}, Completed: ${completedPlayers.length}, Remaining: ${remainingPlayers}, Wickets: ${teamStats.wickets}`); // Debug log

    // Check if all out or target achieved (for 2nd innings)
    if (teamStats.wickets >= 10 || remainingPlayers <= 1) {
      console.log("Innings ending - all out or only 1 player remaining");
      endInnings();
      return;
    }

    // For 2nd innings, check if target is achieved
    if (currentInnings === 2 && matchData.team1Score && teamStats.runs > matchData.team1Score.runs) {
      console.log("Innings ending - target achieved");
      endInnings();
      return;
    }

    // Continue with next player from the SAME team
    currentPlayer = null;
    currentPlayerStats = {
      runs: 0,
      balls: 0,
      fours: 0,
      sixes: 0
    };

    console.log("Moving to next player from the same team");

    // Show player selection again for the same team
    scoreboardSection.style.display = "none";
    playerSelectionSection.style.display = "block";
    loadCurrentTeamPlayers(); // This will load remaining players from current batting team
  }

  function endInnings() {
    console.log(`Ending innings ${currentInnings}`);
    
    // Save current innings data
    if (currentInnings === 1) {
      // Determine which team score to save based on batting team
      if (currentBattingTeam.id === matchData.team1.id) {
        matchData.team1Score = {
          ...teamStats,
          players: [...completedPlayers]
        };
      } else {
        matchData.team2Score = {
          ...teamStats,
          players: [...completedPlayers]
        };
      }
      
      // Start 2nd innings
      currentInnings = 2;
      showMessage("1st Innings Complete! Starting 2nd Innings...", "success");
      
      setTimeout(() => {
        initializeInnings(); // This will set up the second batting team
        scoreboardSection.style.display = "none";
        playerSelectionSection.style.display = "block";
      }, 2000);
      
    } else {
      // Match complete - save second innings data
      if (currentBattingTeam.id === matchData.team1.id) {
        matchData.team1Score = {
          ...teamStats,
          players: [...completedPlayers]
        };
      } else {
        matchData.team2Score = {
          ...teamStats,
          players: [...completedPlayers]
        };
      }
      
      completeMatch();
    }
  }

  function completeMatch() {
    // Calculate match result
    const team1Score = matchData.team1Score;
    const team2Score = matchData.team2Score;
    
    let result = "";
    let winner = null;
    
    if (team1Score.runs > team2Score.runs) {
      winner = matchData.team1;
      const margin = team1Score.runs - team2Score.runs;
      result = `${winner.name} wins by ${margin} runs`;
    } else if (team2Score.runs > team1Score.runs) {
      winner = matchData.team2;
      const wicketsLeft = 10 - team2Score.wickets;
      result = `${winner.name} wins by ${wicketsLeft} wickets`;
    } else {
      result = "Match Tied!";
    }

    matchData.result = result;
    matchData.winner = winner;
    matchData.completedAt = new Date().toISOString();

    // Show match summary
    showMatchSummary();
  }

  function showMatchSummary() {
    scoreboardSection.style.display = "none";
    matchSummarySection.style.display = "block";
    matchSummarySection.classList.add("fade-in");

    // Update summary display
    const team1 = matchData.team1;
    const team2 = matchData.team2;
    const team1Score = matchData.team1Score;
    const team2Score = matchData.team2Score;

    document.getElementById('team1FinalName').textContent = team1.name;
    document.getElementById('team2FinalName').textContent = team2.name;
    
    document.getElementById('team1FinalScore').textContent = 
      `${team1Score.runs}/${team1Score.wickets} (${(team1Score.balls / 6).toFixed(1)} overs)`;
    
    document.getElementById('team2FinalScore').textContent = 
      `${team2Score.runs}/${team2Score.wickets} (${(team2Score.balls / 6).toFixed(1)} overs)`;

    document.getElementById('matchResultText').textContent = matchData.result;
    
    if (matchData.winner) {
      document.getElementById('matchResultDetails').textContent = 
        `Congratulations to ${matchData.winner.name} on their victory!`;
    } else {
      document.getElementById('matchResultDetails').textContent = 
        "An exciting match that ended in a tie!";
    }
  }

  function updateDisplays() {
    // Update team score display
    document.getElementById('teamScore').textContent = teamStats.runs;
    document.getElementById('teamWickets').textContent = teamStats.wickets;
    document.getElementById('teamOvers').textContent = (teamStats.balls / 6).toFixed(1);
    document.getElementById('quickScore').textContent = 
      `${teamStats.runs}/${teamStats.wickets} (${(teamStats.balls / 6).toFixed(1)} overs)`;

    // Update stats
    document.getElementById('totalFours').textContent = teamStats.fours;
    document.getElementById('totalSixes').textContent = teamStats.sixes;
    document.getElementById('ballsFaced').textContent = teamStats.balls;
    
    const runRate = teamStats.balls > 0 ? (teamStats.runs / teamStats.balls * 6).toFixed(2) : "0.00";
    document.getElementById('runRate').textContent = runRate;

    // Update player stats if current player exists
    if (currentPlayer) {
      document.getElementById('playerRuns').textContent = currentPlayerStats.runs;
      document.getElementById('playerBalls').textContent = currentPlayerStats.balls;
      document.getElementById('playerFours').textContent = currentPlayerStats.fours;
      document.getElementById('playerSixes').textContent = currentPlayerStats.sixes;
      
      const strikeRate = currentPlayerStats.balls > 0 ? 
        (currentPlayerStats.runs / currentPlayerStats.balls * 100).toFixed(2) : "0.00";
      document.getElementById('playerStrikeRate').textContent = strikeRate;
    }
  }

  function flashScore(runs) {
    const scoreElement = document.getElementById('teamScore');
    scoreElement.classList.add('score-update');
    
    setTimeout(() => {
      scoreElement.classList.remove('score-update');
    }, 600);

    // Show run scored message
    if (runs === 4) {
      showMessage("Boundary! +4 runs", "success");
    } else if (runs === 6) {
      showMessage("Six! +6 runs", "success");
    } else if (runs > 0) {
      showMessage(`+${runs} run${runs > 1 ? 's' : ''}`, "info");
    }
  }

  function exportMatchData() {
    const exportData = {
      matchInfo: {
        date: new Date().toLocaleDateString(),
        time: new Date().toLocaleTimeString(),
        venue: "Local Ground"
      },
      teams: {
        team1: matchData.team1,
        team2: matchData.team2
      },
      toss: {
        winner: matchData.tossWinner,
        decision: matchData.tossChoice,
        coinResult: matchData.coinResult
      },
      innings: {
        first: {
          battingTeam: matchData.battingFirst,
          score: matchData.team1Score
        },
        second: {
          battingTeam: matchData.battingFirst.id === matchData.team1.id ? matchData.team2 : matchData.team1,
          score: matchData.team2Score
        }
      },
      result: {
        winner: matchData.winner,
        resultText: matchData.result,
        margin: calculateMargin()
      },
      completedAt: matchData.completedAt
    };

    const dataStr = JSON.stringify(exportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `cricket_match_${matchData.team1.name}_vs_${matchData.team2.name}_${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
    
    showMessage('Match data exported successfully!', 'success');
  }

  function calculateMargin() {
    if (!matchData.team1Score || !matchData.team2Score) return null;
    
    const team1Runs = matchData.team1Score.runs;
    const team2Runs = matchData.team2Score.runs;
    
    if (team1Runs > team2Runs) {
      return `${team1Runs - team2Runs} runs`;
    } else if (team2Runs > team1Runs) {
      return `${10 - matchData.team2Score.wickets} wickets`;
    } else {
      return "Tie";
    }
  }

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