// game.js
const socket = io('http://localhost:3000');

// Join game
function joinGame(gameCode, playerName) {
    socket.emit('joinGame', { gameCode, playerName });

    socket.on('playerJoined', (players) => {
        updatePlayerList(players);
    });

    socket.on('cardFlipped', ({ playerId, cardId }) => {
        if (playerId !== socket.id) {
            highlightOpponentMove(cardId);
        }
    });

    socket.on('matchFound', ({ playerId, points }) => {
        updateScoreboard(playerId, points);
    });
}

// Flip card handler
function handleCardFlip(cardId) {
    socket.emit('flipCard', {
        gameCode: currentGameCode,
        cardId
    });
}