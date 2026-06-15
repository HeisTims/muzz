<?php
// EazyMUZE Dynamic Footer & Navigation Template

// Determine path prefix for subdirectories
$path_prefix = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/manage-portal/') !== false) {
    $path_prefix = '../';
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
        </main> <!-- Close appContainer -->

        <?php if (!isset($no_header) || !$no_header): ?>
        <!-- BOTTOM NAVIGATION -->
        <nav class="bottom-nav">
            <div class="nav-item <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>index.php'">
                <i class="fas fa-fan"></i>
                <span>Muze</span>
            </div>
            <div class="nav-item <?php echo ($current_page === 'explore.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>explore.php'">
                <i class="fas fa-search"></i>
                <span>explore</span>
            </div>
            <div class="nav-item <?php echo ($current_page === 'invites.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>invites.php'">
                <i class="fas fa-heart" style="color: var(--neon-pink); text-shadow: 0 0 10px rgba(255, 42, 109, 0.4);"></i>
                <span>invites</span>
            </div>
            <div class="nav-item <?php echo ($current_page === 'messages.php' || $current_page === 'chat.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>messages.php'" style="position: relative;">
                <i class="fas fa-comment-dots"></i>
                <span id="messagesUnreadBadge" style="position: absolute; top: 0px; right: 20px; background: var(--neon-pink); color: white; font-size: 0.65rem; font-weight: bold; min-width: 16px; height: 16px; border-radius: 50%; display: none; align-items: center; justify-content: center; box-shadow: 0 0 8px var(--neon-pink);">0</span>
                <span>msgs</span>
            </div>
            <div class="nav-item <?php echo ($current_page === 'market.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>market.php'">
                <i class="fas fa-shopping-cart"></i>
                <span>market</span>
            </div>
            <div class="nav-item <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $path_prefix; ?>profile.php'">
                <i class="fas fa-place-of-worship" style="color: #f1c40f; text-shadow: 0 0 10px rgba(241, 196, 15, 0.4);"></i>
                <span>temple</span>
            </div>
        </nav>
        <?php endif; ?>

        <!-- Monnify Payment Gateway SDK -->
        <script src="https://sdk-v2.monnify.com/v1/monnify.js"></script>

        <!-- Global Javascript Engine -->
        <script>
            // CSRF Security Configuration
            window.csrfToken = "<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>";
            window.apiUrl = "<?php echo $path_prefix; ?>api/";

            // Global Client-side Error Logger
            window.addEventListener('error', function(event) {
                // Ignore third-party script errors or extension errors
                if (event.filename && !event.filename.includes(window.location.host)) {
                    return;
                }
                
                const user = "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>";
                const errorData = {
                    message: event.message || (event.error && event.error.message) || 'Unknown error',
                    source: 'Client-side JS Error',
                    file: event.filename || 'N/A',
                    line: event.lineno || 0,
                    column: event.colno || 0,
                    stack: (event.error && event.error.stack) || '',
                    user: user,
                    url: window.location.href
                };
                
                fetch(window.apiUrl + 'log_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(errorData)
                }).catch(() => {});
            });

            window.addEventListener('unhandledrejection', function(event) {
                const user = "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>";
                const errorData = {
                    message: (event.reason && (event.reason.message || event.reason)) || 'Unhandled rejection',
                    source: 'Unhandled Promise Rejection',
                    file: 'N/A',
                    line: 'N/A',
                    column: 'N/A',
                    stack: (event.reason && event.reason.stack) || '',
                    user: user,
                    url: window.location.href
                };
                
                fetch(window.apiUrl + 'log_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(errorData)
                }).catch(() => {});
            });

            // 1. Web Audio API Notification System (0 bytes programmatically generated)
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
                            // Quick double high-pitched beep chimes: C6 (1046.5Hz) followed by E6 (1318.5Hz)
                            const playTone = (freq, startTime, duration) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                
                                osc.type = 'triangle';
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
                            // General: Major arpeggio chime chord (C5, G5, C6)
                            const playChime = (freq, startTime, duration) => {
                                const osc = ctx.createOscillator();
                                const gain = ctx.createGain();
                                
                                osc.type = 'sine';
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

                    // Play correct chime sound automatically
                    const isMsg = type === 'message' || 
                                  message.toLowerCase().includes('whisper') || 
                                  message.toLowerCase().includes('message') || 
                                  message.toLowerCase().includes('convo');
                    
                    window.utils.playNotificationSound(isMsg ? 'message' : 'engagement');

                    setTimeout(() => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                }
            };

            // Unblock audio context on gesture
            document.addEventListener('click', () => window.utils.initAudio(), { once: true });
            document.addEventListener('touchstart', () => window.utils.initAudio(), { once: true });

            // 2. Notifications Dropdown Toggle & Sync
            function toggleNotificationsDropdown() {
                const d = document.getElementById('notifDropdown');
                if(!d) return;
                
                if (d.style.display === 'none') {
                    d.style.display = 'block';
                    // Mark all read in DB via API
                    fetch(window.apiUrl + 'notifications.php?action=mark_read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: 'all', csrf_token: window.csrfToken })
                    }).then(() => {
                        const badge = document.getElementById('notifBadge');
                        if (badge) badge.style.display = 'none';
                    });
                } else {
                    d.style.display = 'none';
                }
            }

            // 3. Real-Time Status & Notifications Sync Loop
            let syncIntervalTime = 4000; // Poll every 4 seconds when tab is active
            let syncTimer = null;

            function runSync() {
                <?php if (isset($_SESSION['user_id'])): ?>
                fetch(window.apiUrl + 'notifications.php?action=list')
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success' && res.data) {
                            const unread = res.data.filter(n => n.is_read == 0);
                            
                            // Heartbeat sound chime warning for new unreads
                            const lastCount = parseInt(sessionStorage.getItem('last_notif_unread') || '0');
                            if (unread.length > lastCount) {
                                window.utils.playNotificationSound('engagement');
                                window.utils.showToast(unread[0].message);
                            }
                            sessionStorage.setItem('last_notif_unread', unread.length);

                            // Update Badge count
                            const badge = document.getElementById('notifBadge');
                            if (badge) {
                                if (unread.length > 0) {
                                    badge.style.display = 'block';
                                    badge.innerText = unread.length;
                                } else {
                                    badge.style.display = 'none';
                                }
                            }

                            // Populate notifications lists
                            const list = document.getElementById('notifList');
                            if (list) {
                                if (res.data.length === 0) {
                                    list.innerHTML = '<p style="color:grey; font-size:0.8rem;">No new whispers.</p>';
                                } else {
                                    list.innerHTML = res.data.map(n => `
                                        <div style="padding:10px; border-bottom:1px solid rgba(255,42,109,0.2); ${n.is_read==0?'background:rgba(255,42,109,0.1);':''}">
                                            <p style="margin:0; font-size:0.85rem; color:white;">${n.message}</p>
                                            <small style="color:var(--neon-pink); font-size:0.6rem;">${n.created_at}</small>
                                        </div>
                                    `).join('');
                                }
                            }
                        }
                    }).catch(err => console.warn("Sync warning: network deferred."));

                // Sync inbox unreads count
                fetch(window.apiUrl + 'messages.php?action=inbox')
                    .then(res => res.json())
                    .then(res => {
                        if (res.status === 'success' && res.data) {
                            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
                            const unreadMsgs = res.data.filter(m => Number(m.receiver_id) === currentUserId && !m.is_read);
                            const msgBadge = document.getElementById('messagesUnreadBadge');
                            if (msgBadge) {
                                if (unreadMsgs.length > 0) {
                                    msgBadge.style.display = 'flex';
                                    msgBadge.innerText = unreadMsgs.length;
                                } else {
                                    msgBadge.style.display = 'none';
                                }
                            }
                        }
                    }).catch(err => {});
                <?php endif; ?>
            }

            function startSyncLoop() {
                if (syncTimer) clearInterval(syncTimer);
                syncTimer = setInterval(runSync, syncIntervalTime);
            }

            // Sync speed throttling based on browser visibility
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    syncIntervalTime = 15000; // Slow down to 15s in background
                } else {
                    syncIntervalTime = 4000;  // Active 4s
                    runSync();
                }
                startSyncLoop();
            });

            // Start loop on load
            <?php if (isset($_SESSION['user_id'])): ?>
            runSync();
            startSyncLoop();
            <?php endif; ?>

            // 4. Register PWA Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('<?php echo $path_prefix; ?>sw.js').then(reg => {
                    console.log("PWA Service Worker online.");
                    // Force update check
                    reg.update();
                }).catch(err => {
                    console.log("PWA Service Worker failed to register.");
                });

                // Reload the page when the new service worker takes control
                let refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    if (!refreshing) {
                        refreshing = true;
                        window.location.reload();
                    }
                });
            }
        </script>
</body>
</html>
