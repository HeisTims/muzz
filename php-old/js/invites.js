// EazyMUZE v2.5 - Invites Module
window.invites = {
    render: async () => {
        const container = document.getElementById('invitesContainer');
        if (!container) return;

        // Fetch session
        const sessRes = await window.api.get('auth.php?action=session');
        const currentUser = sessRes.data;
        const currentUserId = currentUser ? currentUser.id : null;

        const res = await window.api.get('invites.php?action=list');
        let invitesListHtml = '';

        if (res.status === 'success' && res.data.length > 0) {
            res.data.forEach(inv => {
                const volunteers = JSON.parse(inv.volunteers || '[]');
                const isHost = inv.user_id === currentUserId;
                const hasJoined = volunteers.some(v => v.id === currentUserId);
                const selectedVolunteers = volunteers.filter(v => v.selected);

                let volListHtml = '';
                if (volunteers.length > 0) {
                    volListHtml += `<div style="margin-top:10px; font-size:0.85rem; color:grey;"><strong>Interested queue:</strong>`;
                    volunteers.forEach(v => {
                        const isSelected = v.selected ? ' <span style="background:var(--neon-pink); color:white; font-size:0.65rem; padding:2px 5px; border-radius:5px; margin-left:5px;">CHOSEN 💋</span>' : '';
                        volListHtml += `<div style="padding:5px 0; display:flex; align-items:center; justify-content:space-between;">
                            <span>@${v.username} ${isSelected}</span>`;
                        if (isHost && !v.selected) {
                            volListHtml += `<input type="checkbox" class="vol-select-${inv.id}" value="${v.id}" style="width:auto;">`;
                        }
                        volListHtml += `</div>`;
                    });
                    volListHtml += `</div>`;
                } else {
                    volListHtml += `<p style="font-size:0.8rem; color:grey; margin-top:10px;">No interest expressed yet. Be the first!</p>`;
                }

                let hostAction = '';
                if (isHost && volunteers.length > 0 && selectedVolunteers.length < 5) {
                    hostAction = `<button class="btn-primary" style="width:100%; margin-top:10px;" onclick="window.invites.selectPartners(${inv.id})">Confirm Selection (Max 5)</button>`;
                } else if (!isHost && !hasJoined) {
                    hostAction = `<button class="btn-primary" style="width:100%; margin-top:10px;" onclick="window.invites.joinQueue(${inv.id})">I'm Interested (₦500)</button>`;
                } else if (hasJoined) {
                    hostAction = `<p style="color:var(--neon-pink); text-align:center; font-size:0.85rem; margin-top:10px;">Interest Registered ✨</p>`;
                }

                invitesListHtml += `
                    <div class="glass-panel fade-in-up" style="margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <strong style="color:var(--neon-pink); font-size:1.1rem;">${inv.title}</strong>
                            <small style="color:grey;">by @${inv.username}</small>
                        </div>
                        <p style="margin: 10px 0; color:white; font-size:0.95rem;">${inv.description}</p>
                        ${volListHtml}
                        ${hostAction}
                    </div>
                `;
            });
        } else {
            invitesListHtml = '<p style="text-align:center; color:var(--text-secondary);">No active event invites in your neighborhood.</p>';
        }

        container.innerHTML = `
            <div class="glass-panel" style="margin-bottom: 25px;">
                <h3 style="color:var(--neon-pink); margin-bottom: 10px;">Host an Event / Party</h3>
                <p style="font-size:0.8rem; color:grey; margin-bottom: 15px;">Create an invite for ₦1,500. People can express interest for ₦500, and you can pick up to 5 people!</p>
                <input type="text" id="inviteTitle" placeholder="Title (e.g. Secret Pool Party 💦)" style="margin-bottom: 10px;">
                <textarea id="inviteDesc" rows="3" placeholder="Tell us who you're looking for (Gays, Hookups, Couples etc.)" style="margin-bottom: 15px;"></textarea>
                <button class="btn-primary" style="width:100%;" onclick="window.invites.postInvite()">Create Invite (₦1,500)</button>
            </div>
            
            <h3 style="color:white; margin-bottom: 15px;">Active Invites</h3>
            <div id="invitesFeed">${invitesListHtml}</div>
        `;
    },

    postInvite: async () => {
        const title = document.getElementById('inviteTitle').value.trim();
        const description = document.getElementById('inviteDesc').value.trim();

        if (!title || !description) {
            return window.utils.showToast('Please fill all fields', 'error');
        }

        const res = await window.api.post('invites.php?action=create', { title, description });
        if (res.status === 'success') {
            window.utils.showToast('Invite Created Successfully! 💋');
            window.invites.render();
        } else {
            window.utils.showToast(res.message, 'error');
        }
    },

    joinQueue: async (inviteId) => {
        const res = await window.api.post('invites.php?action=join', { invite_id: inviteId });
        if (res.status === 'success') {
            window.utils.showToast('Interest Registered! ₦500 charged.');
            window.invites.render();
        } else {
            window.utils.showToast(res.message, 'error');
        }
    },

    selectPartners: async (inviteId) => {
        const checkboxes = document.querySelectorAll(`.vol-select-${inviteId}:checked`);
        const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        if (selectedIds.length === 0) {
            return window.utils.showToast('Select at least one partner', 'error');
        }
        if (selectedIds.length > 5) {
            return window.utils.showToast('You can select a maximum of 5 partners', 'error');
        }

        const res = await window.api.post('invites.php?action=select_volunteers', { invite_id: inviteId, selected_ids: selectedIds });
        if (res.status === 'success') {
            window.utils.showToast('Partners selected! 💋');
            window.invites.render();
        } else {
            window.utils.showToast(res.message, 'error');
        }
    }
};
