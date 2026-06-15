<?php
$page_title = "EazyMUZE — Whisper Room 💬";
require_once 'includes/header.php';
require_once 'api/db.php';

$user_id    = $_SESSION['user_id'];
$partner_id = intval($_GET['partner_id'] ?? 0);
if (!$partner_id) { header('Location: messages.php'); exit; }

// Fetch partner info
$partner_stmt = $pdo->prepare("
    SELECT id, username, avatar, is_verified, preference, location, bio,
           (CASE WHEN last_seen > ? THEN 1 ELSE 0 END) AS is_online,
           last_seen
    FROM users WHERE id = ?
");
$partner_stmt->execute([date('Y-m-d H:i:s', strtotime('-5 minutes')), $partner_id]);
$partner = $partner_stmt->fetch();
if (!$partner) { header('Location: messages.php'); exit; }

// Mark messages from partner to me as read
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$partner_id, $user_id]);

// Fetch last 50 messages between these two users
$msgs_stmt = $pdo->prepare("
    SELECT m.*, u.username, u.avatar
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = :a AND m.receiver_id = :b)
       OR (m.sender_id = :c AND m.receiver_id = :d)
    ORDER BY m.timestamp ASC
    LIMIT 80
");
$msgs_stmt->execute([':a' => $user_id, ':b' => $partner_id, ':c' => $partner_id, ':d' => $user_id]);
$messages = $msgs_stmt->fetchAll();

