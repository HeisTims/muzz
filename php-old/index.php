<?php
$page_title = "EazyMUZE — Muze Feed 💋";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$user_pref = $currentUser['preference'] ?? '';
$user_loc  = $currentUser['location'] ?? '';

// =====================================================================
// Personalized Feed Algorithm (Instagram-style ranking)
// Shows posts by matching preference/gender, then by likes count, then recent
// =====================================================================
$posts_stmt = $pdo->prepare("
    SELECT p.*, 
           u.username, u.avatar, u.is_verified, u.preference, u.gender, u.location,
           (SELECT COUNT(*) FROM bookmarks b WHERE b.post_id = p.id AND b.user_id = :me_bookmark) AS is_bookmarked
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id != :me_feed
    ORDER BY 
        CASE WHEN u.preference = :pref THEN 1 ELSE 2 END ASC,
        JSON_LENGTH(COALESCE(p.likes, '[]')) DESC,
        p.created_at DESC 
    LIMIT 20
");
$posts_stmt->execute([
    ':me_bookmark' => $user_id,
    ':me_feed'     => $user_id,
    ':pref'        => $user_pref,
]);
$posts = $posts_stmt->fetchAll();

// =====================================================================
// Stories (sorted by proximity, then latest)
// =====================================================================
$stories_stmt = $pdo->query("
    SELECT s.*, u.username, u.avatar, u.location
    FROM stories s
    JOIN users u ON s.user_id = u.id
    WHERE s.expires_at > NOW() AND s.user_id != $user_id
    ORDER BY s.created_at DESC
    LIMIT 30
");
$stories = $stories_stmt->fetchAll();

// User's own story check
$my_story = $pdo->prepare("SELECT id FROM stories WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
$my_story->execute([$user_id]);
$has_my_story = $my_story->fetch();

// Bookmarked post IDs for this user
$bk_stmt = $pdo->prepare("SELECT post_id FROM bookmarks WHERE user_id = ?");
$bk_stmt->execute([$user_id]);
$bookmarked_ids = array_column($bk_stmt->fetchAll(), 'post_id');
?>

<!-- ======================== STORIES TRAY ======================== -->
<div class="stories-tray">
    <!-- Add Story Circle -->
    <div class="story-wrapper" onclick="openCreatePostModal('story')">
        <div class="story-circle active" style="position: relative;">
            <img src="<?php echo esc($currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['username']) . '&background=8e1a1a&color=fff'); ?>" alt="Add Story">
            <div class="story-add-icon"><i class="fas fa-plus"></i></div>
        </div>
        <span class="story-name">add story</span>
    </div>
    
    <?php foreach($stories as $story): 
        $isNearby = $user_loc && strtolower($story['location'] ?? '') === strtolower($user_loc);
    ?>
    <div class="story-wrapper" onclick="openStoryViewer(<?php echo $story['id']; ?>, <?php echo $story['user_id']; ?>, '<?php echo esc($story['username']); ?>', '<?php echo esc($story['image']); ?>', '<?php echo esc($story['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($story['username']) . '&background=8e1a1a&color=fff'); ?>', '<?php echo esc($story['caption']); ?>')">
        <div class="story-circle <?php echo $isNearby ? 'nearby-story' : ''; ?>">
            <img src="<?php echo esc($story['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($story['username']) . '&background=8e1a1a&color=fff'); ?>" alt="Story">
        </div>
        <span class="story-name"><?php echo esc(substr($story['username'], 0, 10)); ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- ======================== PROMO BANNER ======================== -->
<div class="promo-banner glass-panel" style="margin: 0 15px 20px; padding: 15px 20px; border-radius: 16px;">
    <span class="sponsored-tag" style="background: var(--neon-pink); color: white; font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; letter-spacing: 1px;">SPONSORED</span>
    <h3 style="margin-top: 8px; font-size: 1rem;">🔥 Get 50% off your first month</h3>
    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px;">Use code: <strong>Muze50</strong></p>
</div>

<!-- ======================== SHARE MOMENT BUTTON ======================== -->
<div style="padding: 0 15px 20px;">
    <button class="btn-primary" style="width: 100%;" onclick="openCreatePostModal('moment')">
        📸 Share Your Moment
    </button>
</div>

<!-- ======================== FEED POSTS ======================== -->
<div id="feedContainer" style="padding: 0 0 20px 0;">
<?php foreach($posts as $idx => $post): 
    $images    = json_decode($post['images'] ?? '[]', true) ?: [];
    $likes     = json_decode($post['likes']   ?? '[]', true) ?: [];
    $comments  = json_decode($post['comments'] ?? '[]', true) ?: [];
    $isLiked   = in_array($user_id, $likes);
    $isBookmarked = in_array($post['id'], $bookmarked_ids);
    $firstImg  = count($images) > 0 ? $images[0] : ($post['image_fallback'] ?? '');
?>
<div class="post-card glass-panel" id="post_<?php echo $post['id']; ?>" style="margin: 0 0 16px 0; padding: 0; border-radius: 20px; overflow: hidden;">
    
    <!-- Post Header -->
    <div class="post-header" style="display: flex; align-items: center; padding: 14px 16px; gap: 12px;">
        <img src="<?php echo esc($post['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($post['username']) . '&background=8e1a1a&color=fff'); ?>" 
             style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--neon-pink); cursor: pointer;"
             onclick="viewUserProfile(<?php echo $post['user_id']; ?>)">
        <div style="flex: 1;">
            <div style="font-weight: 700; color: white; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
                <?php echo esc($post['username']); ?>
                <?php if ($post['is_verified']): ?>
                    <i class="fas fa-check-circle" style="color: var(--neon-pink); font-size: 0.75rem;"></i>
                <?php endif; ?>
                <span style="background: rgba(255,42,109,0.15); color: var(--neon-pink); font-size: 0.65rem; padding: 2px 7px; border-radius: 8px; font-weight: 500;"><?php echo esc($post['preference'] ?? 'muze'); ?></span>
            </div>
            <div style="color: var(--text-secondary); font-size: 0.78rem; margin-top: 2px;">
                <i class="fas fa-map-marker-alt" style="font-size: 0.7rem;"></i> <?php echo esc($post['location_data'] ?? 'Unknown'); ?>
                <?php if ($post['music']): ?> &nbsp;🎵<?php endif; ?>
            </div>
        </div>
        <button onclick="openWhisper(<?php echo $post['user_id']; ?>, '<?php echo esc($post['username']); ?>')" 
                style="background: none; border: 1px solid var(--glass-border); color: var(--text-secondary); padding: 6px 12px; border-radius: 15px; font-size: 0.75rem; cursor: pointer;">
            Whisper
        </button>
    </div>
    
    <!-- Post Image (Double Tap to Like) -->
    <?php if ($firstImg): ?>
    <div class="post-media-wrapper" style="position: relative; cursor: pointer;"
         data-post-id="<?php echo $post['id']; ?>"
         data-liked="<?php echo $isLiked ? '1' : '0'; ?>"
         ondblclick="doubleTapLike(this)"
         ontouchstart="trackTouchStart(event, this)"
         ontouchend="trackTouchEnd(event, this)">
        
        <?php if (count($images) > 1): ?>
        <!-- Multi-image carousel -->
        <div class="post-images-carousel" style="display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none;" id="carousel_<?php echo $post['id']; ?>">
            <?php foreach($images as $img): ?>
            <img src="<?php echo esc($img); ?>" style="min-width: 100%; width: 100%; height: 380px; object-fit: cover; scroll-snap-align: start;" loading="lazy">
            <?php endforeach; ?>
        </div>
        <!-- Dots indicator -->
        <div style="position: absolute; bottom: 10px; width: 100%; display: flex; justify-content: center; gap: 5px;">
            <?php foreach($images as $i => $img): ?>
            <div style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $i === 0 ? 'white' : 'rgba(255,255,255,0.4)'; ?>;" class="img-dot-<?php echo $post['id']; ?>"></div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <img src="<?php echo esc($firstImg); ?>" style="width: 100%; height: 380px; object-fit: cover; display: block;" loading="lazy">
        <?php endif; ?>
        
        <!-- Double-tap heart animation holder -->
        <div class="heart-overlay" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center; opacity: 0;"></div>
    </div>
    <?php endif; ?>
    
    <!-- Post Footer Actions (Instagram-standard layout) -->
    <div style="padding: 12px 16px;">
        <!-- Action Buttons Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <!-- Left: Like, Comment, Whisper (Share) -->
            <div style="display: flex; align-items: center; gap: 18px;">
                <button class="action-btn" onclick="likePost(<?php echo $post['id']; ?>, this)" data-post-id="<?php echo $post['id']; ?>" style="background: none; border: none; cursor: pointer; color: <?php echo $isLiked ? 'var(--neon-pink)' : 'white'; ?>; font-size: 1.5rem; display: flex; align-items: center; gap: 5px; transition: all 0.2s;">
                    <i class="<?php echo $isLiked ? 'fas' : 'far'; ?> fa-heart"></i>
                </button>
                <button class="action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)" style="background: none; border: none; cursor: pointer; color: white; font-size: 1.45rem; display: flex; align-items: center; gap: 5px;">
                    <i class="far fa-comment"></i>
                </button>
                <button onclick="openWhisper(<?php echo $post['user_id']; ?>, '<?php echo esc($post['username']); ?>')" style="background: none; border: none; cursor: pointer; color: white; font-size: 1.45rem;">
                    <i class="far fa-paper-plane"></i>
                </button>
            </div>
            <!-- Right: Bookmark -->
            <button onclick="toggleBookmark(<?php echo $post['id']; ?>, this)" style="background: none; border: none; cursor: pointer; color: <?php echo $isBookmarked ? 'var(--neon-pink)' : 'white'; ?>; font-size: 1.4rem; transition: all 0.2s;">
                <i class="<?php echo $isBookmarked ? 'fas' : 'far'; ?> fa-bookmark"></i>
            </button>
        </div>
        
        <!-- Like Count -->
        <div style="font-weight: 700; color: white; font-size: 0.9rem; margin-bottom: 6px;" id="likeCount_<?php echo $post['id']; ?>">
            <?php echo count($likes) === 1 ? '1 desire' : count($likes) . ' desires'; ?>
        </div>
        
        <!-- Caption -->
        <div style="font-size: 0.9rem; color: var(--text-primary); line-height: 1.4; margin-bottom: 8px;">
            <strong><?php echo esc($post['username']); ?></strong> <?php echo esc($post['caption']); ?>
        </div>
        
        <!-- Comments toggle -->
        <?php if (count($comments) > 0): ?>
        <button onclick="toggleComments(<?php echo $post['id']; ?>)" style="background: none; border: none; color: var(--text-secondary); font-size: 0.82rem; cursor: pointer; padding: 0; margin-bottom: 6px;">
            View all <?php echo count($comments); ?> comments
        </button>
        <?php endif; ?>
        
        <!-- Comments Section (hidden by default) -->
        <div id="comments_<?php echo $post['id']; ?>" style="display: none; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;">
            <?php foreach(array_slice($comments, -3) as $c): ?>
            <div style="font-size: 0.84rem; margin-bottom: 6px;">
                <strong style="color: var(--text-secondary);"><?php echo esc($c['username'] ?? 'anon'); ?></strong> 
                <span style="color: var(--text-primary);"><?php echo esc($c['text']); ?></span>
            </div>
            <?php endforeach; ?>
            
            <!-- Add comment input -->
            <div style="display: flex; gap: 8px; margin-top: 10px; align-items: center;">
                <img src="<?php echo esc($currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['username']) . '&background=8e1a1a&color=fff'); ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                <input type="text" id="commentInput_<?php echo $post['id']; ?>" placeholder="Add a comment..." 
                       style="flex: 1; background: none; border: none; border-bottom: 1px solid rgba(255,255,255,0.15); color: white; padding: 4px 0; font-size: 0.85rem; outline: none;"
                       onkeydown="if(event.key==='Enter') submitComment(<?php echo $post['id']; ?>)">
                <button onclick="submitComment(<?php echo $post['id']; ?>)" style="background: none; border: none; color: var(--neon-pink); font-weight: 700; font-size: 0.85rem; cursor: pointer;">Post</button>
            </div>
        </div>
        
        <!-- Timestamp -->
        <div style="color: var(--text-muted); font-size: 0.72rem; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
            <?php echo esc(date('M j, Y', strtotime($post['created_at']))); ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($posts)): ?>
<div style="text-align: center; padding: 60px 20px;">
    <div style="font-size: 3rem; margin-bottom: 15px;">🌙</div>
    <p style="color: var(--text-secondary);">The temple is quiet. No desires yet.</p>
</div>
<?php endif; ?>
</div>

<!-- ======================== CREATE POST MODAL ======================== -->
<div id="createPostModal" style="display: none; position: fixed; inset: 0; background: rgba(10, 4, 6, 0.96); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="glass-panel fade-in-up" style="background: linear-gradient(135deg, rgba(20, 10, 15, 0.98), rgba(45, 15, 30, 0.98)); border: 2px solid var(--neon-pink); border-radius: 20px; max-width: 420px; width: 90%; padding: 25px; max-height: 90vh; overflow-y: auto;">
        <h3 id="postModalTitle" style="color: var(--neon-pink); margin-bottom: 15px; text-align: center; font-size: 1.1rem;">Share a Desire 💋</h3>
        
        <div style="display: flex; gap: 8px; margin-bottom: 15px;">
            <button id="btnMoment" class="btn-primary" style="flex: 1; font-size: 0.8rem; padding: 10px;" onclick="setPostType('moment')">Moment (Feed)</button>
            <button id="btnStory"  class="btn-primary" style="flex: 1; font-size: 0.8rem; padding: 10px; background: #222; box-shadow: none;" onclick="setPostType('story')">Story (24h)</button>
        </div>
        
        <textarea id="postCaption" rows="3" placeholder="What's on your mind tonight?..." style="width: 100%; padding: 12px; border-radius: 10px; background: rgba(255,255,255,0.05); color: white; border: 1px solid rgba(255,42,109,0.3); font-family: inherit; font-size: 0.9rem; outline: none; resize: none; margin-bottom: 12px;"></textarea>
        
        <div id="momentOnlyFields">
            <select id="postMusic" style="width: 100%; padding: 10px; border-radius: 8px; background: #1a0b12; border: 1px solid rgba(255,42,109,0.3); color: white; margin-bottom: 12px; font-size: 0.88rem; outline: none;">
                <option value="">Select Music Vibe (optional)</option>
                <option value="wizkid">Wizkid — Essence</option>
                <option value="burna">Burna Boy — Last Last</option>
                <option value="ayra">Ayra Starr — Rush</option>
            </select>
        </div>
        
        <div onclick="document.getElementById('postImages').click()" style="border: 2px dashed rgba(255,42,109,0.4); padding: 20px; text-align: center; border-radius: 12px; cursor: pointer; margin-bottom: 12px;">
            <i class="fas fa-camera-retro" style="font-size: 2rem; color: var(--neon-pink);"></i>
            <p style="font-size: 0.8rem; margin-top: 8px; color: var(--text-secondary);">Upload Images (max 3)</p>
            <input type="file" id="postImages" multiple accept="image/*" style="display: none;" onchange="handlePostImages(this)">
        </div>
        <div id="postImagesPreview" style="display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 15px;"></div>
        
        <div style="display: flex; gap: 10px;">
            <button class="btn-primary" style="flex: 1; background: #333; box-shadow: none;" onclick="closePostModal()">Cancel</button>
            <button class="btn-primary" style="flex: 1;" onclick="submitPost()">Post Desire 💋</button>
        </div>
    </div>
</div>

<!-- ======================== STORY VIEWER ======================== -->
<div id="storyViewer" style="display: none; position: fixed; inset: 0; background: black; z-index: 9999;">
    <div id="storyProgressBar" style="position: absolute; top: 10px; width: 100%; display: flex; gap: 4px; padding: 0 12px; box-sizing: border-box; z-index: 10001;">
        <div style="flex: 1; height: 2px; background: rgba(255,255,255,0.6); border-radius: 2px;">
            <div id="storyProgressFill" style="height: 100%; background: white; width: 0%; transition: width linear 5s;"></div>
        </div>
    </div>
    <div style="position: absolute; top: 20px; left: 15px; right: 15px; display: flex; justify-content: space-between; align-items: center; z-index: 10001;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img id="storyViewerAvatar" src="" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--neon-pink); cursor: pointer;" onclick="viewStoryUserProfile()">
            <strong id="storyViewerName" style="color: white; text-shadow: 0 0 5px black; cursor: pointer;" onclick="viewStoryUserProfile()"></strong>
        </div>
        <i class="fas fa-times" style="color: white; font-size: 1.5rem; cursor: pointer;" onclick="closeStoryViewer()"></i>
    </div>
    <img id="storyViewerImg" src="" style="width: 100%; height: 100%; object-fit: cover;">
    <div id="storyViewerCaption" style="position: absolute; bottom: 80px; left: 0; right: 0; text-align: center; color: white; font-size: 1rem; text-shadow: 0 0 10px black; padding: 0 20px;"></div>
    <div style="position: absolute; bottom: 20px; width: 100%; display: flex; justify-content: center; gap: 15px; z-index: 10001;">
        <button class="btn-primary" style="background: rgba(0,0,0,0.5); border: 1px solid white; padding: 10px 20px;" onclick="closeStoryViewer()">
            <i class="fas fa-comment"></i> Reply
        </button>
    </div>
</div>

<!-- ======================== JAVASCRIPT ======================== -->
<script>
let activePostType = 'moment';
let postImagesBase64 = [];
let lastTapTime = 0;
const apiUrl = 'api/';
const csrfToken = "<?php echo esc($_SESSION['csrf_token']); ?>";

// ---- DOUBLE TAP TO LIKE ----
function trackTouchStart(e, el) { el._touchStartTime = Date.now(); el._touchStartX = e.touches[0].clientX; el._touchStartY = e.touches[0].clientY; }
function trackTouchEnd(e, el) {
    const now = Date.now();
    const dt = now - (el._touchStartTime || 0);
    const dx = Math.abs(e.changedTouches[0].clientX - (el._touchStartX || 0));
    const dy = Math.abs(e.changedTouches[0].clientY - (el._touchStartY || 0));
    if (dt < 300 && dx < 15 && dy < 15) {
        const gap = now - lastTapTime;
        if (gap < 350) { doubleTapLike(el); lastTapTime = 0; }
        else { lastTapTime = now; }
    }
}

function doubleTapLike(el) {
    const postId = el.getAttribute('data-post-id');
    const overlay = el.querySelector('.heart-overlay');
    if (overlay) {
        overlay.innerHTML = '<span style="font-size: 5rem; animation: heartFade 0.8s ease-out forwards;">❤️</span>';
        overlay.style.opacity = '1';
        setTimeout(() => { overlay.style.opacity = '0'; overlay.innerHTML = ''; }, 900);
    }
    if (el.getAttribute('data-liked') === '0') {
        likePost(postId, document.querySelector(`[data-post-id="${postId}"] .action-btn`));
        el.setAttribute('data-liked', '1');
    }
    window.utils && window.utils.playNotificationSound('engagement');
}

// ---- LIKE POST ----
function likePost(postId, btn) {
    const icon = btn ? btn.querySelector('i') : document.querySelector(`#post_${postId} .action-btn i`);
    const isLiked = icon && icon.classList.contains('fas');
    
    // Optimistic UI
    if (icon) {
        icon.classList.toggle('far', isLiked);
        icon.classList.toggle('fas', !isLiked);
        if (btn) btn.style.color = isLiked ? 'white' : 'var(--neon-pink)';
    }
    
    fetch(apiUrl + 'feed.php?action=like_post', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status !== 'success') {
            // Rollback on error
            if (icon) { icon.classList.toggle('far', !isLiked); icon.classList.toggle('fas', isLiked); }
        }
    }).catch(() => {});
}

