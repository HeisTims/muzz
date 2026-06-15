<?php
$page_title = "EazyMUZE — Explore Desires 🔍";
require_once 'includes/header.php';

$user_id  = $_SESSION['user_id'];
$fiveMinAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Fetch all users except self, with active status
$users_stmt = $pdo->prepare("
    SELECT id, username, avatar, preference, location, bio, is_verified, gender,
           (CASE WHEN last_seen > :five_min THEN 1 ELSE 0 END) AS is_online
    FROM users
    WHERE id != :me
    ORDER BY is_online DESC, id DESC
");
$users_stmt->execute([':five_min' => $fiveMinAgo, ':me' => $user_id]);
$users = $users_stmt->fetchAll();

// Update last_seen for this user
$pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$user_id]);
?>

<div style="padding: 0 15px 20px;">
    <h2 style="font-size: 1.4rem; margin-bottom: 15px; color: white;">Explore Desires</h2>
    
    <!-- Search & Filter -->
    <div style="position: relative; margin-bottom: 20px;">
        <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.9rem;"></i>
        <input type="text" id="searchFilter" placeholder="Search City, Preference, Username..." 
               style="width: 100%; padding: 12px 14px 12px 38px; border-radius: 25px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: white; outline: none; font-size: 0.9rem;"
               oninput="filterUsers(this.value)">
    </div>
    
    <!-- Filter Pills -->
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; scrollbar-width: none; margin-bottom: 20px;" id="filterPills">
        <button class="filter-pill active" onclick="filterByPref('all', this)">All</button>
        <button class="filter-pill" onclick="filterByPref('straight', this)">Straight</button>
        <button class="filter-pill" onclick="filterByPref('gay', this)">Gay</button>
        <button class="filter-pill" onclick="filterByPref('lesbian', this)">Lesbian</button>
        <button class="filter-pill" onclick="filterByPref('bisexual', this)">Bisexual</button>
        <button class="filter-pill" onclick="filterByPref('sugar_daddy', this)">Sugar Daddy</button>
        <button class="filter-pill" onclick="filterByPref('sugar_mummy', this)">Sugar Mummy</button>
    </div>
    
    <!-- Results Grid -->
    <div id="exploreResults">
        <?php foreach($users as $u): 
            $isOnline = $u['is_online'] == 1;
            $avatar   = $u['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($u['username']) . '&background=8e1a1a&color=fff';
        ?>
        <div class="explore-card glass-panel" 
             data-username="<?php echo esc(strtolower($u['username'])); ?>"
             data-pref="<?php echo esc(strtolower($u['preference'] ?? '')); ?>"
             data-location="<?php echo esc(strtolower($u['location'] ?? '')); ?>"
             style="margin-bottom: 12px; display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px;">
            
            <!-- Avatar with online indicator -->
            <div style="position: relative; flex-shrink: 0;">
                <img src="<?php echo esc($avatar); ?>" 
                     style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $isOnline ? '#2ecc71' : 'var(--glass-border)'; ?>; <?php echo $isOnline ? 'box-shadow: 0 0 10px rgba(46,204,113,0.4);' : ''; ?>"
                     loading="lazy">
                <?php if ($isOnline): ?>
                <div style="position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #2ecc71; border-radius: 50%; border: 2px solid var(--velvet-bg); box-shadow: 0 0 6px #2ecc71;"></div>
                <?php endif; ?>
            </div>
            
            <!-- User Info -->
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 1rem; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo esc($u['username']); ?>
                    </h3>
                    <?php if ($u['is_verified']): ?>
                    <i class="fas fa-check-circle" style="color: var(--neon-pink); font-size: 0.8rem;"></i>
                    <?php endif; ?>
                    <span style="background: rgba(255,42,109,0.12); color: var(--neon-pink); font-size: 0.65rem; padding: 2px 7px; border-radius: 8px; white-space: nowrap;">
                        <?php echo esc($u['preference'] ?? 'muze'); ?>
                    </span>
                </div>
                <p style="margin: 4px 0 0; font-size: 0.78rem; color: var(--text-secondary);">
                    <i class="fas fa-map-marker-alt" style="font-size: 0.7rem;"></i> <?php echo esc($u['location'] ?: 'Unknown'); ?>
                    <?php if ($isOnline): ?>
                    &nbsp;<span style="color: #2ecc71; font-size: 0.7rem; font-weight: 600;">● Active now</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($u['bio'])): ?>
                <p style="margin: 4px 0 0; font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px;">
                    "<?php echo esc($u['bio']); ?>"
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; flex-direction: column; gap: 6px; flex-shrink: 0;">
                <button onclick="window.location.href='chat.php?partner_id=<?php echo $u['id']; ?>&partner_name=<?php echo urlencode($u['username']); ?>'" 
                        class="btn-primary" style="padding: 7px 13px; font-size: 0.78rem; border-radius: 18px; white-space: nowrap;">
                    Whisper
                </button>
                <button onclick="openInviteModal(<?php echo $u['id']; ?>, '<?php echo esc($u['username']); ?>')" 
                        style="background: rgba(255,42,109,0.1); border: 1px solid rgba(255,42,109,0.3); color: var(--neon-pink); padding: 7px 13px; border-radius: 18px; font-size: 0.78rem; cursor: pointer; font-family:'Outfit',sans-serif; white-space: nowrap;">
                    Invite 💕
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($users)): ?>
        <p style="text-align: center; color: var(--text-secondary); margin-top: 40px;">No desires found in the temple yet.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.filter-pill {
    background: rgba(255,42,109,0.1);
    color: var(--neon-pink);
    border: 1px solid rgba(255,42,109,0.25);
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
}
.filter-pill.active, .filter-pill:hover {
    background: var(--neon-pink);
    color: white;
    border-color: var(--neon-pink);
}
</style>

