// EazyMUZE v2.5 - Globals
window.globals = {
    currentUser: null,
    currentLocation: null,
    audioContexts: {},
    songUrls: {
        'wizkid': 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', // Mock afrobeats
        'burna_boy': 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3'
    }
};

// Check for cached user on load
try {
    const cached = localStorage.getItem('currentUser');
    if (cached) window.globals.currentUser = JSON.parse(cached);
} catch (e) {
    console.warn('Failed to parse cached user');
}