// ---- BOOKMARK POST ----
function toggleBookmark(postId, btn) {
    const isBookmarked = btn.style.color.includes('rgb') || btn.querySelector('i').classList.contains('fas');
    const icon = btn.querySelector('i');
    
    // Optimistic UI toggle
    if (icon) {
        icon.classList.toggle('far', isBookmarked);
        icon.classList.toggle('fas', !isBookmarked);
        btn.style.color = isBookmarked ? 'white' : 'var(--neon-pink)';
    }
    window.utils && window.utils.showToast(isBookmarked ? 'Removed from Saved' : 'Desire Saved! 🔖');
    
    fetch(apiUrl + 'feed.php?action=bookmark_post', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, csrf_token: csrfToken })
    }).catch(() => {});
}

// ---- TOGGLE COMMENTS ----
function toggleComments(postId) {
    const el = document.getElementById('comments_' + postId);
    if (el) el.style.display = (el.style.display === 'none' || !el.style.display) ? 'block' : 'none';
}

// ---- SUBMIT COMMENT ----
function submitComment(postId) {
    const input = document.getElementById('commentInput_' + postId);
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    window.utils && window.utils.showToast('Comment whispered 💬');
    
    fetch(apiUrl + 'feed.php?action=comment_post', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, text: text, csrf_token: csrfToken })
    }).catch(() => {});
}

