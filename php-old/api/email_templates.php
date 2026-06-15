<?php
/**
 * EazyMUZE Email Templates — Erotic, Brand-Aligned HTML Templates
 */

function emailTemplate_Registration(string $username, string $verifyLink): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#ff2a6d,#8e1a1a); padding:40px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:2rem; margin:0; letter-spacing:2px; text-shadow:0 0 20px rgba(255,42,109,0.8); }
  .hero p { color:rgba(255,255,255,0.8); margin:8px 0 0; font-style:italic; }
  .body { padding:35px 30px; color:#e89ec0; }
  .body h2 { color:#ff2a6d; margin:0 0 15px; }
  .body p { line-height:1.7; margin:0 0 15px; }
  .btn { display:inline-block; background:linear-gradient(135deg,#ff4d85,#ff2a6d); color:#fff !important; text-decoration:none; padding:15px 35px; border-radius:30px; font-weight:bold; font-size:1rem; margin:20px 0; box-shadow:0 6px 20px rgba(255,42,109,0.5); }
  .footer { background:rgba(0,0,0,0.4); padding:20px 30px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
  .emoji { font-size:2rem; }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div class="emoji">💋</div>
    <h1>EazyMUZE</h1>
    <p>The Temple of Desires has opened its doors for you...</p>
  </div>
  <div class="body">
    <h2>Welcome to the Inner Sanctum, {$username} 🔥</h2>
    <p>You have been chosen. Your initiation into EazyMUZE — the most exclusive adult sanctuary on the internet — is almost complete.</p>
    <p>Your whispers are waiting. Your muze is out there. But first, verify your email to unlock the full power of the Temple.</p>
    <div style="text-align:center;">
      <a href="{$verifyLink}" class="btn">✨ Verify & Enter the Temple</a>
    </div>
    <p style="font-size:0.8rem; color:#5a3050;">If you didn't create this account, simply ignore this email. Your secret is safe with us. 😈</p>
  </div>
  <div class="footer">
    © EazyMUZE | support@eazymuze.ng | 18+ Adults Only | All Rights Reserved
  </div>
</div>
</body>
</html>
HTML;
}

function emailTemplate_Login(string $username, string $ip, string $time): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#c0392b,#8e1a1a); padding:35px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:1.8rem; margin:0; }
  .hero p { color:rgba(255,255,255,0.7); margin:8px 0 0; }
  .body { padding:30px; color:#e89ec0; }
  .info-box { background:rgba(255,42,109,0.1); border:1px solid rgba(255,42,109,0.25); border-radius:12px; padding:15px 20px; margin:15px 0; }
  .info-box p { margin:5px 0; font-size:0.9rem; color:#e89ec0; }
  .footer { background:rgba(0,0,0,0.4); padding:20px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div style="font-size:2rem;">🔐</div>
    <h1>New Entry Detected</h1>
    <p>Someone just entered your Temple...</p>
  </div>
  <div class="body">
    <p>Hi <strong style="color:#ff2a6d;">{$username}</strong>,</p>
    <p>Your EazyMUZE account was just accessed. Here are the details of this login:</p>
    <div class="info-box">
      <p>📍 <strong>IP Address:</strong> {$ip}</p>
      <p>🕐 <strong>Time:</strong> {$time}</p>
    </div>
    <p>If this was you, relax and enjoy the Temple. 🍷</p>
    <p>If this wasn't you, please change your password immediately and contact us at support@eazymuze.ng</p>
  </div>
  <div class="footer">© EazyMUZE | support@eazymuze.ng | 18+ Only</div>
</div>
</body>
</html>
HTML;
}

function emailTemplate_NewWhisper(string $toUsername, string $fromUsername): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#ff2a6d,#c0392b); padding:35px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:1.8rem; margin:0; }
  .body { padding:30px; color:#e89ec0; line-height:1.7; }
  .btn { display:inline-block; background:linear-gradient(135deg,#ff4d85,#ff2a6d); color:#fff !important; text-decoration:none; padding:14px 30px; border-radius:30px; font-weight:bold; margin:20px 0; box-shadow:0 4px 15px rgba(255,42,109,0.5); }
  .footer { background:rgba(0,0,0,0.4); padding:20px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div style="font-size:2rem;">💬</div>
    <h1>Someone whispered to you...</h1>
  </div>
  <div class="body">
    <p>Hello <strong style="color:#ff2a6d;">{$toUsername}</strong>,</p>
    <p>🔥 <strong>{$fromUsername}</strong> has sent you a private Whisper on EazyMUZE. They have something juicy to tell you...</p>
    <p>Don't leave them waiting. The Temple never sleeps. 😈</p>
    <div style="text-align:center;">
      <a href="https://eazymuze.ng" class="btn">💋 Read the Whisper</a>
    </div>
  </div>
  <div class="footer">© EazyMUZE | support@eazymuze.ng | 18+ Only</div>
</div>
</body>
</html>
HTML;
}

function emailTemplate_NewLike(string $toUsername, string $fromUsername, string $type = 'post'): string {
    $typeLabel = $type === 'story' ? 'Status' : 'Moment';
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#ff2a6d,#8e1a1a); padding:35px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:1.8rem; margin:0; }
  .body { padding:30px; color:#e89ec0; line-height:1.7; }
  .btn { display:inline-block; background:linear-gradient(135deg,#ff4d85,#ff2a6d); color:#fff !important; text-decoration:none; padding:14px 30px; border-radius:30px; font-weight:bold; margin:20px 0; box-shadow:0 4px 15px rgba(255,42,109,0.5); }
  .footer { background:rgba(0,0,0,0.4); padding:20px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div style="font-size:2rem;">❤️‍🔥</div>
    <h1>Someone Adored You!</h1>
  </div>
  <div class="body">
    <p>Darling <strong style="color:#ff2a6d;">{$toUsername}</strong>,</p>
    <p>🌹 <strong>{$fromUsername}</strong> just adored your <strong>{$typeLabel}</strong>. Looks like your content is irresistible...</p>
    <p>Keep sharing your desires. The Temple is watching. 💋</p>
    <div style="text-align:center;">
      <a href="https://eazymuze.ng" class="btn">🔥 See Who Adored You</a>
    </div>
  </div>
  <div class="footer">© EazyMUZE | support@eazymuze.ng | 18+ Only</div>
</div>
</body>
</html>
HTML;
}

function emailTemplate_NewView(string $toUsername, string $fromUsername): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#8e1a1a,#c0392b); padding:35px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:1.8rem; margin:0; }
  .body { padding:30px; color:#e89ec0; line-height:1.7; }
  .btn { display:inline-block; background:linear-gradient(135deg,#ff4d85,#ff2a6d); color:#fff !important; text-decoration:none; padding:14px 30px; border-radius:30px; font-weight:bold; margin:20px 0; box-shadow:0 4px 15px rgba(255,42,109,0.5); }
  .footer { background:rgba(0,0,0,0.4); padding:20px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div style="font-size:2rem;">👁️</div>
    <h1>Someone viewed your Muze...</h1>
  </div>
  <div class="body">
    <p>Hey <strong style="color:#ff2a6d;">{$toUsername}</strong>,</p>
    <p>👀 <strong>{$fromUsername}</strong> just viewed your profile. Are they interested? Curious? Obsessed?</p>
    <p>Log in now and make your move before they disappear into the shadows... 😈</p>
    <div style="text-align:center;">
      <a href="https://eazymuze.ng" class="btn">💋 See Who's Watching</a>
    </div>
  </div>
  <div class="footer">© EazyMUZE | support@eazymuze.ng | 18+ Only</div>
</div>
</body>
</html>
HTML;
}

function emailTemplate_WalletFunded(string $username, string $amount, string $accountNumber): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { margin:0; background:#0a0406; font-family:'Arial',sans-serif; }
  .wrap { max-width:600px; margin:0 auto; background:linear-gradient(135deg,#14080e,#1f0a14); border:1px solid rgba(255,42,109,0.3); border-radius:16px; overflow:hidden; }
  .hero { background:linear-gradient(135deg,#1a8e3a,#0d5c28); padding:35px 30px; text-align:center; }
  .hero h1 { color:#fff; font-size:1.8rem; margin:0; }
  .body { padding:30px; color:#e89ec0; line-height:1.7; }
  .amount-box { background:rgba(26,142,58,0.15); border:1px solid rgba(26,142,58,0.4); border-radius:12px; padding:20px; text-align:center; margin:20px 0; }
  .amount-box .amount { font-size:2.5rem; font-weight:bold; color:#2ecc71; }
  .footer { background:rgba(0,0,0,0.4); padding:20px; text-align:center; color:#5a3050; font-size:0.75rem; border-top:1px solid rgba(255,42,109,0.1); }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div style="font-size:2rem;">💰</div>
    <h1>Wallet Funded!</h1>
  </div>
  <div class="body">
    <p>Hey <strong style="color:#ff2a6d;">{$username}</strong>,</p>
    <p>Your EazyMUZE wallet has been credited! Your new funds are ready to use across the platform.</p>
    <div class="amount-box">
      <div class="amount">₦{$amount}</div>
      <p style="margin:5px 0; color:#aaa; font-size:0.85rem;">Credited to Account: {$accountNumber}</p>
    </div>
    <p>Use your balance to unlock exclusive whispers, purchase from the Black Market, and more. 🍷</p>
  </div>
  <div class="footer">© EazyMUZE | support@eazymuze.ng | 18+ Only</div>
</div>
</body>
</html>
HTML;
}
