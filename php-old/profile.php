<?php
$page_title = "EazyMUZE — Temple Profile";
require_once 'includes/header.php';

$user_id     = $_SESSION['user_id'];
$view_uid    = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user_id;
$is_own      = ($view_uid === $user_id);

// Fetch profile user
$stmt = $pdo->prepare("
    SELECT u.*,
           (CASE WHEN u.last_seen > ? THEN 1 ELSE 0 END) AS is_online
    FROM users u WHERE u.id = ?
");
$stmt->execute([date('Y-m-d H:i:s', strtotime('-5 minutes')), $view_uid]);
$profile = $stmt->fetch();
if (!$profile) { header('Location: explore.php'); exit; }

// Fetch their posts
$posts_stmt = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$posts_stmt->execute([$view_uid]);
$user_posts = $posts_stmt->fetchAll();

// Fetch bookmarked posts (only visible on own profile)
$bookmarks = [];
if ($is_own) {
    $bk_stmt = $pdo->prepare("
        SELECT p.*, u.username, u.avatar FROM bookmarks b
        JOIN posts p ON b.post_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE b.user_id = ? ORDER BY b.id DESC
    ");
    $bk_stmt->execute([$user_id]);
    $bookmarks = $bk_stmt->fetchAll();
}

// Post & like counts
$total_posts = count($user_posts);
$total_likes = 0;
foreach ($user_posts as $p) { $total_likes += count(json_decode($p['likes'] ?? '[]', true) ?: []); }

$avatar_url  = $profile['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($profile['username']) . '&background=8e1a1a&color=fff&size=200';
$cover_color = '#1a0b12'; // default cover bg
?>

<style>
.profile-cover {
    height: 180px;
    background: linear-gradient(135deg, #1a0b12 0%, #2d0f1e 50%, #0d0508 100%);
    position: relative;
    overflow: hidden;
}
.profile-cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(255,42,109,0.18) 0%, transparent 70%);
}
.profile-avatar-wrap {
    position: absolute;
    bottom: -40px;
    left: 20px;
    z-index: 10;
}
.profile-avatar-img {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--neon-pink);
    box-shadow: 0 0 20px rgba(255,42,109,0.5);
}
.profile-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin: 0 0 2px;
}
.profile-tab {
    flex: 1;
    text-align: center;
    padding: 14px 4px;
    font-size: 0.8rem;
    color: var(--text-secondary);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.profile-tab.active {
    color: var(--neon-pink);
    border-bottom-color: var(--neon-pink);
    font-weight: 700;
}
.profile-tab i { font-size: 1rem; }
.posts-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
}
.posts-grid-item {
    aspect-ratio: 1;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    background: #1a0b12;
}
.posts-grid-item img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.2s;
    display: block;
}
.posts-grid-item:hover img { transform: scale(1.05); }
.posts-grid-item .grid-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    gap: 10px;
}
.posts-grid-item:hover .grid-overlay { opacity: 1; }
.stat-pill {
    text-align: center;
    flex: 1;
    padding: 4px;
}
.stat-pill .num { font-size: 1.3rem; font-weight: 800; color: white; }
.stat-pill .lbl { font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
</style>

<!-- =================== COVER =================== -->
<div class="profile-cover">
    <?php if (!empty($profile['avatar'])): ?>
    <img src="<?php echo esc($profile['avatar']); ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.3; filter: blur(14px);">
    <?php endif; ?>
    <div class="profile-avatar-wrap">
        <div style="position: relative; display: inline-block;">
            <img src="<?php echo esc($avatar_url); ?>" class="profile-avatar-img" id="profileAvatar"
                 <?php if ($is_own): ?>onclick="document.getElementById('avatarUpload').click()"<?php endif; ?>>
            <?php if ($profile['is_online']): ?>
            <div style="position:absolute;bottom:3px;right:3px;width:14px;height:14px;background:#2ecc71;border-radius:50%;border:2px solid var(--velvet-bg);box-shadow:0 0 6px #2ecc71;"></div>
            <?php endif; ?>
            <?php if ($is_own): ?>
            <div style="position:absolute;bottom:0;right:0;width:26px;height:26px;background:var(--neon-pink);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--velvet-bg);" onclick="document.getElementById('avatarUpload').click()">
                <i class="fas fa-camera" style="font-size:0.6rem;color:white;"></i>
            </div>
            <input type="file" id="avatarUpload" accept="image/*" style="display:none;" onchange="uploadAvatar(this)">
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =================== PROFILE INFO =================== -->
<div style="padding: 50px 20px 12px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
            <h2 style="margin:0; font-size:1.25rem; color:white; display:flex; align-items:center; gap:6px;">
                <?php echo esc($profile['username']); ?>
                <?php if ($profile['is_verified']): ?>
                <i class="fas fa-check-circle" style="color:var(--neon-pink); font-size:0.9rem;"></i>
                <?php endif; ?>
            </h2>
            <p style="margin:4px 0 0; font-size:0.8rem; color:var(--neon-pink);">
                <?php echo esc($profile['preference'] ?? 'Muze'); ?>
                <?php if (!empty($profile['location'])): ?> &bull; <i class="fas fa-map-marker-alt"></i> <?php echo esc($profile['location']); ?><?php endif; ?>
            </p>
        </div>
        <?php if ($is_own): ?>
        <button class="btn-primary" style="font-size:0.8rem; padding:9px 18px;" onclick="openEditProfile()">
            <i class="fas fa-pen"></i> Edit
        </button>
        <?php else: ?>
        <button class="btn-primary" style="font-size:0.8rem; padding:9px 18px;"
                onclick="window.location.href='chat.php?partner_id=<?php echo $profile['id']; ?>&partner_name=<?php echo urlencode($profile['username']); ?>'">
            <i class="fas fa-paper-plane"></i> Whisper
        </button>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($profile['bio'])): ?>
    <p style="margin: 10px 0 0; font-size:0.88rem; color:var(--text-secondary); line-height:1.5;">
        <?php echo nl2br(esc($profile['bio'])); ?>
    </p>
    <?php endif; ?>
    
    <?php if (!$is_own && !$profile['is_online']): ?>
    <p style="font-size:0.75rem; color:var(--text-muted); margin-top:6px;">
        Last seen <?php echo date('M j, g:i A', strtotime($profile['last_seen'] ?? '-')); ?>
    </p>
    <?php endif; ?>
    
    <!-- Stats Row -->
    <div style="display:flex; gap:0; margin-top:20px; border-top:1px solid rgba(255,255,255,0.06); border-bottom:1px solid rgba(255,255,255,0.06); padding:14px 0;">
        <div class="stat-pill">
            <div class="num"><?php echo $total_posts; ?></div>
            <div class="lbl">Moments</div>
        </div>
        <div class="stat-pill" style="border-left:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06);">
            <div class="num"><?php echo $total_likes; ?></div>
            <div class="lbl">Desires</div>
        </div>
        <div class="stat-pill">
            <div class="num">₦<?php echo number_format($is_own ? ($profile['wallet_balance'] ?? 0) : 0, 0); ?></div>
            <div class="lbl"><?php echo $is_own ? 'Wallet' : 'Moments'; ?></div>
        </div>
    </div>
    
    <?php if ($is_own): ?>
    <!-- Quick Actions for own profile -->
    <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
        <a href="wallet.php" class="btn-primary" style="flex:1; text-align:center; text-decoration:none; font-size:0.8rem; padding:10px 12px;">
            <i class="fas fa-wallet"></i> Wallet
        </a>
        <a href="invites.php" class="btn-primary" style="flex:1; text-align:center; text-decoration:none; font-size:0.8rem; padding:10px 12px; background:rgba(255,42,109,0.15); box-shadow:none; border:1px solid var(--neon-pink);">
            <i class="fas fa-heart"></i> Invites
        </a>
        <a href="help.php" class="btn-primary" style="flex:1; text-align:center; text-decoration:none; font-size:0.8rem; padding:10px 12px; background:rgba(255,255,255,0.05); box-shadow:none; border:1px solid rgba(255,255,255,0.1);">
            <i class="fas fa-question-circle"></i> Help
        </a>
        <a href="auth/logout.php" class="btn-primary" style="flex:0 1 auto; text-decoration:none; font-size:0.8rem; padding:10px 14px; background:rgba(180,0,0,0.3); box-shadow:none; border:1px solid rgba(255,50,50,0.4);" onclick="return confirm('Log out of EazyMUZE?')">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- =================== TABS =================== -->