$myAvatar = $currentUser['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($currentUser['username']) . '&background=8e1a1a&color=fff';
$partnerAvatar = $partner['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($partner['username']) . '&background=8e1a1a&color=fff';

$page_title = "Whisper with " . htmlspecialchars($partner['username']) . " — EazyMUZE";
?>

<!-- CHAT WRAPPER (Override default layout padding) -->
<style>
/* ======================================================
   INSTAGRAM-STANDARD CHAT UI
   ====================================================== */
#chatWrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 60px); /* full minus bottom nav */
    overflow: hidden;
}

.chat-header {
    padding: 12px 16px;
    background: linear-gradient(180deg, rgba(10, 4, 6, 0.98) 0%, rgba(20, 10, 15, 0.92) 100%);
    border-bottom: 1px solid rgba(255, 42, 109, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 100;
}

#messagesArea {
    flex: 1;
    overflow-y: auto;
    padding: 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    scroll-behavior: smooth;
}

.msg-bubble {
    max-width: 72%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 0.88rem;
    line-height: 1.45;
    position: relative;
    animation: msgAppear 0.2s ease-out;
    word-break: break-word;
}

@keyframes msgAppear {
    from { opacity: 0; transform: translateY(10px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

.msg-mine {
    background: linear-gradient(135deg, var(--neon-pink), #b5006a);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}

.msg-theirs {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.msg-meta {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.5);
    margin-top: 3px;
}

.msg-image {
    max-width: 220px;
    border-radius: 14px;
    display: block;
    margin-bottom: 4px;
    cursor: pointer;
    object-fit: cover;
}

.msg-reaction {
    position: absolute;
    bottom: -10px;
    right: 5px;
    font-size: 0.85rem;
    background: rgba(20, 10, 15, 0.95);
    border-radius: 10px;
    padding: 2px 5px;
    cursor: pointer;
    border: 1px solid rgba(255,42,109,0.3);
}

/* Read receipt indicators */
.read-receipt {
    font-size: 0.62rem;
    color: rgba(255, 255, 255, 0.5);
    text-align: right;
    margin-top: 2px;
}
.read-receipt.seen { color: var(--neon-pink); }

/* Chat Input Bar */
.chat-input-bar {
    padding: 12px 14px;
    background: rgba(10, 4, 6, 0.98);
    border-top: 1px solid rgba(255, 42, 109, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

#chatInput {
    flex: 1;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255, 42, 109, 0.25);
    border-radius: 22px;
    padding: 10px 16px;
    color: white;
    font-size: 0.9rem;
    outline: none;
    font-family: 'Outfit', sans-serif;
    transition: border-color 0.2s;
    max-height: 100px;
    resize: none;
    overflow-y: auto;
}
#chatInput:focus { border-color: var(--neon-pink); }

.chat-send-btn {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--neon-pink);
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: white;
    transition: all 0.2s;
    flex-shrink: 0;
    box-shadow: 0 0 12px rgba(255, 42, 109, 0.4);
}
.chat-send-btn:hover { transform: scale(1.08); box-shadow: 0 0 18px rgba(255, 42, 109, 0.6); }

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    padding: 10px;
    background: rgba(20, 10, 15, 0.98);
    border-radius: 12px;
    border: 1px solid rgba(255, 42, 109, 0.2);
}

.date-divider {
    text-align: center;
    color: var(--text-muted);
    font-size: 0.72rem;
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.date-divider::before, .date-divider::after {
    content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08);
}

/* Reaction Picker Popup */
#reactionPicker {
    display: none;
    position: fixed;
    background: rgba(20, 10, 15, 0.98);
    border: 1px solid rgba(255, 42, 109, 0.3);
    border-radius: 16px;
    padding: 12px;
    font-size: 1.6rem;
    gap: 8px;
    z-index: 9999;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(12px);
}

/* Typing Indicator */
#typingIndicator {
    display: none;
    padding: 4px 14px;
    font-size: 0.78rem;
    color: var(--text-secondary);
    font-style: italic;
}
</style>

<div id="chatWrapper">
    <!-- =================== HEADER =================== -->
    <div class="chat-header">
        <a href="messages.php" style="color: var(--neon-pink); text-decoration: none; margin-right: 2px;">
            <i class="fas fa-arrow-left" style="font-size: 1.1rem;"></i>
        </a>
        
        <div style="position: relative;" onclick="window.location.href='profile.php?user_id=<?php echo $partner['id']; ?>'">
            <img src="<?php echo esc($partnerAvatar); ?>" 
                 style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; cursor: pointer; border: 2px solid <?php echo $partner['is_online'] ? '#2ecc71' : 'var(--glass-border)'; ?>;">
            <?php if ($partner['is_online']): ?>
            <div style="position: absolute; bottom: 0; right: 0; width: 11px; height: 11px; background: #2ecc71; border-radius: 50%; border: 2px solid #0a0406;"></div>
            <?php endif; ?>
        </div>
        
        <div style="flex: 1; min-width: 0; cursor: pointer;" onclick="window.location.href='profile.php?user_id=<?php echo $partner['id']; ?>'">
            <div style="font-weight: 700; color: white; font-size: 1rem; display: flex; align-items: center; gap: 6px;">
                <?php echo esc($partner['username']); ?>
                <?php if ($partner['is_verified']): ?>
                <i class="fas fa-check-circle" style="color: var(--neon-pink); font-size: 0.75rem;"></i>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.75rem; color: <?php echo $partner['is_online'] ? '#2ecc71' : 'var(--text-secondary)'; ?>">
                <?php if ($partner['is_online']): ?>
                ● Active now
                <?php else: ?>
                Last seen <?php echo date('g:i A', strtotime($partner['last_seen'] ?? '-')); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions -->
        <div style="display: flex; gap: 18px; color: var(--neon-pink); font-size: 1.15rem;">
            <i class="fas fa-phone" style="cursor: pointer;" onclick="window.utils && window.utils.showToast('Voice calls coming soon! 🔮')"></i>
            <i class="fas fa-video" style="cursor: pointer;" onclick="window.utils && window.utils.showToast('Video calls coming soon! 🔮')"></i>
            <i class="fas fa-ellipsis-v" style="cursor: pointer;" onclick="toggleChatMenu()"></i>
        </div>
    </div>

    <!-- =================== MESSAGES AREA =================== -->
    <div id="messagesArea">
        <?php 
        $lastDate = null;
        foreach ($messages as $msg): 
            $isMine = ($msg['sender_id'] == $user_id);
            $msgDate = date('Y-m-d', strtotime($msg['timestamp']));
            $displayDate = ($msgDate === date('Y-m-d')) ? 'Today' : (($msgDate === date('Y-m-d', strtotime('-1 day'))) ? 'Yesterday' : date('M j', strtotime($msg['timestamp'])));
            $isImage = !empty($msg['image_url']);
        ?>
        
        <?php if ($msgDate !== $lastDate): $lastDate = $msgDate; ?>
        <div class="date-divider"><?php echo $displayDate; ?></div>
        <?php endif; ?>
        
        <div class="msg-row" style="display: flex; <?php echo $isMine ? 'justify-content: flex-end;' : 'justify-content: flex-start;'; ?> gap: 8px; align-items: flex-end;">
            <?php if (!$isMine): ?>
            <img src="<?php echo esc($partnerAvatar); ?>" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
            <?php endif; ?>
            
            <div>
                <div class="msg-bubble <?php echo $isMine ? 'msg-mine' : 'msg-theirs'; ?>" 
                     data-msg-id="<?php echo $msg['id']; ?>"
                     oncontextmenu="showReactionPicker(event, <?php echo $msg['id']; ?>); return false;"
                     ontouchstart="touchHoldStart(event, <?php echo $msg['id']; ?>)"
                     ontouchend="touchHoldEnd()">
                    
                    <?php if ($isImage): ?>
                    <img src="<?php echo esc($msg['image_url']); ?>" class="msg-image" onclick="openImageViewer('<?php echo esc($msg['image_url']); ?>')">
                    <?php endif; ?>
                    
                    <?php if (!empty($msg['text'])): ?>
                    <span><?php echo esc($msg['text']); ?></span>
                    <?php endif; ?>
                    
                    <?php if (!empty($msg['reaction'])): ?>
                    <span class="msg-reaction"><?php echo esc($msg['reaction']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="read-receipt <?php echo ($isMine && $msg['is_read']) ? 'seen' : ''; ?>">
                    <?php if ($isMine): ?>
                        <?php echo $msg['is_read'] ? '✓✓ Seen' : '✓ Sent'; ?>
                        <?php if ($msg['is_read']): ?>&nbsp;<?php echo date('g:i A', strtotime($msg['timestamp'])); ?><?php endif; ?>
                    <?php else: ?>
                        <?php echo date('g:i A', strtotime($msg['timestamp'])); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($isMine): ?>
            <img src="<?php echo esc($myAvatar); ?>" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <!-- Typing indicator -->
        <div id="typingIndicator"><?php echo esc($partner['username']); ?> is typing<span class="typing-dots">...</span></div>
    </div>

    <!-- =================== INPUT BAR =================== -->
    <div class="chat-input-bar">
        <!-- Image upload -->
        <button onclick="document.getElementById('chatImgInput').click()" style="background: none; border: none; color: var(--neon-pink); font-size: 1.3rem; cursor: pointer;">
            <i class="fas fa-image"></i>
        </button>
        <input type="file" id="chatImgInput" accept="image/*" style="display: none;" onchange="handleChatImage(this)">
        
        <!-- Emoji button -->
        <button onclick="toggleEmojiPanel()" style="background: none; border: none; font-size: 1.3rem; cursor: pointer; line-height: 1;">
            😊
        </button>
        
        <!-- Text Input -->
        <textarea id="chatInput" placeholder="Whisper something..." rows="1"
                  oninput="autoResizeInput(this); handleTyping();"
                  onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendMessage();}"></textarea>
        
        <!-- Voice note button (placeholder) -->
        <button id="voiceBtn" onclick="voiceNoteHint()" style="background: none; border: none; color: var(--text-secondary); font-size: 1.3rem; cursor: pointer;" id="chatMicBtn">
            <i class="fas fa-microphone"></i>
        </button>
        
        <!-- Send button -->
        <button class="chat-send-btn" onclick="sendMessage()" id="chatSendBtn">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- ===================== EMOJI PANEL ===================== -->
<div id="emojiPanel" style="display: none; position: fixed; bottom: 80px; left: 10px; right: 10px; z-index: 9000;">
    <div class="emoji-grid">
        <?php foreach(['❤️','💋','🔥','😘','🥰','😍','💕','🌹','✨','😏','🙈','💦','🍑','😈','🖤','💗','🌙','👄','💆','🤭','😊','😂','🎉','💯','🙏','😭','💀','🤩','😬','🥺','🤤','🫦'] as $em): ?>
        <button onclick="appendEmoji('<?php echo $em; ?>')" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; padding: 5px; border-radius: 8px; transition: background 0.15s;" onmouseover="this.style.background='rgba(255,42,109,0.15)'" onmouseout="this.style.background='none'"><?php echo $em; ?></button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================== REACTION PICKER ===================== -->
<div id="reactionPicker">
    <?php foreach(['❤️','😂','😮','😢','😡','👍','💯'] as $em): ?>
    <span onclick="reactToMessage(activeMsgId, '<?php echo $em; ?>')" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.3)'" onmouseout="this.style.transform='scale(1)'"><?php echo $em; ?></span>
    <?php endforeach; ?>
</div>

<!-- ===================== IMAGE VIEWER ===================== -->
<div id="imageViewerModal" onclick="this.style.display='none'" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.97); z-index: 99999; display: none; align-items: center; justify-content: center;">
    <img id="imageViewerImg" src="" style="max-width: 95%; max-height: 90%; border-radius: 12px; object-fit: contain;">
