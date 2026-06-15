<?php
$page_title = "EazyMUZE — Market 🛒";
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch market listings
$stmt = $pdo->query("
    SELECT m.*, u.username, u.avatar, u.is_verified
    FROM market m
    JOIN users u ON m.seller_id = u.id
    WHERE m.status = 'active'
    ORDER BY m.created_at DESC
    LIMIT 60
");
$listings = $stmt->fetchAll();

// Categories
$categories = ['All', 'Photos', 'Videos', 'Services', 'Meetups', 'Exclusive Content'];
?>

<div style="padding: 0 15px 20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <h2 style="font-size:1.4rem; margin:0; color:white;">Desire Market 🛒</h2>
        <button class="btn-primary" style="padding:9px 16px; font-size:0.8rem;" onclick="openListingModal()">
            <i class="fas fa-plus"></i> List
        </button>
    </div>
    
    <!-- Search -->
    <div style="position:relative; margin-bottom:16px;">
        <i class="fas fa-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:0.9rem;"></i>
        <input type="text" id="marketSearch" placeholder="Search listings..." oninput="filterListings(this.value)"
               style="width:100%; padding:11px 14px 11px 38px; border-radius:25px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; box-sizing:border-box;">
    </div>
    
    <!-- Category Pills -->
    <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:10px; scrollbar-width:none; margin-bottom:20px;">
        <?php foreach($categories as $i => $cat): ?>
        <button class="filter-pill <?php echo $i === 0 ? 'active' : ''; ?>" onclick="filterCategory('<?php echo $cat; ?>', this)" style="white-space:nowrap;">
            <?php echo $cat; ?>
        </button>
        <?php endforeach; ?>
    </div>
    
    <!-- Listings Grid -->
    <div id="marketGrid" style="display:grid; grid-template-columns:repeat(2, 1fr); gap:12px;">
        <?php if (empty($listings)): ?>
        <div style="grid-column:1/-1; text-align:center; padding:50px 20px;">
            <div style="font-size:3rem; margin-bottom:12px;">🛒</div>
            <p style="color:var(--text-secondary); font-size:0.9rem;">The market is quiet. Be the first to list something irresistible.</p>
            <button class="btn-primary" style="margin-top:15px;" onclick="openListingModal()">List Something</button>
        </div>
        <?php else: ?>
        <?php foreach($listings as $item):
            $img = $item['image'] ?? '';
            $sellerAvatar = $item['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($item['username']) . '&background=8e1a1a&color=fff';
        ?>
        <div class="market-card glass-panel" 
             data-category="<?php echo esc($item['category'] ?? 'other'); ?>"
             data-title="<?php echo esc(strtolower($item['title'])); ?>"
             style="border-radius:16px; overflow:hidden; cursor:pointer; transition:transform 0.2s;"
             onclick="openItemDetail(<?php echo $item['id']; ?>)"
             onmouseover="this.style.transform='translateY(-3px)'"
             onmouseout="this.style.transform='translateY(0)'">
            
            <!-- Image -->
            <div style="height:150px; overflow:hidden; position:relative; background:#1a0b12;">
                <?php if ($img): ?>
                <img src="<?php echo esc($img); ?>" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                <?php else: ?>
                <div style="height:100%; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-shopping-bag" style="font-size:2.5rem; color:rgba(255,42,109,0.3);"></i>
                </div>
                <?php endif; ?>
                <div style="position:absolute; top:8px; left:8px; background:var(--neon-pink); color:white; font-size:0.65rem; padding:3px 8px; border-radius:8px; font-weight:700;">
                    <?php echo esc($item['category'] ?? 'Market'); ?>
                </div>
            </div>
            
            <!-- Info -->
            <div style="padding:12px;">
                <h3 style="margin:0 0 4px; font-size:0.9rem; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?php echo esc($item['title']); ?>
                </h3>
                <p style="margin:0 0 8px; font-size:0.75rem; color:var(--text-secondary); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                    <?php echo esc($item['description']); ?>
                </p>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--neon-pink); font-weight:800; font-size:0.95rem;">
                        ₦<?php echo number_format($item['price'], 0); ?>
                    </span>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <img src="<?php echo esc($sellerAvatar); ?>" style="width:22px; height:22px; border-radius:50%; object-fit:cover; border:1px solid var(--glass-border);">
                        <span style="font-size:0.72rem; color:var(--text-muted);"><?php echo esc($item['username']); ?></span>
                        <?php if ($item['is_verified']): ?><i class="fas fa-check-circle" style="color:var(--neon-pink); font-size:0.6rem;"></i><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- =================== CREATE LISTING MODAL =================== -->
<div id="listingModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.96); z-index:9999; align-items:center; justify-content:center;">
    <div class="glass-panel" style="background:linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98)); border:2px solid var(--neon-pink); border-radius:20px; max-width:420px; width:90%; padding:25px; max-height:90vh; overflow-y:auto;">
        <h3 style="color:var(--neon-pink); margin-bottom:18px; text-align:center;">List Your Desire 🛒</h3>
        
        <input type="text" id="listingTitle" placeholder="Title (e.g. Exclusive Photoshoot 📸)"
               style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; margin-bottom:12px; box-sizing:border-box; font-family:inherit;">
        
        <textarea id="listingDesc" rows="3" placeholder="Description..."
               style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; margin-bottom:12px; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
        
        <div style="display:flex; gap:10px; margin-bottom:12px;">
            <div style="flex:1;">
                <input type="number" id="listingPrice" placeholder="Price (₦)"
                       style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:rgba(255,255,255,0.05); color:white; outline:none; font-size:0.88rem; box-sizing:border-box; font-family:inherit;">
            </div>
            <div style="flex:1;">
                <select id="listingCategory" style="width:100%; padding:11px 14px; border-radius:10px; border:1px solid rgba(255,42,109,0.3); background:#1a0b12; color:white; outline:none; font-size:0.85rem; font-family:inherit; box-sizing:border-box;">
                    <option value="">Category</option>
                    <?php foreach(['Photos','Videos','Services','Meetups','Exclusive Content'] as $c): ?>
                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div onclick="document.getElementById('listingImageInput').click()" style="border:2px dashed rgba(255,42,109,0.4); padding:16px; text-align:center; border-radius:12px; cursor:pointer; margin-bottom:16px;">
            <i class="fas fa-image" style="font-size:1.8rem; color:var(--neon-pink);"></i>
            <p style="font-size:0.8rem; margin-top:8px; color:var(--text-secondary);">Upload Preview Image</p>
            <input type="file" id="listingImageInput" accept="image/*" style="display:none;" onchange="previewListingImage(this)">
        </div>
        <img id="listingImagePreview" src="" style="display:none; width:100%; height:120px; object-fit:cover; border-radius:10px; margin-bottom:12px; border:1px solid var(--neon-pink);">
        
        <div style="display:flex; gap:10px;">
            <button class="btn-primary" style="flex:1; background:#333; box-shadow:none;" onclick="closeListingModal()">Cancel</button>
            <button class="btn-primary" style="flex:1;" onclick="submitListing()">List Now 🛒</button>
        </div>
    </div>
</div>

<!-- =================== ITEM DETAIL MODAL =================== -->
<div id="itemDetailModal" style="display:none; position:fixed; inset:0; background:rgba(10,4,6,0.97); z-index:9999; overflow-y:auto; padding:20px;">
    <button onclick="document.getElementById('itemDetailModal').style.display='none'" style="position:fixed; top:15px; right:15px; background:rgba(255,255,255,0.1); border:none; color:white; font-size:1.3rem; width:36px; height:36px; border-radius:50%; cursor:pointer; z-index:10;">✕</button>
    <div id="itemDetailContent" style="max-width:480px; margin:40px auto 0;"></div>
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
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
}
.filter-pill.active, .filter-pill:hover {
    background: var(--neon-pink);
    color: white;
    border-color: var(--neon-pink);
}
</style>