<div class="profile-tabs" id="profileTabs">
    <div class="profile-tab active" id="tab-posts" onclick="switchTab('posts')">
        <i class="fas fa-th"></i> Posts
    </div>
    <?php if ($is_own): ?>
    <div class="profile-tab" id="tab-saved" onclick="switchTab('saved')">
        <i class="fas fa-bookmark"></i> Saved
    </div>
    <div class="profile-tab" id="tab-settings" onclick="switchTab('settings')">
        <i class="fas fa-sliders-h"></i> Settings
    </div>
    <?php endif; ?>
</div>

<!-- =================== TAB CONTENT =================== -->

<!-- Posts Grid -->
<div id="panel-posts">
    <?php if (empty($user_posts)): ?>
    <div style="text-align:center; padding:50px 20px; color:var(--text-secondary);">
        <i class="fas fa-camera-retro" style="font-size:3rem; color:rgba(255,42,109,0.3);"></i>
        <p style="margin-top:12px;"><?php echo $is_own ? 'Share your first moment 💋' : 'No moments shared yet.'; ?></p>
        <?php if ($is_own): ?><button class="btn-primary" style="margin-top:12px;" onclick="window.location.href='index.php'">Post Now</button><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="posts-grid">
        <?php foreach($user_posts as $p):
            $imgs = json_decode($p['images'] ?? '[]', true) ?: [];
            $thumb = count($imgs) > 0 ? $imgs[0] : ($p['image_fallback'] ?? '');
            $likes_count = count(json_decode($p['likes'] ?? '[]', true) ?: []);
            $comments_count = count(json_decode($p['comments'] ?? '[]', true) ?: []);
        ?>
        <div class="posts-grid-item" onclick="openPostDetail(<?php echo $p['id']; ?>)">
            <?php if ($thumb): ?>
            <img src="<?php echo esc($thumb); ?>" loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,42,109,0.1);">
                <i class="fas fa-comment-alt" style="color:rgba(255,42,109,0.4);font-size:1.5rem;"></i>
            </div>
            <?php endif; ?>
            <div class="grid-overlay">
                <span><i class="fas fa-heart"></i> <?php echo $likes_count; ?></span>
                <span><i class="fas fa-comment"></i> <?php echo $comments_count; ?></span>
            </div>
            <?php if (count($imgs) > 1): ?>
            <div style="position:absolute;top:6px;right:6px;"><i class="fas fa-clone" style="color:white;font-size:0.7rem;"></i></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Saved/Bookmarks Grid -->