</div>

<!-- ===================== JAVASCRIPT ===================== -->
<script>
const PARTNER_ID = <?php echo $partner_id; ?>;
const PARTNER_NAME = "<?php echo esc($partner['username']); ?>";
const USER_ID = <?php echo $user_id; ?>;
const CSRF = "<?php echo esc($_SESSION['csrf_token']); ?>";
let lastMsgId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
let typingTimer = null;
let activeMsgId = null;
let holdTimer = null;
let previewImageBase64 = null;

// Auto-scroll to bottom
function scrollToBottom() {
    const area = document.getElementById('messagesArea');
    area.scrollTop = area.scrollHeight;
}
scrollToBottom();

// Auto-resize textarea
function autoResizeInput(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

// ---- SEND MESSAGE ----
async function sendMessage() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text && !previewImageBase64) return;
    
    const payload = {
        receiver_id: PARTNER_ID,
        text: text || '',
        image_url: previewImageBase64 || '',
        csrf_token: CSRF
    };
    
    // Optimistic UI – append bubble immediately
    appendMsgBubble({ text, image_url: previewImageBase64, sender_id: USER_ID, is_read: 0, id: 'temp_' + Date.now(), timestamp: new Date().toISOString() });
    
    input.value = '';
    input.style.height = 'auto';
    previewImageBase64 = null;
    const preview = document.getElementById('chatImgPreview');
    if (preview) preview.remove();
    scrollToBottom();
    
    try {
        const res = await fetch('api/messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json());
        
        if (res.status !== 'success') {
            window.utils && window.utils.showToast('Failed to send whisper', 'error');
        } else {
            lastMsgId = Math.max(lastMsgId, res.data?.id || lastMsgId);
        }
    } catch(e) {}
}

