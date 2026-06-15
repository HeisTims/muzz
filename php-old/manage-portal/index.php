<?php
// =====================================================================
// EazyMUZE — Admin Control Panel (manage-portal/index.php)
// =====================================================================
session_start();
require_once dirname(__DIR__) . '/includes/env.php';

// ── Auth guard ──────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/api/db.php';

// ── Admin PIN (for API calls) ────────────────────────────────────────
$admin_pin = getenv('ADMIN_PIN') ?: 'admin123';

// ── Fetch live stats ─────────────────────────────────────────────────
$stats = [
    'total_users'    => $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'new_today'      => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'online_now'     => $pdo->query("SELECT COUNT(*) FROM users WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(),
    'total_posts'    => $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'total_msgs'     => $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'total_revenue'  => $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments')->fetchColumn(),
    'open_tickets'   => 0,
    'pending_reports'=> 0,
];
// Graceful fallback for tables that may not exist yet
try { $stats['open_tickets']    = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn(); } catch (Exception $e) {}
try { $stats['pending_reports'] = $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn(); } catch (Exception $e) {}

// ── Fetch users (paginated, 50 per page) ─────────────────────────────
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');

$whereClause = '1=1';
$params = [];
if ($search) {
    $whereClause .= " AND (username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
}
if ($filter === 'verified') { $whereClause .= ' AND is_verified = 1'; }
if ($filter === 'unverified') { $whereClause .= ' AND is_verified = 0'; }
if ($filter === 'banned') { $whereClause .= ' AND is_active = 0'; }

$total_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereClause");
$total_count_stmt->execute($params);
$total_users_count = $total_count_stmt->fetchColumn();
$total_pages = ceil($total_users_count / $limit);

$users_stmt = $pdo->prepare("
    SELECT id, username, email, phone, preference, gender, location,
           wallet_balance, is_verified, is_active, created_at,
           (CASE WHEN last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS is_online
    FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?
");
$users_stmt->execute(array_merge($params, [$limit, $offset]));
$users = $users_stmt->fetchAll();

// ── Recent transactions ───────────────────────────────────────────────
$payments = $pdo->query("
    SELECT p.*, u.username FROM payments p
    LEFT JOIN users u ON p.payer_id = u.id
    ORDER BY p.created_at DESC LIMIT 20
")->fetchAll();

// ── Support tickets ───────────────────────────────────────────────────
$tickets = [];
try {
    $tickets = $pdo->query("
        SELECT t.*, u.username FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {}

// ── Reports ───────────────────────────────────────────────────────────
$reports = [];
try {
    $reports = $pdo->query("
        SELECT r.*, u.username AS reporter_username FROM reports r
        LEFT JOIN users u ON r.reporter_id = u.id
        ORDER BY r.created_at DESC LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {}

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EazyMUZE — Admin Control Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-pink: #ff2a6d;
            --obsidian:  #0a0406;
            --velvet-bg: #0d0508;
            --card-bg:   rgba(20, 10, 15, 0.95);
            --border:    rgba(255, 42, 109, 0.2);
            --text:      #f0e6ea;
            --text-muted: rgba(255,255,255,0.4);
            --success:   #2ecc71;
            --warning:   #f1c40f;
            --danger:    #e74c3c;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: var(--obsidian); color: var(--text); min-height: 100vh; display: flex; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            background: linear-gradient(180deg, rgba(20,10,15,0.98) 0%, rgba(45,15,30,0.98) 100%);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sidebar-logo span { font-size: 1rem; font-weight: 800; color: var(--neon-pink); }
        .sidebar-logo small { font-size: 0.65rem; color: var(--text-muted); display: block; }
        .nav-section { padding: 16px 14px 6px; font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 20px;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 0;
            transition: all 0.18s;
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .nav-item:hover, .nav-item.active {
            color: white;
            background: rgba(255,42,109,0.1);
            border-left-color: var(--neon-pink);
        }
        .nav-item i { width: 18px; text-align: center; font-size: 0.9rem; }
        .nav-badge {
            margin-left: auto;
            background: var(--neon-pink);
            color: white;
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 700;
        }
        .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid var(--border); }

        /* ── MAIN CONTENT ── */
        .main { margin-left: 240px; flex: 1; min-height: 100vh; overflow-x: hidden; }

        /* ── TOP BAR ── */
        .topbar {
            padding: 16px 28px;
            background: rgba(10,4,6,0.97);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar h1 { font-size: 1.1rem; font-weight: 700; color: white; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .online-badge { background: rgba(46,204,113,0.15); color: var(--success); padding: 5px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; border: 1px solid rgba(46,204,113,0.3); }

        /* ── CONTENT PANELS ── */
        .content { padding: 24px 28px; }
        .panel { display: none; }
        .panel.active { display: block; }

        /* ── STAT CARDS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 80px; height: 80px;
            border-radius: 0 0 0 80px;
            opacity: 0.1;
        }
        .stat-card.pink::before { background: var(--neon-pink); }
        .stat-card.green::before { background: var(--success); }
        .stat-card.yellow::before { background: var(--warning); }
        .stat-card.blue::before { background: #3498db; }
        .stat-label { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: white; line-height: 1; }
        .stat-sub { font-size: 0.72rem; margin-top: 6px; color: var(--text-muted); }
        .stat-icon { position: absolute; top: 18px; right: 18px; font-size: 1.4rem; opacity: 0.3; }

        /* ── TABLE ── */
        .table-wrap { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .table-header { padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); }
        .table-header h3 { font-size: 0.95rem; color: white; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; border-bottom: 1px solid var(--border); background: rgba(255,255,255,0.02); }
        td { padding: 12px 16px; font-size: 0.85rem; color: var(--text); border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,42,109,0.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; }
        .badge-green  { background: rgba(46,204,113,0.15); color: var(--success); }
        .badge-pink   { background: rgba(255,42,109,0.15); color: var(--neon-pink); }
        .badge-yellow { background: rgba(241,196,15,0.15); color: var(--warning); }
        .badge-red    { background: rgba(231,76,60,0.15); color: var(--danger); }
        .badge-grey   { background: rgba(255,255,255,0.08); color: var(--text-muted); }

        /* ── BUTTONS ── */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; font-family: 'Outfit', sans-serif; cursor: pointer; border: none; transition: all 0.18s; }
        .btn-pink  { background: var(--neon-pink); color: white; box-shadow: 0 2px 12px rgba(255,42,109,0.3); }
        .btn-pink:hover { box-shadow: 0 4px 20px rgba(255,42,109,0.5); transform: translateY(-1px); }
        .btn-ghost { background: rgba(255,255,255,0.06); color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: rgba(255,255,255,0.1); }
        .btn-red   { background: rgba(231,76,60,0.2); color: var(--danger); border: 1px solid rgba(231,76,60,0.3); }
        .btn-red:hover { background: rgba(231,76,60,0.35); }
        .btn-green { background: rgba(46,204,113,0.2); color: var(--success); border: 1px solid rgba(46,204,113,0.3); }
        .btn-sm    { padding: 5px 10px; font-size: 0.74rem; }

        /* ── SEARCH / FILTER BAR ── */
        .filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .filter-bar input, .filter-bar select {
            padding: 9px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: white;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            outline: none;
        }
        .filter-bar input { flex: 1; min-width: 200px; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--neon-pink); }

        /* ── MODAL ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: linear-gradient(135deg, rgba(20,10,15,0.99), rgba(45,15,30,0.99));
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }
        .modal-box h3 { font-size: 1.05rem; color: var(--neon-pink); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .form-field label { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-field input, .form-field select, .form-field textarea {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: white;
            font-family: 'Outfit', sans-serif;
            font-size: 0.88rem;
            outline: none;
        }
        .form-field input:focus, .form-field select:focus { border-color: var(--neon-pink); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-actions .btn { flex: 1; justify-content: center; padding: 12px; }

        /* ── WALLET ADJUST ── */
        .wallet-adjust {
            background: rgba(255,42,109,0.08);
            border: 1px solid rgba(255,42,109,0.2);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 14px;
        }

        /* ── TOASTS ── */
        #adminToast {
            position: fixed; bottom: 24px; right: 24px;
            background: rgba(20,10,15,0.98);
            border: 1px solid var(--neon-pink);
            border-radius: 12px;
            padding: 12px 18px;
            color: white;
            font-size: 0.88rem;
            z-index: 99999;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            max-width: 320px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        }
        #adminToast.show { transform: translateX(0); }

        /* ── CHARTS placeholder ── */
        .chart-area { height: 180px; background: rgba(255,42,109,0.04); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 0.85rem; border: 1px dashed var(--border); }

        /* ── PAGINATION ── */
        .pagination { display: flex; gap: 6px; align-items: center; margin-top: 16px; justify-content: center; flex-wrap: wrap; }
        .pg-btn { padding: 7px 13px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; border: 1px solid var(--border); background: rgba(255,255,255,0.04); color: var(--text); font-family: 'Outfit', sans-serif; text-decoration: none; }
        .pg-btn.active { background: var(--neon-pink); color: white; border-color: var(--neon-pink); }
        .pg-btn:hover:not(.active) { background: rgba(255,255,255,0.08); }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ======================== SIDEBAR ======================== -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div style="width:38px;height:38px;background:linear-gradient(135deg,#ff2a6d,#b5006a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">💋</div>
        <div>
            <span>EazyMUZE</span>
            <small>Admin Panel v3</small>
        </div>
    </div>

    <div class="nav-section">Overview</div>
    <a class="nav-item active" onclick="showPanel('dashboard')"><i class="fas fa-chart-pie"></i> Dashboard</a>

    <div class="nav-section">Management</div>
    <a class="nav-item" onclick="showPanel('users')"><i class="fas fa-users"></i> Users
        <span class="nav-badge"><?php echo $stats['total_users']; ?></span>
    </a>
    <a class="nav-item" onclick="showPanel('posts')"><i class="fas fa-images"></i> Posts & Stories</a>
    <a class="nav-item" onclick="showPanel('messages')"><i class="fas fa-comment-dots"></i> Messages</a>
    <a class="nav-item" onclick="showPanel('payments')"><i class="fas fa-money-bill-wave"></i> Payments
        <span class="nav-badge">₦</span>
    </a>
    <a class="nav-item" onclick="showPanel('market_admin')"><i class="fas fa-shopping-cart"></i> Market</a>

    <div class="nav-section">Support</div>
    <a class="nav-item" onclick="showPanel('tickets')"><i class="fas fa-headset"></i> Tickets
        <?php if ($stats['open_tickets'] > 0): ?><span class="nav-badge"><?php echo $stats['open_tickets']; ?></span><?php endif; ?>
    </a>
    <a class="nav-item" onclick="showPanel('reports')"><i class="fas fa-flag"></i> Reports
        <?php if ($stats['pending_reports'] > 0): ?><span class="nav-badge" style="background:#e74c3c;"><?php echo $stats['pending_reports']; ?></span><?php endif; ?>
    </a>

    <div class="nav-section">System</div>
    <a class="nav-item" onclick="runMigrations()"><i class="fas fa-database"></i> Run Migrations</a>
    <a class="nav-item" onclick="runSeed()"><i class="fas fa-seedling"></i> Seed Users</a>
    <a class="nav-item" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>

    <div class="sidebar-footer">
        <a class="nav-item" href="login.php" style="color:var(--danger);" onclick="return confirm('Log out?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<!-- ======================== MAIN ======================== -->
<div class="main">

    <!-- TOP BAR -->
    <div class="topbar">
        <h1 id="panelTitle">📊 Dashboard Overview</h1>
        <div class="topbar-right">
            <span class="online-badge">● <?php echo $stats['online_now']; ?> online now</span>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?php echo date('D, M j · g:i A'); ?></span>
        </div>
    </div>

    <div class="content">

        <!-- ======================== DASHBOARD PANEL ======================== -->
        <div class="panel active" id="panel-dashboard">
            <div class="stats-grid">
                <div class="stat-card pink">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-sub">+<?php echo $stats['new_today']; ?> today</div>
                </div>
                <div class="stat-card green">
                    <i class="fas fa-image stat-icon"></i>
                    <div class="stat-label">Total Posts</div>
                    <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                    <div class="stat-sub">Moments shared</div>
                </div>
                <div class="stat-card yellow">
                    <i class="fas fa-comment-dots stat-icon"></i>
                    <div class="stat-label">Messages</div>
                    <div class="stat-value"><?php echo number_format($stats['total_msgs']); ?></div>
                    <div class="stat-sub">Whispers sent</div>
                </div>
                <div class="stat-card blue">
                    <i class="fas fa-naira-sign stat-icon"></i>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">₦<?php echo number_format($stats['total_revenue'], 0); ?></div>
                    <div class="stat-sub">Platform earnings</div>
                </div>
            </div>

            <!-- Secondary Stats -->
            <div style="display:grid; grid-template-columns: repeat(3,1fr); gap:16px; margin-bottom:28px;">
                <div class="stat-card">
                    <div class="stat-label">Active Right Now</div>
                    <div class="stat-value" style="color:var(--success);"><?php echo $stats['online_now']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Open Support Tickets</div>
                    <div class="stat-value" style="color:var(--warning);"><?php echo $stats['open_tickets']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Reports</div>
                    <div class="stat-value" style="color:var(--danger);"><?php echo $stats['pending_reports']; ?></div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="table-wrap">
                <div class="table-header">
                    <h3><i class="fas fa-user-plus" style="color:var(--neon-pink);margin-right:6px;"></i> Newest Members</h3>
                    <button class="btn btn-pink btn-sm" onclick="showPanel('users')">View All →</button>
                </div>
                <table>
                    <thead>
                        <tr><th>User</th><th>Preference</th><th>Wallet</th><th>Status</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach(array_slice($users, 0, 8) as $u): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700;color:white;"><?php echo esc($u['username']); ?></div>
                                <div style="font-size:0.72rem;color:var(--text-muted);"><?php echo esc($u['email']); ?></div>
                            </td>
                            <td><span class="badge badge-pink"><?php echo esc($u['preference'] ?? '-'); ?></span></td>
                            <td style="color:var(--neon-pink);font-weight:700;">₦<?php echo number_format($u['wallet_balance'], 2); ?></td>
                            <td>
                                <?php if ($u['is_online']): ?>
                                <span class="badge badge-green">● Online</span>
                                <?php elseif ($u['is_active']): ?>
                                <span class="badge badge-grey">Active</span>
                                <?php else: ?>
                                <span class="badge badge-red">Banned</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-muted);font-size:0.78rem;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ======================== USERS PANEL ======================== -->
        <div class="panel" id="panel-users">
            <div class="filter-bar">
                <form method="GET" style="display:contents;">
                    <input type="hidden" name="panel" value="users">
                    <input type="text" name="q" placeholder="🔍 Search username, email, phone..." value="<?php echo esc($search); ?>">
                    <select name="filter">
                        <option value="all" <?php echo $filter==='all'?'selected':''; ?>>All Users</option>
                        <option value="verified" <?php echo $filter==='verified'?'selected':''; ?>>Verified Only</option>
                        <option value="unverified" <?php echo $filter==='unverified'?'selected':''; ?>>Unverified</option>
                        <option value="banned" <?php echo $filter==='banned'?'selected':''; ?>>Banned</option>
                    </select>
                    <button type="submit" class="btn btn-pink">Search</button>
                    <?php if ($search || $filter !== 'all'): ?>
                    <a href="?panel=users" class="btn btn-ghost">Clear</a>
                    <?php endif; ?>
                </form>
                <button class="btn btn-green" onclick="openCreateUserModal()"><i class="fas fa-plus"></i> Create User</button>
            </div>

            <div class="table-wrap">
                <div class="table-header">
                    <h3><i class="fas fa-users" style="color:var(--neon-pink);margin-right:6px;"></i> All Users (<?php echo $total_users_count; ?>)</h3>
                    <span style="font-size:0.78rem;color:var(--text-muted);">Page <?php echo $page; ?> of <?php echo max(1,$total_pages); ?></span>
                </div>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>ID</th><th>User</th><th>Phone</th><th>Pref</th><th>Wallet</th><th>Verified</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.78rem;">#<?php echo $u['id']; ?></td>
                            <td>
                                <div style="font-weight:700;color:white;display:flex;align-items:center;gap:5px;">
                                    <?php echo esc($u['username']); ?>
                                    <?php if ($u['is_online']): ?><span style="width:7px;height:7px;background:var(--success);border-radius:50%;display:inline-block;"></span><?php endif; ?>
                                </div>
                                <div style="font-size:0.72rem;color:var(--text-muted);"><?php echo esc($u['email']); ?></div>
                            </td>
                            <td style="font-size:0.78rem;"><?php echo esc($u['phone'] ?? '-'); ?></td>
                            <td><span class="badge badge-pink"><?php echo esc($u['preference'] ?? '-'); ?></span></td>
                            <td>
                                <span style="color:var(--neon-pink);font-weight:700;">₦<?php echo number_format($u['wallet_balance'], 2); ?></span>
                            </td>
                            <td><?php echo $u['is_verified'] ? '<span class="badge badge-green">✓ Yes</span>' : '<span class="badge badge-grey">No</span>'; ?></td>
                            <td><?php echo $u['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Banned</span>'; ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <div style="display:flex;gap:5px;flex-wrap:nowrap;">
                                    <button class="btn btn-ghost btn-sm" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                                    <button class="btn btn-ghost btn-sm" onclick="adjustWallet(<?php echo $u['id']; ?>, '<?php echo esc($u['username']); ?>', <?php echo $u['wallet_balance']; ?>)" title="Wallet"><i class="fas fa-wallet"></i></button>
                                    <button class="btn btn-ghost btn-sm" onclick="toggleVerify(<?php echo $u['id']; ?>, <?php echo $u['is_verified']; ?>)" title="Toggle Verify"><i class="fas fa-check-circle" style="color:<?php echo $u['is_verified'] ? 'var(--neon-pink)' : 'var(--text-muted)'; ?>;"></i></button>
                                    <button class="btn btn-ghost btn-sm" onclick="toggleBan(<?php echo $u['id']; ?>, <?php echo $u['is_active']; ?>)" title="Ban/Unban" style="color:<?php echo $u['is_active'] ? 'var(--warning)' : 'var(--success)'; ?>;"><i class="fas fa-ban"></i></button>
                                    <button class="btn btn-red btn-sm" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo esc($u['username']); ?>')" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination" style="padding:16px;">
                    <?php if ($page > 1): ?><a href="?panel=users&q=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="pg-btn">‹ Prev</a><?php endif; ?>
                    <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
                    <a href="?panel=users&q=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>&page=<?php echo $p; ?>" class="pg-btn <?php echo $p == $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?><a href="?panel=users&q=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="pg-btn">Next ›</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ======================== PAYMENTS PANEL ======================== -->
        <div class="panel" id="panel-payments">
            <div class="table-wrap">
                <div class="table-header">
                    <h3><i class="fas fa-money-bill-wave" style="color:var(--neon-pink);margin-right:6px;"></i> Recent Transactions</h3>
                    <span style="color:var(--neon-pink);font-weight:800;font-size:0.95rem;">Total: ₦<?php echo number_format($stats['total_revenue'], 2); ?></span>
                </div>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Type</th><th>User</th><th>Amount</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): 
                            $typeMap = ['wallet_funding'=>['Wallet Top-up','badge-green'],'whisper_init'=>['Whisper Fee','badge-pink'],'market_purchase'=>['Market Buy','badge-yellow'],'withdrawal'=>['Withdrawal','badge-red']];
                            [$tlabel, $tbadge] = $typeMap[$p['type']] ?? [ucfirst(str_replace('_',' ',$p['type'])), 'badge-grey'];
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.75rem;">#<?php echo $p['id']; ?></td>
                            <td><span class="badge <?php echo $tbadge; ?>"><?php echo $tlabel; ?></span></td>
                            <td style="font-weight:600;"><?php echo esc($p['username'] ?? 'System'); ?></td>
                            <td style="color:var(--neon-pink);font-weight:700;">₦<?php echo number_format($p['amount'], 2); ?></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?php echo date('M j, Y g:i A', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ======================== TICKETS PANEL ======================== -->
        <div class="panel" id="panel-tickets">
            <div class="table-wrap">
                <div class="table-header">
                    <h3><i class="fas fa-headset" style="color:var(--neon-pink);margin-right:6px;"></i> Support Tickets</h3>
                </div>
                <?php if (empty($tickets)): ?>
                <div style="padding:40px;text-align:center;color:var(--text-muted);">No support tickets yet.</div>
                <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>User</th><th>Category</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($tickets as $t): 
                            $sc = ['open'=>'badge-yellow','in_progress'=>'badge-pink','resolved'=>'badge-green'][$t['status']] ?? 'badge-grey';
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.75rem;">#<?php echo $t['id']; ?></td>
                            <td style="font-weight:600;">@<?php echo esc($t['username'] ?? '?'); ?></td>
                            <td><span class="badge badge-pink"><?php echo esc($t['category']); ?></span></td>
                            <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.82rem;"><?php echo esc($t['message']); ?></td>
                            <td><span class="badge <?php echo $sc; ?>"><?php echo ucfirst(str_replace('_',' ',$t['status'])); ?></span></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?php echo date('M j', strtotime($t['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-green btn-sm" onclick="resolveTicket(<?php echo $t['id']; ?>)">Resolve</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ======================== REPORTS PANEL ======================== -->
        <div class="panel" id="panel-reports">
            <div class="table-wrap">
                <div class="table-header">
                    <h3><i class="fas fa-flag" style="color:#e74c3c;margin-right:6px;"></i> User Reports</h3>
                </div>
                <?php if (empty($reports)): ?>
                <div style="padding:40px;text-align:center;color:var(--text-muted);">No reports submitted yet.</div>
                <?php else: ?>
                <table>
                    <thead><tr><th>ID</th><th>Reporter</th><th>Reported</th><th>Reason</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($reports as $r): 
                            $rc = ['pending'=>'badge-yellow','reviewed'=>'badge-pink','actioned'=>'badge-red'][$r['status']] ?? 'badge-grey';
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-size:0.75rem;">#<?php echo $r['id']; ?></td>
                            <td style="font-size:0.82rem;">@<?php echo esc($r['reporter_username'] ?? '?'); ?></td>
                            <td style="font-weight:600;color:var(--danger);">@<?php echo esc($r['reported_username']); ?></td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.82rem;"><?php echo esc($r['reason']); ?></td>
                            <td><span class="badge <?php echo $rc; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                            <td style="font-size:0.75rem;color:var(--text-muted);"><?php echo date('M j', strtotime($r['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-red btn-sm" onclick="banReportedUser(<?php echo $r['reported_user_id'] ?? 0; ?>, <?php echo $r['id']; ?>)">Ban User</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ======================== POSTS / STORIES / MESSAGES / MARKET ======================== -->
        <div class="panel" id="panel-posts"><div class="table-wrap"><div class="table-header"><h3>Posts & Stories</h3><button class="btn btn-pink btn-sm" onclick="loadTable('posts')"><i class="fas fa-sync"></i> Load</button></div><div id="postsTableBody" style="padding:20px;color:var(--text-muted);text-align:center;">Click Load to fetch posts.</div></div></div>
        <div class="panel" id="panel-messages"><div class="table-wrap"><div class="table-header"><h3>Recent Messages</h3><button class="btn btn-pink btn-sm" onclick="loadTable('messages')"><i class="fas fa-sync"></i> Load</button></div><div id="messagesTableBody" style="padding:20px;color:var(--text-muted);text-align:center;">Click Load to fetch messages.</div></div></div>
        <div class="panel" id="panel-market_admin"><div class="table-wrap"><div class="table-header"><h3>Market Listings</h3><button class="btn btn-pink btn-sm" onclick="loadTable('market')"><i class="fas fa-sync"></i> Load</button></div><div id="marketTableBody" style="padding:20px;color:var(--text-muted);text-align:center;">Click Load to fetch listings.</div></div></div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ======================== MODALS ======================== -->

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-box">
        <h3><i class="fas fa-user-edit"></i> Edit User</h3>
        <input type="hidden" id="editUserId">
        <div class="form-row">
            <div class="form-field"><label>Username</label><input type="text" id="editUsername"></div>
            <div class="form-field"><label>Email</label><input type="email" id="editEmail"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Phone</label><input type="text" id="editPhone"></div>
            <div class="form-field"><label>Gender</label>
                <select id="editGender"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field"><label>Preference</label>
                <select id="editPreference">
                    <?php foreach(['straight','gay','lesbian','bisexual','sugar_daddy','sugar_mummy','open'] as $p): ?>
                    <option value="<?php echo $p; ?>"><?php echo ucfirst(str_replace('_',' ',$p)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Role</label>
                <select id="editRole"><option value="user">User</option><option value="admin">Admin</option></select>
            </div>
        </div>
        <div class="form-field"><label>Location</label><input type="text" id="editLocation"></div>
        <div class="form-field"><label>Bio</label><textarea id="editBio" rows="2" style="resize:none;"></textarea></div>
        <div class="form-row">
            <div class="form-field"><label>Verified</label><select id="editVerified"><option value="1">Yes ✓</option><option value="0">No</option></select></div>
            <div class="form-field"><label>Active</label><select id="editActive"><option value="1">Active</option><option value="0">Banned</option></select></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('editUserModal')">Cancel</button>
            <button class="btn btn-pink" onclick="saveUserEdit()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</div>

<!-- Wallet Adjust Modal -->
<div class="modal-overlay" id="walletModal">
    <div class="modal-box">
        <h3><i class="fas fa-wallet"></i> Adjust Wallet — <span id="walletModalUsername"></span></h3>
        <input type="hidden" id="walletUserId">
        <div class="wallet-adjust">
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:6px;">Current Balance</div>
            <div style="font-size:2rem;font-weight:800;color:var(--neon-pink);" id="walletCurrentBal">₦0.00</div>
        </div>
        <div class="form-field">
            <label>Amount to Add (positive) or Deduct (negative)</label>
            <input type="number" id="walletAdjustAmount" placeholder="e.g. 5000 or -2000" step="0.01">
        </div>
        <div class="form-field">
            <label>Reason / Note (optional)</label>
            <input type="text" id="walletAdjustNote" placeholder="Admin bonus, refund, penalty...">
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal('walletModal')">Cancel</button>
            <button class="btn btn-pink" onclick="saveWalletAdjust()"><i class="fas fa-exchange-alt"></i> Apply</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="adminToast"></div>

<!-- ======================== JAVASCRIPT ======================== -->
<script>
const ADMIN_PIN = "<?php echo esc($admin_pin); ?>";
const API = "../api/admin.php?pin=" + encodeURIComponent(ADMIN_PIN);

// ── PANEL SWITCHING ──────────────────────────────────────────────────
function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.classList.add('active');
    const titles = {
        dashboard:'📊 Dashboard Overview', users:'👥 User Management',
        posts:'📸 Posts & Stories', messages:'💬 Messages',
        payments:'💳 Payments & Revenue', market_admin:'🛒 Market Listings',
        tickets:'🎫 Support Tickets', reports:'🚩 User Reports'
    };
    document.getElementById('panelTitle').textContent = titles[name] || name;

    // Set active sidebar
    event?.target?.closest('.nav-item')?.classList.add('active');
    if (document.getElementById('panel-' + name)) document.getElementById('panel-' + name).scrollIntoView({ block: 'nearest' });
    history.replaceState({}, '', '?panel=' + name);
}

// Auto-restore panel from URL
const urlPanel = new URLSearchParams(location.search).get('panel');
if (urlPanel) showPanel(urlPanel);

// ── TOAST ────────────────────────────────────────────────────────────
function toast(msg, ok = true) {
    const el = document.getElementById('adminToast');
    el.textContent = (ok ? '✅ ' : '❌ ') + msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 3500);
}

// ── MODAL HELPERS ─────────────────────────────────────────────────────
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

// ── EDIT USER ─────────────────────────────────────────────────────────
function editUser(u) {
    document.getElementById('editUserId').value    = u.id;
    document.getElementById('editUsername').value  = u.username || '';
    document.getElementById('editEmail').value     = u.email || '';
    document.getElementById('editPhone').value     = u.phone || '';
    document.getElementById('editGender').value    = u.gender || 'female';
    document.getElementById('editPreference').value= u.preference || 'straight';
    document.getElementById('editRole').value      = u.role || 'user';
    document.getElementById('editLocation').value  = u.location || '';
    document.getElementById('editBio').value       = u.bio || '';
    document.getElementById('editVerified').value  = u.is_verified ?? 0;
    document.getElementById('editActive').value    = u.is_active ?? 1;
    openModal('editUserModal');
}

async function saveUserEdit() {
    const id = document.getElementById('editUserId').value;
    const res = await fetch(API + '&action=update_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id,
            username:   document.getElementById('editUsername').value,
            email:      document.getElementById('editEmail').value,
            phone:      document.getElementById('editPhone').value,
            gender:     document.getElementById('editGender').value,
            preference: document.getElementById('editPreference').value,
            role:       document.getElementById('editRole').value,
            location:   document.getElementById('editLocation').value,
            bio:        document.getElementById('editBio').value,
            is_verified:document.getElementById('editVerified').value,
            is_active:  document.getElementById('editActive').value,
        })
    }).then(r => r.json());
    toast(res.message, res.status === 'success');
    if (res.status === 'success') { closeModal('editUserModal'); setTimeout(() => location.reload(), 1200); }
}

// ── TOGGLE VERIFY ──────────────────────────────────────────────────────
async function toggleVerify(id, current) {
    const res = await fetch(API + '&action=update_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, is_verified: current ? 0 : 1 })
    }).then(r => r.json());
    toast(res.message || 'Done', res.status === 'success');
    if (res.status === 'success') setTimeout(() => location.reload(), 1000);
}

// ── TOGGLE BAN ─────────────────────────────────────────────────────────
async function toggleBan(id, currentActive) {
    const action = currentActive ? 'ban' : 'unban';
    if (!confirm(`${action.toUpperCase()} this user?`)) return;
    const res = await fetch(API + '&action=update_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, is_active: currentActive ? 0 : 1 })
    }).then(r => r.json());
    toast(res.message || 'Done', res.status === 'success');
    if (res.status === 'success') setTimeout(() => location.reload(), 1000);
}

// ── DELETE USER ────────────────────────────────────────────────────────
async function deleteUser(id, username) {
    if (!confirm(`PERMANENTLY delete @${username}? This cannot be undone!`)) return;
    const res = await fetch(API + '&action=delete_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(r => r.json());
    toast(res.message, res.status === 'success');
    if (res.status === 'success') setTimeout(() => location.reload(), 1200);
}

// ── WALLET ADJUST ──────────────────────────────────────────────────────
function adjustWallet(id, username, current) {
    document.getElementById('walletUserId').value      = id;
    document.getElementById('walletModalUsername').textContent = '@' + username;
    document.getElementById('walletCurrentBal').textContent   = '₦' + parseFloat(current).toLocaleString('en-NG', {minimumFractionDigits:2});
    document.getElementById('walletAdjustAmount').value = '';
    document.getElementById('walletAdjustNote').value   = '';
    openModal('walletModal');
}

async function saveWalletAdjust() {
    const id     = document.getElementById('walletUserId').value;
    const amount = parseFloat(document.getElementById('walletAdjustAmount').value);
    if (isNaN(amount) || amount === 0) { toast('Enter a valid amount (positive to add, negative to deduct)', false); return; }

    const res = await fetch(API + '&action=update_user_wallet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, amount })
    }).then(r => r.json());
    toast(res.message || 'Done', res.status === 'success');
    if (res.status === 'success') { closeModal('walletModal'); setTimeout(() => location.reload(), 1200); }
}

// ── CREATE USER ────────────────────────────────────────────────────────
function openCreateUserModal() {
    toast('Use the Seed Users button to seed demo users, or register via the main site.', true);
}

// ── LAZY-LOAD TABLES ──────────────────────────────────────────────────
async function loadTable(type) {
    const elId = { posts: 'postsTableBody', messages: 'messagesTableBody', market: 'marketTableBody' }[type];
    if (!elId) return;
    const el = document.getElementById(elId);
    el.innerHTML = '<div style="padding:20px;text-align:center;"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--neon-pink);"></i></div>';
    
    const res = await fetch(API + '&action=' + type).then(r => r.json()).catch(() => ({ status: 'error' }));
    if (!res.data || !res.data.length) { el.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">No records found.</div>'; return; }
    
    if (type === 'posts') {
        el.innerHTML = `<table><thead><tr><th>ID</th><th>User</th><th>Caption</th><th>Likes</th><th>Date</th><th>Del</th></tr></thead><tbody>` +
            res.data.map(p => `<tr>
                <td style="color:var(--text-muted);font-size:0.75rem;">#${p.id}</td>
                <td>User #${p.user_id}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;">${(p.caption||'').substring(0,60)}</td>
                <td>${JSON.parse(p.likes||'[]').length}</td>
                <td style="font-size:0.75rem;color:var(--text-muted);">${new Date(p.created_at).toLocaleDateString()}</td>
                <td><button class="btn btn-red btn-sm" onclick="deleteRecord('post',${p.id})"><i class="fas fa-trash"></i></button></td>
            </tr>`).join('') + `</tbody></table>`;
    } else if (type === 'messages') {
        el.innerHTML = `<table><thead><tr><th>ID</th><th>From</th><th>To</th><th>Text</th><th>Date</th><th>Del</th></tr></thead><tbody>` +
            res.data.map(m => `<tr>
                <td style="color:var(--text-muted);font-size:0.75rem;">#${m.id}</td>
                <td style="font-weight:600;">${m.sender||'?'}</td>
                <td>${m.receiver||'?'}</td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.82rem;">${(m.text||'[Image]').substring(0,50)}</td>
                <td style="font-size:0.75rem;color:var(--text-muted);">${new Date(m.timestamp).toLocaleDateString()}</td>
                <td><button class="btn btn-red btn-sm" onclick="deleteRecord('message',${m.id})"><i class="fas fa-trash"></i></button></td>
            </tr>`).join('') + `</tbody></table>`;
    } else if (type === 'market') {
        el.innerHTML = `<table><thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Price</th><th>Status</th><th>Date</th><th>Del</th></tr></thead><tbody>` +
            res.data.map(m => `<tr>
                <td style="color:var(--text-muted);font-size:0.75rem;">#${m.id}</td>
                <td style="font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${m.title}</td>
                <td><span class="badge badge-pink">${m.category}</span></td>
                <td style="color:var(--neon-pink);font-weight:700;">₦${parseFloat(m.price).toLocaleString()}</td>
                <td><span class="badge ${m.status==='active'?'badge-green':m.status==='sold'?'badge-yellow':'badge-red'}">${m.status}</span></td>
                <td style="font-size:0.75rem;color:var(--text-muted);">${new Date(m.created_at).toLocaleDateString()}</td>
                <td><button class="btn btn-red btn-sm" onclick="deleteMarketItem(${m.id})"><i class="fas fa-trash"></i></button></td>
            </tr>`).join('') + `</tbody></table>`;
    }
}

// ── DELETE RECORDS ─────────────────────────────────────────────────────
async function deleteRecord(type, id) {
    if (!confirm(`Delete this ${type}?`)) return;
    const action = 'delete_' + type;
    const res = await fetch(API + '&action=' + action, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(r => r.json());
    toast(res.message || 'Done', res.status === 'success');
    if (res.status === 'success') loadTable(type + 's');
}

async function deleteMarketItem(id) {
    if (!confirm('Remove this market listing?')) return;
    toast('Market admin delete — coming in next update.');
}

// ── RESOLVE TICKET ─────────────────────────────────────────────────
async function resolveTicket(id) {
    if (!confirm('Mark ticket #' + id + ' as resolved?')) return;
    const res = await fetch(API + '&action=resolve_ticket', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(r => r.json());
    toast(res.message || 'Done', res.status === 'success');
    if (res.status === 'success') setTimeout(() => location.reload(), 1200);
}

// ── BAN REPORTED USER ──────────────────────────────────────────────────
async function banReportedUser(userId, reportId) {
    if (!userId) { toast('No user ID for this report', false); return; }
    if (!confirm('Ban this reported user?')) return;
    const res = await fetch(API + '&action=update_user', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId, is_active: 0 })
    }).then(r => r.json());
    toast(res.message || 'User banned', res.status === 'success');
    if (res.status === 'success') setTimeout(() => location.reload(), 1200);
}

// ── SYSTEM TOOLS ──────────────────────────────────────────────────────
async function runMigrations() {
    if (!confirm('Run all pending database migrations?')) return;
    const res = await fetch('../api/migrate_v8.php').then(r => r.json()).catch(() => null);
    if (res) {
        const errors = res.results?.filter(r => r.status !== 'OK') || [];
        toast(errors.length ? `Migrations done (${errors.length} errors, check console)` : 'All migrations ran successfully! ✅');
        console.table(res.results);
    } else {
        toast('Migration request sent. Check server logs.', false);
    }
}

async function runSeed() {
    if (!confirm('Seed 50 demo users? This may take a moment.')) return;
    toast('Seeding users... check network tab.', true);
    fetch('../api/seed_50_users.php').then(r => r.text()).then(t => {
        toast('Seed complete! Reload to see new users.', true);
    }).catch(() => toast('Seed request sent.', true));
}

// ── CLOSE MODAL ON BACKDROP CLICK ─────────────────────────────────────
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>

</body>
</html>
