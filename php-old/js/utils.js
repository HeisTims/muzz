// EazyMUZE v2.5 - Utilities
window.utils = {
    audioCtx: null,

    initAudio: () => {
        try {
            if (!window.utils.audioCtx) {
                window.utils.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (window.utils.audioCtx.state === 'suspended') {
                window.utils.audioCtx.resume();
            }
        } catch (e) {
            console.warn('AudioContext failed to initialize', e);
        }
    },

    playNotificationSound: (type = 'engagement') => {
        try {
            window.utils.initAudio();
            const ctx = window.utils.audioCtx;
            if (!ctx) return;

            const now = ctx.currentTime;
            
            if (type === 'message') {
                // Quick double high-pitched beep: C6 (1046.5Hz) followed by E6 (1318.5Hz)
                const playTone = (freq, startTime, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    
                    osc.type = 'triangle'; // Soft and warm sound
                    osc.frequency.setValueAtTime(freq, startTime);
                    
                    gain.gain.setValueAtTime(0, startTime);
                    gain.gain.linearRampToValueAtTime(0.25, startTime + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                    
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };
                
                playTone(1046.50, now, 0.10);
                playTone(1318.51, now + 0.10, 0.15);
                
            } else {
                // Engagement / General notification: Major arpeggio chime chord (C5, G5, C6)
                const playChime = (freq, startTime, duration) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    
                    osc.type = 'sine'; // Pure glass-like bell chime
                    osc.frequency.setValueAtTime(freq, startTime);
                    
                    gain.gain.setValueAtTime(0, startTime);
                    gain.gain.linearRampToValueAtTime(0.15, startTime + 0.04);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                    
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };
                
                playChime(523.25, now, 0.35);       // C5
                playChime(783.99, now + 0.06, 0.35);  // G5
                playChime(1046.50, now + 0.12, 0.45); // C6
            }
        } catch (e) {
            console.warn('Web Audio playback error:', e);
        }
    },

    showToast: (message, type = 'success') => {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = message;
        
        if (type === 'error') {
            toast.style.borderLeftColor = 'var(--blood-moon)';
        }
        
        container.appendChild(toast);

        // Play correct notification sound automatically
        const isMsg = type === 'message' || 
                      message.toLowerCase().includes('whisper') || 
                      message.toLowerCase().includes('message') || 
                      message.toLowerCase().includes('convo');
        
        window.utils.playNotificationSound(isMsg ? 'message' : 'engagement');

        // Trigger native OS level push notification for loud background alert
        try {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification("EazyMUZE 💋", {
                    body: message,
                    icon: "assets/img/logo.png",
                    silent: true // Handled by Web Audio directly if page is open
                });
            }
        } catch (e) {}

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },

    fetchLocationSilently: async () => {
        try {
            const res = await fetch('https://ipapi.co/json/');
            const data = await res.json();
            window.globals.currentLocation = `${data.city}, ${data.country_name}`;
            return window.globals.currentLocation;
        } catch (e) {
            console.warn('Silent GPS fetch failed');
            return 'Unknown Location';
        }
    },

    shuffleArray: (array) => {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }
};

// Auto-unlock AudioContext on user interaction
document.addEventListener('click', () => window.utils.initAudio(), { once: true });
document.addEventListener('touchstart', () => window.utils.initAudio(), { once: true });
