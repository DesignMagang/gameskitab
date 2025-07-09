<?php
// get_sound.php
$soundFile = 'sounds/buzzer_sound.mp3'; // Path relatif

if (file_exists($soundFile)) {
    header('Content-Type: audio/mpeg');
    readfile($soundFile);
} else {
    // Fallback ke suara default
    header('Location: https://assets.mixkit.co/sfx/preview/mixkit-game-show-buzzer-961.mp3');
}
?>