CREATE DATABASE IF NOT EXISTS cricket_scoreboard;
USE cricket_scoreboard;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Teams table
CREATE TABLE teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    captain VARCHAR(50) NOT NULL,
    description VARCHAR(200) NOT NULL,
    logo LONGTEXT NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Players table
CREATE TABLE players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team_id INT NOT NULL,
    player_name VARCHAR(50) NOT NULL,
    position VARCHAR(30) NOT NULL,
    contact VARCHAR(15),
    email VARCHAR(100),
    description VARCHAR(200),
    photo LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

-- Matches table
CREATE TABLE matches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
    toss_winner_id INT NOT NULL,
    coin_result ENUM('heads', 'tails') NOT NULL,
    toss_choice ENUM('batting', 'fielding') NOT NULL,
    batting_first_id INT NOT NULL,
    fielding_first_id INT NOT NULL,
    status ENUM('setup', 'live', 'completed') DEFAULT 'setup',
    result_text VARCHAR(255),
    winner_id INT,
    innings1_data JSON,
    innings2_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (team1_id) REFERENCES teams(id),
    FOREIGN KEY (team2_id) REFERENCES teams(id),
    FOREIGN KEY (toss_winner_id) REFERENCES teams(id),
    FOREIGN KEY (batting_first_id) REFERENCES teams(id),
    FOREIGN KEY (fielding_first_id) REFERENCES teams(id),
    FOREIGN KEY (winner_id) REFERENCES teams(id)
);

-- Match scores tracking (for live scoring)
CREATE TABLE match_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    match_id INT NOT NULL,
    innings INT NOT NULL,
    batting_team_id INT NOT NULL,
    runs INT DEFAULT 0,
    wickets INT DEFAULT 0,
    balls INT DEFAULT 0,
    fours INT DEFAULT 0,
    sixes INT DEFAULT 0,
    completed_players JSON,
    current_player JSON,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (batting_team_id) REFERENCES teams(id)
);