<script>
let listingImageBase64 = '';

function filterListings(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.market-card').forEach(card => {
        const m = !q || card.dataset.title.includes(q) || card.dataset.category.includes(q);
        card.style.display = m ? 'block' : 'none';
    });
}

function filterCategory(cat, btn) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.market-card').forEach(card => {
        card.style.display = (cat === 'All' || card.dataset.category.toLowerCase() === cat.toLowerCase()) ? 'block' : 'none';
    });
}

function openListingModal() { document.getElementById('listingModal').style.display = 'flex'; }
function closeListingModal() { document.getElementById('listingModal').style.display = 'none'; }

function previewListingImage(input) {
    const file = input.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        listingImageBase64 = e.target.result;
        const prev = document.getElementById('listingImagePreview');
        prev.src = e.target.result; prev.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

async function submitListing() {
    const title    = document.getElementById('listingTitle').value.trim();
    const desc     = document.getElementById('listingDesc').value.trim();
    const price    = parseFloat(document.getElementById('listingPrice').value);
    const category = document.getElementById('listingCategory').value;
    if (!title || !desc || !price || !category) { window.utils.showToast('Fill all fields!', 'error'); return; }
    
    const res = await fetch('api/market.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description: desc, price, category, image: listingImageBase64, csrf_token: window.csrfToken })
    }).then(r => r.json());
    
    if (res.status === 'success') { window.utils.showToast('Listing created 🛒'); closeListingModal(); setTimeout(() => location.reload(), 1200); }
    else window.utils.showToast(res.message || 'Failed to create listing', 'error');
}

