'use client';

import { useEffect, useState, useRef } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function FeedPage() {
  const { user, profile } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const [posts, setPosts] = useState([]);
  const [stories, setStories] = useState([]);
  const [activeAd, setActiveAd] = useState(null);
  const [loading, setLoading] = useState(true);

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [postType, setPostType] = useState('moment'); // 'moment' or 'story'
  const [caption, setCaption] = useState('');
  const [music, setMusic] = useState('');
  const [images, setImages] = useState([]);
  const [posting, setPosting] = useState(false);

  // Story Viewer State
  const [viewingStory, setViewingStory] = useState(null);
  const [storyProgress, setStoryProgress] = useState(0);
  const storyTimer = useRef(null);

  // Open comments maps
  const [openComments, setOpenComments] = useState({});
  const [commentInputs, setCommentInputs] = useState({});

  // Fetch Feed Data
  const fetchFeed = async () => {
    try {
      setLoading(true);
      // Fetch posts joined with profiles
      const { data: postsData, error: postsErr } = await supabase
        .from('posts')
        .select(`
          *,
          profiles:user_id (id, username, avatar, is_verified, preference, location)
        `)
        .order('created_at', { ascending: false });

      if (!postsErr && postsData) {
        setPosts(postsData);
      }

      // Fetch active stories joined with profiles
      const { data: storiesData, error: storiesErr } = await supabase
        .from('stories')
        .select(`
          *,
          profiles:user_id (id, username, avatar, location)
        `)
        .gt('expires_at', new Date().toISOString())
        .order('created_at', { ascending: false });

      if (!storiesErr && storiesData) {
        setStories(storiesData);
      }

      // Fetch active sponsored ads
      const { data: adsData } = await supabase
        .from('ads')
        .select('*')
        .eq('is_active', true)
        .limit(1);

      if (adsData && adsData.length > 0) {
        setActiveAd(adsData[0]);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchFeed();
    }
  }, [user]);

  // Image Upload helper (base64)
  const handleImageChange = (e) => {
    const files = Array.from(e.target.files).slice(0, 3);
    const loadedImages = [];
    files.forEach(file => {
      const reader = new FileReader();
      reader.onload = (uploadEvent) => {
        loadedImages.push(uploadEvent.target.result);
        if (loadedImages.length === files.length) {
          setImages(loadedImages);
        }
      };
      reader.readAsDataURL(file);
    });
  };

  // Submit Post/Story
  const handleSubmitPost = async () => {
    if (!caption && images.length === 0) {
      alert('Please enter a caption or upload an image.');
      return;
    }

    setPosting(true);
    try {
      if (postType === 'story') {
        if (images.length === 0) {
          alert('Stories require an image.');
          setPosting(false);
          return;
        }

        const expiresAt = new Date();
        expiresAt.setHours(expiresAt.getHours() + 24);

        const { error } = await supabase.from('stories').insert({
          user_id: user.id,
          image: images[0],
          media_type: 'image',
          caption,
          expires_at: expiresAt.toISOString()
        });

        if (error) throw error;
        alert('Story uploaded! 💋');
      } else {
        const { error } = await supabase.from('posts').insert({
          user_id: user.id,
          caption,
          music,
          images: images,
          image_fallback: images[0] || '',
          likes: [],
          comments: []
        });

        if (error) throw error;
        alert('Moment shared! 📸');
      }

      // Reset
      setIsModalOpen(false);
      setCaption('');
      setMusic('');
      setImages([]);
      fetchFeed();
    } catch (e) {
      alert(e.message || 'Error uploading post');
    } finally {
      setPosting(false);
    }
  };

  // Story Viewer Auto-progression
  useEffect(() => {
    if (viewingStory) {
      setStoryProgress(0);
      storyTimer.current = setInterval(() => {
        setStoryProgress(prev => {
          if (prev >= 100) {
            clearInterval(storyTimer.current);
            setViewingStory(null);
            return 100;
          }
          return prev + 2; // progress 2% every 100ms = 5s total
        });
      }, 100);
    } else {
      if (storyTimer.current) clearInterval(storyTimer.current);
    }
    return () => {
      if (storyTimer.current) clearInterval(storyTimer.current);
    };
  }, [viewingStory]);

  const handleOpenStory = (story) => {
    setViewingStory(story);
  };

  const handleLike = async (post) => {
    const isLiked = post.likes?.includes(user.id);
    const updatedLikes = isLiked
      ? post.likes.filter(id => id !== user.id)
      : [...(post.likes || []), user.id];

    // Optimistic UI update
    setPosts(prev => prev.map(p => p.id === post.id ? { ...p, likes: updatedLikes } : p));

    await supabase
      .from('posts')
      .update({ likes: updatedLikes })
      .eq('id', post.id);
  };

  const handleDoubleTap = (post) => {
    if (!post.likes?.includes(user.id)) {
      handleLike(post);
    }
    // Show heart animation overlay
    const overlay = document.getElementById(`heart-overlay-${post.id}`);
    if (overlay) {
      overlay.style.opacity = '1';
      overlay.style.transform = 'translate(-50%, -50%) scale(1.2)';
      setTimeout(() => {
        overlay.style.opacity = '0';
        overlay.style.transform = 'translate(-50%, -50%) scale(0)';
      }, 800);
    }
  };

  const handleAddComment = async (post) => {
    const text = commentInputs[post.id]?.trim();
    if (!text) return;

    const newComment = {
      id: Math.random().toString(36).substr(2, 9),
      user_id: user.id,
      username: profile?.username || 'anon',
      text,
      created_at: new Date().toISOString()
    };

    const updatedComments = [...(post.comments || []), newComment];

    // Optimistic UI update
    setPosts(prev => prev.map(p => p.id === post.id ? { ...p, comments: updatedComments } : p));
    setCommentInputs(prev => ({ ...prev, [post.id]: '' }));

    await supabase
      .from('posts')
      .update({ comments: updatedComments })
      .eq('id', post.id);
  };

  return (
    <div style={{ paddingBottom: '20px' }}>
      {/* Stories Tray */}
      <div className="stories-tray">
        {/* Add Story Circle */}
        <div className="story-wrapper" onClick={() => { setPostType('story'); setIsModalOpen(true); }}>
          <div className="story-circle active" style={{ position: 'relative' }}>
            <img src={profile?.avatar || `https://ui-avatars.com/api/?name=${profile?.username || 'user'}&background=8e1a1a&color=fff`} alt="Add Story" />
            <div className="story-add-icon"><i className="fas fa-plus"></i></div>
          </div>
          <span className="story-name">add story</span>
        </div>

        {/* Dynamic Stories */}
        {stories.map(story => (
          <div key={story.id} className="story-wrapper" onClick={() => handleOpenStory(story)}>
            <div className={`story-circle ${profile?.location && story.profiles?.location && profile.location.toLowerCase() === story.profiles.location.toLowerCase() ? 'nearby-story' : ''}`}>
              <img src={story.profiles?.avatar || `https://ui-avatars.com/api/?name=${story.profiles?.username}&background=8e1a1a&color=fff`} alt="Story" />
            </div>
            <span className="story-name">{story.profiles?.username?.substring(0, 10)}</span>
          </div>
        ))}
      </div>

      {/* Sponsored Ad */}
      {activeAd && (
        <div className="promo-banner glass-panel" style={{ margin: '0 15px 20px', padding: '15px 20px', borderRadius: '16px' }}>
          <span className="sponsored-tag" style={{ background: 'var(--neon-pink)', color: 'white', fontSize: '0.65rem', padding: '2px 8px', borderRadius: '10px', letterSpacing: '1px' }}>SPONSORED</span>
          <a href={activeAd.link} target="_blank" rel="noopener noreferrer" style={{ textDecoration: 'none', color: 'inherit' }}>
            <h3 style={{ marginTop: '8px', fontSize: '1.1rem' }}>{activeAd.caption}</h3>
          </a>
        </div>
      )}

      {/* Share Moment Button */}
      <div style={{ padding: '0 15px 20px' }}>
        <button className="btn-primary" style={{ width: '100%' }} onClick={() => { setPostType('moment'); setIsModalOpen(true); }}>
          📸 Share Your Moment
        </button>
      </div>

      {/* Posts Feed */}
      <div id="feedContainer">
        {loading ? (
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
          </div>
        ) : posts.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '60px 20px' }}>
            <div style={{ fontSize: '3rem', marginBottom: '15px' }}>🌙</div>
            <p style={{ color: 'var(--text-secondary)' }}>The temple is quiet. No desires yet.</p>
          </div>
        ) : (
          posts.map(post => {
            const hasLiked = post.likes?.includes(user.id);
            const isCommentsOpen = openComments[post.id];
            const firstImg = post.images?.[0] || post.image_fallback;

            return (
              <div key={post.id} className="post-card glass-panel" style={{ marginBottom: '16px', borderRadius: '20px', overflow: 'hidden' }}>
                {/* Post Header */}
                <div className="post-header" style={{ display: 'flex', alignItems: 'center', padding: '14px 16px', gap: '12px' }}>
                  <img 
                    src={post.profiles?.avatar || `https://ui-avatars.com/api/?name=${post.profiles?.username}&background=8e1a1a&color=fff`} 
                    style={{ width: '44px', height: '44px', borderRadius: '50%', objectFit: 'cover', border: '2px solid var(--neon-pink)', cursor: 'pointer' }}
                    onClick={() => router.push(`/profile/${post.profiles?.id}`)}
                    alt=""
                  />
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: '700', color: 'white', fontSize: '0.95rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
                      {post.profiles?.username}
                      {post.profiles?.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.75rem' }}></i>}
                      <span className="pref-badge">{post.profiles?.preference || 'muze'}</span>
                    </div>
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.78rem', marginTop: '2px' }}>
                      <i className="fas fa-map-marker-alt" style={{ fontSize: '0.7rem' }}></i> {post.profiles?.location || 'Unknown'}
                      {post.music && ` • 🎵 ${post.music}`}
                    </div>
                  </div>
                  <button 
                    onClick={() => router.push(`/messages?partner=${post.profiles?.id}`)} 
                    style={{ background: 'none', border: '1px solid var(--glass-border)', color: 'var(--text-secondary)', padding: '6px 12px', borderRadius: '15px', fontSize: '0.75rem', cursor: 'pointer' }}
                  >
                    Whisper
                  </button>
                </div>

                {/* Post Media (Double tap to like) */}
                {firstImg && (
                  <div 
                    style={{ position: 'relative', cursor: 'pointer', overflow: 'hidden', height: '380px' }}
                    onDoubleClick={() => handleDoubleTap(post)}
                  >
                    <img src={firstImg} style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }} alt="" />
                    <div 
                      id={`heart-overlay-${post.id}`} 
                      className="double-tap-heart" 
                      style={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%) scale(0)', opacity: 0, transition: 'all 0.4s ease', pointerEvents: 'none', zIndex: 10 }}
                    >
                      ❤️
                    </div>
                  </div>
                )}

                {/* Footer Actions */}
                <div style={{ padding: '12px 16px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '18px' }}>
                      <button onClick={() => handleLike(post)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: hasLiked ? 'var(--neon-pink)' : 'white', fontSize: '1.5rem' }}>
                        <i className={`${hasLiked ? 'fas' : 'far'} fa-heart`}></i>
                      </button>
                      <button onClick={() => setOpenComments(prev => ({ ...prev, [post.id]: !isCommentsOpen }))} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'white', fontSize: '1.45rem' }}>
                        <i className="far fa-comment"></i>
                      </button>
                      <button onClick={() => router.push(`/messages?partner=${post.profiles?.id}`)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'white', fontSize: '1.45rem' }}>
                        <i className="far fa-paper-plane"></i>
                      </button>
                    </div>
                  </div>

                  <div style={{ fontWeight: '700', color: 'white', fontSize: '0.9rem', marginBottom: '6px' }}>
                    {post.likes?.length === 1 ? '1 desire' : `${post.likes?.length || 0} desires`}
                  </div>

                  <div style={{ fontSize: '0.9rem', color: 'var(--text-primary)', lineHeight: '1.4', marginBottom: '8px' }}>
                    <strong>{post.profiles?.username}</strong> {post.caption}
                  </div>

                  {post.comments?.length > 0 && (
                    <button 
                      onClick={() => setOpenComments(prev => ({ ...prev, [post.id]: !isCommentsOpen }))}
                      style={{ background: 'none', border: 'none', color: 'var(--text-secondary)', fontSize: '0.82rem', cursor: 'pointer', padding: 0, marginBottom: '6px' }}
                    >
                      {isCommentsOpen ? 'Hide comments' : `View all ${post.comments.length} comments`}
                    </button>
                  )}

                  {/* Comments list */}
                  {isCommentsOpen && (
                    <div style={{ marginTop: '10px', borderTop: '1px solid rgba(255,255,255,0.06)', paddingTop: '10px' }}>
                      {(post.comments || []).map((c, i) => (
                        <div key={i} style={{ fontSize: '0.84rem', marginBottom: '6px' }}>
                          <strong style={{ color: 'var(--text-secondary)' }}>{c.username}</strong>{' '}
                          <span style={{ color: 'var(--text-primary)' }}>{c.text}</span>
                        </div>
                      ))}

                      {/* Comment Input */}
                      <div style={{ display: 'flex', gap: '8px', marginTop: '10px', alignItems: 'center' }}>
                        <img src={profile?.avatar || `https://ui-avatars.com/api/?name=${profile?.username || 'user'}&background=8e1a1a&color=fff`} style={{ width: '30px', height: '30px', borderRadius: '50%', objectFit: 'cover' }} alt="" />
                        <input 
                          type="text" 
                          placeholder="Add a comment..."
                          value={commentInputs[post.id] || ''}
                          onChange={(e) => setCommentInputs(prev => ({ ...prev, [post.id]: e.target.value }))}
                          onKeyDown={(e) => { if (e.key === 'Enter') handleAddComment(post); }}
                          style={{ flex: 1, background: 'none', border: 'none', borderBottom: '1px solid rgba(255,255,255,0.15)', color: 'white', padding: '4px 0', fontSize: '0.85rem', outline: 'none' }}
                        />
                        <button onClick={() => handleAddComment(post)} style={{ background: 'none', border: 'none', color: 'var(--neon-pink)', fontWeight: '700', fontSize: '0.85rem', cursor: 'pointer' }}>Post</button>
                      </div>
                    </div>
                  )}

                  <div style={{ color: 'var(--text-muted)', fontSize: '0.72rem', marginTop: '8px', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                    {new Date(post.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Story Viewer Overlay */}
      {viewingStory && (
        <div id="storyViewer" style={{ display: 'block', position: 'fixed', inset: 0, background: 'black', zIndex: 9999 }}>
          {/* Progress bar */}
          <div id="storyProgressBar" style={{ position: 'absolute', top: '10px', width: '100%', display: 'flex', gap: '4px', padding: '0 12px', boxSizing: 'border-box', zIndex: 10001 }}>
            <div style={{ flex: 1, height: '2px', background: 'rgba(255,255,255,0.6)', borderRadius: '2px', overflow: 'hidden' }}>
              <div style={{ height: '100%', background: 'white', width: `${storyProgress}%` }}></div>
            </div>
          </div>
          {/* Header */}
          <div style={{ position: 'absolute', top: '20px', left: '15px', right: '15px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', zIndex: 10001 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
              <img src={viewingStory.profiles?.avatar || `https://ui-avatars.com/api/?name=${viewingStory.profiles?.username}&background=8e1a1a&color=fff`} style={{ width: '40px', height: '40px', borderRadius: '50%', border: '2px solid var(--neon-pink)', cursor: 'pointer' }} onClick={() => { setViewingStory(null); router.push(`/profile/${viewingStory.profiles?.id}`); }} alt="" />
              <strong style={{ color: 'white', textShadow: '0 0 5px black', cursor: 'pointer' }} onClick={() => { setViewingStory(null); router.push(`/profile/${viewingStory.profiles?.id}`); }}>@{viewingStory.profiles?.username}</strong>
            </div>
            <i className="fas fa-times" style={{ color: 'white', fontSize: '1.5rem', cursor: 'pointer' }} onClick={() => setViewingStory(null)}></i>
          </div>
          <img src={viewingStory.image} style={{ width: '100%', height: '100%', objectFit: 'cover' }} alt="" />
          <div style={{ position: 'absolute', bottom: '80px', left: 0, right: 0, textAlign: 'center', color: 'white', fontSize: '1rem', textShadow: '0 0 10px black', padding: '0 20px' }}>
            {viewingStory.caption}
          </div>
        </div>
      )}

      {/* Share Modal */}
      {isModalOpen && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10, 4, 6, 0.96)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', backdropFilter: 'blur(8px)' }}>
          <div className="glass-panel" style={{ background: 'linear-gradient(135deg, rgba(20, 10, 15, 0.98), rgba(45, 15, 30, 0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '420px', width: '90%', padding: '25px' }}>
            <h3 style={{ color: 'var(--neon-pink)', marginBottom: '15px', textAlign: 'center', fontSize: '1.1rem' }}>
              {postType === 'story' ? 'Add a Story 💋' : 'Share a Moment 💋'}
            </h3>
            
            <div style={{ display: 'flex', gap: '8px', marginBottom: '15px' }}>
              <button 
                className="btn-primary" 
                style={{ flex: 1, fontSize: '0.8rem', padding: '10px', background: postType === 'moment' ? 'var(--neon-pink)' : '#222', boxShadow: postType === 'moment' ? '' : 'none' }}
                onClick={() => setPostType('moment')}
              >
                Moment (Feed)
              </button>
              <button 
                className="btn-primary" 
                style={{ flex: 1, fontSize: '0.8rem', padding: '10px', background: postType === 'story' ? 'var(--neon-pink)' : '#222', boxShadow: postType === 'story' ? '' : 'none' }}
                onClick={() => setPostType('story')}
              >
                Story (24h)
              </button>
            </div>

            <textarea 
              rows="3" 
              placeholder="What's on your mind tonight?..." 
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
              style={{ width: '100%', padding: '12px', borderRadius: '10px', background: 'rgba(255,255,255,0.05)', color: 'white', border: '1px solid rgba(255,42,109,0.3)', fontSize: '0.9rem', outline: 'none', resize: 'none', marginBottom: '12px' }}
            />

            {postType === 'moment' && (
              <select 
                value={music}
                onChange={(e) => setMusic(e.target.value)}
                style={{ width: '100%', padding: '10px', borderRadius: '8px', background: '#1a0b12', border: '1px solid rgba(255,42,109,0.3)', color: 'white', marginBottom: '12px', fontSize: '0.88rem', outline: 'none' }}
              >
                <option value="">Select Music Vibe (optional)</option>
                <option value="wizkid">Wizkid — Essence</option>
                <option value="burna">Burna Boy — Last Last</option>
                <option value="ayra">Ayra Starr — Rush</option>
              </select>
            )}

            <div 
              onClick={() => document.getElementById('postImages').click()}
              style={{ border: '2px dashed rgba(255,42,109,0.4)', padding: '20px', textAlign: 'center', borderRadius: '12px', cursor: 'pointer', marginBottom: '12px' }}
            >
              <i className="fas fa-camera-retro" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
              <p style={{ fontSize: '0.8rem', marginTop: '8px', color: 'var(--text-secondary)' }}>Upload Images (max 3)</p>
              <input type="file" id="postImages" multiple accept="image/*" style={{ display: 'none' }} onChange={handleImageChange} />
            </div>

            <div style={{ display: 'flex', gap: '5px', flexWrap: 'wrap', marginBottom: '15px' }}>
              {images.map((img, idx) => (
                <img key={idx} src={img} style={{ width: '70px', height: '70px', objectFit: 'cover', borderRadius: '8px', border: '1px solid var(--neon-pink)' }} alt="" />
              ))}
            </div>

            <div style={{ display: 'flex', gap: '10px' }}>
              <button className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={() => setIsModalOpen(false)}>Cancel</button>
              <button className="btn-primary" style={{ flex: 1 }} disabled={posting} onClick={handleSubmitPost}>
                {posting ? 'Posting...' : 'Post Desire 💋'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