<?php if ($is_own): ?>
<div id="panel-saved" style="display:none;">
    <?php if (empty($bookmarks)): ?>
    <div style="text-align:center; padding:50px 20px; color:var(--text-secondary);">
        <i class="fas fa-bookmark" style="font-size:3rem; color:rgba(255,42,109,0.3);"></i>
        <p style="margin-top:12px;">No saved desires yet. Tap the bookmark icon on any post!</p>
    </div>
    <?php else: ?>
    <div class="posts-grid">
        <?php foreach($bookmarks as $p):
            $imgs = json_decode($p['images'] ?? '[]', true) ?: [];
            $thumb = count($imgs) > 0 ? $imgs[0] : ($p['image_fallback'] ?? '');
        ?>
        <div class="posts-grid-item" onclick="openPostDetail(<?php echo $p['id']; ?>)">
            <?php if ($thumb): ?>
            <img src="<?php echo esc($thumb); ?>" loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(255,42,109,0.1);">
                <i class="fas fa-heart" style="color:rgba(255,42,109,0.4);font-size:1.5rem;"></i>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Settings Panel -->
<div id="panel-settings" style="display:none; padding:20px;">
    <div class="glass-panel" style="padding:20px; border-radius:16px; margin-bottom:16px;">
        <h3 style="margin:0 0 16px; font-size:1rem; color:var(--neon-pink);">Account Settings</h3>
        <form id="settingsForm" onsubmit="saveSettings(event)">
            <div style="margin-bottom:14px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:6px;">Display Name / Username</label>
                <input type="text" name="username" value="<?php echo esc($currentUser['username']); ?>"
                       style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:6px;">Bio</label>
                <textarea name="bio" rows="3" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; resize:none; font-family:inherit; box-sizing:border-box;"><?php echo esc($currentUser['bio'] ?? ''); ?></textarea>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:6px;">Location</label>
                <input type="text" name="location" value="<?php echo esc($currentUser['location'] ?? ''); ?>"
                       style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:6px;">Preference</label>
                <select name="preference" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid var(--glass-border); background:#1a0b12; color:white; outline:none; font-size:0.88rem; font-family:inherit; box-sizing:border-box;">
                    <?php foreach(['straight','gay','lesbian','bisexual','sugar_daddy','sugar_mummy','open'] as $pref): ?>
                    <option value="<?php echo $pref; ?>" <?php echo (($currentUser['preference'] ?? '') === $pref) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ',$pref)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:18px;">
                <label style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:6px;">New Password (leave blank to keep current)</label>
                <input type="password" name="password" placeholder="••••••••"
                       style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; box-sizing:border-box;">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
    
    <!-- Danger Zone -->
    <div class="glass-panel" style="padding:20px; border-radius:16px; border:1px solid rgba(255,50,50,0.25);">
        <h3 style="margin:0 0 12px; font-size:0.95rem; color:#e74c3c;">Danger Zone</h3>
        <button onclick="if(confirm('Delete your account permanently? This cannot be undone.')) deleteAccount()" style="width:100%; background:rgba(231,76,60,0.2); color:#e74c3c; border:1px solid rgba(231,76,60,0.4); padding:12px; border-radius:10px; cursor:pointer; font-family:inherit; font-size:0.9rem;">
            <i class="fas fa-trash-alt"></i> Delete My Account
        </button>
    </div>
