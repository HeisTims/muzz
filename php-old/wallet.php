<?php
$page_title = "EazyMUZE — My Wallet 💳";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$wallet  = floatval($currentUser['wallet_balance'] ?? 0);

// Fetch payment history
$payments_stmt = $pdo->prepare("
    SELECT * FROM payments WHERE payer_id = ? OR recipient_id = ?
    ORDER BY created_at DESC LIMIT 30
");
$payments_stmt->execute([$user_id, $user_id]);
$payments = $payments_stmt->fetchAll();

$monnify_account = $currentUser['monnify_account_number'] ?? '';
$monnify_bank    = $currentUser['monnify_bank_name'] ?? '';
?>

<div style="padding:0 15px 30px;">
    <h2 style="font-size:1.4rem; margin-bottom:20px; color:white;">My Wallet 💳</h2>
    
    <!-- Balance Card -->
    <div style="background:linear-gradient(135deg, #1a0b12, #2d0f1e); border:1px solid rgba(255,42,109,0.4); border-radius:20px; padding:24px; margin-bottom:20px; position:relative; overflow:hidden;">
        <div style="position:absolute; top:-20px; right:-20px; width:120px; height:120px; background:radial-gradient(circle, rgba(255,42,109,0.3) 0%, transparent 70%); border-radius:50%;"></div>
        <div style="position:absolute; bottom:-30px; left:-10px; width:100px; height:100px; background:radial-gradient(circle, rgba(255,42,109,0.15) 0%, transparent 70%); border-radius:50%;"></div>
        
        <p style="margin:0 0 6px; font-size:0.78rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px;">Available Balance</p>
        <h1 style="margin:0 0 18px; font-size:2.4rem; font-weight:800; color:white;">₦<?php echo number_format($wallet, 2); ?></h1>
        
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; padding:12px;" onclick="openFundModal()">
                <i class="fas fa-plus-circle"></i> Fund Wallet
            </button>
            <button class="btn-primary" style="flex:1; padding:12px; background:rgba(255,255,255,0.08); box-shadow:none; border:1px solid rgba(255,255,255,0.15);" onclick="openWithdrawModal()">
                <i class="fas fa-arrow-up"></i> Withdraw
            </button>
        </div>
    </div>
    
    <!-- Virtual Account Info -->
    <?php if ($monnify_account): ?>
    <div class="glass-panel" style="border-radius:16px; padding:18px; margin-bottom:20px;">
        <h3 style="margin:0 0 14px; font-size:0.9rem; color:var(--neon-pink); display:flex; align-items:center; gap:8px;">
            <i class="fas fa-university"></i> Your Virtual Account
        </h3>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <div>
                <p style="margin:0; font-size:0.78rem; color:var(--text-secondary);">Bank</p>
                <p style="margin:3px 0 0; font-size:0.95rem; color:white; font-weight:600;"><?php echo esc($monnify_bank); ?></p>
            </div>
            <div style="text-align:right;">
                <p style="margin:0; font-size:0.78rem; color:var(--text-secondary);">Account Number</p>
                <p style="margin:3px 0 0; font-size:1.1rem; color:white; font-weight:700; letter-spacing:1px;"><?php echo esc($monnify_account); ?></p>
            </div>
        </div>
        <button onclick="copyAccount()" style="width:100%; background:rgba(255,42,109,0.1); border:1px dashed rgba(255,42,109,0.4); color:var(--neon-pink); padding:10px; border-radius:10px; cursor:pointer; font-family:inherit; font-size:0.85rem; display:flex; align-items:center; justify-content:center; gap:8px;">
            <i class="fas fa-copy"></i> Copy Account Number
        </button>
        <p style="margin:10px 0 0; font-size:0.75rem; color:var(--text-muted); text-align:center; line-height:1.4;">
            Transfer any amount to this account to automatically fund your wallet.
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Quick Fund Options -->
    <div class="glass-panel" style="border-radius:16px; padding:18px; margin-bottom:20px;">
        <h3 style="margin:0 0 14px; font-size:0.9rem; color:white;">Quick Fund</h3>
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:10px;">
            <?php foreach([500, 1000, 2000, 5000, 10000, 20000] as $amount): ?>
            <button onclick="quickFund(<?php echo $amount; ?>)" 
                    style="background:rgba(255,42,109,0.1); border:1px solid rgba(255,42,109,0.25); color:var(--neon-pink); padding:12px 8px; border-radius:10px; cursor:pointer; font-family:inherit; font-size:0.85rem; font-weight:600; transition:all 0.2s;"
                    onmouseover="this.style.background='rgba(255,42,109,0.25)'"
                    onmouseout="this.style.background='rgba(255,42,109,0.1)'">
                ₦<?php echo number_format($amount, 0); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="glass-panel" style="border-radius:16px; padding:18px;">
        <h3 style="margin:0 0 16px; font-size:0.9rem; color:white;">Transaction History</h3>
        <?php if (empty($payments)): ?>
        <p style="color:var(--text-secondary); text-align:center; font-size:0.85rem; padding:20px 0;">No transactions yet.</p>
        <?php else: ?>
        <?php foreach($payments as $txn):
            $isIncoming = ($txn['recipient_id'] == $user_id);
            $typeLabels = [
                'wallet_funding' => 'Wallet Top-up',
                'whisper_init'   => 'Whisper Fee',
                'market_purchase'=> 'Market Purchase',
                'market_sale'    => 'Market Sale',
                'subscription'   => 'Subscription',
                'withdrawal'     => 'Withdrawal',
            ];
            $typeLabel = $typeLabels[$txn['type']] ?? ucfirst(str_replace('_', ' ', $txn['type']));
            $typeIcons = [
                'wallet_funding'  => 'fa-wallet',
                'whisper_init'    => 'fa-comment-dots',
                'market_purchase' => 'fa-shopping-bag',
                'market_sale'     => 'fa-store',
                'subscription'    => 'fa-crown',
                'withdrawal'      => 'fa-arrow-up',
            ];
            $icon = $typeIcons[$txn['type']] ?? 'fa-exchange-alt';
        ?>
        <div style="display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.06);">
            <div style="width:40px; height:40px; border-radius:50%; background:<?php echo $isIncoming ? 'rgba(46,204,113,0.15)' : 'rgba(255,42,109,0.12)'; ?>; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas <?php echo $icon; ?>" style="color:<?php echo $isIncoming ? '#2ecc71' : 'var(--neon-pink)'; ?>; font-size:0.9rem;"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <p style="margin:0; font-size:0.88rem; color:white; font-weight:600;"><?php echo $typeLabel; ?></p>
                <p style="margin:2px 0 0; font-size:0.73rem; color:var(--text-muted);"><?php echo date('M j, Y · g:i A', strtotime($txn['created_at'])); ?></p>
            </div>
            <div style="text-align:right; font-weight:700; font-size:0.95rem; color:<?php echo $isIncoming ? '#2ecc71' : 'var(--neon-pink)'; ?>; flex-shrink:0;">
                <?php echo $isIncoming ? '+' : '-'; ?>₦<?php echo number_format($txn['amount'], 2); ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- =================== FUND MODAL =================== -->
<div id="fundModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:400px; width:90%; padding:25px;">
        <h3 style="color:var(--neon-pink); margin-bottom:18px; text-align:center;"><i class="fas fa-wallet"></i> Fund Wallet</h3>
        
        <input type="number" id="fundAmount" placeholder="Enter amount (₦)" min="100"
               style="width:100%; padding:14px; border-radius:12px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:1rem; font-family:inherit; margin-bottom:16px; box-sizing:border-box; text-align:center; font-weight:700;">
        
        <p style="color:var(--text-secondary); font-size:0.82rem; text-align:center; margin-bottom:18px; line-height:1.5;">
            You'll be redirected to our secure Monnify payment gateway to complete your top-up.
        </p>
        
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="document.getElementById('fundModal').style.display='none'">Cancel</button>
            <button class="btn-primary" style="flex:1;" onclick="initiateFunding()">Pay Now 🔒</button>
        </div>
    </div>
</div>

<!-- =================== WITHDRAW MODAL =================== -->
<div id="withdrawModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:400px; width:90%; padding:25px;">
        <h3 style="color:var(--neon-pink); margin-bottom:18px; text-align:center;"><i class="fas fa-arrow-up"></i> Withdraw Funds</h3>
        
        <p style="color:var(--text-muted); text-align:center; font-size:0.82rem; margin-bottom:14px;">Balance: <strong style="color:var(--neon-pink);">₦<?php echo number_format($wallet, 2); ?></strong></p>
        
        <input type="number" id="withdrawAmount" placeholder="Amount to withdraw (₦)"
               style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; font-family:inherit; margin-bottom:10px; box-sizing:border-box;">
        <input type="text" id="withdrawBank" placeholder="Bank Name"
               style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; font-family:inherit; margin-bottom:10px; box-sizing:border-box;">
        <input type="text" id="withdrawAccount" placeholder="Account Number"
               style="width:100%; padding:12px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.9rem; font-family:inherit; margin-bottom:16px; box-sizing:border-box;">
        
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="document.getElementById('withdrawModal').style.display='none'">Cancel</button>
            <button class="btn-primary" style="flex:1;" onclick="submitWithdrawal()">Request Withdrawal</button>
        </div>
    </div>
</div>

<script>
const MONNIFY_KEY  = "<?php echo esc(getenv('MONNIFY_API_KEY') ?: ''); ?>";
const MONNIFY_REF  = "<?php echo esc($currentUser['monnify_ref'] ?? ''); ?>";
const USER_EMAIL   = "<?php echo esc($currentUser['email'] ?? ''); ?>";
const USER_NAME    = "<?php echo esc($currentUser['username'] ?? ''); ?>";
const MONNIFY_ACC  = "<?php echo esc($monnify_account); ?>";

function openFundModal() { document.getElementById('fundModal').style.display = 'flex'; }
function openWithdrawModal() { document.getElementById('withdrawModal').style.display = 'flex'; }

function quickFund(amount) {
    document.getElementById('fundAmount').value = amount;
    openFundModal();
}

function copyAccount() {
    navigator.clipboard.writeText(MONNIFY_ACC).then(() => window.utils.showToast('Account number copied! 📋'));
}

function initiateFunding() {
    const amount = parseFloat(document.getElementById('fundAmount').value);
    if (!amount || amount < 100) { window.utils.showToast('Enter a valid amount (min ₦100)', 'error'); return; }
    
    // Try Monnify inline payment
    if (typeof MonnifySDK !== 'undefined') {
        MonnifySDK.initialize({
            amount,
            currency: 'NGN',
            reference: 'EMZ-' + Date.now(),
            customerFullName: USER_NAME,
            customerEmail: USER_EMAIL,
            apiKey: MONNIFY_KEY,
            contractCode: "<?php echo esc(getenv('MONNIFY_CONTRACT_CODE') ?: ''); ?>",
            paymentDescription: 'EazyMUZE Wallet Funding',
            isTestMode: true,
            onLoadStart: () => {},
            onLoadComplete: () => {},
            onComplete: function(res) {
                if (res.status === 'SUCCESS' || res.paymentStatus === 'PAID') {
                    fetch(window.apiUrl + 'wallet.php?action=fund', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ amount, reference: res.transactionReference, csrf_token: window.csrfToken })
                    }).then(r => r.json()).then(r => {
                        window.utils.showToast(r.message || 'Wallet funded! 💰');
                        setTimeout(() => location.reload(), 1500);
                    });
                }
            },
            onClose: function(data) {}
        });
    } else {
        // Fallback: transfer info
        window.utils.showToast('Transfer ₦' + amount.toLocaleString() + ' to your account above!');
        document.getElementById('fundModal').style.display = 'none';
    }
}

async function submitWithdrawal() {
    const amount  = parseFloat(document.getElementById('withdrawAmount').value);
    const bank    = document.getElementById('withdrawBank').value.trim();
    const account = document.getElementById('withdrawAccount').value.trim();
    if (!amount || amount < 500) { window.utils.showToast('Minimum withdrawal is ₦500', 'error'); return; }
    if (!bank || !account) { window.utils.showToast('Enter bank details', 'error'); return; }
    
    const res = await fetch(window.apiUrl + 'wallet.php?action=withdraw', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ amount, bank, account_number: account, csrf_token: window.csrfToken })
    }).then(r => r.json());
    
    window.utils.showToast(res.message || (res.status === 'success' ? 'Withdrawal requested! We\'ll process within 24h 💌' : 'Failed'), res.status);
    if (res.status === 'success') { document.getElementById('withdrawModal').style.display = 'none'; setTimeout(() => location.reload(), 1500); }
}
</script>

<?php require_once 'includes/footer.php'; ?>
