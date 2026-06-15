// EazyMUZE v2.5 - Bootstrapper
window.app = {
    init: async () => {
        const currentUser = localStorage.getItem('currentUser');
        if (currentUser) {
            window.globals.currentUser = JSON.parse(currentUser);
            window.utils.showToast(`Welcome back to the Temple, ${window.globals.currentUser.username} 💋`);
            
            // Fetch location silently
            await window.utils.fetchLocationSilently();
            
            // Load data
            await window.dataManager.loadAll();
            
            // Init Views
            if(window.feed) window.feed.render();
            
            window.app.switchView('feed');
            

            // Notifications Setup & Active Sync Loop
            window.app.fetchNotifications();
            
            window.app.syncTimer = null;
            window.app.syncInterval = 2000; // 2s when active
            
            window.app.startSyncLoop = () => {
                if (window.app.syncTimer) clearInterval(window.app.syncTimer);
                window.app.syncTimer = setInterval(() => {
                    if (window.globals.currentUser) window.app.fetchNotifications();
                }, window.app.syncInterval);
            };
            
            // Adjust sync intervals dynamically to save battery/data when blurred
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    window.app.syncInterval = 15000; // 15s when backgrounded
                } else {
                    window.app.syncInterval = 2000;  // 2s when active
                    if (window.globals.currentUser) window.app.fetchNotifications();
                }
                window.app.startSyncLoop();
            });
            
            window.app.startSyncLoop();
            
            // Show notification permission banner on load if default
            setTimeout(() => {
                window.app.showNotificationPermissionBanner();
            }, 1500);
            
            // Register PWA Service Worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('sw.js').then(reg => {
                    console.log("Service Worker Active for Background Whispers.");
                }).catch(err => {
                    console.log("SW offline fallback mode active.");
                });
            }
        } else {
            window.location.href = 'auth/index.html';
        }
    },
    
    switchView: (viewName) => {
        document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(v => v.classList.remove('active'));
        
        document.getElementById(`view-${viewName}`).classList.add('active');
        
        // Activate correct nav item
        const navMap = {'feed': 0, 'explore': 1, 'invites': 2, 'messages': 3, 'market': 4, 'profile': 5};
        if (navMap[viewName] !== undefined) {
            document.querySelectorAll('.nav-item')[navMap[viewName]].classList.add('active');
        }
        
        // Render views dynamically based on JS modules
        if (viewName === 'feed' && window.feed) window.feed.render();
        if (viewName === 'explore' && window.explore) window.explore.render();
        if (viewName === 'invites' && window.invites) window.invites.render();
        if (viewName === 'messages' && window.messages) window.messages.render();
        if (viewName === 'market' && window.market) window.market.render();
        if (viewName === 'profile' && window.profile) window.profile.render();
        if (viewName === 'help' && window.help) window.help.render();
    },
    
    logout: async () => {
        await window.api.get('auth.php?action=logout');
        localStorage.removeItem('currentUser');
        window.location.href = 'auth/index.html';
    },

    fetchNotifications: async () => {
        const res = await window.api.get('notifications.php?action=list');
        if(res.status === 'success') {
            const unread = res.data.filter(n => n.is_read == 0);
            
            // Check for new notifications since last fetch to fire system-level background alert
            const lastNotifCount = parseInt(sessionStorage.getItem('last_notif_count') || '0');
            if (unread.length > lastNotifCount) {
                const newestNotif = unread[0];
                if (newestNotif) {
                    // Native notification trigger (works in background / screen locked)
                    try {
                        if ('Notification' in window && Notification.permission === 'granted') {
                            navigator.serviceWorker.ready.then(reg => {
                                reg.showNotification("EazyMUZE 💋", {
                                    body: newestNotif.message,
                                    icon: "assets/img/logo.png",
                                    badge: "assets/img/logo.png",
                                    tag: "muze-notif-" + newestNotif.id,
                                    renotify: true,
                                    silent: false
                                });
                            }).catch(() => {
                                // Fallback standard notification
                                new Notification("EazyMUZE 💋", {
                                    body: newestNotif.message,
                                    icon: "assets/img/logo.png"
                                });
                            });
                        }
                    } catch (e) {}
                }
            }
            sessionStorage.setItem('last_notif_count', unread.length);

            const badge = document.getElementById('notifBadge');
            if(badge) {
                if(unread.length > 0) {
                    badge.style.display = 'block';
                    badge.innerText = unread.length;
                } else {
                    badge.style.display = 'none';
                }
            }
            
            const list = document.getElementById('notifList');
            if(list) {
                if(res.data.length === 0) {
                    list.innerHTML = '<p style="color:grey; font-size:0.8rem;">No new whispers.</p>';
                } else {
                    list.innerHTML = res.data.map(n => `
                        <div style="padding:10px; border-bottom:1px solid rgba(255,42,109,0.2); ${n.is_read==0?'background:rgba(255,42,109,0.1);':''}">
                            <p style="margin:0; font-size:0.85rem; color:white;">${n.message}</p>
                            <small style="color:var(--neon-pink); font-size:0.6rem;">${new Date(n.created_at).toLocaleString()}</small>
                        </div>
                    `).join('');
                }
            }
            
            // Instantly compute and show unread messages badge on messages footer icon
            const msgs = window.dataManager.cache.messages || [];
            const currentUserId = window.globals.currentUser?.id;
            if (currentUserId) {
                const unreadMsgs = msgs.filter(m => Number(m.receiver_id) === Number(currentUserId) && !m.is_read);
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
        }
    },
    
    toggleNotifications: () => {
        const d = document.getElementById('notifDropdown');
        if(!d) return;
        d.style.display = d.style.display === 'none' ? 'block' : 'none';
        if(d.style.display === 'block') {
            window.api.post('notifications.php?action=mark_read', {id: 'all'}).then(() => {
                document.getElementById('notifBadge').style.display = 'none';
            });
        }
    },

    showNotificationPermissionBanner: () => {
        if ('Notification' in window && Notification.permission === 'default') {
            if (document.getElementById('notifPermissionBanner')) return;
            
            const banner = document.createElement('div');
            banner.id = 'notifPermissionBanner';
            banner.style.cssText = `
                position: fixed;
                bottom: 80px;
                left: 20px;
                right: 20px;
                background: linear-gradient(135deg, var(--neon-pink), var(--velvet));
                color: white;
                padding: 15px 20px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(255, 42, 109, 0.4);
                z-index: 999;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-family: 'Outfit', sans-serif;
                font-size: 0.9rem;
                border: 1px solid rgba(255,255,255,0.2);
            `;
            
            banner.innerHTML = `
                <div style="display:flex; align-items:center; gap:10px; flex:1; cursor:pointer;" onclick="window.app.requestNotificationPermission()">
                    <i class="fas fa-bell" style="font-size:1.3rem; color:#fff; animation: bounce 1s infinite;"></i>
                    <div>
                        <strong style="display:block;">Enable Real-Time Alerts 🔔</strong>
                        <span style="font-size:0.75rem; color:rgba(255,255,255,0.85);">Receive instant whispers & matches on your phone.</span>
                    </div>
                </div>
                <i class="fas fa-times" style="cursor:pointer; padding:5px; margin-left:10px; opacity:0.7;" onclick="document.getElementById('notifPermissionBanner').remove()"></i>
            `;
            
            document.body.appendChild(banner);
        }
    },
    
    requestNotificationPermission: () => {
        if ('Notification' in window) {
            Notification.requestPermission().then(permission => {
                const banner = document.getElementById('notifPermissionBanner');
                if (banner) banner.remove();
                
                if (permission === 'granted') {
                    window.utils.showToast("🔔 Real-time alerts activated!");
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.ready.then(reg => {
                            reg.showNotification("EazyMUZE 💋", {
                                body: "Real-time alerts successfully linked to your device!",
                                icon: "assets/img/logo.png",
                                badge: "assets/img/logo.png"
                            });
                        });
                    }
                } else {
                    window.utils.showToast("Alerts disabled", "error");
                }
            });
        }
    },
};

document.addEventListener('DOMContentLoaded', () => {
    if (!window.location.pathname.includes('auth')) {
        window.app.init();
    }
});

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;
    
    // Show the install button if we are on the profile page and the button exists
    const installBtn = document.getElementById('pwaInstallBtn');
    if (installBtn) {
        installBtn.style.display = 'block';
    }
});

// Listen for navigation messages from the service worker (PWA clicks)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', event => {
        if (event.data && event.data.action === 'navigate') {
            window.app.switchView(event.data.view);
        }
    });
}
