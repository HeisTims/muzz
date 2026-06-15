'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function ExplorePage() {
  const { user, profile } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const [users, setUsers] = useState([]);
  const [filteredUsers, setFilteredUsers] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeFilter, setActiveFilter] = useState('all');
  const [loading, setLoading] = useState(true);

  // Invite Modal
  const [inviteTarget, setInviteTarget] = useState(null);
  const [inviteMessage, setInviteMessage] = useState('');
  const [sendingInvite, setSendingInvite] = useState(false);

  const fetchUsers = async () => {
    try {
      setLoading(true);
      const { data, error } = await supabase
        .from('profiles')
        .select('*')
        .neq('id', user.id)
        .order('is_online', { ascending: false });

      if (!error && data) {
        setUsers(data);
        setFilteredUsers(data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchUsers();
    }
  }, [user]);

  // Handle Search and Filter Pills
  useEffect(() => {
    let result = users;

    if (activeFilter !== 'all') {
      result = result.filter(u => u.preference?.toLowerCase() === activeFilter.toLowerCase());
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase().trim();
      result = result.filter(u => 
        u.username?.toLowerCase().includes(q) ||
        u.preference?.toLowerCase().includes(q) ||
        u.location?.toLowerCase().includes(q)
      );
    }

    setFilteredUsers(result);
  }, [searchQuery, activeFilter, users]);

  const handleSendInvite = async () => {
    if (!inviteTarget) return;
    setSendingInvite(true);

    try {
      // Check if pending invite already exists
      const { data: existing, error: checkError } = await supabase
        .from('invites')
        .select('id')
        .eq('sender_id', user.id)
        .eq('receiver_id', inviteTarget.id)
        .eq('status', 'pending')
        .limit(1);

      if (existing && existing.length > 0) {
        alert('You already have a pending invite sent to this person.');
        setSendingInvite(false);
        setInviteTarget(null);
        return;
      }

      // Insert connection invite
      const { error: inviteError } = await supabase
        .from('invites')
        .insert({
          sender_id: user.id,
          receiver_id: inviteTarget.id,
          message: inviteMessage.trim(),
          status: 'pending',
          username: profile?.username || 'anon'
        });

      if (inviteError) throw inviteError;

      // Create notification for recipient
      await supabase
        .from('notifications')
        .insert({
          user_id: inviteTarget.id,
          type: 'invite',
          message: `@${profile?.username || 'Someone'} sent you a connection invite! 💕`
        });

      alert('Invite sent! 💕');
      setInviteTarget(null);
      setInviteMessage('');
    } catch (e) {
      alert(e.message || 'Failed to send invite.');
    } finally {
      setSendingInvite(false);
    }
  };

  return (
    <div style={{ padding: '0 15px 20px' }}>
      <h2 style={{ fontSize: '1.4rem', marginBottom: '15px', color: 'white' }}>Explore Desires</h2>
      
      {/* Search Bar */}
      <div style={{ position: 'relative', marginBottom: '20px' }}>
        <i className="fas fa-search" style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)', fontSize: '0.9rem' }}></i>
        <input 
          type="text" 
          placeholder="Search City, Preference, Username..." 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          style={{ width: '100%', padding: '12px 14px 12px 38px', borderRadius: '25px', border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.9rem' }}
        />
      </div>
      
      {/* Filter Pills */}
      <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '10px', scrollbarWidth: 'none', marginBottom: '20px' }}>
        {['all', 'straight', 'gay', 'lesbian', 'bisexual', 'sugar_daddy', 'sugar_mummy'].map(pill => (
          <button 
            key={pill} 
            className={`filter-pill ${activeFilter === pill ? 'active' : ''}`}
            onClick={() => setActiveFilter(pill)}
          >
            {pill.replace('_', ' ')}
          </button>
        ))}
      </div>
      
      {/* Results Grid */}
      <div id="exploreResults">
        {loading ? (
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
          </div>
        ) : filteredUsers.length === 0 ? (
          <p style={{ textAlign: 'center', color: 'var(--text-secondary)', marginTop: '40px' }}>No desires found in the temple yet.</p>
        ) : (
          filteredUsers.map(u => {
            const avatarUrl = u.avatar || `https://ui-avatars.com/api/?name=${u.username}&background=8e1a1a&color=fff`;
            return (
              <div key={u.id} className="explore-card glass-panel" style={{ marginBottom: '12px', display: 'flex', alignItems: 'center', gap: '14px', padding: '14px 16px', borderRadius: '16px' }}>
                {/* Avatar with online status */}
                <div style={{ position: 'relative', flexShrink: 0 }}>
                  <img 
                    src={avatarUrl} 
                    style={{ width: '60px', height: '60px', borderRadius: '50%', objectFit: 'cover', border: `2px solid ${u.is_online ? '#2ecc71' : 'var(--glass-border)'}`, boxShadow: u.is_online ? '0 0 10px rgba(46,204,113,0.4)' : '' }} 
                    alt="" 
                  />
                  {u.is_online && (
                    <div style={{ position: 'absolute', bottom: '2px', right: '2px', width: '12px', height: '12px', background: '#2ecc71', borderRadius: '50%', border: '2px solid var(--velvet-bg)', boxShadow: '0 0 6px #2ecc71' }}></div>
                  )}
                </div>
                
                {/* User Info */}
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                    <h3 style={{ margin: 0, fontSize: '1rem', color: 'white', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {u.username}
                    </h3>
                    {u.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.8rem' }}></i>}
                    <span style={{ background: 'rgba(255,42,109,0.12)', color: 'var(--neon-pink)', fontSize: '0.65rem', padding: '2px 7px', borderRadius: '8px', whiteSpace: 'nowrap' }}>
                      {u.preference || 'muze'}
                    </span>
                  </div>
                  <p style={{ margin: '4px 0 0', fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                    <i className="fas fa-map-marker-alt" style={{ fontSize: '0.7rem' }}></i> {u.location || 'Unknown'}
                    {u.is_online && <span style={{ color: '#2ecc71', fontSize: '0.7rem', fontWeight: '600', marginLeft: '6px' }}>● Active now</span>}
                  </p>
                  {u.bio && (
                    <p style={{ margin: '4px 0 0', fontSize: '0.78rem', color: 'var(--text-muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '190px' }}>
                      &quot;{u.bio}&quot;
                    </p>
                  )}
                </div>
                
                {/* Actions */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', flexShrink: 0 }}>
                  <button 
                    onClick={() => router.push(`/messages?partner=${u.id}`)}
                    className="btn-primary" 
                    style={{ padding: '7px 13px', fontSize: '0.78rem', borderRadius: '18px', whiteSpace: 'nowrap', width: 'auto' }}
                  >
                    Whisper
                  </button>
                  <button 
                    onClick={() => setInviteTarget(u)}
                    style={{ background: 'rgba(255,42,109,0.1)', border: '1px solid rgba(255,42,109,0.3)', color: 'var(--neon-pink)', padding: '7px 13px', borderRadius: '18px', fontSize: '0.78rem', cursor: 'pointer', whiteSpace: 'nowrap' }}
                  >
                    Invite 💕
                  </button>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Invite Modal */}
      {inviteTarget && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10,4,6,0.96)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div className="glass-panel" style={{ background: 'linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '400px', width: '90%', padding: '25px' }}>
            <h3 style={{ color: 'var(--neon-pink)', marginBottom: '6px', textAlign: 'center' }}>Send Connection Invite 💕</h3>
            <p style={{ color: 'var(--text-secondary)', textAlign: 'center', fontSize: '0.82rem', marginBottom: '16px' }}>
              Inviting @{inviteTarget.username} to connect
            </p>
            <textarea 
              rows="3" 
              placeholder="Add a personal message (optional)..."
              value={inviteMessage}
              onChange={(e) => setInviteMessage(e.target.value)}
              style={{ width: '100%', padding: '12px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem', resize: 'none', marginBottom: '14px', boxSizing: 'border-box' }}
            />
            <div style={{ display: 'flex', gap: '10px' }}>
              <button className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={() => setInviteTarget(null)}>Cancel</button>
              <button className="btn-primary" style={{ flex: 1 }} disabled={sendingInvite} onClick={handleSendInvite}>
                {sendingInvite ? 'Sending...' : 'Send Invite 💕'}
              </button>
            </div>
          </div>
        </div>
      )}

      <style jsx>{`
        .filter-pill {
          background: rgba(255,42,109,0.1);
          color: var(--neon-pink);
          border: 1px solid rgba(255,42,109,0.25);
          padding: 7px 16px;
          border-radius: 20px;
          font-size: 0.8rem;
          cursor: pointer;
          white-space: nowrap;
          transition: all 0.2s;
        }
        .filter-pill.active, .filter-pill:hover {
          background: var(--neon-pink);
          color: white;
          border-color: var(--neon-pink);
        }
      `}</style>
    </div>
  );
}
