'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function InvitesPage() {
  const { user, profile } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const [receivedInvites, setReceivedInvites] = useState([]);
  const [sentInvites, setSentInvites] = useState([]);
  const [activeTab, setActiveTab] = useState('received');
  const [loading, setLoading] = useState(true);

  const fetchInvites = async () => {
    try {
      setLoading(true);
      // Fetch received invites joined with sender profile
      const { data: received, error: recError } = await supabase
        .from('invites')
        .select(`
          *,
          sender:sender_id (id, username, avatar, preference, location, is_verified, bio)
        `)
        .eq('receiver_id', user.id)
        .eq('status', 'pending')
        .order('created_at', { ascending: false });

      if (!recError && received) {
        setReceivedInvites(received);
      }

      // Fetch sent invites joined with receiver profile
      const { data: sent, error: sentError } = await supabase
        .from('invites')
        .select(`
          *,
          receiver:receiver_id (id, username, avatar, is_verified)
        `)
        .eq('sender_id', user.id)
        .order('created_at', { ascending: false })
        .limit(20);

      if (!sentError && sent) {
        setSentInvites(sent);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchInvites();
    }
  }, [user]);

  const handleRespondInvite = async (inviteId, senderId, action) => {
    try {
      const status = action === 'accept' ? 'accepted' : 'declined';
      
      const { error } = await supabase
        .from('invites')
        .update({ status })
        .eq('id', inviteId);

      if (error) throw error;

      if (action === 'accept') {
        // Send notification to sender
        await supabase
          .from('notifications')
          .insert({
            user_id: senderId,
            type: 'invite_accepted',
            message: `@${profile?.username || 'Someone'} accepted your connection invite! Start whispering 💋`
          });
        alert('💕 Connection made! You can now whisper.');
      } else {
        alert('Invite declined.');
      }

      // Refresh invites lists
      fetchInvites();
    } catch (e) {
      alert(e.message || 'Failed to respond to invite.');
    }
  };

  return (
    <div style={{ padding: '0 15px 30px' }}>
      <h2 style={{ fontSize: '1.4rem', marginBottom: '6px', color: 'white' }}>Invites & Connections 💕</h2>
      <p style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', marginBottom: '20px' }}>
        People who want to connect with you
      </p>

      {/* Tabs */}
      <div style={{ display: 'flex', borderBottom: '1px solid rgba(255,255,255,0.08)', marginBottom: '20px' }}>
        <button 
          className={`invite-tab ${activeTab === 'received' ? 'active' : ''}`} 
          onClick={() => setActiveTab('received')}
        >
          Received 
          {receivedInvites.length > 0 && (
            <span style={{ background: 'var(--neon-pink)', color: 'white', fontSize: '0.6rem', padding: '2px 6px', borderRadius: '10px', marginLeft: '5px' }}>
              {receivedInvites.length}
            </span>
          )}
        </button>
        <button 
          className={`invite-tab ${activeTab === 'sent' ? 'active' : ''}`} 
          onClick={() => setActiveTab('sent')}
        >
          Sent
        </button>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', padding: '40px' }}>
          <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
        </div>
      ) : activeTab === 'received' ? (
        <div id="panel-received">
          {receivedInvites.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '50px 20px' }}>
              <div style={{ fontSize: '3.5rem', marginBottom: '14px' }}>💕</div>
              <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem' }}>No new invites yet.</p>
              <p style={{ color: 'var(--text-muted)', fontSize: '0.82rem', marginTop: '6px' }}>Explore the temple to get noticed.</p>
              <button onClick={() => router.push('/explore')} className="btn-primary" style={{ marginTop: '16px', width: 'auto', padding: '10px 24px' }}>Explore</button>
            </div>
          ) : (
            receivedInvites.map(inv => {
              const senderAvatar = inv.sender?.avatar || `https://ui-avatars.com/api/?name=${inv.sender?.username}&background=8e1a1a&color=fff`;
              return (
                <div key={inv.id} className="glass-panel" style={{ marginBottom: '14px', padding: '16px', borderRadius: '18px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  {/* User row */}
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <img src={senderAvatar} style={{ width: '56px', height: '56px', borderRadius: '50%', objectFit: 'cover', border: '2px solid var(--neon-pink)' }} alt="" />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                        <h3 style={{ margin: 0, fontSize: '1rem', color: 'white', fontWeight: 700 }}>{inv.sender?.username}</h3>
                        {inv.sender?.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.75rem' }}></i>}
                        <span style={{ background: 'rgba(255,42,109,0.15)', color: 'var(--neon-pink)', fontSize: '0.65rem', padding: '2px 7px', borderRadius: '8px' }}>
                          {inv.sender?.preference}
                        </span>
                      </div>
                      <p style={{ margin: '3px 0 0', fontSize: '0.78rem', color: 'var(--text-secondary)' }}>
                        <i className="fas fa-map-marker-alt" style={{ fontSize: '0.7rem' }}></i> {inv.sender?.location || 'Unknown'}
                      </p>
                      {inv.sender?.bio && (
                        <p style={{ margin: '4px 0 0', fontSize: '0.78rem', color: 'var(--text-muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          &quot;{inv.sender?.bio.substring(0, 60)}&quot;
                        </p>
                      )}
                    </div>
                  </div>
                  
                  {inv.message && (
                    <div style={{ background: 'rgba(255,42,109,0.08)', borderLeft: '3px solid var(--neon-pink)', padding: '10px 14px', borderRadius: '0 10px 10px 0' }}>
                      <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--text-primary)', fontStyle: 'italic' }}>&quot;{inv.message}&quot;</p>
                    </div>
                  )}
                  
                  {/* Action Buttons */}
                  <div style={{ display: 'flex', gap: '10px' }}>
                    <button className="btn-primary" style={{ flex: 1, padding: '11px', width: 'auto' }} onClick={() => handleRespondInvite(inv.id, inv.sender_id, 'accept')}>
                      <i className="fas fa-heart"></i> Accept
                    </button>
                    <button 
                      onClick={() => handleRespondInvite(inv.id, inv.sender_id, 'decline')}
                      style={{ flex: 1, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', color: 'var(--text-secondary)', padding: '11px', borderRadius: '10px', cursor: 'pointer', fontSize: '0.9rem' }}
                    >
                      <i className="fas fa-times"></i> Decline
                    </button>
                    <button 
                      onClick={() => router.push(`/messages?partner=${inv.sender_id}`)}
                      style={{ padding: '11px 14px', background: 'rgba(255,42,109,0.12)', border: '1px solid rgba(255,42,109,0.3)', color: 'var(--neon-pink)', borderRadius: '10px', cursor: 'pointer', fontSize: '0.9rem' }}
                    >
                      <i className="fas fa-comment"></i>
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      ) : (
        <div id="panel-sent">
          {sentInvites.length === 0 ? (
            <p style={{ textAlign: 'center', color: 'var(--text-secondary)', padding: '40px 20px', fontSize: '0.9rem' }}>
              You haven&apos;t sent any invites yet.
            </p>
          ) : (
            sentInvites.map(inv => {
              const receiverAvatar = inv.receiver?.avatar || `https://ui-avatars.com/api/?name=${inv.receiver?.username}&background=8e1a1a&color=fff`;
              const statusColor = 
                inv.status === 'accepted' ? '#2ecc71' :
                inv.status === 'declined' ? '#e74c3c' : '#f1c40f';

              return (
                <div key={inv.id} className="glass-panel" style={{ marginBottom: '10px', padding: '14px 16px', borderRadius: '14px', display: 'flex', alignItems: 'center', gap: '14px' }}>
                  <img src={receiverAvatar} style={{ width: '48px', height: '48px', borderRadius: '50%', objectFit: 'cover', border: `2px solid ${statusColor}` }} alt="" />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                      <h3 style={{ margin: 0, fontSize: '0.95rem', color: 'white' }}>{inv.receiver?.username}</h3>
                      {inv.receiver?.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.7rem' }}></i>}
                    </div>
                    <p style={{ margin: '3px 0 0', fontSize: '0.75rem', color: statusColor }}>
                      <span style={{ fontWeight: 600 }}>● {inv.status.charAt(0).toUpperCase() + inv.status.slice(1)}</span>
                    </p>
                  </div>
                  <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
                    {new Date(inv.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                  </span>
                </div>
              );
            })
          )}
        </div>
      )}

      <style jsx>{`
        .invite-tab {
          flex: 1;
          background: none;
          border: none;
          border-bottom: 2px solid transparent;
          color: var(--text-secondary);
          padding: 12px;
          font-size: 0.9rem;
          cursor: pointer;
          font-weight: 600;
          transition: all 0.2s;
        }
        .invite-tab.active {
          color: var(--neon-pink);
          border-bottom-color: var(--neon-pink);
        }
      `}</style>
    </div>
  );
}