// ---- OPEN WHISPER (Navigate to Chat) ----
function openWhisper(userId, username) {
    window.location.href = 'chat.php?partner_id=' + userId + '&partner_name=' + encodeURIComponent(username);
}

function viewUserProfile(userId) {
    window.location.href = 'profile.php?user_id=' + userId;
}

// ---- CREATE POST MODAL ----
function openCreatePostModal(type) {
    document.getElementById('createPostModal').style.display = 'flex';
    setPostType(type);
}

function closePostModal() {
    document.getElementById('createPostModal').style.display = 'none';
    document.getElementById('postCaption').value = '';
    document.getElementById('postImagesPreview').innerHTML = '';
    postImagesBase64 = [];
}

function setPostType(type) {
    activePostType = type;
    const btnMoment = document.getElementById('btnMoment');
    const btnStory = document.getElementById('btnStory');
    const momentFields = document.getElementById('momentOnlyFields');
    const title = document.getElementById('postModalTitle');
    if (type === 'story') {
        btnMoment.style.background = '#222'; btnMoment.style.boxShadow = 'none';
        btnStory.style.background  = 'var(--neon-pink)'; btnStory.style.boxShadow = '';
        momentFields.style.display = 'none';
        title.innerText = 'Add a Story 💋';
    } else {
        btnMoment.style.background = 'var(--neon-pink)'; btnMoment.style.boxShadow = '';
        btnStory.style.background  = '#222'; btnStory.style.boxShadow = 'none';
        momentFields.style.display = 'block';
        title.innerText = 'Share a Moment 💋';
    }
}

