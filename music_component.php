<?php
session_start();
require 'db.php';
require 'music_player.php';

$music_settings = isset($_SESSION['user_id']) ? 
    getUserMusicSettings($conn, $_SESSION['user_id']) : 
    ['is_music_on' => true, 'volume' => 50, 'current_track' => 0];
?>

<div id="music-controls" class="fixed bottom-4 right-4 bg-white bg-opacity-20 backdrop-blur-md rounded-full p-3 shadow-lg z-50">
    <div class="flex items-center gap-3">
        <button id="musicToggle" class="music-btn bg-white bg-opacity-30 hover:bg-opacity-40 p-3 rounded-full">
            <i class="fas <?= $music_settings['is_music_on'] ? 'fa-volume-up' : 'fa-volume-mute' ?> text-white"></i>
        </button>
        
        <div id="musicInfo" class="text-white text-sm hidden md:block">
            <div class="font-semibold" id="nowPlaying">Memuat musik...</div>
            <div class="text-xs opacity-80" id="trackInfo">Playlist sistem</div>
        </div>
    </div>
</div>

<audio id="backgroundMusic"></audio>

<script>
// Inisialisasi musik player
const musicPlayer = {
    player: document.getElementById('backgroundMusic'),
    playlist: [],
    currentTrack: 0,
    isPlaying: <?= $music_settings['is_music_on'] ? 'true' : 'false' ?>,
    volume: <?= $music_settings['volume'] / 100 ?>,
    
    init: async function() {
        // Ambil playlist dari server
        try {
            const response = await fetch('music_player.php?action=get_playlist');
            this.playlist = await response.json();
            
            if (this.playlist.length > 0) {
                this.currentTrack = <?= $music_settings['current_track'] % count(getPlaylist($conn)) ?>;
                this.updatePlayer();
                
                if (this.isPlaying) {
                    this.play();
                }
            }
        } catch (error) {
            console.error('Gagal memuat playlist:', error);
        }
        
        // Event listeners
        this.player.addEventListener('ended', () => this.nextTrack());
        document.getElementById('musicToggle').addEventListener('click', () => this.togglePlay());
    },
    
    updatePlayer: function() {
        if (this.playlist.length === 0) return;
        
        const track = this.playlist[this.currentTrack];
        this.player.src = track.file_path;
        this.player.volume = this.volume;
        
        document.getElementById('nowPlaying').textContent = track.display_name;
        document.getElementById('trackInfo').textContent = `Track ${this.currentTrack + 1} of ${this.playlist.length}`;
        
        // Simpan pengaturan ke server
        if (<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
            this.saveSettings();
        }
    },
    
    play: function() {
        if (this.playlist.length === 0) return;
        
        this.player.play()
            .then(() => {
                this.isPlaying = true;
                document.getElementById('musicToggle').innerHTML = '<i class="fas fa-volume-up text-white"></i>';
            })
            .catch(error => {
                console.error('Gagal memutar musik:', error);
            });
    },
    
    pause: function() {
        this.player.pause();
        this.isPlaying = false;
        document.getElementById('musicToggle').innerHTML = '<i class="fas fa-volume-mute text-white"></i>';
    },
    
    togglePlay: function() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
        
        // Simpan pengaturan ke server
        if (<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>) {
            this.saveSettings();
        }
    },
    
    nextTrack: function() {
        if (this.playlist.length === 0) return;
        
        this.currentTrack = (this.currentTrack + 1) % this.playlist.length;
        this.updatePlayer();
        
        if (this.isPlaying) {
            this.play();
        }
    },
    
    saveSettings: function() {
        const settings = {
            action: 'update_settings',
            is_music_on: this.isPlaying,
            volume: Math.round(this.volume * 100),
            current_track: this.currentTrack
        };
        
        fetch('music_player.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(settings)
        });
    }
};

// Inisialisasi saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => musicPlayer.init());
</script>