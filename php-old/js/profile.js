// EazyMUZE v2.5 - Profile Module (with Monnify + Virtual Account)
window.profile = {
    render: async () => {
        const container = document.getElementById('profileDetails');
        
        // Refresh session to get latest balance
        const res = await window.api.get('auth.php?action=session');
        if (res.status === 'success') {
            window.globals.currentUser = res.data;
            localStorage.setItem('currentUser', JSON.stringify(res.data));
        }
        
        const user = window.globals.currentUser;
        if (!user) return;
        
        const posts = (window.dataManager.cache.posts || []).filter(p => Number(p.user_id) === Number(user.id));

        // Virtual Account section
        let hasVA = user.monnify_account_number && user.monnify_bank_name;
        
        // Auto-provision virtual account if missing during load
        if (!hasVA && !window.profile.isProvisioning) {
            window.profile.isProvisioning = true;
            window.api.post('monnify.php?action=get_account', {}).then(async (res) => {
                if (res && res.status === 'success' && res.data && res.data.monnify_account_number) {
                    // Instantly update local session & re-render
                    const userRes = await window.api.get('auth.php?action=session');
                    if (userRes.status === 'success') {
                        window.globals.currentUser = userRes.data;
                        localStorage.setItem('currentUser', JSON.stringify(userRes.data));
                        window.profile.render();
                    }
                }
                window.profile.isProvisioning = false;
            }).catch(() => {
                window.profile.isProvisioning = false;
            });
        }

        const vaSection = hasVA ? `
            <div style="margin-top:20px; background:linear-gradient(135deg, rgba(255,42,109,0.1), rgba(142,26,26,0.15)); border:1px solid rgba(255,42,109,0.3); border-radius:16px; padding:18px; text-align:left; box-shadow: 0 5px 15px rgba(255, 42, 109, 0.1);">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <i class="fas fa-university" style="color:var(--neon-pink); font-size:1.2rem;"></i>
                    <strong style="color:white; font-size:1rem;">Your Temple Virtual Account</strong>
                </div>
                <div style="background:rgba(0,0,0,0.4); padding:14px; border-radius:12px; margin-bottom:10px; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="color:#aaa; font-size:0.75rem; margin:0 0 4px;">Bank Name</p>
                    <p style="color:white; font-size:1rem; font-weight:600; margin:0;">${user.monnify_bank_name}</p>
                </div>
                <div style="background:rgba(0,0,0,0.4); padding:14px; border-radius:12px; margin-bottom:10px; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="color:#aaa; font-size:0.75rem; margin:0 0 4px;">Account Number</p>
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <p style="color:#2ecc71; font-size:1.3rem; font-weight:800; letter-spacing:2px; margin:0; text-shadow: 0 0 5px rgba(46, 204, 113, 0.2);">${user.monnify_account_number}</p>
                        <button onclick="window.profile.copyAccount('${user.monnify_account_number}')" style="background:var(--neon-pink); border:none; color:white; padding:6px 12px; border-radius:8px; cursor:pointer; font-size:0.75rem; font-weight:600; box-shadow: 0 3px 8px rgba(255, 42, 109, 0.3);">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
                <div style="background:rgba(0,0,0,0.4); padding:14px; border-radius:12px; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="color:#aaa; font-size:0.75rem; margin:0 0 4px;">Account Name</p>
                    <p style="color:white; font-size:0.95rem; font-weight:600; margin:0;">EazyMUZE - ${user.username}</p>
                </div>
                <p style="text-align:center; color:#e89ec0; font-size:0.75rem; margin-top:12px; font-weight:300;">Transfer to this account to fund your wallet instantly. <strong style="color:var(--neon-pink);">Active 24/7</strong></p>
            </div>
        ` : `
            <div style="margin-top:20px; background:rgba(255,42,109,0.05); border:1px dashed rgba(255,42,109,0.3); border-radius:16px; padding:22px; text-align:center; position:relative;">
                <i class="fas fa-circle-notch fa-spin" style="color:var(--neon-pink); font-size:1.5rem; margin-bottom:8px;"></i>
                <p style="color:#ffb3c6; font-size:0.85rem; font-weight: 500;">Generating Your Private Virtual Account...</p>
                <p style="color:grey; font-size:0.7rem; margin-top: 4px;">Connecting to secure banking channels.</p>
            </div>
        `;

        // Email verified badge
        const emailBadge = user.email_verified == 1
            ? '<span style="background:rgba(46,204,113,0.2); color:#2ecc71; padding:3px 10px; border-radius:10px; font-size:0.7rem; margin-left:8px;"><i class="fas fa-envelope-open"></i> Email Verified</span>'
            : '<span style="background:rgba(255,42,109,0.15); color:#ff2a6d; padding:3px 10px; border-radius:10px; font-size:0.7rem; margin-left:8px;"><i class="fas fa-envelope"></i> Unverified</span>';
        
        let html = `
            <div class="glass-panel" style="text-align: center;">
                <img src="${user.avatar || 'https://via.placeholder.com/100'}" style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--neon-pink);">
                <h2 style="color: white; margin-top: 10px;">${user.username} ${user.is_verified ? '<i class="fas fa-check-circle" style="color:var(--neon-pink);"></i>' : ''} ${emailBadge}</h2>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">${user.preference} • ${user.location || 'Unknown'}</p>
                <p style="margin-top: 10px; color: white;">"${user.bio || 'No bio written'}"</p>
                
                <div style="display: flex; justify-content: space-around; margin-top: 20px; background: rgba(0,0,0,0.5); padding: 15px; border-radius: 12px;">
                    <div>
                        <strong style="color: var(--neon-pink); font-size: 1.2rem;">₦${user.wallet_balance || '0.00'}</strong><br>
                        <small style="color: grey;">Wallet</small>
                    </div>
                    <div>
                        <strong style="color: var(--neon-pink); font-size: 1.2rem;">${user.streak_count || 0} 🔥</strong><br>
                        <small style="color: grey;">Streak</small>
                    </div>
                    <div>
                        <strong style="color: var(--neon-pink); font-size: 1.2rem;">${posts.length}</strong><br>
                        <small style="color: grey;">Posts</small>
                    </div>
                </div>
                
                <!-- Streak Progress Bar -->
                <div style="margin-top: 15px; text-align: left; padding: 0 10px;">
                    <small style="color: var(--neon-pink);">Flame Level</small>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: ${Math.min((user.streak_count || 0) * 10, 100)}%;"></div>
                    </div>
                </div>
                
                ${vaSection}
                
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button class="btn-primary" style="flex:1;" onclick="window.profile.fundWallet()">Fund Wallet 💰</button>
                    <button class="btn-primary" style="flex:1; background:transparent; border:1px solid var(--neon-pink);" onclick="window.profile.openEditModal()">Edit Profile</button>
                </div>

                <!-- App Downloads -->
                <div style="margin-top:20px; background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,42,109,0.1);">
                    <h4 style="color:white; margin:0 0 10px; font-size:0.9rem;"><i class="fas fa-download"></i> Get The App</h4>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="#" onclick="window.utils.showToast('APK Download started')" class="btn-primary" style="flex:1; min-width:80px; background:#3DDC84; text-decoration:none; font-size:0.75rem; padding:8px; color:black; font-weight:bold;">
                            <i class="fab fa-android"></i> APK
                        </a>
                        <a href="#" onclick="window.utils.showToast('IPA Download started')" class="btn-primary" style="flex:1; min-width:80px; background:#111; text-decoration:none; font-size:0.75rem; padding:8px; border:1px solid #333;">
                            <i class="fab fa-apple"></i> iOS
                        </a>
                        <button id="pwaInstallBtn" class="btn-primary" style="flex:1; min-width:80px; background:var(--neon-pink); display:none; font-size:0.75rem; padding:8px;" onclick="window.profile.installPWA()">
                            <i class="fas fa-mobile-alt"></i> Install
                        </button>
                    </div>
                </div>

                <!-- Support Links -->
                <div style="display:flex; gap:10px; margin-top:12px;">
                    <a href="https://wa.me/message/eazymuze" target="_blank" class="btn-primary" style="flex:1; background:#25D366; text-decoration:none; font-size:0.8rem; padding:10px;">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                    <a href="https://t.me/eazymuze" target="_blank" class="btn-primary" style="flex:1; background:#0088cc; text-decoration:none; font-size:0.8rem; padding:10px;">
                        <i class="fab fa-telegram"></i> Telegram
                    </a>
                    <button class="btn-primary" style="flex:1; background:#333; font-size:0.8rem; padding:10px;" onclick="window.app.switchView('help')">
                        <i class="fas fa-headset"></i> Help
                    </button>
                </div>

                <button class="btn-primary" style="width: 100%; margin-top: 10px; background: #333;" onclick="window.app.logout()">Logout</button>
            </div>
            
            <!-- Edit Profile Modal -->
            <div id="editProfileModal" class="modal-overlay">
                <div class="modal-content fade-in-up">
                    <h3 style="color:var(--neon-pink); margin-bottom: 15px;">Update Profile</h3>
                    <input type="text" id="editLocation" value="${user.location || ''}" placeholder="Location" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px; background:rgba(255,255,255,0.1); color:white; border:none;">
                    <textarea id="editBio" rows="3" placeholder="Bio" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px; background:rgba(255,255,255,0.1); color:white; border:none;">${user.bio || ''}</textarea>
                    <div style="display:flex; gap:10px;">
                        <button class="btn-primary" style="flex:1; background:#333;" onclick="document.getElementById('editProfileModal').style.display='none'">Cancel</button>
                        <button class="btn-primary" style="flex:1;" onclick="window.profile.saveProfile()">Save</button>
                    </div>
                </div>
            </div>
            
            <h3 style="color: white; margin-top: 20px;">My Gallery</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
        `;
        
        if (posts.length === 0) {
            html += `<p style="color: grey;">No posts yet.</p>`;
        } else {
            posts.forEach(p => {
                const img = (p.images && p.images.length > 0) ? p.images[0] : p.image_fallback;
                html += `<img src="${img}" style="width: calc(33% - 7px); height: 100px; object-fit: cover; border-radius: 8px;">`;
            });
        }
        
        html += `</div>`;
        
        container.innerHTML = html;
        
        if (window.deferredPrompt) {
            const installBtn = document.getElementById('pwaInstallBtn');
            if (installBtn) installBtn.style.display = 'block';
        }
    },
    
    fundWallet: () => {
        window.utils.showToast('Initializing Monnify Gateway...', 'success');
        
        setTimeout(() => {
            if (window.MonnifySDK) {
                const user = window.globals.currentUser;
                MonnifySDK.initialize({
                    amount: 5000,
                    currency: "NGN",
                    reference: 'EMZ-FUND-' + user.id + '-' + Date.now(),
                    customerName: user.fullname || user.username,
                    customerEmail: user.email,
                    apiKey: "MK_PROD_Z1N1VE409T",
                    contractCode: "479854013966",
                    paymentDescription: "EazyMUZE Wallet Funding",
                    metadata: {
                        name: "Powered by Welfus",
                        type: "wallet_funding"
                    },
                    onLoadStart: () => {
                        console.log("Monnify loading...");
                    },
                    onComplete: function(response) {
                        if (response.status === "SUCCESS") {
                            window.utils.showToast('💰 Wallet Funded Successfully!');
                            setTimeout(() => window.profile.render(), 2000);
                        }
                    },
                    onClose: function(data) {
                        window.utils.showToast('Payment window closed', 'error');
                    }
                });
            } else {
                // Show virtual account as fallback
                const user = window.globals.currentUser;
                if (user.monnify_account_number) {
                    window.utils.showToast(`Transfer to ${user.monnify_account_number} (${user.monnify_bank_name}) to fund your wallet.`);
                } else {
                    window.utils.showToast('Payment gateway unavailable. Please try again later.', 'error');
                }
            }
        }, 500);
    },

    copyAccount: (acctNum) => {
        navigator.clipboard.writeText(acctNum).then(() => {
            window.utils.showToast('Account number copied! 📋');
        }).catch(() => {
            window.utils.showToast(acctNum, 'success');
        });
    },
    
    openEditModal: () => {
        document.getElementById('editProfileModal').style.display = 'flex';
    },
    
    saveProfile: async () => {
        const location = document.getElementById('editLocation').value;
        const bio = document.getElementById('editBio').value;
        
        const res = await window.api.post('auth.php?action=update_profile', { location, bio });
        if(res.status === 'success') {
            window.utils.showToast('Profile Updated');
            document.getElementById('editProfileModal').style.display = 'none';
            window.profile.render();
        } else {
            window.utils.showToast('Failed to update', 'error');
        }
    },

    installPWA: async () => {
        if (!window.deferredPrompt) {
            window.utils.showToast('Install prompt not available', 'error');
            return;
        }
        window.deferredPrompt.prompt();
        const { outcome } = await window.deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        window.deferredPrompt = null;
        const installBtn = document.getElementById('pwaInstallBtn');
        if (installBtn) installBtn.style.display = 'none';
    }
};
