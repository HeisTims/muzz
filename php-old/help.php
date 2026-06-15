<?php
$page_title = "EazyMUZE — Help & Support 💌";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

$faqs = [
    ['q' => 'How do I start a whisper?', 'a' => 'Browse the Explore page, find someone who catches your eye, and tap "Whisper". Your first whisper is FREE. Subsequent new whisper threads cost ₦500 from your wallet.'],
    ['q' => 'How do I fund my wallet?', 'a' => 'Go to your Wallet page and either transfer directly to your unique virtual account number, or use our secure Monnify payment gateway. Funds are credited instantly.'],
    ['q' => 'What is a "Muze"?', 'a' => 'A Muze is another user who inspires or captures your attention. When you connect with your Muze, a private encrypted whisper channel opens between you.'],
    ['q' => 'Is my identity kept private?', 'a' => 'Yes. EazyMUZE uses end-to-end encrypted whispers. Your data is never sold or shared. You can also message anonymously using a display name of your choice.'],
    ['q' => 'How do invites work?', 'a' => 'You can send an invite with a personal message to anyone you wish to connect with. If they accept, you\'ll be connected for free private whispers.'],
    ['q' => 'How do I post a Moment or Story?', 'a' => 'Tap "Share Your Moment" on the Feed. Choose between a permanent Moment (Feed post) or a Story that expires in 24 hours. You can upload up to 3 images.'],
    ['q' => 'How does the Market work?', 'a' => 'The Market allows users to sell exclusive content, services, or experiences. Create a listing with a price, and buyers can purchase using their wallet balance. Funds are transferred directly.'],
    ['q' => 'What happens if I forget my password?', 'a' => 'On the login screen, tap "Forgot Password" and enter your email. A secure reset link will be sent to you immediately.'],
    ['q' => 'How do I delete my account?', 'a' => 'Go to Profile → Settings → Danger Zone → Delete Account. This action is permanent and cannot be reversed.'],
    ['q' => 'How do I become Verified?', 'a' => 'Verified status (blue checkmark) is granted to notable, authentic accounts. Contact support to apply for verification.'],
];
?>

