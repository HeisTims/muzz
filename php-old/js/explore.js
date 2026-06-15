// EazyMUZE v2.5 - Explore Module
window.explore = {
    render: async () => {
        // Fetch all users to explore
        const res = await window.api.get('admin.php?action=users&pin=admin123'); // Using admin endpoint for now just to fetch users, in a real app create a public users endpoint
        if (res.status === 'success') {
            window.dataManager.cache.users = res.data;
            window.explore.renderResults(res.data);
        }
        
        document.getElementById('searchFilter').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = window.dataManager.cache.users.filter(u => 
                (u.location && u.location.toLowerCase().includes(query)) || 
                (u.preference && u.preference.toLowerCase().includes(query)) ||
                (u.username && u.username.toLowerCase().includes(query))
            );
            window.explore.renderResults(filtered);
        });
    },
    
    renderResults: (users) => {
        const container = document.getElementById('exploreResults');
        let html = '';
        
        users.forEach(u => {
            if (u.id === window.globals.currentUser?.id) return; // Skip self
            
            html += `
                <div class="glass-panel" style="margin-bottom: 10px; display: flex; align-items: center; gap: 15px;">
                    <img src="${u.avatar || 'https://via.placeholder.com/60'}" style="width: 60px; height: 60px; border-radius: 50%;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0; color: var(--text-primary);">${u.username} ${u.is_verified ? '<i class="fas fa-check-circle" style="color:var(--neon-pink);"></i>' : ''}</h3>
                        <p style="margin: 0; font-size: 0.8rem; color: var(--text-secondary);">${u.preference} • ${u.location || 'Unknown'}</p>
                    </div>
                    <div>
                        <button class="btn-primary" style="padding: 8px 15px; font-size: 0.8rem;" onclick="window.messages.startWhisper(${u.id}, '${u.username}')">Whisper</button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html || '<p style="text-align:center; color:var(--text-secondary);">No desires found.</p>';
    }
};
