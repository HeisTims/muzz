// EazyMUZE v2.5 - Messages Module
window.messages = {
    render: () => {
        const container = document.getElementById('messagesInbox');
        const msgs = window.dataManager.cache.messages || [];
        const currentUserId = window.globals.currentUser?.id;
        
        if (!currentUserId) return;
        
        // Group messages by conversation partner
        const convos = {};
        msgs.forEach(m => {
            const partnerId = m.sender_id === currentUserId ? m.receiver_id : m.sender_id;
            const partnerName = m.sender_id === currentUserId ? m.receiver_name : m.sender_name;
            const partnerAvatar = m.sender_id === currentUserId ? m.receiver_avatar : m.sender_avatar;
            
            if (!convos[partnerId]) {
                convos[partnerId] = { partnerId, partnerName, partnerAvatar, messages: [] };
            }
            convos[partnerId].messages.push(m);
        });
        
        let html = '';
        let totalUnreadConvos = 0;
        
        Object.values(convos).forEach(c => {
            const lastMsg = c.messages[c.messages.length - 1];
            const isUnreadAndLocked = lastMsg.receiver_id === currentUserId && !lastMsg.is_read;
            if (isUnreadAndLocked) {
                totalUnreadConvos++;
            }
            
            html += `
                <div class="glass-panel" style="margin-bottom: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--neon-pink)'" onmouseout="this.style.borderColor='var(--glass-border)'" onclick="window.messages.openChat(${c.partnerId}, '${c.partnerName}')">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="position: relative;">
                            <img src="${c.partnerAvatar || 'https://via.placeholder.com/50'}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1.5px solid ${isUnreadAndLocked ? 'var(--neon-pink)' : 'var(--glass-border)'}">
                            ${isUnreadAndLocked ? `<span style="position:absolute; top:-2px; right:-2px; background:var(--neon-pink); border:2px solid var(--velvet-bg); width:12px; height:12px; border-radius:50%; box-shadow: 0 0 8px var(--neon-pink);"></span>` : ''}
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: ${isUnreadAndLocked ? 'var(--neon-pink)' : 'white'};">${c.partnerName}</h3>
                            <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 200px;">
                                ${isUnreadAndLocked ? '🔒 Locked Whisper' : (lastMsg.sender_id === currentUserId ? 'You: ' : '') + lastMsg.text}
                            </p>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            ${new Date(lastMsg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Dynamic footer unread update
        const msgBadge = document.getElementById('messagesUnreadBadge');
        if (msgBadge) {
            if (totalUnreadConvos > 0) {
                msgBadge.style.display = 'flex';
                msgBadge.innerText = totalUnreadConvos;
            } else {
                msgBadge.style.display = 'none';
            }
        }
        
        container.innerHTML = html || '<p style="text-align:center; color:var(--text-secondary);">No active whispers.</p>';
    },
    
    startWhisper: (userId, username) => {
        window.app.switchView('messages');
        if (username) {
            window.messages.openChat(userId, username);
        } else {
            const user = window.dataManager.cache.users?.find(u => u.id === userId);
            if (user) {
                window.messages.openChat(userId, user.username);
            } else {
                window.messages.openChat(userId, `User #${userId}`);
            }
        }
    },
    
    closeChat: () => {
        document.body.style.overflow = '';
        const overlay = document.getElementById('chatOverlay');
        if (overlay) overlay.remove();
        
        window.messages.activePartnerId = null;
        window.messages.activePartnerName = null;
        if (window.messages.pollInterval) {
            clearInterval(window.messages.pollInterval);
            window.messages.pollInterval = null;
        }
    },

    openChat: (partnerId, partnerName) => {
        // Build an overlay for chat
        const existing = document.getElementById('chatOverlay');
        if (existing) existing.remove();
        
        window.messages.activePartnerId = partnerId;
        window.messages.activePartnerName = partnerName;
        
        const currentUserId = window.globals.currentUser?.id;
        const user = window.globals.currentUser || {};
        
        const msgs = (window.dataManager.cache.messages || []).filter(m => 
            (m.sender_id === currentUserId && m.receiver_id === partnerId) ||
            (m.sender_id === partnerId && m.receiver_id === currentUserId)
        );
        
        // Find highest message ID to start tracking from
        let maxId = 0;
        msgs.forEach(m => {
            if (m.id && Number(m.id) > maxId) maxId = Number(m.id);
        });
        window.messages.lastMessageId = maxId;
        
        const overlay = document.createElement('div');
        overlay.id = 'chatOverlay';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.backgroundColor = 'var(--obsidian)';
        overlay.style.zIndex = '1000';
        overlay.style.display = 'flex';
        overlay.style.flexDirection = 'column';
        overlay.style.overflow = 'hidden';
        
        // Lock body scrolling while chat is active
        document.body.style.overflow = 'hidden';
        
        // Check if the chat should be locked/blurred for initiation
        const isLocked = msgs.length === 0 && Number(user.has_used_free_whisper) === 1;
        
        let msgsHtml = '';
        if (isLocked) {
            const hasVA = user.monnify_account_number && user.monnify_bank_name;
            const balanceText = `₦${user.wallet_balance || '0.00'}`;
            const isInsufficient = Number(user.wallet_balance) < 500;
            
            let paymentSection = '';
            if (isInsufficient) {
                paymentSection = `
                    <div style="background:rgba(0,0,0,0.5); border:1px solid rgba(255,42,109,0.3); border-radius:16px; padding:15px; margin-top:15px; font-size:0.85rem; text-align:left; color:#ccc; width:100%; max-width:340px;">
                        <p style="color:#ff2a6d; font-weight:bold; margin:0 0 10px; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <i class="fas fa-exclamation-triangle"></i> Insufficient Wallet Balance (${balanceText})
                        </p>
                        ${hasVA ? `
                            <p style="margin: 0 0 8px; font-size:0.75rem; color:#aaa; text-align:center;">Transfer to your dedicated Temple virtual account to credit your wallet instantly:</p>
                            <div style="background:rgba(255,42,109,0.05); border:1px solid rgba(255,42,109,0.15); padding:12px; border-radius:10px; margin-bottom:10px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:0.75rem;">
                                    <span>Bank Name:</span> <strong style="color:white;">${user.monnify_bank_name}</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:0.75rem;">
                                    <span>Account No:</span> <strong style="color:#2ecc71; letter-spacing:1px; font-size:0.95rem;">${user.monnify_account_number}</strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:0.75rem;">
                                    <span>Account Name:</span> <strong style="color:white;">EazyMUZE - ${user.username}</strong>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button onclick="window.messages.copyAccount('${user.monnify_account_number}')" style="flex:1; background:var(--neon-pink); border:none; padding:8px 10px; border-radius:8px; color:white; font-size:0.75rem; cursor:pointer; font-weight:bold; display:flex; align-items:center; justify-content:center; gap:4px;"><i class="fas fa-copy"></i> Copy No</button>
                                <button onclick="window.messages.fundViaSDK(500)" style="flex:1; background:#0088cc; border:none; padding:8px 10px; border-radius:8px; color:white; font-size:0.75rem; cursor:pointer; font-weight:bold; display:flex; align-items:center; justify-content:center; gap:4px;"><i class="fas fa-credit-card"></i> Pay Online</button>
                            </div>
                        ` : `
                            <p style="margin: 0 0 10px; text-align:center;">Please fund your wallet online using Monnify Gateway:</p>
                            <button onclick="window.messages.fundViaSDK(500)" style="width:100%; background:linear-gradient(135deg, #ff2a6d, #8e1a1a); border:none; padding:10px; border-radius:8px; color:white; font-size:0.8rem; cursor:pointer; font-weight:bold;"><i class="fas fa-credit-card"></i> Fund Wallet Online</button>
                        `}
                    </div>
                `;
            } else {
                paymentSection = `
                    <button onclick="window.messages.initiate(${partnerId}, '${partnerName}')" style="background: linear-gradient(135deg, var(--neon-pink), var(--velvet)); border:none; padding:12px 25px; color:white; font-size:0.9rem; border-radius:30px; font-weight: bold; cursor:pointer; box-shadow: 0 0 15px rgba(255,42,109,0.5); width:100%; max-width:280px; margin-top:15px; display:flex; align-items:center; justify-content:center; gap:8px; transition:0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="fas fa-key"></i> Pay ₦500 to Whisper 💋
                    </button>
                    <p style="font-size:0.75rem; color:grey; margin-top:8px;">Current Wallet Balance: <strong style="color:var(--neon-pink);">${balanceText}</strong></p>
                `;
            }
            
            msgsHtml = `
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:80%; text-align:center; padding:20px; font-family:'Outfit', sans-serif;">
                    <div class="heartbeat" style="background: rgba(255,42,109,0.1); border: 2px solid var(--neon-pink); width:70px; height:70px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 0 20px rgba(255,42,109,0.3); margin-bottom:20px;">
                        <i class="fas fa-lock" style="font-size:2rem; color:var(--neon-pink);"></i>
                    </div>
                    <h3 style="color:white; margin:0 0 10px; font-size:1.3rem;">EazyMUZE Black Market Whisper</h3>
                    <p style="color:var(--text-secondary); font-size:0.85rem; max-width:320px; line-height:1.5; margin:0 0 10px;">
                        A one-time initiation fee of <strong style="color:var(--neon-pink);">₦500</strong> is required to open a whisper channel with <strong style="color:white;">${partnerName}</strong>. 
                    </p>
                    <p style="color:var(--text-muted); font-size:0.75rem; max-width:300px; line-height:1.4; margin:0;">
                        Subsequent replies in this thread are completely free. 💋
                    </p>
                    ${paymentSection}
                </div>
            `;
        } else {
            if (msgs.length === 0) {
                msgsHtml = `<p style="text-align:center; color:var(--text-secondary); margin-top: 40px;">Your first whisper is completely free! Go ahead and say hello... 💋</p>`;
            } else {
                msgs.forEach(m => {
                    const isMe = m.sender_id === currentUserId;
                    const time = new Date(m.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    if (!isMe && !m.is_read) {
                        const hasFreeRead = Number(user.has_used_free_read) === 0;
                        const unlockText = hasFreeRead ? 'Unlock for Free 💋' : 'Unlock for ₦200 💋';
                        msgsHtml += `
                            <div class="chat-message chat-received" style="position: relative; overflow: hidden; padding: 12px 15px; margin-bottom:12px;">
                                <span style="filter: blur(6px); opacity: 0.2; user-select: none; pointer-events: none; display: inline-block;">
                                    ${m.text || "Hey baby, check out my latest private moments and let us connect tonight..."}
                                </span>
                                <div style="position: absolute; inset: 0; background: rgba(10, 4, 6, 0.75); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5px;">
                                    <span style="font-size: 0.75rem; font-weight: bold; color: #ffb3c6; text-shadow: 0 0 5px black; margin-bottom: 4px;">🔞 Locked Whisper</span>
                                    <button onclick="window.messages.unlock(${m.id}, ${partnerId}, '${partnerName}')" style="background: linear-gradient(135deg, var(--neon-pink), var(--velvet)); border:none; padding:4px 10px; color:white; font-size:0.7rem; border-radius:5px; font-weight: bold; cursor:pointer; box-shadow: 0 0 10px rgba(255,42,109,0.5);">${unlockText}</button>
                                </div>
                            </div>
                        `;
                    } else {
                        msgsHtml += `
                            <div class="chat-message ${isMe ? 'chat-sent' : 'chat-received'}">
                                ${m.text}
                                <span class="chat-timestamp">${time}</span>
                            </div>
                        `;
                    }
                });
            }
        }
        
        overlay.innerHTML = `
            <header style="padding: 15px; background: var(--glass); display: flex; align-items: center; border-bottom: 1px solid var(--glass-border);">
                <i class="fas fa-arrow-left" style="font-size: 1.5rem; margin-right: 15px; cursor: pointer;" onclick="window.messages.closeChat()"></i>
                <h3 style="margin: 0; color: white;">${partnerName}</h3>
            </header>
            <div id="chatHistory" style="flex: 1; padding: 15px 15px 85px 15px; overflow-y: auto; background: var(--velvet-bg);">
                ${msgsHtml}
            </div>
            
            <div id="emojiPicker" class="emoji-picker">
                <span onclick="window.messages.addEmoji('😘')">😘</span>
                <span onclick="window.messages.addEmoji('💦')">💦</span>
                <span onclick="window.messages.addEmoji('🍆')">🍆</span>
                <span onclick="window.messages.addEmoji('🍑')">🍑</span>
                <span onclick="window.messages.addEmoji('💋')">💋</span>
                <span onclick="window.messages.addEmoji('😈')">😈</span>
                <span onclick="window.messages.addEmoji('💸')">💸</span>
                <span onclick="window.messages.addEmoji('🍾')">🍾</span>
            </div>
            
            ${isLocked ? `
                <div class="chat-toolbar" style="opacity: 0.45; pointer-events: none; background: rgba(10, 4, 6, 0.98);">
                    <i class="far fa-smile"></i>
                    <i class="fas fa-paperclip"></i>
                    <textarea id="chatInput" disabled placeholder="🔒 Unlock conversation to whisper..." style="flex:1; padding:10px 15px; border-radius:15px; border:none; background:rgba(255,255,255,0.1); color:white; outline:none; resize:none; height:40px; font-family:inherit;"></textarea>
                    <button class="btn-primary" disabled style="padding: 8px 15px; border-radius: 50%; height: 40px; width: 40px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-paper-plane"></i></button>
                </div>
            ` : `
                <div class="chat-toolbar">
                    <i class="far fa-smile" onclick="document.getElementById('emojiPicker').style.display = document.getElementById('emojiPicker').style.display === 'grid' ? 'none' : 'grid'"></i>
                    <i class="fas fa-paperclip" onclick="window.utils.showToast('Attachments coming soon!')"></i>
                    <textarea id="chatInput" placeholder="Whisper your desire..." style="flex:1; padding:10px 15px; border-radius:15px; border:none; background:rgba(255,255,255,0.1); color:white; outline:none; resize:none; height:40px; font-family:inherit;"></textarea>
                    <button class="btn-primary" style="padding: 8px 15px; border-radius: 50%; height: 40px; width: 40px; display: flex; align-items: center; justify-content: center;" onclick="window.messages.send(${partnerId}, '${partnerName}')"><i class="fas fa-paper-plane"></i></button>
                </div>
            `}
        `;
        
        document.body.appendChild(overlay);
        const history = document.getElementById('chatHistory');
        history.scrollTop = history.scrollHeight;

        // Setup active chat polling loop
        if (window.messages.pollInterval) {
            clearInterval(window.messages.pollInterval);
        }
        
        window.messages.pollInterval = setInterval(async () => {
            if (!window.messages.activePartnerId) return;
            
            const res = await window.api.get(`messages.php?action=sync&last_id=${window.messages.lastMessageId}`);
            if (res.status === 'success' && res.data && res.data.length > 0) {
                let updated = false;
                
                res.data.forEach(newMsg => {
                    // Check if message is already in cache
                    const exists = window.dataManager.cache.messages.some(m => m.id === newMsg.id);
                    if (!exists) {
                        window.dataManager.cache.messages.push(newMsg);
                        if (Number(newMsg.id) > window.messages.lastMessageId) {
                            window.messages.lastMessageId = Number(newMsg.id);
                        }
                        updated = true;
                        
                        // Append dynamically if it belongs to this active chat
                        const isFromPartner = Number(newMsg.sender_id) === Number(partnerId);
                        const isFromMe = Number(newMsg.sender_id) === Number(currentUserId);
                        
                        if (isFromPartner || isFromMe) {
                            const chatHistory = document.getElementById('chatHistory');
                            if (chatHistory) {
                                // Clear placeholders
                                if (chatHistory.innerHTML.includes('first whisper is completely free') || chatHistory.innerHTML.includes('No active whispers')) {
                                    chatHistory.innerHTML = '';
                                }
                                
                                const time = new Date(newMsg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                                let bubbleHtml = '';
                                
                                if (isFromPartner && !newMsg.is_read) {
                                    // Play sound for incoming message
                                    window.utils.playNotificationSound('message');
                                    
                                    const hasFreeRead = Number(user.has_used_free_read) === 0;
                                    const unlockText = hasFreeRead ? 'Unlock for Free 💋' : 'Unlock for ₦200 💋';
                                    bubbleHtml = `
                                        <div class="chat-message chat-received fade-in-up" id="msg_${newMsg.id}" style="position: relative; overflow: hidden; padding: 12px 15px; margin-bottom:12px;">
                                            <span style="filter: blur(6px); opacity: 0.2; user-select: none; pointer-events: none; display: inline-block;">
                                                ${newMsg.text || "Hey baby, connect tonight..."}
                                            </span>
                                            <div style="position: absolute; inset: 0; background: rgba(10, 4, 6, 0.75); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5px;">
                                                <span style="font-size: 0.75rem; font-weight: bold; color: #ffb3c6; text-shadow: 0 0 5px black; margin-bottom: 4px;">🔞 Locked Whisper</span>
                                                <button onclick="window.messages.unlock(${newMsg.id}, ${partnerId}, '${partnerName}')" style="background: linear-gradient(135deg, var(--neon-pink), var(--velvet)); border:none; padding:4px 10px; color:white; font-size:0.7rem; border-radius:5px; font-weight: bold; cursor:pointer; box-shadow: 0 0 10px rgba(255,42,109,0.5);">${unlockText}</button>
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    if (isFromPartner) {
                                        window.utils.playNotificationSound('message');
                                    }
                                    bubbleHtml = `
                                        <div class="chat-message ${isFromMe ? 'chat-sent' : 'chat-received'} fade-in-up" id="msg_${newMsg.id}">
                                            ${newMsg.text}
                                            <span class="chat-timestamp">${time} ${isFromMe ? '<i class="fas fa-check" style="color:#2ecc71; font-size:0.6rem; margin-left: 2px;"></i>' : ''}</span>
                                        </div>
                                    `;
                                }
                                
                                chatHistory.innerHTML += bubbleHtml;
                                chatHistory.scrollTop = chatHistory.scrollHeight;
                            }
                        }
                    }
                });
                
                if (updated) {
                    localStorage.setItem('eazymuze_cache', JSON.stringify(window.dataManager.cache));
                    window.messages.render();
                }
            }
        }, 2000);
    },
    
    send: async (partnerId, partnerName) => {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        if (!text) return;
        
        input.value = '';
        input.style.height = '40px'; // Reset height
        
        // Optimistically insert user message instantly for seamless chat feel
        const chatHistory = document.getElementById('chatHistory');
        const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const tempMsgId = 'temp_' + Date.now();
        
        const tempMsgHtml = `
            <div class="chat-message chat-sent fade-in-up" id="${tempMsgId}" style="opacity: 0.9;">
                ${text}
                <span class="chat-timestamp">${time} <i class="fas fa-circle-notch fa-spin" style="font-size:0.5rem; margin-left: 2px;"></i></span>
            </div>
        `;
        
        // If "no whispers" placeholder is active, clear it first
        if (chatHistory.innerHTML.includes('first whisper is completely free') || chatHistory.innerHTML.includes('No active whispers')) {
            chatHistory.innerHTML = '';
        }
        
        chatHistory.innerHTML += tempMsgHtml;
        chatHistory.scrollTop = chatHistory.scrollHeight;
        
        const loc = window.globals.currentLocation || 'Unknown';
        
        const res = await window.api.post('messages.php?action=send', { receiver_id: partnerId, text, location_data: loc });
        
        if (res.status === 'success') {
            // Replace loading icon with check
            const tempEl = document.getElementById(tempMsgId);
            if (tempEl) {
                const ts = tempEl.querySelector('.chat-timestamp');
                if (ts) ts.innerHTML = `${time} <i class="fas fa-check" style="color:#2ecc71; font-size:0.6rem;"></i>`;
            }
            
            // Refresh session details & data
            const userRes = await window.api.get('auth.php?action=session');
            if (userRes.status === 'success') {
                window.globals.currentUser = userRes.data;
                localStorage.setItem('currentUser', JSON.stringify(userRes.data));
            }

            await window.dataManager.loadMessages();
            
            // Simulating real-time typing response for seeded bot profiles
            if (partnerId <= 50) {
                // Instantly inject typing indicator
                const typingId = 'typing_' + Date.now();
                const typingHtml = `
                    <div class="chat-message chat-received" id="${typingId}" style="display: flex; align-items: center; gap: 5px; width: fit-content; padding: 10px 15px;">
                        <span style="color:var(--text-secondary); font-size: 0.8rem; font-style: italic;">${partnerName} is whispering</span>
                        <div style="display:flex; gap:3px;">
                            <span class="typing-dot">.</span>
                            <span class="typing-dot" style="animation-delay: 0.2s;">.</span>
                            <span class="typing-dot" style="animation-delay: 0.4s;">.</span>
                        </div>
                    </div>
                `;
                chatHistory.innerHTML += typingHtml;
                chatHistory.scrollTop = chatHistory.scrollHeight;
                
                setTimeout(async () => {
                    const typingEl = document.getElementById(typingId);
                    if (typingEl) typingEl.remove();
                    
                    await window.dataManager.loadMessages();
                    window.messages.openChat(partnerId, partnerName);
                }, 2500);
            } else {
                window.messages.openChat(partnerId, partnerName);
            }
        } else {
            const tempEl = document.getElementById(tempMsgId);
            if (tempEl) tempEl.remove();
            
            window.utils.showToast(res.message, 'error');
            if (res.message.includes('Insufficient')) {
                window.utils.showToast('Please fund your wallet via the Temple', 'error');
            }
        }
    },
    
    unlock: async (msgId, partnerId, partnerName) => {
        const res = await window.api.post('messages.php?action=unlock', { message_id: msgId });
        if (res.status === 'success') {
            window.utils.showToast('Whisper Unlocked');
            
            // Refresh session info so we have the updated wallet balance and free whisper status
            const userRes = await window.api.get('auth.php?action=session');
            if (userRes.status === 'success') {
                window.globals.currentUser = userRes.data;
                localStorage.setItem('currentUser', JSON.stringify(userRes.data));
            }

            await window.dataManager.loadMessages();
            window.messages.openChat(partnerId, partnerName);
        } else {
            window.utils.showToast(res.message, 'error');
        }
    },
    
    initiate: async (partnerId, partnerName) => {
        window.utils.showToast('Initiating whisper connection...', 'success');
        const res = await window.api.post('messages.php?action=initiate_convo', { receiver_id: partnerId });
        if (res.status === 'success') {
            window.utils.showToast('💋 Whisper Channel Activated!');
            
            // Refresh session info so we have the updated wallet balance and free whisper status
            const userRes = await window.api.get('auth.php?action=session');
            if (userRes.status === 'success') {
                window.globals.currentUser = userRes.data;
                localStorage.setItem('currentUser', JSON.stringify(userRes.data));
            }
            
            await window.dataManager.loadMessages();
            window.messages.openChat(partnerId, partnerName);
        } else {
            window.utils.showToast(res.message, 'error');
        }
    },
    
    copyAccount: (acctNum) => {
        navigator.clipboard.writeText(acctNum).then(() => {
            window.utils.showToast('Virtual account number copied! 📋');
        }).catch(() => {
            window.utils.showToast(acctNum, 'success');
        });
    },

    fundViaSDK: (amount) => {
        window.utils.showToast('Opening payment gateway...', 'success');
        if (window.MonnifySDK) {
            const user = window.globals.currentUser;
            MonnifySDK.initialize({
                amount: amount || 5000,
                currency: "NGN",
                reference: 'EMZ-FUND-' + user.id + '-' + Date.now(),
                customerName: user.fullname || user.username,
                customerEmail: user.email,
                apiKey: "MK_PROD_Z1N1VE409T",
                contractCode: "479854013966",
                paymentDescription: "EazyMUZE Whisper Unlock Funding",
                metadata: {
                    name: "Powered by Welfus",
                    type: "wallet_funding"
                },
                onLoadStart: () => {},
                onComplete: async function(response) {
                    if (response.status === "SUCCESS") {
                        window.utils.showToast('💰 Wallet Funded Successfully!');
                        // Refresh session to get new balance
                        const userRes = await window.api.get('auth.php?action=session');
                        if (userRes.status === 'success') {
                            window.globals.currentUser = userRes.data;
                            localStorage.setItem('currentUser', JSON.stringify(userRes.data));
                        }
                        // Re-open chat to update UI state
                        window.messages.openChat(window.messages.activePartnerId, window.messages.activePartnerName);
                    }
                },
                onClose: function(data) {
                    window.utils.showToast('Payment window closed', 'error');
                }
            });
        } else {
            window.utils.showToast('Monnify SDK unavailable. Transfer to virtual account instead.', 'error');
        }
    },
    
    addEmoji: (emoji) => {
        const input = document.getElementById('chatInput');
        input.value += emoji;
        document.getElementById('emojiPicker').style.display = 'none';
        input.focus();
    }
};