<div style="padding:0 15px 30px;">
    <h2 style="font-size:1.4rem; margin-bottom:6px; color:white;">Help & Support 💌</h2>
    <p style="color:var(--text-secondary); font-size:0.85rem; margin-bottom:24px;">We're here to keep your experience divine.</p>
    
    <!-- Contact Support Card -->
    <div style="background:linear-gradient(135deg, rgba(255,42,109,0.15), rgba(45,15,30,0.9)); border:1px solid rgba(255,42,109,0.35); border-radius:20px; padding:20px; margin-bottom:24px; display:flex; align-items:center; gap:16px;">
        <div style="width:50px; height:50px; background:var(--neon-pink); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 0 18px rgba(255,42,109,0.5);">
            <i class="fas fa-headset" style="color:white; font-size:1.3rem;"></i>
        </div>
        <div style="flex:1;">
            <h3 style="margin:0 0 4px; font-size:1rem; color:white;">24/7 Live Support</h3>
            <p style="margin:0; font-size:0.8rem; color:var(--text-secondary);">Our team is always available to help you.</p>
        </div>
        <button class="btn-primary" style="padding:10px 16px; font-size:0.8rem; flex-shrink:0;" onclick="openSupportChat()">
            Chat Now
        </button>
    </div>
    
    <!-- Quick Links -->
    <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:10px; margin-bottom:24px;">
        <a href="wallet.php" style="text-decoration:none;">
            <div class="glass-panel" style="padding:16px; border-radius:14px; text-align:center; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--neon-pink)'" onmouseout="this.style.borderColor=''">
                <i class="fas fa-wallet" style="font-size:1.6rem; color:var(--neon-pink); margin-bottom:8px;"></i>
                <p style="margin:0; font-size:0.85rem; color:white; font-weight:600;">Wallet & Payments</p>
            </div>
        </a>
        <a href="profile.php" style="text-decoration:none;">
            <div class="glass-panel" style="padding:16px; border-radius:14px; text-align:center; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--neon-pink)'" onmouseout="this.style.borderColor=''">
                <i class="fas fa-user-cog" style="font-size:1.6rem; color:var(--neon-pink); margin-bottom:8px;"></i>
                <p style="margin:0; font-size:0.85rem; color:white; font-weight:600;">Account Settings</p>
            </div>
        </a>
        <div class="glass-panel" style="padding:16px; border-radius:14px; text-align:center; cursor:pointer; transition:all 0.2s;" onclick="openReportModal()" onmouseover="this.style.borderColor='var(--neon-pink)'" onmouseout="this.style.borderColor=''">
            <i class="fas fa-flag" style="font-size:1.6rem; color:#f1c40f; margin-bottom:8px;"></i>
            <p style="margin:0; font-size:0.85rem; color:white; font-weight:600;">Report a User</p>
        </div>
        <div class="glass-panel" style="padding:16px; border-radius:14px; text-align:center; cursor:pointer; transition:all 0.2s;" onclick="openSuggestModal()" onmouseover="this.style.borderColor='var(--neon-pink)'" onmouseout="this.style.borderColor=''">
            <i class="fas fa-lightbulb" style="font-size:1.6rem; color:#9b59b6; margin-bottom:8px;"></i>
            <p style="margin:0; font-size:0.85rem; color:white; font-weight:600;">Suggest a Feature</p>
        </div>
    </div>
    
    <!-- FAQ Accordion -->
    <h3 style="font-size:1rem; color:white; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        <i class="fas fa-question-circle" style="color:var(--neon-pink);"></i> Frequently Asked Questions
    </h3>
    
    <div id="faqAccordion">
        <?php foreach($faqs as $i => $faq): ?>
        <div class="glass-panel" style="margin-bottom:8px; border-radius:14px; overflow:hidden;">
            <button onclick="toggleFaq(<?php echo $i; ?>)" style="width:100%; text-align:left; padding:16px; background:none; border:none; color:white; font-family:inherit; font-size:0.9rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-weight:600;">
                <?php echo esc($faq['q']); ?>
                <i id="faq-icon-<?php echo $i; ?>" class="fas fa-chevron-down" style="color:var(--neon-pink); font-size:0.75rem; transition:transform 0.3s; flex-shrink:0; margin-left:10px;"></i>
            </button>
            <div id="faq-<?php echo $i; ?>" style="display:none; padding:0 16px 16px; color:var(--text-secondary); font-size:0.85rem; line-height:1.6;">
                <?php echo esc($faq['a']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Legal Links -->
    <div style="margin-top:24px; text-align:center; color:var(--text-muted); font-size:0.75rem; line-height:2;">
        <span onclick="window.utils.showToast('Privacy Policy — Coming soon')" style="cursor:pointer; color:var(--neon-pink);">Privacy Policy</span>
        &nbsp;&bull;&nbsp;
        <span onclick="window.utils.showToast('Terms of Service — Coming soon')" style="cursor:pointer; color:var(--neon-pink);">Terms of Service</span>
        &nbsp;&bull;&nbsp;
        <span onclick="window.utils.showToast('Community Guidelines — Coming soon')" style="cursor:pointer; color:var(--neon-pink);">Community Guidelines</span>
        <p style="margin-top:10px;">EazyMUZE v3.0 &copy; <?php echo date('Y'); ?>. All rights reserved.</p>
    </div>
</div>

<!-- =================== SUPPORT CHAT MODAL =================== -->
<div id="supportChatModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:420px; width:90%; padding:25px; max-height:90vh; overflow-y:auto;">
        <h3 style="color:var(--neon-pink); margin-bottom:8px; text-align:center;">Support Chat 💌</h3>
        <p style="color:var(--text-secondary); text-align:center; font-size:0.82rem; margin-bottom:18px;">Describe your issue and we'll get back to you shortly.</p>
        
        <select id="supportCategory" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:#1a0b12; color:white; outline:none; font-size:0.88rem; font-family:inherit; margin-bottom:12px; box-sizing:border-box;">
            <option value="">Select Category</option>
            <option>Account Issues</option>
            <option>Payment Problems</option>
            <option>Harassment / Safety</option>
            <option>Bug Report</option>
            <option>Feature Request</option>
            <option>Other</option>
        </select>
        
        <textarea id="supportMessage" rows="5" placeholder="Describe your issue in detail..."
               style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; resize:none; font-family:inherit; margin-bottom:14px; box-sizing:border-box;"></textarea>
        
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="document.getElementById('supportChatModal').style.display='none'">Cancel</button>
            <button class="btn-primary" style="flex:1;" onclick="submitSupport()">Send 💌</button>
        </div>
    </div>
</div>

<!-- =================== REPORT MODAL =================== -->
<div id="reportModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid #e74c3c; border-radius:20px; max-width:400px; width:90%; padding:25px;">
        <h3 style="color:#e74c3c; margin-bottom:18px; text-align:center;"><i class="fas fa-flag"></i> Report a User</h3>
        <input type="text" id="reportUsername" placeholder="Username to report"
               style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(231,76,60,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; font-family:inherit; margin-bottom:10px; box-sizing:border-box;">
        <textarea id="reportReason" rows="3" placeholder="Reason for report..."
               style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(231,76,60,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; resize:none; font-family:inherit; margin-bottom:14px; box-sizing:border-box;"></textarea>
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="document.getElementById('reportModal').style.display='none'">Cancel</button>
            <button class="btn-primary" style="flex:1; background:rgba(231,76,60,0.6); box-shadow:none; border:1px solid #e74c3c;" onclick="submitReport()">Submit Report</button>
        </div>
    </div>
</div>

<script>
function toggleFaq(i) {
    const el  = document.getElementById('faq-' + i);
    const ico = document.getElementById('faq-icon-' + i);
    const open = el.style.display === 'block';
    el.style.display  = open ? 'none' : 'block';
    ico.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}

function openSupportChat() { document.getElementById('supportChatModal').style.display = 'flex'; }
function openReportModal()  { document.getElementById('reportModal').style.display = 'flex'; }
function openSuggestModal() { window.utils.showToast('Feature suggestions coming soon!'); }

async function submitSupport() {
    const cat = document.getElementById('supportCategory').value;
    const msg = document.getElementById('supportMessage').value.trim();
    if (!cat || !msg) { window.utils.showToast('Fill all fields', 'error'); return; }
    const res = await fetch(window.apiUrl + 'help.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'support', category: cat, message: msg, csrf_token: window.csrfToken })
    }).then(r => r.json());
    window.utils.showToast(res.message || 'Support ticket sent! We\'ll respond via email 💌');
    document.getElementById('supportChatModal').style.display = 'none';
}

async function submitReport() {
    const username = document.getElementById('reportUsername').value.trim();
    const reason   = document.getElementById('reportReason').value.trim();
    if (!username || !reason) { window.utils.showToast('Fill all fields', 'error'); return; }
    const res = await fetch(window.apiUrl + 'help.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'report', username, reason, csrf_token: window.csrfToken })
    }).then(r => r.json());
    window.utils.showToast(res.message || 'Report submitted. Thank you 🛡️');
    document.getElementById('reportModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
