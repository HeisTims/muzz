<?php
session_start();
require_once 'db.php';
require_once 'smtp.php';
require_once 'email_templates.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Update last_seen for active user on every API call
    if (isset($_SESSION['user_id'])) {
        $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]);
    }

    // ── Fetch single post ─────────────────────────────────────────────
    if ($action === 'get_post' && isset($_GET['id'])) {
        $post_id = intval($_GET['id']);
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.avatar, u.is_verified
            FROM posts p JOIN users u ON p.user_id = u.id
            WHERE p.id = ? LIMIT 1
        ");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        if ($post) sendResponse('success', $post);
        sendResponse('error', null, 'Post not found');
    }

    if ($action === 'posts') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
        $offset = ($page - 1) * $limit;

        $user_id = $_SESSION['user_id'] ?? 0;
        $user_pref = '';
        if ($user_id) {
            $u_stmt = $pdo->prepare('SELECT preference FROM users WHERE id = ?');
            $u_stmt->execute([$user_id]);
            $user_pref = $u_stmt->fetchColumn() ?: '';
        }

        $stmt = $pdo->prepare('
            SELECT p.*, u.username, u.avatar, u.is_verified, u.preference, u.gender 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY 
                CASE 
                    WHEN ? != \'\' AND u.gender = ? THEN 1
                    ELSE 2 
                END ASC,
                p.created_at DESC 
            LIMIT ? OFFSET ?
        ');
        $stmt->bindValue(1, $user_pref, PDO::PARAM_STR);
        $stmt->bindValue(2, $user_pref, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll();

        if (count($posts) === 0) {
            $seed_posts = [
                [
                    'user_id' => 2,
                    'image_fallback' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80',
                    'images' => '["https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80", "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=80"]',
                    'caption' => 'Loving the evening vibe tonight... who is up for a chill conversation? 💋🥂',
                    'music' => 'wizkid',
                    'likes' => '[]',
                    'comments' => '[]',
                    'location_data' => 'Lekki Staging'
                ],
                [
                    'user_id' => 3,
                    'image_fallback' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80',
                    'images' => '["https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80"]',
                    'caption' => 'Living life without rules. DM is open to all open-minded partners in the area. 😈🔥',
                    'music' => 'burna',
                    'likes' => '[]',
                    'comments' => '[]',
                    'location_data' => 'Victoria Island'
                ],
                [
                    'user_id' => 4,
                    'image_fallback' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80',
                    'images' => '["https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80"]',
                    'caption' => 'Tonight feels wild. Looking for a charming companion to share penthouse vibes. 🍷✨',
                    'music' => 'ayra',
                    'likes' => '[]',
                    'comments' => '[]',
                    'location_data' => 'Ikeja'
                ]
            ];
            
            foreach ($seed_posts as $sp) {
                $u_chk = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $u_chk->execute([$sp['user_id']]);
                $chk = $u_chk->fetch();
                $uid = $chk ? $sp['user_id'] : 1;
                
                $ins = $pdo->prepare('INSERT INTO posts (user_id, image_fallback, images, caption, music, likes, comments, location_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$uid, $sp['image_fallback'], $sp['images'], $sp['caption'], $sp['music'], $sp['likes'], $sp['comments'], $sp['location_data']]);
            }
            
            $stmt = $pdo->prepare('
                SELECT p.*, u.username, u.avatar, u.is_verified, u.preference, u.gender 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                ORDER BY 
                    CASE 
                        WHEN ? != \'\' AND u.gender = ? THEN 1
                        ELSE 2 
                    END ASC,
                    p.created_at DESC 
                LIMIT ? OFFSET ?
            ');
            $stmt->bindValue(1, $user_pref, PDO::PARAM_STR);
            $stmt->bindValue(2, $user_pref, PDO::PARAM_STR);
            $stmt->bindValue(3, $limit, PDO::PARAM_INT);
            $stmt->bindValue(4, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $posts = $stmt->fetchAll();
        }

        // Fetch active ads
        $ads_stmt = $pdo->query('SELECT * FROM ads WHERE is_active = 1');
        $ads = $ads_stmt->fetchAll();
        
        $final_feed = [];
        $ad_index = 0;
        
        foreach($posts as $i => &$post) {
            $post['is_ad'] = false;
            $post['images'] = json_decode($post['images'] ?? '[]');
            $post['likes'] = json_decode($post['likes'] ?? '[]');
            $post['comments'] = json_decode($post['comments'] ?? '[]');
            $final_feed[] = $post;
            
            // Inject an ad every 5 posts if available
            if (($i + 1) % 5 == 0 && count($ads) > 0) {
                $ad = $ads[$ad_index % count($ads)];
                $ad_index++;
                $final_feed[] = [
                    'id' => 'ad_' . $ad['id'],
                    'is_ad' => true,
                    'username' => 'Sponsored',
                    'avatar' => 'https://via.placeholder.com/45?text=AD',
                    'is_verified' => true,
                    'preference' => 'promo',
                    'location_data' => 'Global',
                    'image_fallback' => $ad['image'],
                    'images' => [],
                    'caption' => $ad['caption'],
                    'music' => '',
                    'likes' => [],
                    'link' => $ad['link']
                ];
            }
        }
        
        sendResponse('success', $final_feed);
    }
    elseif ($action === 'stories') {
        $user_loc = '';
        if (isset($_SESSION['user_id'])) {
            $u_stmt = $pdo->prepare('SELECT location FROM users WHERE id = ?');
            $u_stmt->execute([$_SESSION['user_id']]);
            $user_loc = $u_stmt->fetchColumn() ?: '';
        }

        $stmt = $pdo->query('
            SELECT s.*, u.username, u.avatar, u.location 
            FROM stories s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.expires_at > NOW()
        ');
        $stories = $stmt->fetchAll();

        if (count($stories) === 0) {
            $seed_stories = [
                ['user_id' => 2, 'image' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80', 'caption' => 'Getting ready for tonight! 💅'],
                ['user_id' => 3, 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80', 'caption' => 'Poolside lounging 💦'],
                ['user_id' => 4, 'image' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80', 'caption' => 'Cocktails are served 🍸']
            ];
            
            foreach ($seed_stories as $ss) {
                $u_chk = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $u_chk->execute([$ss['user_id']]);
                $chk = $u_chk->fetch();
                $uid = $chk ? $ss['user_id'] : 1;
                
                $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $ins = $pdo->prepare('INSERT INTO stories (user_id, image, caption, likes, comments, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
                $ins->execute([$uid, $ss['image'], $ss['caption'], '[]', '[]', $exp]);
            }
            
            $stmt = $pdo->query('
                SELECT s.*, u.username, u.avatar, u.location 
                FROM stories s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.expires_at > NOW()
            ');
            $stories = $stmt->fetchAll();
        }

        // Randomize stories
        shuffle($stories);
        
        // Sort by location proximity (exact match first)
        if ($user_loc) {
            usort($stories, function($a, $b) use ($user_loc) {
                $a_match = (strcasecmp($a['location'] ?? '', $user_loc) === 0) ? 1 : 0;
                $b_match = (strcasecmp($b['location'] ?? '', $user_loc) === 0) ? 1 : 0;
                return $b_match - $a_match; // Higher match comes first
            });
        }

        foreach($stories as &$story) {
            $story['likes'] = json_decode($story['likes'] ?? '[]');
            $story['comments'] = json_decode($story['comments'] ?? '[]');
        }
        sendResponse('success', $stories);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', null, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user_id'];

    if ($action === 'create_post') {
        $caption = $input['caption'] ?? '';
        $images = json_encode($input['images'] ?? []);
        $image_fallback = $input['image_fallback'] ?? '';
        $music = $input['music'] ?? '';
        $location_data = $input['location_data'] ?? '';

        $stmt = $pdo->prepare('INSERT INTO posts (user_id, image_fallback, images, caption, music, likes, location_data) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$user_id, $image_fallback, $images, $caption, $music, '[]', $location_data])) {
            sendResponse('success', ['id' => $pdo->lastInsertId()], 'Post created');
        } else {
            sendResponse('error', null, 'Failed to create post');
        }
    }
    elseif ($action === 'create_story') {
        $image = $input['image'] ?? '';
        $media_type = $input['media_type'] ?? 'image';
        $caption = $input['caption'] ?? '';
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare('INSERT INTO stories (user_id, image, media_type, caption, likes, comments, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$user_id, $image, $media_type, $caption, '[]', '[]', $expires_at])) {
            sendResponse('success', ['id' => $pdo->lastInsertId()], 'Story created');
        } else {
            sendResponse('error', null, 'Failed to create story');
        }
    }
    elseif ($action === 'like_post') {
        $post_id = $input['post_id'] ?? 0;
        $stmt = $pdo->prepare('SELECT likes, user_id FROM posts WHERE id = ?');
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if ($post) {
            $likes = json_decode($post['likes'] ?? '[]', true);
            $key = array_search($user_id, $likes);
            
            if ($key === false) {
                // Like
                $likes[] = $user_id;
                $update = $pdo->prepare('UPDATE posts SET likes = ? WHERE id = ?');
                $update->execute([json_encode($likes), $post_id]);
                
                // Notify the post owner
                if ($post['user_id'] != $user_id) {
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'like', 'Someone liked your post!')");
                    $notif->execute([$post['user_id']]);

                    // --- Send Like Email ---
                    $likerStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                    $likerStmt->execute([$user_id]);
                    $likerName = $likerStmt->fetchColumn();

                    $ownerStmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
                    $ownerStmt->execute([$post['user_id']]);
                    $ownerInfo = $ownerStmt->fetch();

                    if ($ownerInfo && filter_var($ownerInfo['email'], FILTER_VALIDATE_EMAIL)) {
                        $emailBody = emailTemplate_NewLike($ownerInfo['username'], $likerName, 'post');
                        sendMuzeEmail($ownerInfo['email'], $ownerInfo['username'], '❤️‍🔥 ' . $likerName . ' Adored Your Moment!', $emailBody);
                    }
                }
                sendResponse('success', null, 'Post liked');
            } else {
                // Unlike
                unset($likes[$key]);
                $likes = array_values($likes);
                $update = $pdo->prepare('UPDATE posts SET likes = ? WHERE id = ?');
                $update->execute([json_encode($likes), $post_id]);
                sendResponse('success', null, 'Post unliked');
            }
        }
        sendResponse('error', null, 'Post not found');
    }
    elseif ($action === 'comment_post') {
        $post_id = $input['post_id'] ?? 0;
        $comment_text = $input['text'] ?? '';
        
        $stmt = $pdo->prepare('SELECT comments, user_id FROM posts WHERE id = ?');
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if ($post) {
            $comments = json_decode($post['comments'] ?? '[]', true);
            $comments[] = [
                'user_id' => $user_id,
                'username' => 'User',
                'text' => $comment_text,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // We need to fetch the username from DB since we are in PHP
            $u_stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $u_stmt->execute([$user_id]);
            $username = $u_stmt->fetchColumn();
            
            // Fix the comment array
            $comments[count($comments) - 1]['username'] = $username;
            
            $update = $pdo->prepare('UPDATE posts SET comments = ? WHERE id = ?');
            $update->execute([json_encode($comments), $post_id]);
            
            // Notify the post owner
            if ($post['user_id'] != $user_id) {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'comment', 'Someone commented on your post!')");
                $notif->execute([$post['user_id']]);
            }

            sendResponse('success', null, 'Comment added');
        }
        sendResponse('error', null, 'Post not found');
    }
    elseif ($action === 'bookmark_post') {
        $post_id = intval($input['post_id'] ?? 0);
        if (!$post_id) sendResponse('error', null, 'Invalid post');
        // Toggle: if exists remove, else add
        $check = $pdo->prepare('SELECT id FROM bookmarks WHERE user_id = ? AND post_id = ?');
        $check->execute([$user_id, $post_id]);
        if ($check->fetch()) {
            $pdo->prepare('DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?')->execute([$user_id, $post_id]);
            sendResponse('success', null, 'Removed from saved');
        } else {
            $pdo->prepare('INSERT INTO bookmarks (user_id, post_id) VALUES (?, ?)')->execute([$user_id, $post_id]);
            sendResponse('success', null, 'Post saved!');
        }
    }
}
?>
