<?php
session_start();
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Matching Alkitab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
        <link rel="icon" href="logo.png" type="image/png">
    <style>
        :root {
            --primary: #6a11cb;
            --secondary: #2575fc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        .card-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .btn-glow {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-glow::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to bottom right,
                rgba(255, 255, 255, 0),
                rgba(255, 255, 255, 0.3),
                rgba(255, 255, 255, 0)
            );
            transform: rotate(30deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) rotate(30deg); }
            100% { transform: translateX(100%) rotate(30deg); }
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        
        /* Modal styles */
        .modal-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-content {
            background: transparent;
            border: none;
        }
        
        .form-control-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .form-control-glass::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .form-control-glass:focus {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center animate__animated animate__fadeIn">
                <div class="floating mb-4">
                    <h1 class="display-4 fw-bold">🎮 Game Matching Alkitab</h1>
                    <p class="lead">Buat atau ikuti game seru dengan teman-teman</p>
                </div>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger animate__animated animate__shakeX">
                        <?php 
                        switch($_GET['error']) {
                            case 'invalid_code':
                                echo "Kode game tidak valid atau sudah berakhir";
                                break;
                            case 'name_exists':
                                echo "Nama sudah digunakan di game ini";
                                break;
                            default:
                                echo "Terjadi kesalahan";
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-3 col-md-6 mx-auto mt-5">
                    <a href="create_matching.php" class="btn btn-glow btn-lg py-3 animate__animated animate__bounceIn">
                        <i class="fas fa-plus-circle me-2"></i>Buat Game Baru
                    </a>
                    <button id="btn-join-game" class="btn btn-glow btn-lg py-3 animate__animated animate__bounceIn" style="animation-delay: 0.2s">
                        <i class="fas fa-sign-in-alt me-2"></i>Masuk dengan Kode
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Join Game Modal -->
    <div class="modal fade" id="joinModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-glass">
                <div class="modal-header border-0">
                    <h5 class="modal-title">🔑 Masuk ke Game</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="joinForm">
                        <div class="mb-4">
                            <label class="form-label">Kode Game</label>
                            <input type="text" class="form-control form-control-glass text-center fs-2 py-3" 
                                   placeholder="XXXXXX" maxlength="6" id="input-game-code" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Nama Pemain</label>
                            <input type="text" class="form-control form-control-glass" 
                                   placeholder="Nama Anda" id="player-name" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg py-3" id="join-game-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>Gabung Game
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-glass">
                <div class="modal-body text-center p-5">
                    <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>Sedang bergabung ke game...</h5>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // DOM Elements
        const joinModal = new bootstrap.Modal('#joinModal');
        const loadingModal = new bootstrap.Modal('#loadingModal');
        const joinForm = document.getElementById('joinForm');
        const gameCodeInput = document.getElementById('input-game-code');
        const playerNameInput = document.getElementById('player-name');
        const joinBtn = document.getElementById('btn-join-game');
        
        // Show join modal
        joinBtn.addEventListener('click', () => {
            joinModal.show();
        });
        
        // Auto-uppercase and limit to 6 chars for game code
        gameCodeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().substring(0, 6);
        });
        
        // Handle form submission
        joinForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const code = gameCodeInput.value.trim();
            const name = playerNameInput.value.trim();
            
            // Validate inputs
            if (code.length !== 6) {
                alert('Kode game harus 6 karakter!');
                return;
            }
            
            if (!name) {
                alert('Masukkan nama Anda!');
                return;
            }
            
            // Show loading
            joinModal.hide();
            loadingModal.show();
            
            try {
                // Join the game
                const response = await fetch('join_game_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        code: code,
                        name: name
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Store player data in sessionStorage
                    sessionStorage.setItem('playerData', JSON.stringify({
                        playerId: result.player_id,
                        playerName: name,
                        gameCode: code
                    }));
                    
                    // Redirect to waiting room
                    window.location.href = `waiting_room.php?code=${code}`;
                } else {
                    loadingModal.hide();
                    window.location.href = `matching.php?error=${result.error || 'join_failed'}`;
                }
            } catch (error) {
                console.error('Error:', error);
                loadingModal.hide();
                window.location.href = 'matching.php?error=connection_failed';
            }
        });
        
        // Focus on code input when modal shown
        document.getElementById('joinModal').addEventListener('shown.bs.modal', () => {
            gameCodeInput.focus();
        });
    </script>
</body>
</html>