function openItemDetail(id) {
    const modal = document.getElementById('itemDetailModal');
    const content = document.getElementById('itemDetailContent');
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--neon-pink);"></i></div>';
    
    fetch('api/market.php?action=get&id=' + id)
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const item = res.data;
                const imgHtml = item.image ? `<img src="${item.image}" style="width:100%;height:250px;object-fit:cover;border-radius:16px 16px 0 0;display:block;">` : '';
                content.innerHTML = `
                    <div class="glass-panel" style="border-radius:16px;overflow:hidden;padding:0;">
                        ${imgHtml}
                        <div style="padding:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                <h2 style="margin:0;font-size:1.1rem;color:white;">${item.title}</h2>
                                <span style="background:var(--neon-pink);color:white;padding:3px 10px;border-radius:10px;font-size:0.7rem;font-weight:700;">${item.category}</span>
                            </div>
                            <p style="color:var(--text-secondary);font-size:0.88rem;line-height:1.6;margin-bottom:16px;">${item.description}</p>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                                <span style="color:var(--neon-pink);font-size:1.3rem;font-weight:800;">₦${Number(item.price).toLocaleString()}</span>
                                <span style="color:var(--text-muted);font-size:0.78rem;">by @${item.username}</span>
                            </div>
                            <button class="btn-primary" style="width:100%;" onclick="purchaseItem(${item.id}, ${item.price})">
                                <i class="fas fa-lock-open"></i> Purchase with Wallet
                            </button>
                            <button onclick="window.location.href='chat.php?partner_id=${item.seller_id}&partner_name=${encodeURIComponent(item.username)}'" style="width:100%;margin-top:10px;background:none;border:1px solid var(--glass-border);color:var(--text-secondary);padding:12px;border-radius:10px;cursor:pointer;font-family:inherit;font-size:0.9rem;">
                                <i class="fas fa-comment-dots"></i> Ask Seller
                            </button>
                        </div>
                    </div>
                `;
            }
        }).catch(() => { content.innerHTML = '<p style="color:var(--text-secondary);text-align:center;">Failed to load item</p>'; });
}

async function purchaseItem(itemId, price) {
    if (!confirm(`Purchase this item for ₦${Number(price).toLocaleString()} from your wallet?`)) return;
    const res = await fetch('api/market.php?action=purchase', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, csrf_token: window.csrfToken })
    }).then(r => r.json());
    window.utils.showToast(res.message || (res.status === 'success' ? 'Purchase successful! 🎉' : 'Purchase failed'), res.status);
    if (res.status === 'success') document.getElementById('itemDetailModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
