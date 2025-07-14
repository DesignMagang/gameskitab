<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shell Game</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .cup {
            width: 120px;
            height: 120px;
            background-color: #a0a0a0;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            cursor: pointer;
            position: relative;
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden; /* Ensure ball doesn't overflow when hidden */
        }
        .cup.selected {
            border: 4px solid #4CAF50; /* Highlight selected cup */
        }
        .ball {
            width: 30px;
            height: 30px;
            background-color: #FFD700;
            border-radius: 50%;
            position: absolute;
            bottom: 10px;
            display: none; /* Hidden by default */
        }
        .cup-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            position: relative;
            padding-bottom: 20px; /* Space for the ball to appear */
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg text-center">
        <h1 class="text-3xl font-bold mb-6">Tebak di Mana Bolanya!</h1>
        <div id="game-area" class="flex justify-center space-x-8 mb-8">
            <div id="cup-0" class="cup">
                <div class="ball"></div>
            </div>
            <div id="cup-1" class="cup">
                <div class="ball"></div>
            </div>
            <div id="cup-2" class="cup">
                <div class="ball"></div>
            </div>
        </div>
        <button id="start-button" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-full text-lg">Mulai Permainan</button>
        <p id="message" class="mt-4 text-lg font-semibold"></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
    const cups = document.querySelectorAll('.cup');
    const balls = document.querySelectorAll('.ball');
    const startButton = document.getElementById('start-button');
    const messageDisplay = document.getElementById('message');

    let ballPosition = -1; // Index of the cup containing the ball
    let isShuffling = false;
    let isGameOver = false;

    // --- Game Initialization ---
    function initializeGame() {
        // Reset all cups and hide balls
        cups.forEach(cup => {
            cup.classList.remove('selected');
            cup.style.transform = 'translateY(0)'; // Reset cup position
        });
        balls.forEach(ball => ball.style.display = 'none');

        messageDisplay.textContent = 'Klik "Mulai Permainan" untuk bermain!';
        startButton.style.display = 'block';
        isShuffling = false;
        isGameOver = false;
    }

    // --- Place the Ball Randomly ---
    function placeBall() {
        ballPosition = Math.floor(Math.random() * cups.length);
        balls[ballPosition].style.display = 'block';
        messageDisplay.textContent = 'Perhatikan di mana bolanya!';

        // Briefly show the ball before shuffling
        setTimeout(() => {
            balls[ballPosition].style.display = 'none'; // Hide the ball again
            messageDisplay.textContent = 'Cangkir sedang diacak...';
            shuffleCups();
        }, 1500); // Show ball for 1.5 seconds
    }

    // --- Shuffle Cups Animation ---
    async function shuffleCups() {
        isShuffling = true;
        startButton.style.display = 'none';
        const shuffleCount = 15; // Number of swaps
        const swapDuration = 300; // Milliseconds per swap

        for (let i = 0; i < shuffleCount; i++) {
            const [index1, index2] = getRandomCupIndices();
            await swapCups(cups[index1], cups[index2], swapDuration);

            // Update ball position if it was in one of the swapped cups
            if (ballPosition === index1) {
                ballPosition = index2;
            } else if (ballPosition === index2) {
                ballPosition = index1;
            }

            await new Promise(resolve => setTimeout(resolve, swapDuration / 2)); // Short pause between swaps
        }

        isShuffling = false;
        messageDisplay.textContent = 'Sudah selesai mengacak! Tebak di mana bolanya!';
        enableCupClicks();
    }

    // Helper to get two distinct random indices
    function getRandomCupIndices() {
        let index1 = Math.floor(Math.random() * cups.length);
        let index2 = Math.floor(Math.random() * cups.length);
        while (index1 === index2) {
            index2 = Math.floor(Math.random() * cups.length);
        }
        return [index1, index2];
    }

    // --- Animate Cup Swaps ---
    function swapCups(cup1, cup2, duration) {
        return new Promise(resolve => {
            const cup1Rect = cup1.getBoundingClientRect();
            const cup2Rect = cup2.getBoundingClientRect();

            const deltaX = cup2Rect.left - cup1Rect.left;

            // Apply transformations
            cup1.style.transition = `transform ${duration / 1000}s ease-in-out`;
            cup2.style.transition = `transform ${duration / 1000}s ease-in-out`;

            // Move cups
            cup1.style.transform = `translateX(${deltaX}px)`;
            cup2.style.transform = `translateX(${-deltaX}px)`;

            // After animation, reset positions and swap DOM elements
            setTimeout(() => {
                const parent = cup1.parentNode;
                // Temporarily remove transitions to prevent re-animation on DOM swap
                cup1.style.transition = 'none';
                cup2.style.transition = 'none';

                if (cup1.nextSibling === cup2) {
                    parent.insertBefore(cup2, cup1);
                } else {
                    parent.insertBefore(cup1, cup2);
                }

                // Reset transform to 0 after DOM swap
                cup1.style.transform = 'translateX(0)';
                cup2.style.transform = 'translateX(0)';

                // Re-enable transition for future moves
                setTimeout(() => {
                    cup1.style.transition = `transform 0.3s ease-in-out`;
                    cup2.style.transition = `transform 0.3s ease-in-out`;
                    resolve();
                }, 50); // Small delay to allow transform reset to apply
            }, duration);
        });
    }

    // --- Enable/Disable Cup Clicks ---
    function enableCupClicks() {
        cups.forEach((cup, index) => {
            cup.onclick = () => handleCupClick(index);
            cup.style.cursor = 'pointer';
        });
    }

    function disableCupClicks() {
        cups.forEach(cup => {
            cup.onclick = null;
            cup.style.cursor = 'default';
        });
    }

    // --- Handle Player's Guess ---
    function handleCupClick(selectedIndex) {
        if (isShuffling || isGameOver) return;

        isGameOver = true;
        disableCupClicks(); // Prevent further clicks

        // Lift the selected cup slightly
        cups[selectedIndex].style.transform = 'translateY(-20px)';
        cups[selectedIndex].classList.add('selected');

        setTimeout(() => {
            // Show all balls after the guess
            balls[ballPosition].style.display = 'block'; // Show the correct ball
            cups[ballPosition].style.transform = 'translateY(-20px)'; // Lift the correct cup if different

            if (selectedIndex === ballPosition) {
                messageDisplay.textContent = 'Selamat! Anda menebak dengan benar!';
                messageDisplay.classList.add('text-green-600');
                messageDisplay.classList.remove('text-red-600');
            } else {
                messageDisplay.textContent = 'Maaf, Anda salah. Bolanya ada di bawah cangkir ini.';
                messageDisplay.classList.add('text-red-600');
                messageDisplay.classList.remove('text-green-600');
            }

            // Option to play again
            setTimeout(() => {
                startButton.textContent = 'Main Lagi';
                startButton.style.display = 'block';
                messageDisplay.classList.remove('text-green-600', 'text-red-600');
            }, 2000);
        }, 1000); // Delay before revealing result
    }

    // --- Event Listeners ---
    startButton.addEventListener('click', () => {
        initializeGame();
        placeBall();
    });

    // Initial setup
    initializeGame();
});
    </script>
</body>
</html>