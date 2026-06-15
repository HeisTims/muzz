<?php
// EazyMUZE v4.5 - Massive 50 Profiles Seeder
require_once 'db.php';

header("Content-Type: application/json");

try {
    // 1. Array of 50 unique premium model profiles
    $profiles = [
        ['username' => 'Sensual_Sandra', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Lekki Phase 1', 'bio' => 'A classy lady with wild ideas. Let\'s explore Lekki nightlife together.'],
        ['username' => 'SugarMummy_Rita', 'pref' => 'sugar_mummy', 'gender' => 'F', 'location' => 'Victoria Island', 'bio' => 'Generous, elegant, and looking to pamper a respectful young gentleman.'],
        ['username' => 'SugarDaddy_Gideon', 'pref' => 'sugar_daddy', 'gender' => 'M', 'location' => 'Ikoyi Penthouse', 'bio' => 'Successful entrepreneur looking to spoil a charming lady. Privacy is key.'],
        ['username' => 'Bisexual_Lola', 'pref' => 'bisexual', 'gender' => 'F', 'location' => 'Ikeja GRA', 'bio' => 'Open-minded baddie. I love testing boundaries. Let\'s get details in DM.'],
        ['username' => 'Gay_Damian', 'pref' => 'gay', 'gender' => 'M', 'location' => 'Lekki Staging', 'bio' => 'Fit, masculine, and extremely direct. Looking for similar partners.'],
        ['username' => 'Lesbian_Zara', 'pref' => 'lesbian', 'gender' => 'F', 'location' => 'Abuja Wuse', 'bio' => 'Charming and selective. Looking for a real connection with a beautiful lady.'],
        ['username' => 'Baddie_Mercy', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Surulere', 'bio' => 'High fashion, high standards. Only mature conversations allowed here.'],
        ['username' => 'Wild_West', 'pref' => 'bisexual', 'gender' => 'M', 'location' => 'Yaba District', 'bio' => 'Late night gamer. Hookups, gays, and bisexuals in the neighborhood welcome.'],
        ['username' => 'Desire_Chloe', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Banana Island', 'bio' => 'Living luxuriously. Seeking a handsome partner for candlelight dinners.'],
        ['username' => 'Erotic_Ethan', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Lekki Phase 2', 'bio' => 'Fit gym trainer. Exploring deeper desires with open-minded women.'],
        ['username' => 'Seductive_Sophia', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Gwarinpa, Abuja', 'bio' => 'Charming aura. Looking for someone to match my intense energy.'],
        ['username' => 'Spicy_Shawn', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Victoria Island', 'bio' => 'Gentleman by day, wild companion by night. Let\'s talk over cocktails.'],
        ['username' => 'Flirty_Fiona', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Ajah, Lagos', 'bio' => 'Life is too short to be boring. Whisper to me and let\'s explore.'],
        ['username' => 'Naughty_Nate', 'pref' => 'bisexual', 'gender' => 'M', 'location' => 'Maryland', 'bio' => 'Spontaneous and adventurous. Looking to unlock new experiences.'],
        ['username' => 'Curvy_Clara', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Enugu State', 'bio' => 'Proudly curvy. Looking for a bold partner who knows what they want.'],
        ['username' => 'Bold_Bella', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Port Harcourt', 'bio' => 'Unapologetically bold. Let\'s create unforgettable memories together.'],
        ['username' => 'Hot_Harry', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Lekki Phase 1', 'bio' => 'Classy dresser. Seeking a gorgeous lady to accompany me to high-end events.'],
        ['username' => 'Sweet_Sarah', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Asokoro, Abuja', 'bio' => 'Sweet as honey, but full of surprises. Ready to get initiated.'],
        ['username' => 'Charming_Charles', 'pref' => 'sugar_daddy', 'gender' => 'M', 'location' => 'Maitama, Abuja', 'bio' => 'Executive sponsor. Offering luxury vacations to the right partner.'],
        ['username' => 'Sleek_Stella', 'pref' => 'lesbian', 'gender' => 'F', 'location' => 'Victoria Island', 'bio' => 'Sleek, stylish, and looking to share premium vibes with other ladies.'],
        ['username' => 'Exotic_Eva', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Calabar', 'bio' => 'Exotic features, beautiful vibes. Looking for a companion to spoil.'],
        ['username' => 'Passionate_Paul', 'pref' => 'gay', 'gender' => 'M', 'location' => 'Ikeja GRA', 'bio' => 'Passionate, loyal, and looking for a long-term partner in Lagos.'],
        ['username' => 'Lusty_Lisa', 'pref' => 'bisexual', 'gender' => 'F', 'location' => 'Lekki Phase 1', 'bio' => 'Life is an adventure. DM is open to couples or single open partners.'],
        ['username' => 'Tempting_Toby', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Ikoyi', 'bio' => 'Athletic build. Looking for a beautiful muse to keep things interesting.'],
        ['username' => 'Sensory_Sienna', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Eko Atlantic', 'bio' => 'A fan of luxury, sunsets, and deep late-night intimacy.'],
        ['username' => 'Steamy_Steve', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Surulere', 'bio' => 'Steamy sessions and respectful chats. Looking for a fun lady.'],
        ['username' => 'Radiant_Rachel', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Lekki Staging', 'bio' => 'Radiating positive energy and sensual vibes. Let\'s hook up.'],
        ['username' => 'Bold_Brandon', 'pref' => 'bisexual', 'gender' => 'M', 'location' => 'Victoria Island', 'bio' => 'Confident guy. Ready to explore everything the black market has to offer.'],
        ['username' => 'Elegant_Elena', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Banana Island', 'bio' => 'Sophisticated and selective. Whispers only for verified gentlemen.'],
        ['username' => 'Subtle_Simon', 'pref' => 'gay', 'gender' => 'M', 'location' => 'Ajah', 'bio' => 'Subtle, private, and looking for someone classy in Lekki/Ajah.'],
        ['username' => 'Velvet_Vicky', 'pref' => 'lesbian', 'gender' => 'F', 'location' => 'Ikeja GRA', 'bio' => 'Velvet touch. Looking for a cozy partner to spend rainy nights with.'],
        ['username' => 'Daring_Drake', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Surulere', 'bio' => 'Daring and highly active. Let\'s match our energies tonight.'],
        ['username' => 'Midnight_Mia', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Victoria Island', 'bio' => 'Midnight drives, penthouse talks, and intense connections.'],
        ['username' => 'Seductive_Sam', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Banana Island', 'bio' => 'Seductive gentleman seeking an elegant lady for private dates.'],
        ['username' => 'Glow_Gabriella', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Lekki Phase 2', 'bio' => 'Sun-kissed baddie. Let\'s create some beautiful moments.'],
        ['username' => 'Sensual_Sasha', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Abuja Maitama', 'bio' => 'Sensual vibes only. Seeking someone to appreciate true intimacy.'],
        ['username' => 'Golden_George', 'pref' => 'sugar_daddy', 'gender' => 'M', 'location' => 'Lekki Phase 1', 'bio' => 'Mature sponsor. Looking to give premium pampering to a gorgeous muse.'],
        ['username' => 'Exotic_Eniola', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Ikeja GRA', 'bio' => 'Pure African beauty. Seductive curves and beautiful vibes.'],
        ['username' => 'Sugar_Shola', 'pref' => 'sugar_mummy', 'gender' => 'F', 'location' => 'Banana Island', 'bio' => 'Looking to sponsor a respectful, fit guy. Absolute discretion.'],
        ['username' => 'Naughty_Nengi', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Victoria Island', 'bio' => 'Cute look, naughty mind. DM me for exciting hookups.'],
        ['username' => 'Lusty_Laycon', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Surulere', 'bio' => 'Charming dude looking to show a baddie real Lekki luxury.'],
        ['username' => 'Spicy_Seyi', 'pref' => 'bisexual', 'gender' => 'F', 'location' => 'Abuja Wuse', 'bio' => 'Spicy vibes. Let\'s explore deep secrets together.'],
        ['username' => 'Baddie_Boma', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Lekki Phase 1', 'bio' => 'Top tier baddie. High class only. Respect my boundaries.'],
        ['username' => 'Wild_Wande', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Yaba', 'bio' => 'Wild ideas, fun experiences. Seeking open-minded companions.'],
        ['username' => 'Desire_Daniel', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Ikoyi', 'bio' => 'Seeking a beautiful lady for candlelight dinner dates.'],
        ['username' => 'Erotic_Eku', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Lekki Penthouse', 'bio' => 'Penthouse host. Seeking like-minded models for pool party vibes.'],
        ['username' => 'Seductive_Simi', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Victoria Island', 'bio' => 'Sweet Simi. A charming lady with a highly sensual side.'],
        ['username' => 'Spicy_Sola', 'pref' => 'straight', 'gender' => 'M', 'location' => 'Ikeja', 'bio' => 'Spicy conversations. Looking for a lady to hang out with tonight.'],
        ['username' => 'Flirty_Fadekemi', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Ajah', 'bio' => 'Flirty, bubbly, and full of sensual ideas. Whisper to me.'],
        ['username' => 'Naughty_Nneka', 'pref' => 'straight', 'gender' => 'F', 'location' => 'Surulere', 'bio' => 'Naughty Nneka. Classy baddie with high standards. Let\'s chat!']
    ];

    // 2. High-quality model images from Unsplash
    $female_pics = [
        'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1508214751196-bcfd4ca60f91?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1506919258185-6078bba55d2a?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=600&q=80'
    ];

    $male_pics = [
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1489980508314-941910ded1f4?auto=format&fit=crop&w=600&q=80',
        'https://images.unsplash.com/photo-1513956589380-bad6acb9b9d4?auto=format&fit=crop&w=600&q=80'
    ];

    $music_options = ['wizkid', 'burna', 'ayra', 'asake', 'davido', 'rema', 'tems'];

    // 3. Clear existing seeded profiles to prevent conflicts
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@muze.net'");

    $passHash = password_hash('password123', PASSWORD_BCRYPT);
    $seededCount = 0;

    foreach ($profiles as $i => $p) {
        // Choose avatar based on gender
        $avatarArr = ($p['gender'] === 'F') ? $female_pics : $male_pics;
        $avatar = $avatarArr[$i % count($avatarArr)];

        // Generate properties
        $email = strtolower($p['username']) . '@muze.net';
        $fullname = ucwords(str_replace('_', ' ', $p['username']));
        $dob = date('Y-m-d', strtotime('-' . rand(20, 35) . ' years'));
        $wallet = rand(5000, 25000) . '.00';
        $streak = rand(5, 40);

        // Insert user
        $u_stmt = $pdo->prepare('
            INSERT INTO users (username, password, fullname, email, dob, preference, location, bio, avatar, is_verified, is_online, wallet_balance, streak_count, streak_last_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, CURDATE())
        ');
        $u_stmt->execute([
            $p['username'],
            $passHash,
            $fullname,
            $email,
            $dob,
            $p['pref'],
            $p['location'],
            $p['bio'],
            $avatar,
            $wallet,
            $streak
        ]);
        
        $userId = $pdo->lastInsertId();

        // 4. Create premium post for this user
        $postPic = $avatar; // Use avatar or adjacent for post
        $music = $music_options[rand(0, count($music_options) - 1)];
        $caption = $p['bio'] . ' Whisper to me and let\'s explore new horizons. 💋🥂';
        $imagesArr = json_encode([$postPic]);

        $p_stmt = $pdo->prepare('
            INSERT INTO posts (user_id, image_fallback, images, caption, music, likes, comments, location_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $p_stmt->execute([
            $userId,
            $postPic,
            $imagesArr,
            $caption,
            $music,
            '[]',
            '[]',
            $p['location']
        ]);

        // 5. Create active status/story for this user
        $storyPic = $avatarArr[($i + 1) % count($avatarArr)];
        $storyCap = 'Active right now in ' . $p['location'] . '! 💋🔥';
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $s_stmt = $pdo->prepare('
            INSERT INTO stories (user_id, image, caption, likes, comments, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $s_stmt->execute([
            $userId,
            $storyPic,
            $storyCap,
            '[]',
            '[]',
            $expires
        ]);

        $seededCount++;
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully seeded $seededCount highly premium model profiles, each with an active Moment post and expiring Status story!"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Seeding failed: ' . $e->getMessage()
    ]);
}
?>
