// server.js
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*"
    }
});

// Game state
const games = new Map();

io.on('connection', (socket) => {
    console.log('New client connected:', socket.id);

    // Admin creates game
    socket.on('createGame', (gameData) => {
        const gameCode = generateGameCode();
        games.set(gameCode, {
            ...gameData,
            players: new Map(),
            state: 'waiting',
            matches: 0
        });
        socket.join(gameCode);
        socket.emit('gameCreated', gameCode);
    });

    // Player joins game
    socket.on('joinGame', ({ gameCode, playerName }) => {
        if (!games.has(gameCode)) {
            return socket.emit('error', 'Game not found');
        }

        const game = games.get(gameCode);
        game.players.set(socket.id, {
            id: socket.id,
            name: playerName,
            score: 0
        });

        socket.join(gameCode);
        io.to(gameCode).emit('playerJoined', Array.from(game.players.values()));
    });

    // Gameplay events
    socket.on('flipCard', ({ gameCode, cardId }) => {
        const game = games.get(gameCode);
        io.to(gameCode).emit('cardFlipped', { playerId: socket.id, cardId });
    });

    socket.on('disconnect', () => {
        console.log('Client disconnected');
        // Clean up player from all games
    });
});

function generateGameCode() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

server.listen(3000, () => console.log('Server running on port 3000'));