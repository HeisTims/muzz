<?php
$page_title = "EazyMUZE — Whispers 💬";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch all messages where current user is sender or receiver, group by conversation partner
$inbox_stmt = $pdo->prepare("
    SELECT m.*,
           u1.username AS sender_name, u1.avatar AS sender_avatar,
           u2.username AS receiver_name, u2.avatar AS receiver_avatar,
           u_partner.id AS partner_id, u_partner.username AS partner_username, 
           u_partner.avatar AS partner_avatar_main, u_partner.is_verified AS partner_verified,
           (CASE WHEN u_partner.last_seen > :fma THEN 1 ELSE 0 END) AS partner_online
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
    JOIN users u_partner ON u_partner.id = (CASE WHEN m.sender_id = :me THEN m.receiver_id ELSE m.sender_id END)
    WHERE m.sender_id = :me2 OR m.receiver_id = :me3
    ORDER BY m.timestamp DESC
");
$inbox_stmt->execute([
    ':me' => $user_id, ':me2' => $user_id, ':me3' => $user_id,
    ':fma' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
]);
$all_messages = $inbox_stmt->fetchAll();

// Group into conversations (latest message per partner)
$conversations = [];
foreach ($all_messages as $m) {
    $pid = $m['partner_id'];
    if (!isset($conversations[$pid])) {
        $conversations[$pid] = $m;
    }
}
?>

<div style="padding: 0 15px 20px;">
    <h2 style="font-size: 1.4rem; margin-bottom: 15px; color: white;">
        Whispers <span style="color: var(--neon-pink);">💬</span>
    </h2>
    
    <!-- Search conversations -->
    <div style="position: relative; margin-bottom: 20px;">
        <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.9rem;"></i>
        <input type="text" id="convoSearch" placeholder="Search whispers..." 
               style="width: 100%; padding: 11px 14px 11px 38px; border-radius: 25px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: white; outline: none; font-size: 0.88rem;"
               oninput="filterConversations(this.value)">
    </div>
    
    <!-- Conversations List -->
    <div id="conversationsList">
        <?php if (empty($conversations)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 3rem; margin-bottom: 15px;">💬</div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">No whispers yet. Explore the temple and start a conversation!</p>
            <a href="explore.php" class="btn-primary" style="display: inline-block; margin-top: 15px; text-decoration: none; padding: 12px 24px;">Explore Desires</a>
        </div>
        <?php else: ?>
        <?php foreach($conversations as $c):
            $pid        = $c['partner_id'];
            $pname      = $c['partner_username'];
            $pavatar    = $c['partner_avatar_main'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($pname) . '&background=8e1a1a&color=fff';
            $pverified  = $c['partner_verified'];
            $ponline    = $c['partner_online'] == 1;
            $isReceiver = ($c['receiver_id'] == $user_id);
            $isUnread   = $isReceiver && !$c['is_read'];
            $lastText   = $isUnread ? '🔒 Locked Whisper' : (($c['sender_id'] == $user_id ? 'You: ' : '') . mb_substr($c['text'], 0, 50));
        ?>
        <div class="convo-card glass-panel" 
             data-username="<?php echo esc(strtolower($pname)); ?>"
             onclick="window.location.href='chat.php?partner_id=<?php echo $pid; ?>&partner_name=<?php echo urlencode($pname); ?>'"
             style="margin-bottom: 10px; display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px; cursor: pointer; border: 1px solid <?php echo $isUnread ? 'var(--neon-pink)' : 'var(--glass-border)'; ?>; transition: all 0.2s;"
             onmouseover="this.style.borderColor='var(--neon-pink)'"
             onmouseout="this.style.borderColor='<?php echo $isUnread ? 'var(--neon-pink)' : 'var(--glass-border)'; ?>'">
            
            <!-- Avatar with online dot -->
            <div style="position: relative; flex-shrink: 0;">
                <img src="<?php echo esc($pavatar); ?>" 
                     style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $ponline ? '#2ecc71' : ($isUnread ? 'var(--neon-pink)' : 'var(--glass-border)'); ?>;"
                     loading="lazy">
                <?php if ($ponline): ?>
                <div style="position: absolute; bottom: 1px; right: 1px; width: 12px; height: 12px; background: #2ecc71; border-radius: 50%; border: 2px solid var(--velvet-bg); box-shadow: 0 0 5px #2ecc71;"></div>
                <?php endif; ?>
                <?php if ($isUnread): ?>
                <div style="position: absolute; top: -2px; left: -2px; width: 12px; height: 12px; background: var(--neon-pink); border-radius: 50%; border: 2px solid var(--velvet-bg); box-shadow: 0 0 6px var(--neon-pink);"></div>
                <?php endif; ?>
            </div>
            
            <!-- Conversation Info -->
            <div style="flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <h3 style="margin: 0; font-size: 1rem; color: <?php echo $isUnread ? 'var(--neon-pink)' : 'white'; ?>; font-weight: <?php echo $isUnread ? '700' : '600'; ?>;">
                        <?php echo esc($pname); ?>
                    </h3>
                    <?php if ($pverified): ?>
                    <i class="fas fa-check-circle" style="color: var(--neon-pink); font-size: 0.75rem;"></i>
                    <?php endif; ?>
                </div>
                <p style="margin: 3px 0 0; font-size: 0.82rem; color: <?php echo $isUnread ? 'var(--text-primary)' : 'var(--text-secondary)'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?php echo esc($lastText); ?>
                </p>
            </div>
            
            <!-- Timestamp & Unread badge -->
            <div style="text-align: right; flex-shrink: 0;">
                <div style="font-size: 0.72rem; color: var(--text-muted);">
                    <?php echo date('H:i', strtotime($c['timestamp'])); ?>
                </div>
                <?php if ($isUnread): ?>
                <div style="width: 8px; height: 8px; background: var(--neon-pink); border-radius: 50%; margin: 4px auto 0; box-shadow: 0 0 5px var(--neon-pink);"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- New Conversation button -->
    <div style="margin-top: 20px;">
        <a href="explore.php" class="btn-primary" style="display: block; text-align: center; text-decoration: none; width: 100%;">
            <i class="fas fa-plus"></i> Start New Whisper
        </a>
    </div>
</div>

<script>
function filterConversations(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.convo-card').forEach(card => {
        const match = !q || card.dataset.username.includes(q);
        card.style.display = match ? 'flex' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