<!-- =================== INVITE MODAL =================== -->
<div id="inviteModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:400px; width:90%; padding:25px;">
        <h3 style="color:var(--neon-pink); margin-bottom:6px; text-align:center;">Send Connection Invite 💕</h3>
        <p id="inviteToName" style="color:var(--text-secondary); text-align:center; font-size:0.82rem; margin-bottom:16px;"></p>
        <input type="hidden" id="inviteReceiverId">
        <textarea id="inviteMessage" rows="3" placeholder="Add a personal message (optional)..."
               style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; resize:none; font-family:inherit; margin-bottom:14px; box-sizing:border-box;"></textarea>
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="document.getElementById('inviteModal').style.display='none'">Cancel</button>
            <button class="btn-primary" style="flex:1;" onclick="sendInvite()">Send Invite 💕</button>
        </div>
    </div>
</div>

<script>
function filterUsers(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.explore-card').forEach(card => {
        const matches = !q ||
            card.dataset.username.includes(q) ||
            card.dataset.pref.includes(q) ||
            card.dataset.location.includes(q);
        card.style.display = matches ? 'flex' : 'none';
    });
}

function filterByPref(pref, btn) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.explore-card').forEach(card => {
        card.style.display = (pref === 'all' || card.dataset.pref === pref) ? 'flex' : 'none';
    });
}

function openInviteModal(userId, username) {
    document.getElementById('inviteReceiverId').value = userId;
    document.getElementById('inviteToName').textContent = 'Inviting @' + username + ' to connect';
    document.getElementById('inviteMessage').value = '';
    document.getElementById('inviteModal').style.display = 'flex';
}

async function sendInvite() {
    const receiver_id = document.getElementById('inviteReceiverId').value;
    const message     = document.getElementById('inviteMessage').value.trim();
    const res = await fetch('api/invites.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receiver_id: parseInt(receiver_id), message, csrf_token: window.csrfToken })
    }).then(r => r.json());
    window.utils.showToast(res.message || (res.status === 'success' ? 'Invite sent! 💕' : 'Failed'), res.status === 'success' ? 'success' : 'error');
    document.getElementById('inviteModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