function handlePostImages(input) {
    const files = Array.from(input.files).slice(0, 3);
    const preview = document.getElementById('postImagesPreview');
    preview.innerHTML = '';
    postImagesBase64 = [];
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            postImagesBase64.push(e.target.result);
            preview.innerHTML += `<img src="${e.target.result}" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid var(--neon-pink);">`;
        };
        reader.readAsDataURL(file);
    });
}

async function submitPost() {
    const caption = document.getElementById('postCaption').value;
    const music = document.getElementById('postMusic')?.value || '';
    
    if (activePostType === 'story') {
        if (postImagesBase64.length === 0) { window.utils.showToast('Upload an image for your story', 'error'); return; }
        const res = await fetch(apiUrl + 'feed.php?action=create_story', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: postImagesBase64[0], caption, csrf_token: csrfToken })
        }).then(r => r.json());
        if (res.status === 'success') { window.utils.showToast('Story posted! 💋'); closePostModal(); setTimeout(() => location.reload(), 1000); }
        else window.utils.showToast(res.message || 'Failed to post', 'error');
    } else {
        if (!caption && postImagesBase64.length === 0) { window.utils.showToast('Say something or upload a slide', 'error'); return; }
        const res = await fetch(apiUrl + 'feed.php?action=create_post', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ caption, music, images: postImagesBase64, image_fallback: postImagesBase64[0] || '', location_data: '', csrf_token: csrfToken })
        }).then(r => r.json());
        if (res.status === 'success') { window.utils.showToast('Moment posted 💋'); closePostModal(); setTimeout(() => location.reload(), 1000); }
        else window.utils.showToast(res.message || 'Failed to post', 'error');
    }
}

// ---- STORY VIEWER ----
let storyTimer = null;
let storyViewerUserId = null;
function openStoryViewer(id, userId, username, image, avatar, caption) {
    storyViewerUserId = userId;
    document.getElementById('storyViewer').style.display = 'block';
    document.getElementById('storyViewerImg').src = image;
    document.getElementById('storyViewerAvatar').src = avatar;
    document.getElementById('storyViewerName').innerText = '@' + username;
    document.getElementById('storyViewerCaption').innerText = caption || '';
    // Progress bar
    const fill = document.getElementById('storyProgressFill');
    fill.style.transition = 'none'; fill.style.width = '0%';
    setTimeout(() => { fill.style.transition = 'width linear 5s'; fill.style.width = '100%'; }, 50);
    if (storyTimer) clearTimeout(storyTimer);
    storyTimer = setTimeout(() => closeStoryViewer(), 5000);
}
function closeStoryViewer() {
    document.getElementById('storyViewer').style.display = 'none';
    if (storyTimer) clearTimeout(storyTimer);
}
function viewStoryUserProfile() {
    if (storyViewerUserId) {
        window.location.href = 'profile.php?user_id=' + storyViewerUserId;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
