class MusicPlayer {
    constructor() {
        this.audio = new Audio();
        this.playlist = [];
        this.currentTrackIndex = 0;
        this.isPlaying = false;
        this.volume = 80;
        this.isMuted = false;
        this.userId = null;

        this.init();
    }

    async init() {
        // Dapatkan user ID dari session
        this.userId = await this.getUserId();

        // Muat playlist dari server
        await this.loadPlaylist();

        // Muat preferensi user
        await this.loadUserPreferences();

        // Setup event listeners
        this.setupEventListeners();

        // Coba mulai musik
        this.tryAutoPlay();
    }

    async getUserId() {
        // Anda mungkin perlu menyesuaikan ini sesuai sistem auth Anda
        try {
            const response = await fetch('get_user_id.php');
            const data = await response.json();
            return data.user_id || null;
        } catch (error) {
            console.error("Gagal mendapatkan user ID:", error);
            return null;
        }
    }

    async loadPlaylist() {
        try {
            const response = await fetch('get_music_playlist.php');
            const data = await response.json();

            if (data.success && data.playlist.length > 0) {
                this.playlist = data.playlist;
                console.log("Playlist dimuat:", this.playlist);
            } else {
                console.warn("Playlist kosong atau gagal dimuat");
            }
        } catch (error) {
            console.error("Gagal memuat playlist:", error);
        }
    }

    async loadUserPreferences() {
        if (!this.userId) return;

        try {
            const response = await fetch(`get_music_preferences.php?user_id=${this.userId}`);
            const data = await response.json();

            if (data.success) {
                this.volume = data.volume || 80;
                this.isMuted = data.is_muted || false;
                this.audio.volume = this.isMuted ? 0 : this.volume / 100;
                console.log("Preferensi user dimuat:", data);
            }
        } catch (error) {
            console.error("Gagal memuat preferensi user:", error);
        }
    }

    setupEventListeners() {
        // Ketika track selesai, mainkan berikutnya
        this.audio.addEventListener('ended', () => {
            this.nextTrack();
        });

        // Update UI ketika musik play/pause
        this.audio.addEventListener('play', () => {
            this.isPlaying = true;
            this.updateMusicControls();
            this.savePlaybackStatus();
        });

        this.audio.addEventListener('pause', () => {
            this.isPlaying = false;
            this.updateMusicControls();
            this.savePlaybackStatus();
        });

        // Tombol kontrol di halaman manapun
        document.addEventListener('click', (e) => {
            if (e.target.closest('.music-toggle')) {
                this.togglePlayback();
            } else if (e.target.closest('.music-next')) {
                this.nextTrack();
            } else if (e.target.closest('.music-prev')) {
                this.prevTrack();
            } else if (e.target.closest('.music-volume')) {
                this.toggleMute();
            }
        });
    }

    async tryAutoPlay() {
        if (this.playlist.length === 0) return;

        // Mulai dengan track terakhir yang diputar user
        if (this.userId) {
            try {
                const response = await fetch(`get_last_played.php?user_id=${this.userId}`);
                const data = await response.json();

                if (data.success && data.track_id) {
                    const trackIndex = this.playlist.findIndex(track => track.id == data.track_id);
                    if (trackIndex !== -1) {
                        this.currentTrackIndex = trackIndex;
                    }
                }
            } catch (error) {
                console.error("Gagal memuat last played:", error);
            }
        }

        this.playCurrentTrack();
    }

    playCurrentTrack() {
        if (this.playlist.length === 0) return;

        const track = this.playlist[this.currentTrackIndex];
        this.audio.src = track.file_path;
        this.audio.load();

        // Coba play, mungkin membutuhkan interaksi user
        const playPromise = this.audio.play();

        if (playPromise !== undefined) {
            playPromise.catch(error => {
                console.log("Autoplay prevented:", error);
                // Tampilkan UI bahwa user perlu interaksi
            });
        }

        // Update UI
        this.updateNowPlaying();
        this.savePlaybackStatus();
    }

    togglePlayback() {
        if (this.isPlaying) {
            this.audio.pause();
        } else {
            this.audio.play().catch(error => {
                console.log("Playback prevented:", error);
            });
        }
    }

    nextTrack() {
        if (this.playlist.length === 0) return;

        this.currentTrackIndex = (this.currentTrackIndex + 1) % this.playlist.length;
        this.playCurrentTrack();
    }

    prevTrack() {
        if (this.playlist.length === 0) return;

        this.currentTrackIndex = (this.currentTrackIndex - 1 + this.playlist.length) % this.playlist.length;
        this.playCurrentTrack();
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        this.audio.volume = this.isMuted ? 0 : this.volume / 100;
        this.updateMusicControls();
        this.saveUserPreferences();
    }

    updateMusicControls() {
        // Update semua tombol musik di semua halaman
        document.querySelectorAll('.music-toggle i').forEach(icon => {
            icon.className = this.isPlaying ? 'fas fa-pause' : 'fas fa-play';
        });

        document.querySelectorAll('.music-volume i').forEach(icon => {
            icon.className = this.isMuted ? 'fas fa-volume-mute' : 'fas fa-volume-up';
        });
    }

    updateNowPlaying() {
        if (this.playlist.length === 0) return;

        const track = this.playlist[this.currentTrackIndex];
        document.querySelectorAll('.now-playing').forEach(el => {
            el.textContent = `${track.title} - ${track.artist || 'Unknown'}`;
        });
    }

    async savePlaybackStatus() {
        if (!this.userId || this.playlist.length === 0) return;

        try {
            await fetch('save_playback_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.userId,
                    track_id: this.playlist[this.currentTrackIndex].id,
                    is_playing: this.isPlaying
                })
            });
        } catch (error) {
            console.error("Gagal menyimpan status playback:", error);
        }
    }

    async saveUserPreferences() {
        if (!this.userId) return;

        try {
            await fetch('save_music_preferences.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.userId,
                    volume: this.volume,
                    is_muted: this.isMuted
                })
            });
        } catch (error) {
            console.error("Gagal menyimpan preferensi user:", error);
        }
    }
}

// Inisialisasi ketika DOM siap
document.addEventListener('DOMContentLoaded', () => {
    window.musicPlayer = new MusicPlayer();
});