// ---- APPEND A MSG BUBBLE TO DOM ----
function appendMsgBubble(msg) {
    const area = document.getElementById('messagesArea');
    const isMine = msg.sender_id == USER_ID;
    const myAvatar = "<?php echo esc($myAvatar); ?>";
    const partnerAvatar = "<?php echo esc($partnerAvatar); ?>";
    
    const wrap = document.createElement('div');
    wrap.className = 'msg-row';
    wrap.dataset.msgId = msg.id;
    wrap.style.cssText = `display:flex; ${isMine ? 'justify-content:flex-end' : 'justify-content:flex-start'}; gap:8px; align-items:flex-end;`;
    
    const bubble = document.createElement('div');
    bubble.className = `msg-bubble ${isMine ? 'msg-mine' : 'msg-theirs'}`;
    bubble.dataset.msgId = msg.id;
    bubble.setAttribute('oncontextmenu', `showReactionPicker(event, ${msg.id}); return false;`);
    bubble.setAttribute('ontouchstart', `touchHoldStart(event, ${msg.id})`);
    bubble.setAttribute('ontouchend', 'touchHoldEnd()');
    
    let inner = '';
    if (msg.image_url) {
        inner += `<img src="${msg.image_url}" class="msg-image" onclick="openImageViewer('${msg.image_url}')">`;
    }
    if (msg.text) inner += `<span>${escHtml(msg.text)}</span>`;
    bubble.innerHTML = inner;
    
    const receipt = document.createElement('div');
    receipt.className = 'read-receipt';
    receipt.style.textAlign = isMine ? 'right' : 'left';
    receipt.innerHTML = isMine ? '✓ Sent' : formatMsgTime(msg.timestamp);
    
    const avatar = document.createElement('img');
    avatar.src = isMine ? myAvatar : partnerAvatar;
    avatar.style.cssText = 'width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;';
    
    const contentDiv = document.createElement('div');
    contentDiv.appendChild(bubble);
    contentDiv.appendChild(receipt);
    
    if (isMine) { wrap.appendChild(contentDiv); wrap.appendChild(avatar); }
    else { wrap.appendChild(avatar); wrap.appendChild(contentDiv); }
    
    area.appendChild(wrap);
    scrollToBottom();
}

function escHtml(text) {
    const d = document.createElement('div');
    d.innerText = text;
    return d.innerHTML;
}

function formatMsgTime(ts) {
    const d = new Date(ts);
    const h = d.getHours() % 12 || 12;
    const m = d.getMinutes().toString().padStart(2, '0');
    return `${h}:${m} ${d.getHours() >= 12 ? 'PM' : 'AM'}`;
}

