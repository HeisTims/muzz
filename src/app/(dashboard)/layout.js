'use client';

import { useApp } from '@/context/AppContext';
import { useRouter, usePathname } from 'next/navigation';
import { useEffect, useState } from 'react';
import { createClient } from '@/utils/supabase/client';
import Link from 'next/link';

export default function DashboardLayout({ children }) {
  const { user, profile, loading } = useApp();
  const router = useRouter();
  const pathname = usePathname();
  const supabase = createClient();

  const [notifDropdownOpen, setNotifDropdownOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadNotifCount, setUnreadNotifCount] = useState(0);
  const [unreadMsgCount, setUnreadMsgCount] = useState(0);

  // Sound play utility
  const playSound = (type = 'engagement') => {
    try {
      const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const now = audioCtx.currentTime;
      if (type === 'message') {
        const playTone = (freq, startTime, duration) => {
          const osc = audioCtx.createOscillator();
          const gain = audioCtx.createGain();
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(freq, startTime);
          gain.gain.setValueAtTime(0, startTime);
          gain.gain.linearRampToValueAtTime(0.25, startTime + 0.01);
          gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
          osc.connect(gain);
          gain.connect(audioCtx.destination);
          osc.start(startTime);
          osc.stop(startTime + duration);
        };
        playTone(1046.50, now, 0.10);
        playTone(1318.51, now + 0.10, 0.15);
      } else {
        const playChime = (freq, startTime, duration) => {
          const osc = audioCtx.createOscillator();
          const gain = audioCtx.createGain();
          osc.type = 'sine';
          osc.frequency.setValueAtTime(freq, startTime);
          gain.gain.setValueAtTime(0, startTime);
          gain.gain.linearRampToValueAtTime(0.15, startTime + 0.04);
          gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
          osc.connect(gain);
          gain.connect(audioCtx.destination);
          osc.start(startTime);
          osc.stop(startTime + duration);
        };
        playChime(523.25, now, 0.35);
        playChime(783.99, now + 0.06, 0.35);
        playChime(1046.50, now + 0.12, 0.45);
      }
    } catch (e) {
      console.warn('Web Audio playback error:', e);
    }
  };

  // Fetch initial notifications and messages unread counts
  useEffect(() => {
    if (!user) return;

    const fetchCountsAndNotifications = async () => {
      // Fetch notifications
      const { data: notifs, error: notifErr } = await supabase
        .from('notifications')
        .select('*')
        .eq('user_id', user.id)
        .order('created_at', { ascending: false })
        .limit(30);

      if (!notifErr && notifs) {
        setNotifications(notifs);
        setUnreadNotifCount(notifs.filter(n => !n.is_read).length);
      }

      // Fetch unread messages count
      const { count, error: msgErr } = await supabase
        .from('messages')
        .select('*', { count: 'exact', head: true })
        .eq('receiver_id', user.id)
        .eq('is_read', false);

      if (!msgErr) {
        setUnreadMsgCount(count || 0);
      }
    };

    fetchCountsAndNotifications();

    // Subscribe to notifications changes
    const notifSubscription = supabase
      .channel(`notifs-${user.id}`)
      .on('postgres_changes', {
        event: 'INSERT',
        schema: 'public',
        table: 'notifications',
        filter: `user_id=eq.${user.id}`,
      }, (payload) => {
        setNotifications(prev => [payload.new, ...prev]);
        setUnreadNotifCount(c => c + 1);
        playSound('engagement');
      })
      .subscribe();

    // Subscribe to messages changes
    const msgSubscription = supabase
      .channel(`msgs-${user.id}`)
      .on('postgres_changes', {
        event: 'INSERT',
        schema: 'public',
        table: 'messages',
        filter: `receiver_id=eq.${user.id}`,
      }, (payload) => {
        setUnreadMsgCount(c => c + 1);
        playSound('message');
      })
      .subscribe();

    return () => {
      supabase.removeChannel(notifSubscription);
      supabase.removeChannel(msgSubscription);
    };
  }, [user]);

  const handleMarkNotificationsRead = async () => {
    setNotifDropdownOpen(!notifDropdownOpen);
    if (!notifDropdownOpen && unreadNotifCount > 0) {
      await supabase
        .from('notifications')
        .update({ is_read: true })
        .eq('user_id', user.id)
        .eq('is_read', false);

      setUnreadNotifCount(0);
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
    }
  };

  if (loading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', color: 'var(--neon-pink)' }}>
        <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem' }}></i>
      </div>
    );
  }

  if (!user) {
    return null; // Let middleware handle redirection
  }

  return (
    <>
      <header className="app-header">
        <Link href="/" className="app-logo" style={{ textDecoration: 'none', display: 'flex', alignItems: 'center' }}>
          <img src="/assets/img/logo.png" alt="EazyMUZE Logo" style={{ height: '50px', borderRadius: '8px' }} />
        </Link>
        <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
          {/* Wallet Link */}
          <Link href="/wallet" style={{ display: 'flex', alignItems: 'center', gap: '6px', textDecoration: 'none', background: 'rgba(255, 42, 109, 0.1)', border: '1px solid rgba(255, 42, 109, 0.3)', padding: '6px 12px', borderRadius: '20px', fontSize: '0.8rem', color: 'white' }}>
            <i className="fas fa-wallet" style={{ color: '#f1c40f' }}></i>
            <span>₦{profile?.wallet_balance || '0.00'}</span>
          </Link>
          {/* Notifications Bell */}
          <div style={{ position: 'relative', cursor: 'pointer' }} onClick={handleMarkNotificationsRead}>
            <i className="fas fa-bell" style={{ color: '#f1c40f', fontSize: '1.2rem', textShadow: '0 0 10px rgba(241, 196, 15, 0.5)' }}></i>
            {unreadNotifCount > 0 && (
              <span id="notifBadge" style={{ position: 'absolute', top: '-5px', right: '-5px', background: 'var(--neon-pink)', color: 'white', fontSize: '0.6rem', padding: '2px 5px', borderRadius: '50%' }}>
                {unreadNotifCount}
              </span>
            )}
          </div>
        </div>
      </header>

      {/* Notification Dropdown */}
      {notifDropdownOpen && (
        <div id="notifDropdown" className="glass-panel" style={{ position: 'fixed', top: '70px', right: 'calc(50% - 230px)', width: '300px', maxHeight: '400px', overflowY: 'auto', zIndex: 1000, left: 'auto', maxWidth: 'calc(100% - 40px)' }}>
          <h3 style={{ marginBottom: '10px', color: 'var(--neon-pink)' }}>Notifications</h3>
          <div id="notifList">
            {notifications.length === 0 ? (
              <p style={{ color: 'grey', fontSize: '0.8rem' }}>No new whispers.</p>
            ) : (
              notifications.map(n => (
                <div key={n.id} style={{ padding: '10px', borderBottom: '1px solid rgba(255,42,109,0.2)', background: !n.is_read ? 'rgba(255,42,109,0.1)' : 'transparent' }}>
                  <p style={{ margin: 0, fontSize: '0.85rem', color: 'white' }}>{n.message}</p>
                  <small style={{ color: 'var(--neon-pink)', fontSize: '0.6rem' }}>{new Date(n.created_at).toLocaleDateString()}</small>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* Content Area */}
      <div style={{ minHeight: 'calc(100vh - 165px)', padding: '10px 0' }}>
        {children}
      </div>

      {/* Bottom Nav */}
      <nav className="bottom-nav">
        <Link href="/" className={`nav-item ${pathname === '/' ? 'active' : ''}`} style={{ textDecoration: 'none' }}>
          <i className="fas fa-fan"></i>
          <span>Muze</span>
        </Link>
        <Link href="/explore" className={`nav-item ${pathname.startsWith('/explore') ? 'active' : ''}`} style={{ textDecoration: 'none' }}>
          <i className="fas fa-search"></i>
          <span>explore</span>
        </Link>
        <Link href="/invites" className={`nav-item ${pathname.startsWith('/invites') ? 'active' : ''}`} style={{ textDecoration: 'none' }}>
          <i className="fas fa-heart" style={{ color: 'var(--neon-pink)', textShadow: '0 0 10px rgba(255, 42, 109, 0.4)' }}></i>
          <span>invites</span>
        </Link>
        <Link href="/messages" className={`nav-item ${pathname.startsWith('/messages') ? 'active' : ''}`} style={{ textDecoration: 'none', position: 'relative' }}>
          <i className="fas fa-comment-dots"></i>
          {unreadMsgCount > 0 && (
            <span id="messagesUnreadBadge" style={{ position: 'absolute', top: '0px', right: '20px', background: 'var(--neon-pink)', color: 'white', fontSize: '0.65rem', fontWeight: 'bold', minWidth: '16px', height: '16px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 0 8px var(--neon-pink)' }}>
              {unreadMsgCount}
            </span>
          )}
          <span>msgs</span>
        </Link>
        <Link href="/market" className={`nav-item ${pathname.startsWith('/market') ? 'active' : ''}`} style={{ textDecoration: 'none' }}>
          <i className="fas fa-shopping-cart"></i>
          <span>market</span>
        </Link>
        <Link href={profile ? `/profile/${profile.id}` : '#'} className={`nav-item ${pathname.startsWith('/profile') ? 'active' : ''}`} style={{ textDecoration: 'none' }}>
          <i className="fas fa-place-of-worship" style={{ color: '#f1c40f', textShadow: '0 0 10px rgba(241, 196, 15, 0.4)' }}></i>
          <span>temple</span>
        </Link>
      </nav>
    </>
  );
}
