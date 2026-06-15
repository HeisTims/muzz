'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';

export default function ProfilePage() {
  const { id } = useParams();
  const { user, profile: currentUser, refreshProfile, logout } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const isOwn = user?.id === id;

  const [profile, setProfile] = useState(null);
  const [posts, setPosts] = useState([]);
  const [bookmarks, setBookmarks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('posts'); // 'posts', 'saved', 'settings'

  // Settings form state
  const [username, setUsername] = useState('');
  const [fullname, setFullname] = useState('');
  const [bio, setBio] = useState('');
  const [location, setLocation] = useState('');
  const [preference, setPreference] = useState('straight');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [savingSettings, setSavingSettings] = useState(false);

  // Selected post for modal
  const [selectedPost, setSelectedPost] = useState(null);

  // Fetch Profile & Data
  const fetchProfileData = async () => {
    try {
      setLoading(true);

      // Fetch user profile
      const { data: prof, error: profErr } = await supabase
        .from('profiles')
        .select('*')
        .eq('id', id)
        .single();

      if (profErr || !prof) {
        console.error('Error fetching profile:', profErr);
        // Fallback or redirect if not found
        return;
      }

      setProfile(prof);

      // Pre-fill settings form if own profile
      if (isOwn) {
        setUsername(prof.username || '');
        setFullname(prof.fullname || '');
        setBio(prof.bio || '');
        setLocation(prof.location || '');
        setPreference(prof.preference || 'straight');
        setPhone(prof.phone || '');
      }

      // Fetch user posts
      const { data: postsData, error: postsErr } = await supabase
        .from('posts')
        .select('*')
        .eq('user_id', id)
        .order('created_at', { ascending: false });

      if (!postsErr && postsData) {
        setPosts(postsData);
      }

      // Fetch bookmarks (only visible if own profile)
      if (isOwn) {
        const { data: bookmarksData, error: bookmarksErr } = await supabase
          .from('bookmarks')
          .select(`
            id,
            post:post_id (
              id,
              images,
              image_fallback,
              caption,
              likes,
              comments,
              created_at
            )
          `)
          .eq('user_id', id)
          .order('created_at', { ascending: false });

        if (!bookmarksErr && bookmarksData) {
          // Flatten to get post objects
          const flattened = bookmarksData.map(b => b.post).filter(Boolean);
          setBookmarks(flattened);
        }
      }

    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user && id) {
      fetchProfileData();
    }
  }, [user, id]);

  // Handle Avatar Upload (base64)
  const handleAvatarUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      alert('Avatar image must be smaller than 2MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = async (uploadEvent) => {
      const base64Data = uploadEvent.target.result;
      
      // Optimistic update
      setProfile(prev => ({ ...prev, avatar: base64Data }));

      const { error } = await supabase
        .from('profiles')
        .update({ avatar: base64Data })
        .eq('id', user.id);

      if (error) {
        alert('Failed to save avatar to profile');
      } else {
        await refreshProfile();
        alert('Avatar updated! 💋');
      }
    };
    reader.readAsDataURL(file);
  };

  // Save Settings
  const handleSaveSettings = async (e) => {
    e.preventDefault();
    setSavingSettings(true);
    try {
      const { error: profileErr } = await supabase
        .from('profiles')
        .update({
          username,
          fullname,
          bio,
          location,
          preference,
          phone
        })
        .eq('id', user.id);

      if (profileErr) throw profileErr;

      if (password.trim() !== '') {
        const { error: authErr } = await supabase.auth.updateUser({
          password: password
        });
        if (authErr) throw authErr;
        setPassword('');
      }

      await refreshProfile();
      await fetchProfileData();
      alert('Profile updated successfully! 💋');
    } catch (err) {
      alert(err.message || 'Error updating settings');
    } finally {
      setSavingSettings(false);
    }
  };

  // Delete Account
  const handleDeleteAccount = async () => {
    if (confirm('Delete your account permanently? This action is irreversible.')) {
      try {
        const { error } = await supabase
          .from('profiles')
          .delete()
          .eq('id', user.id);

        if (error) throw error;

        alert('Account deleted.');
        await logout();
      } catch (err) {
        alert(err.message || 'Error deleting account');
      }
    }
  };

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '60vh', color: 'var(--neon-pink)' }}>
        <i className="fas fa-fan fa-spin" style={{ fontSize: '2.5rem' }}></i>
      </div>
    );
  }

  if (!profile) {
    return (
      <div style={{ textAlign: 'center', padding: '40px 20px', color: 'var(--text-secondary)' }}>
        <p>Profile not found.</p>
        <Link href="/" className="btn-primary" style={{ marginTop: '20px', textDecoration: 'none' }}>Return to Feed</Link>
      </div>
    );
  }

  // Stats calculations
  const momentsCount = posts.length;
  const desiresCount = posts.reduce((sum, p) => sum + (p.likes?.length || 0), 0);
  const avatarUrl = profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.username || 'user')}&background=8e1a1a&color=fff&size=200`;

  return (
    <div style={{ paddingBottom: '30px' }}>
      {/* Cover Banner */}
      <div 
        className="profile-cover" 
        style={{ 
          height: '180px', 
          background: 'linear-gradient(135deg, #1a0b12 0%, #2d0f1e 50%, #0d0508 100%)', 
          position: 'relative', 
          overflow: 'hidden' 
        }}
      >
        {profile.avatar && (
          <img 
            src={profile.avatar} 
            style={{ width: '100%', height: '100%', objectFit: 'cover', opacity: '0.25', filter: 'blur(16px)' }} 
            alt=""
          />
        )}
        
        {/* Avatar Wrap */}
        <div className="profile-avatar-wrap" style={{ position: 'absolute', bottom: '-40px', left: '20px', zIndex: 10 }}>
          <div style={{ position: 'relative', display: 'inline-block' }}>
            <img 
              src={avatarUrl} 
              className="profile-avatar-img" 
              style={{ 
                width: '90px', 
                height: '90px', 
                borderRadius: '50%', 
                objectFit: 'cover', 
                border: '3px solid var(--neon-pink)', 
                boxShadow: '0 0 20px rgba(255, 42, 109, 0.6)',
                cursor: isOwn ? 'pointer' : 'default'
              }}
              onClick={() => isOwn && document.getElementById('avatarUploadTrigger').click()}
              alt="Avatar"
            />
            {profile.is_online && (
              <div 
                style={{ 
                  position: 'absolute', 
                  bottom: '3px', 
                  right: '3px', 
                  width: '14px', 
                  height: '14px', 
                  background: '#2ecc71', 
                  borderRadius: '50%', 
                  border: '2px solid var(--velvet-bg)', 
                  boxShadow: '0 0 6px #2ecc71' 
                }}
              ></div>
            )}
            {isOwn && (
              <>
                <div 
                  style={{ 
                    position: 'absolute', 
                    bottom: '0', 
                    right: '0', 
                    width: '26px', 
                    height: '26px', 
                    background: 'var(--neon-pink)', 
                    borderRadius: '50%', 
                    display: 'flex', 
                    alignItems: 'center', 
                    justifyContent: 'center', 
                    cursor: 'pointer', 
                    border: '2px solid var(--velvet-bg)' 
                  }}
                  onClick={() => document.getElementById('avatarUploadTrigger').click()}
                >
                  <i className="fas fa-camera" style={{ fontSize: '0.65rem', color: 'white' }}></i>
                </div>
                <input 
                  type="file" 
                  id="avatarUploadTrigger" 
                  accept="image/*" 
                  style={{ display: 'none' }} 
                  onChange={handleAvatarUpload}
                />
              </>
            )}
          </div>
        </div>
      </div>

      {/* Profile Info */}
      <div style={{ padding: '50px 20px 15px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h2 style={{ margin: 0, fontSize: '1.35rem', color: 'white', display: 'flex', alignItems: 'center', gap: '6px' }}>
              {profile.fullname || profile.username}
              {profile.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.95rem' }}></i>}
            </h2>
            <p style={{ margin: '4px 0 0', fontSize: '0.82rem', color: 'var(--neon-pink)', textTransform: 'capitalize' }}>
              @{profile.username} &bull; {profile.preference?.replace('_', ' ')}
              {profile.location && (
                <>
                  {' '}&bull; <i className="fas fa-map-marker-alt"></i> {profile.location}
                </>
              )}
            </p>
          </div>
          {isOwn ? (
            <button 
              className="btn-primary" 
              style={{ fontSize: '0.78rem', padding: '8px 16px', width: 'auto' }}
              onClick={() => setActiveTab('settings')}
            >
              <i className="fas fa-pen" style={{ marginRight: '6px' }}></i> Edit Profile
            </button>
          ) : (
            <button 
              className="btn-primary" 
              style={{ fontSize: '0.78rem', padding: '8px 16px', width: 'auto' }}
              onClick={() => router.push(`/messages?partner=${profile.id}`)}
            >
              <i className="fas fa-paper-plane" style={{ marginRight: '6px' }}></i> Whisper
            </button>
          )}
        </div>

        {profile.bio && (
          <p style={{ margin: '15px 0 0', fontSize: '0.9rem', color: 'var(--text-secondary)', lineHeight: '1.5', whiteSpace: 'pre-line' }}>
            {profile.bio}
          </p>
        )}

        {/* Stats Row */}
        <div 
          style={{ 
            display: 'flex', 
            margin: '20px 0', 
            borderTop: '1px solid rgba(255, 255, 255, 0.08)', 
            borderBottom: '1px solid rgba(255, 255, 255, 0.08)', 
            padding: '14px 0' 
          }}
        >
          <div style={{ flex: 1, textAlign: 'center' }}>
            <div style={{ fontSize: '1.25rem', fontWeight: '800', color: 'white' }}>{momentsCount}</div>
            <div style={{ fontSize: '0.68rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.5px', marginTop: '3px' }}>Moments</div>
          </div>
          <div style={{ flex: 1, textAlign: 'center', borderLeft: '1px solid rgba(255,255,255,0.08)', borderRight: '1px solid rgba(255,255,255,0.08)' }}>
            <div style={{ fontSize: '1.25rem', fontWeight: '800', color: 'white' }}>{desiresCount}</div>
            <div style={{ fontSize: '0.68rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.5px', marginTop: '3px' }}>Desires</div>
          </div>
          <div style={{ flex: 1, textAlign: 'center' }}>
            <div style={{ fontSize: '1.25rem', fontWeight: '800', color: '#f1c40f' }}>
              ₦{isOwn ? Math.floor(currentUser?.wallet_balance || 0).toLocaleString() : '—'}
            </div>
            <div style={{ fontSize: '0.68rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '0.5px', marginTop: '3px' }}>
              {isOwn ? 'Wallet' : 'Access'}
            </div>
          </div>
        </div>

        {isOwn && (
          /* Quick Actions Row */
          <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
            <Link href="/wallet" className="btn-primary" style={{ flex: 1, textAlign: 'center', textDecoration: 'none', fontSize: '0.8rem', padding: '10px' }}>
              <i className="fas fa-wallet" style={{ marginRight: '6px' }}></i> Wallet
            </Link>
            <Link href="/invites" className="btn-primary" style={{ flex: 1, textAlign: 'center', textDecoration: 'none', fontSize: '0.8rem', padding: '10px', background: 'rgba(255, 42, 109, 0.15)', border: '1px solid var(--neon-pink)', boxShadow: 'none' }}>
              <i className="fas fa-heart" style={{ marginRight: '6px' }}></i> Invites
            </Link>
            <button 
              className="btn-primary" 
              style={{ flex: '0 0 auto', width: '40px', background: 'rgba(180, 0, 0, 0.3)', border: '1px solid rgba(255, 50, 50, 0.4)', boxShadow: 'none' }}
              onClick={() => confirm('Log out of EazyMUZE? 💋') && logout()}
            >
              <i className="fas fa-sign-out-alt"></i>
            </button>
          </div>
        )}
      </div>

      {/* Tabs */}
      <div 
        className="profile-tabs" 
        style={{ 
          display: 'flex', 
          borderBottom: '1px solid rgba(255, 255, 255, 0.08)', 
          margin: '0 0 15px' 
        }}
      >
        <div 
          className={`profile-tab ${activeTab === 'posts' ? 'active' : ''}`} 
          style={{ 
            flex: 1, 
            textAlign: 'center', 
            padding: '14px 4px', 
            fontSize: '0.85rem', 
            color: activeTab === 'posts' ? 'var(--neon-pink)' : 'var(--text-secondary)', 
            cursor: 'pointer', 
            borderBottom: activeTab === 'posts' ? '2px solid var(--neon-pink)' : '2px solid transparent',
            fontWeight: activeTab === 'posts' ? '700' : '400',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '4px'
          }}
          onClick={() => setActiveTab('posts')}
        >
          <i className="fas fa-th"></i>
          <span>Moments</span>
        </div>

        {isOwn && (
          <>
            <div 
              className={`profile-tab ${activeTab === 'saved' ? 'active' : ''}`} 
              style={{ 
                flex: 1, 
                textAlign: 'center', 
                padding: '14px 4px', 
                fontSize: '0.85rem', 
                color: activeTab === 'saved' ? 'var(--neon-pink)' : 'var(--text-secondary)', 
                cursor: 'pointer', 
                borderBottom: activeTab === 'saved' ? '2px solid var(--neon-pink)' : '2px solid transparent',
                fontWeight: activeTab === 'saved' ? '700' : '400',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: '4px'
              }}
              onClick={() => setActiveTab('saved')}
            >
              <i className="fas fa-bookmark"></i>
              <span>Saved</span>
            </div>

            <div 
              className={`profile-tab ${activeTab === 'settings' ? 'active' : ''}`} 
              style={{ 
                flex: 1, 
                textAlign: 'center', 
                padding: '14px 4px', 
                fontSize: '0.85rem', 
                color: activeTab === 'settings' ? 'var(--neon-pink)' : 'var(--text-secondary)', 
                cursor: 'pointer', 
                borderBottom: activeTab === 'settings' ? '2px solid var(--neon-pink)' : '2px solid transparent',
                fontWeight: activeTab === 'settings' ? '700' : '400',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: '4px'
              }}
              onClick={() => setActiveTab('settings')}
            >
              <i className="fas fa-sliders-h"></i>
              <span>Settings</span>
            </div>
          </>
        )}
      </div>

      {/* Tab Panels */}
      <div>
        {/* Moments Tab */}
        {activeTab === 'posts' && (
          <div>
            {posts.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '50px 20px', color: 'var(--text-secondary)' }}>
                <i className="fas fa-camera-retro" style={{ fontSize: '3rem', color: 'rgba(255, 42, 109, 0.3)', marginBottom: '10px' }}></i>
                <p>{isOwn ? 'Share your first moment 💋' : 'No moments shared yet.'}</p>
                {isOwn && (
                  <button className="btn-primary" style={{ marginTop: '12px', width: 'auto' }} onClick={() => router.push('/')}>
                    Post Now
                  </button>
                )}
              </div>
            ) : (
              <div className="posts-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '2px' }}>
                {posts.map(post => {
                  const thumb = post.images?.[0] || post.image_fallback;
                  return (
                    <div 
                      key={post.id} 
                      className="posts-grid-item" 
                      style={{ aspectRatio: '1', overflow: 'hidden', cursor: 'pointer', position: 'relative', background: '#1a0b12' }}
                      onClick={() => setSelectedPost(post)}
                    >
                      {thumb ? (
                        <img src={thumb} style={{ width: '100%', height: '100%', objectFit: 'cover' }} alt="" />
                      ) : (
                        <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(255,42,109,0.1)' }}>
                          <i className="fas fa-comment-alt" style={{ color: 'rgba(255,42,109,0.4)', fontSize: '1.5rem' }}></i>
                        </div>
                      )}
                      <div className="grid-overlay" style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px', color: 'white', opacity: 0, transition: 'opacity 0.2s' }}>
                        <span><i className="fas fa-heart"></i> {post.likes?.length || 0}</span>
                        <span><i className="fas fa-comment"></i> {post.comments?.length || 0}</span>
                      </div>
                      {post.images?.length > 1 && (
                        <div style={{ position: 'absolute', top: '6px', right: '6px', color: 'white', fontSize: '0.75rem', textShadow: '0 0 4px black' }}>
                          <i className="fas fa-clone"></i>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Bookmarks Tab */}
        {isOwn && activeTab === 'saved' && (
          <div>
            {bookmarks.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '50px 20px', color: 'var(--text-secondary)' }}>
                <i className="fas fa-bookmark" style={{ fontSize: '3rem', color: 'rgba(255, 42, 109, 0.3)', marginBottom: '10px' }}></i>
                <p>No saved desires yet.</p>
                <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '5px' }}>Tap the bookmark icon on any post in the feed!</p>
              </div>
            ) : (
              <div className="posts-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '2px' }}>
                {bookmarks.map(post => {
                  const thumb = post.images?.[0] || post.image_fallback;
                  return (
                    <div 
                      key={post.id} 
                      className="posts-grid-item" 
                      style={{ aspectRatio: '1', overflow: 'hidden', cursor: 'pointer', position: 'relative', background: '#1a0b12' }}
                      onClick={() => setSelectedPost(post)}
                    >
                      {thumb ? (
                        <img src={thumb} style={{ width: '100%', height: '100%', objectFit: 'cover' }} alt="" />
                      ) : (
                        <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(255,42,109,0.1)' }}>
                          <i className="fas fa-heart" style={{ color: 'rgba(255,42,109,0.4)', fontSize: '1.5rem' }}></i>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Settings Tab */}
        {isOwn && activeTab === 'settings' && (
          <div style={{ padding: '0 20px' }}>
            <div className="glass-panel" style={{ padding: '20px', borderRadius: '16px', marginBottom: '16px' }}>
              <h3 style={{ margin: '0 0 16px', fontSize: '1rem', color: 'var(--neon-pink)' }}>Account Settings</h3>
              
              <form onSubmit={handleSaveSettings}>
                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Username (Username only, no spaces)</label>
                  <input 
                    type="text" 
                    value={username} 
                    onChange={(e) => setUsername(e.target.value)}
                    required
                  />
                </div>

                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Full Name</label>
                  <input 
                    type="text" 
                    value={fullname} 
                    onChange={(e) => setFullname(e.target.value)}
                  />
                </div>

                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Bio</label>
                  <textarea 
                    rows="3" 
                    value={bio} 
                    onChange={(e) => setBio(e.target.value)}
                    style={{ resize: 'none' }}
                  />
                </div>

                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Location</label>
                  <input 
                    type="text" 
                    value={location} 
                    onChange={(e) => setLocation(e.target.value)}
                  />
                </div>

                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Phone Number</label>
                  <input 
                    type="text" 
                    value={phone} 
                    onChange={(e) => setPhone(e.target.value)}
                  />
                </div>

                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>Preference</label>
                  <select 
                    value={preference} 
                    onChange={(e) => setPreference(e.target.value)}
                  >
                    <option value="straight">Straight</option>
                    <option value="gay">Gay</option>
                    <option value="lesbian">Lesbian</option>
                    <option value="bisexual">Bisexual</option>
                    <option value="sugar_daddy">Sugar Daddy</option>
                    <option value="sugar_mummy">Sugar Mummy</option>
                  </select>
                </div>

                <div style={{ marginBottom: '18px' }}>
                  <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '6px' }}>New Password (leave blank to keep current)</label>
                  <input 
                    type="password" 
                    placeholder="••••••••" 
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                </div>

                <button type="submit" className="btn-primary" disabled={savingSettings}>
                  {savingSettings ? 'Saving...' : 'Save Changes'}
                </button>
              </form>
            </div>

            {/* Danger Zone */}
            <div className="glass-panel" style={{ padding: '20px', borderRadius: '16px', border: '1px solid rgba(255, 50, 50, 0.25)' }}>
              <h3 style={{ margin: '0 0 12px', fontSize: '0.95rem', color: '#e74c3c' }}>Danger Zone</h3>
              <button 
                onClick={handleDeleteAccount}
                style={{ 
                  width: '100%', 
                  background: 'rgba(231, 76, 60, 0.2)', 
                  color: '#e74c3c', 
                  border: '1px solid rgba(231, 76, 60, 0.4)', 
                  padding: '12px', 
                  borderRadius: '16px', 
                  cursor: 'pointer', 
                  fontWeight: '600' 
                }}
              >
                <i className="fas fa-trash-alt" style={{ marginRight: '6px' }}></i> Delete My Account
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Post Detail Modal */}
      {selectedPost && (
        <div 
          style={{ 
            position: 'fixed', 
            inset: 0, 
            background: 'rgba(10, 4, 6, 0.98)', 
            zIndex: 9999, 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'center', 
            padding: '20px', 
            backdropFilter: 'blur(10px)' 
          }}
          onClick={() => setSelectedPost(null)}
        >
          <div 
            className="glass-panel" 
            style={{ 
              maxWidth: '450px', 
              width: '100%', 
              padding: 0, 
              borderRadius: '24px', 
              overflow: 'hidden', 
              border: '1px solid var(--neon-pink)' 
            }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Media */}
            {(selectedPost.images?.[0] || selectedPost.image_fallback) && (
              <img 
                src={selectedPost.images?.[0] || selectedPost.image_fallback} 
                style={{ width: '100%', maxHeight: '380px', objectFit: 'cover', display: 'block' }} 
                alt=""
              />
            )}
            
            {/* Modal Body */}
            <div style={{ padding: '20px' }}>
              <p style={{ color: 'white', fontSize: '0.92rem', lineHeight: '1.5', margin: 0 }}>
                {selectedPost.caption}
              </p>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '15px' }}>
                <span style={{ fontSize: '0.78rem', color: 'var(--text-muted)' }}>
                  {new Date(selectedPost.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                </span>
                <div style={{ display: 'flex', gap: '12px', color: 'var(--text-secondary)', fontSize: '0.85rem' }}>
                  <span><i className="fas fa-heart" style={{ color: 'var(--neon-pink)' }}></i> {selectedPost.likes?.length || 0}</span>
                  <span><i className="fas fa-comment"></i> {selectedPost.comments?.length || 0}</span>
                </div>
              </div>
            </div>

            {/* Modal Close Button */}
            <div style={{ padding: '0 20px 20px' }}>
              <button 
                className="btn-primary" 
                style={{ background: '#333', boxShadow: 'none' }}
                onClick={() => setSelectedPost(null)}
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
      
      {/* Dynamic Hover Grid overlays CSS */}
      <style jsx global>{`
        .posts-grid-item:hover .grid-overlay {
          opacity: 1 !important;
        }
      `}</style>
    </div>
  );
}
