<?php
$page_title = "EazyMUZE — Invites & Connections 💕";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch active invites sent to this user
$invites_stmt = $pdo->prepare("
    SELECT i.*, u.username AS sender_name, u.avatar AS sender_avatar, 
           u.preference AS sender_pref, u.location AS sender_location,
           u.is_verified AS sender_verified,
           u.bio AS sender_bio
    FROM invites i
    JOIN users u ON i.sender_id = u.id
    WHERE i.receiver_id = ? AND i.status = 'pending'
    ORDER BY i.created_at DESC
");
$invites_stmt->execute([$user_id]);
$received_invites = $invites_stmt->fetchAll();

// Sent invites
$sent_stmt = $pdo->prepare("
    SELECT i.*, u.username AS receiver_name, u.avatar AS receiver_avatar, u.is_verified AS receiver_verified
    FROM invites i
    JOIN users u ON i.receiver_id = u.id
    WHERE i.sender_id = ? ORDER BY i.created_at DESC LIMIT 20
");
$sent_stmt->execute([$user_id]);
$sent_invites = $sent_stmt->fetchAll();
?>

<div style="padding:0 15px 30px;">
    <h2 style="font-size:1.4rem; margin-bottom:6px; color:white;">Invites & Connections 💕</h2>
    <p style="color:var(--text-secondary); font-size:0.85rem; margin-bottom:20px;">People who want to connect with you</p>
    
    <!-- Tabs -->
    <div style="display:flex; gap:0; border-bottom:1px solid rgba(255,255,255,0.08); margin-bottom:20px;">
        <button class="invite-tab active" id="itab-received" onclick="switchInviteTab('received')">
            Received <?php if (!empty($received_invites)): ?><span style="background:var(--neon-pink); color:white; font-size:0.6rem; padding:2px 6px; border-radius:10px; margin-left:5px;"><?php echo count($received_invites); ?></span><?php endif; ?>
        </button>
        <button class="invite-tab" id="itab-sent" onclick="switchInviteTab('sent')">Sent</button>
    </div>
    
    <!-- Received Invites -->
    <div id="panel-received">
        <?php if (empty($received_invites)): ?>
        <div style="text-align:center; padding:50px 20px;">
            <div style="font-size:3.5rem; margin-bottom:14px;">💕</div>
            <p style="color:var(--text-secondary); font-size:0.9rem;">No new invites yet.</p>
            <p style="color:var(--text-muted); font-size:0.82rem; margin-top:6px;">Explore the temple to get noticed.</p>
            <a href="explore.php" class="btn-primary" style="display:inline-block; margin-top:16px; text-decoration:none;">Explore</a>
        </div>
        <?php else: ?>
        <?php foreach($received_invites as $inv):
            $senderAvatar = $inv['sender_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($inv['sender_name']) . '&background=8e1a1a&color=fff';
        ?>
        <div class="glass-panel" style="margin-bottom:14px; padding:16px; border-radius:18px; display:flex; flex-direction:column; gap:12px;">
            <!-- User row -->
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="<?php echo esc($senderAvatar); ?>" style="width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid var(--neon-pink);" loading="lazy">
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:5px;">
                        <h3 style="margin:0; font-size:1rem; color:white; font-weight:700;"><?php echo esc($inv['sender_name']); ?></h3>
                        <?php if ($inv['sender_verified']): ?><i class="fas fa-check-circle" style="color:var(--neon-pink); font-size:0.75rem;"></i><?php endif; ?>
                        <span style="background:rgba(255,42,109,0.15); color:var(--neon-pink); font-size:0.65rem; padding:2px 7px; border-radius:8px;"><?php echo esc($inv['sender_pref'] ?? ''); ?></span>
                    </div>
                    <p style="margin:3px 0 0; font-size:0.78rem; color:var(--text-secondary);">
                        <i class="fas fa-map-marker-alt" style="font-size:0.7rem;"></i> <?php echo esc($inv['sender_location'] ?? 'Unknown'); ?>
                    </p>
                    <?php if (!empty($inv['sender_bio'])): ?>
                    <p style="margin:4px 0 0; font-size:0.78rem; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">"<?php echo esc(substr($inv['sender_bio'], 0, 60)); ?>"</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($inv['message'])): ?>
            <div style="background:rgba(255,42,109,0.08); border-left:3px solid var(--neon-pink); padding:10px 14px; border-radius:0 10px 10px 0;">
                <p style="margin:0; font-size:0.85rem; color:var(--text-primary); font-style:italic;">"<?php echo esc($inv['message']); ?>"</p>
            </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div style="display:flex; gap:10px;">
                <button class="btn-primary" style="flex:1; padding:11px;" onclick="respondInvite(<?php echo $inv['id']; ?>, 'accept', this)">
                    <i class="fas fa-heart"></i> Accept
                </button>
                <button onclick="respondInvite(<?php echo $inv['id']; ?>, 'decline', this)" style="flex:1; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:var(--text-secondary); padding:11px; border-radius:10px; cursor:pointer; font-family:inherit; font-size:0.9rem; transition:all 0.2s;">
                    <i class="fas fa-times"></i> Decline
                </button>
                <button onclick="window.location.href='chat.php?partner_id=<?php echo $inv['sender_id']; ?>&partner_name=<?php echo urlencode($inv['sender_name']); ?>'" style="padding:11px 14px; background:rgba(255,42,109,0.12); border:1px solid rgba(255,42,109,0.3); color:var(--neon-pink); border-radius:10px; cursor:pointer; font-size:0.9rem;">
                    <i class="fas fa-comment"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Sent Invites -->
    <div id="panel-sent" style="display:none;">
        <?php if (empty($sent_invites)): ?>
        <p style="text-align:center; color:var(--text-secondary); padding:40px 20px; font-size:0.9rem;">You haven't sent any invites yet.</p>
        <?php else: ?>
        <?php foreach($sent_invites as $inv):
            $rAvatar = $inv['receiver_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($inv['receiver_name']) . '&background=8e1a1a&color=fff';
            $statusColors = ['pending' => '#f1c40f', 'accepted' => '#2ecc71', 'declined' => '#e74c3c'];
            $statusColor  = $statusColors[$inv['status']] ?? '#aaa';
        ?>
        <div class="glass-panel" style="margin-bottom:10px; padding:14px 16px; border-radius:14px; display:flex; align-items:center; gap:14px;">
            <img src="<?php echo esc($rAvatar); ?>" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid <?php echo $statusColor; ?>;" loading="lazy">
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:5px;">
                    <h3 style="margin:0; font-size:0.95rem; color:white;"><?php echo esc($inv['receiver_name']); ?></h3>
                    <?php if ($inv['receiver_verified']): ?><i class="fas fa-check-circle" style="color:var(--neon-pink); font-size:0.7rem;"></i><?php endif; ?>
                </div>
                <p style="margin:3px 0 0; font-size:0.75rem;" style="color:<?php echo $statusColor; ?>">
                    <span style="color:<?php echo $statusColor; ?>; font-weight:600;">● <?php echo ucfirst($inv['status']); ?></span>
                </p>
            </div>
            <span style="font-size:0.72rem; color:var(--text-muted);"><?php echo date('M j', strtotime($inv['created_at'])); ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.invite-tab {
    flex: 1;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--text-secondary);
    padding: 12px;
    font-size: 0.9rem;
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    font-weight: 600;
    transition: all 0.2s;
}
.invite-tab.active {
    color: var(--neon-pink);
    border-bottom-color: var(--neon-pink);
}
</style>

<script>
function switchInviteTab(tab) {
    ['received', 'sent'].forEach(t => {
        const panel = document.getElementById('panel-' + t);
        const tabEl = document.getElementById('itab-' + t);
        if (panel) panel.style.display = t === tab ? 'block' : 'none';
        if (tabEl) tabEl.classList.toggle('active', t === tab);
    });
}

async function respondInvite(inviteId, action, btn) {
    const card = btn.closest('.glass-panel');
    const res = await fetch(window.apiUrl + 'invites.php?action=respond', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ invite_id: inviteId, action, csrf_token: window.csrfToken })
    }).then(r => r.json());
    
    if (res.status === 'success') {
        window.utils.showToast(action === 'accept' ? '💕 Connection made! You can now whisper.' : 'Invite declined.');
        if (card) { card.style.opacity = '0'; card.style.transform = 'translateX(-30px)'; setTimeout(() => card.remove(), 400); }
    } else {
        window.utils.showToast(res.message || 'Failed', 'error');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