// ---- POLLING – REAL-TIME MESSAGES ----
async function pollNewMessages() {
    try {
        const res = await fetch(`api/messages.php?action=get_messages&partner_id=${PARTNER_ID}&last_id=${lastMsgId}`).then(r => r.json());
        if (res.status === 'success' && Array.isArray(res.data)) {
            res.data.forEach(msg => {
                if (parseInt(msg.id) > lastMsgId) {
                    lastMsgId = parseInt(msg.id);
                    appendMsgBubble(msg);
                    // Play notification sound for incoming
                    if (msg.sender_id != USER_ID) {
                        window.utils && window.utils.playNotificationSound('message');
                    }
                }
            });
        }
        // Update typing status
        const typingRes = await fetch(`api/messages.php?action=is_typing&partner_id=${PARTNER_ID}`).then(r => r.json()).catch(() => ({}));
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.style.display = typingRes.typing ? 'block' : 'none';
    } catch(e) {}
}
setInterval(pollNewMessages, 2000);

// ---- TYPING INDICATOR ----
let isTypingSent = false;
function handleTyping() {
    clearTimeout(typingTimer);
    if (!isTypingSent) {
        isTypingSent = true;
        fetch('api/messages.php?action=set_typing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ partner_id: PARTNER_ID, is_typing: 1, csrf_token: CSRF })
        }).catch(() => {});
    }
    typingTimer = setTimeout(() => {
        isTypingSent = false;
        fetch('api/messages.php?action=set_typing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ partner_id: PARTNER_ID, is_typing: 0, csrf_token: CSRF })
        }).catch(() => {});
    }, 2000);
}

// ---- EMOJI PANEL ----
function toggleEmojiPanel() {
    const p = document.getElementById('emojiPanel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function appendEmoji(emoji) {
    const input = document.getElementById('chatInput');
    input.value += emoji;
    input.focus();
    document.getElementById('emojiPanel').style.display = 'none';
}
document.addEventListener('click', e => {
    const ep = document.getElementById('emojiPanel');
    if (ep && !ep.contains(e.target) && !e.target.closest('[onclick="toggleEmojiPanel()"]')) {
        ep.style.display = 'none';
    }
    const rp = document.getElementById('reactionPicker');
    if (rp && !rp.contains(e.target)) rp.style.display = 'none';
});

// ---- REACTION PICKER ----
function showReactionPicker(event, msgId) {
    activeMsgId = msgId;
    const picker = document.getElementById('reactionPicker');
    picker.style.display = 'flex';
    const x = Math.min(event.clientX, window.innerWidth - 220);
    const y = Math.max(event.clientY - 60, 20);
    picker.style.left = x + 'px';
    picker.style.top = y + 'px';
}

function touchHoldStart(e, msgId) {
    holdTimer = setTimeout(() => showReactionPicker({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY }, msgId), 500);
}
function touchHoldEnd() { clearTimeout(holdTimer); }

function reactToMessage(msgId, emoji) {
    document.getElementById('reactionPicker').style.display = 'none';
    fetch('api/messages.php?action=react_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ msg_id: msgId, reaction: emoji, csrf_token: CSRF })
    }).catch(() => {});
    // Update UI optimistically
    const bubble = document.querySelector(`[data-msg-id="${msgId}"].msg-bubble`);
    if (bubble) {
        let reactionEl = bubble.querySelector('.msg-reaction');
        if (!reactionEl) { reactionEl = document.createElement('span'); reactionEl.className = 'msg-reaction'; bubble.appendChild(reactionEl); }
        reactionEl.innerText = emoji;
    }
}

// ---- IMAGE IN CHAT ----
function handleChatImage(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        previewImageBase64 = e.target.result;
        // Show a small preview near input
        const existing = document.getElementById('chatImgPreview');
        if (existing) existing.remove();
        const prev = document.createElement('div');
        prev.id = 'chatImgPreview';
        prev.style.cssText = 'position: absolute; bottom: 75px; left: 14px; z-index: 100;';
        prev.innerHTML = `<img src="${e.target.result}" style="width: 70px; height: 70px; object-fit: cover; border-radius: 10px; border: 2px solid var(--neon-pink);">
                          <span onclick="previewImageBase64=null; this.parentElement.remove();" style="position: absolute; top: -5px; right: -5px; background: var(--neon-pink); border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.65rem; cursor: pointer;">✕</span>`;
        document.getElementById('chatWrapper').appendChild(prev);
    };
    reader.readAsDataURL(file);
}

// ---- IMAGE VIEWER ----
function openImageViewer(src) {
    const modal = document.getElementById('imageViewerModal');
    document.getElementById('imageViewerImg').src = src;
    modal.style.display = 'flex';
}

// ---- VOICE NOTE HINT ----
function voiceNoteHint() {
    window.utils && window.utils.showToast('🎤 Voice notes coming in next update!');
}

function toggleChatMenu() {
    window.utils && window.utils.showToast('Report / Block options coming soon!');
}
</script>

<?php require_once 'includes/footer.php'; ?>