</div>
<?php endif; ?>

<!-- =================== POST DETAIL MODAL =================== -->
<div id="postDetailModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.97); z-index:9999; overflow-y:auto; padding:20px;">
    <button onclick="document.getElementById('postDetailModal').style.display='none'" style="position:fixed; top:15px; right:15px; background:rgba(255,255,255,0.1); border:none; color:white; font-size:1.3rem; width:36px; height:36px; border-radius:50%; cursor:pointer; z-index:10;">✕</button>
    <div id="postDetailContent" style="max-width:480px; margin:0 auto;"></div>
</div>

<?php if ($is_own): ?>
<!-- =================== EDIT PROFILE MODAL =================== -->
<div id="editProfileModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:420px; width:90%; padding:25px; max-height:90vh; overflow-y:auto;">
        <h3 style="color:var(--neon-pink); margin-bottom:18px; text-align:center;">Edit Profile</h3>
        <!-- Same as settings form for quick access from header -->
        <p style="color:var(--text-secondary); text-align:center; font-size:0.85rem;">Scroll to Settings tab for full profile editor.</p>
        <div style="display:flex; gap:10px; margin-top:16px;">
            <button onclick="closeEditProfile()" class="btn-primary" style="flex:1; background:#333; box-shadow:none;">Close</button>
            <button onclick="switchTab('settings'); closeEditProfile();" class="btn-primary" style="flex:1;">Go to Settings</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ---- TABS ----
function switchTab(tab) {
    ['posts','saved','settings'].forEach(t => {
        const panel = document.getElementById('panel-' + t);
        const tabEl = document.getElementById('tab-' + t);
        if (panel) panel.style.display = (t === tab) ? 'block' : 'none';
        if (tabEl) tabEl.classList.toggle('active', t === tab);
    });
}

// ---- OPEN POST DETAIL ----
function openPostDetail(postId) {
    const modal = document.getElementById('postDetailModal');
    const content = document.getElementById('postDetailContent');
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--neon-pink);"></i></div>';
    fetch('api/feed.php?action=get_post&id=' + postId)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const p = res.data;
                const imgs = JSON.parse(p.images || '[]');
                const imgHtml = imgs.length ? `<img src="${imgs[0]}" style="width:100%;border-radius:14px;margin-bottom:12px;max-height:400px;object-fit:cover;">` : '';
                content.innerHTML = `
                    <div class="glass-panel" style="border-radius:20px;overflow:hidden;padding:0;">
                        ${imgHtml}
                        <div style="padding:16px;">
                            <p style="margin:0;color:var(--text-primary);font-size:0.92rem;line-height:1.5;">${p.caption || ''}</p>
                            <p style="margin:10px 0 0;font-size:0.78rem;color:var(--text-muted);">${new Date(p.created_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})}</p>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = '<p style="color:var(--text-secondary);text-align:center;">Post not found</p>';
            }
        }).catch(() => { content.innerHTML = '<p style="color:var(--text-secondary);text-align:center;">Failed to load post</p>'; });
}

// ---- SAVE SETTINGS ----
async function saveSettings(e) {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = Object.fromEntries(form.entries());
    data.csrf_token = window.csrfToken;
    const res = await fetch('api/profile.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json());
    window.utils.showToast(res.message || (res.status === 'success' ? 'Profile updated 💋' : 'Update failed'), res.status);
}

// ---- UPLOAD AVATAR ----
function uploadAvatar(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('profileAvatar').src = e.target.result;
        fetch('api/profile.php?action=update_avatar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ avatar: e.target.result, csrf_token: window.csrfToken })
        }).then(r => r.json()).then(res => window.utils.showToast(res.message || 'Avatar updated!'));
    };
    reader.readAsDataURL(file);
}

// ---- EDIT PROFILE MODAL ----
function openEditProfile() {
    document.getElementById('editProfileModal').style.display = 'flex';
}
function closeEditProfile() {
    document.getElementById('editProfileModal').style.display = 'none';
}

// ---- DELETE ACCOUNT ----
async function deleteAccount() {
    const res = await fetch('api/profile.php?action=delete_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: window.csrfToken })
    }).then(r => r.json());
    if (res.status === 'success') window.location.href = 'auth/login.php';
}
</script>

<?php require_once 'includes/footer.php'; ?>
