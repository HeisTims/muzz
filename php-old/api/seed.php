<?php
// EazyMUZE v2.5 - Database Seeder
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

// Warning: This will clear the database before seeding
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE users');
    $pdo->exec('TRUNCATE TABLE posts');
    $pdo->exec('TRUNCATE TABLE stories');
    $pdo->exec('TRUNCATE TABLE messages');
    $pdo->exec('TRUNCATE TABLE black_market_orders');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (Exception $e) {
    sendResponse('error', null, 'Failed to truncate tables. ' . $e->getMessage());
}

$preferences = ['straight', 'gay', 'lesbian', 'bisexual', 'sugar_mummy', 'sugar_daddy'];
$cities = ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Enugu', 'Kano', 'Owerri', 'Calabar'];
$bios = [
    'Living life to the fullest 💋', 
    'Looking for my muze.', 
    'Here for a good time, not a long time.',
    'VIP access only.', 
    'Let’s make magic happen.', 
    'Bored. Entertain me.',
    'Send a whisper if you dare.', 
    'Night owl 🦉', 
    'Sugar and spice.'
];

$first_names = ['Chidi', 'Ngozi', 'Emeka', 'Aisha', 'Tunde', 'Chioma', 'Ibrahim', 'Fatima', 'Oluwaseun', 'Amaka', 'Femi', 'Zainab', 'Chika', 'Ada', 'Kelechi'];
$last_names = ['Okafor', 'Adeyemi', 'Ibrahim', 'Nwosu', 'Balogun', 'Obi', 'Abubakar', 'Eze', 'Ogunleye', 'Okoro'];

$users_created = 0;

for ($i = 1; $i <= 50; $i++) {
    $fname = $first_names[array_rand($first_names)];
    $lname = $last_names[array_rand($last_names)];
    $fullname = $fname . ' ' . $lname;
    $username = strtolower($fname) . '_' . $i;
    
    // Default password is 'password123'
    $password = password_hash('password123', PASSWORD_DEFAULT);
    $email = $username . '@eazymuze.com';
    
    // Random DOB between 1980 and 2003 (ensuring 18+)
    $year = rand(1980, 2003);
    $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
    $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
    $dob = "$year-$month-$day";
    
    $pref = $preferences[array_rand($preferences)];
    $loc = $cities[array_rand($cities)];
    $bio = $bios[array_rand($bios)];
    
    // Using Pravatar for random faces
    $avatar = "https://i.pravatar.cc/150?u=" . $i;
    
    $stmt = $pdo->prepare('INSERT INTO users (username, password, fullname, email, dob, preference, location, bio, avatar, is_verified, wallet_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 5000)');
    $stmt->execute([$username, $password, $fullname, $email, $dob, $pref, $loc, $bio, $avatar]);
    
    $user_id = $pdo->lastInsertId();
    $users_created++;
    
    // Generate 1 to 3 posts for this user
    $num_posts = rand(1, 3);
    for ($j = 0; $j < $num_posts; $j++) {
        // High-quality random images via Picsum
        $image = "https://picsum.photos/seed/post_{$i}_{$j}/600/800";
        $caption = "Just chilling in $loc today! 💋 #vibes";
        
        // 30% chance to have background music
        $music = (rand(1, 10) > 7) ? 'wizkid' : '';
        
        // Randomly simulate likes from other users (mock IDs 1-50)
        $likes = [];
        $num_likes = rand(0, 10);
        for ($k = 0; $k < $num_likes; $k++) {
            $random_liker = rand(1, 50);
            if (!in_array($random_liker, $likes)) {
                $likes[] = $random_liker;
            }
        }
        $likes_json = json_encode($likes);
        
        $p_stmt = $pdo->prepare('INSERT INTO posts (user_id, image_fallback, images, caption, music, likes, location_data) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $p_stmt->execute([$user_id, $image, '[]', $caption, $music, $likes_json, $loc]);
    }
    
    // 50% chance to have an active story
    if (rand(1, 10) > 5) {
        $s_image = "https://picsum.photos/seed/story_{$i}/400/700";
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $s_stmt = $pdo->prepare('INSERT INTO stories (user_id, image, media_type, caption, likes, comments, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $s_stmt->execute([$user_id, $s_image, 'image', 'Current mood.', '[]', '[]', $expires]);
    }
}

sendResponse('success', null, "Successfully seeded $users_created mock users with dynamic posts and stories! Default password for all accounts is 'password123'.");
?>
