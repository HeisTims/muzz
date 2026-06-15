'use client';

import { useEffect, useState, useRef, Suspense } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';

function MessagesContent() {
  const { user, profile, refreshProfile } = useApp();
  const supabase = createClient();
  const router = useRouter();
  const searchParams = useSearchParams();
  const partnerId = searchParams.get('partner');

  // Inbox List state
  const [conversations, setConversations] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [inboxLoading, setInboxLoading] = useState(true);

  // Chat Room state
  const [partner, setPartner] = useState(null);
  const [messages, setMessages] = useState([]);
  const [chatLoading, setChatLoading] = useState(false);
  const [newMessageText, setNewMessageText] = useState('');
  const [attachedImage, setAttachedImage] = useState(null);
  const [showEmojiPanel, setShowEmojiPanel] = useState(false);
  const [isPartnerTyping, setIsPartnerTyping] = useState(false);
  const [activeMsgIdForReaction, setActiveMsgIdForReaction] = useState(null);
  const [reactionPosition, setReactionPosition] = useState({ x: 0, y: 0 });

  // Refs
  const messagesEndRef = useRef(null);
  const typingTimeoutRef = useRef(null);
  const chatChannelRef = useRef(null);

  // Emojis list
  const emojis = ['❤️','💋','🔥','😘','🥰','😍','💕','🌹','✨','😏','🙈','💦','🍑','😈','🖤','💗','🌙','👄','💆','🤭','😊','😂','🎉','💯','🙏','😭','💀','🤩','😬','🥺','🤤','🫦'];
  const reactionEmojis = ['❤️','😂','😮','😢','😡','👍','💯'];

  // Fetch conversations
  const fetchConversations = async () => {
    if (!user) return;
    try {
      setInboxLoading(true);
      const { data, error } = await supabase
        .from('messages')
        .select(`
          *,
          sender:sender_id (id, username, avatar, is_verified, is_online),
          receiver:receiver_id (id, username, avatar, is_verified, is_online)
        `)
        .or(`sender_id.eq.${user.id},receiver_id.eq.${user.id}`)
        .order('timestamp', { ascending: false });

      if (!error && data) {
        const conversationsMap = {};
        data.forEach(msg => {
          const isSender = msg.sender_id === user.id;
          const counterpart = isSender ? msg.receiver : msg.sender;
          if (!counterpart) return;
          if (!conversationsMap[counterpart.id]) {
            conversationsMap[counterpart.id] = {
              partner: counterpart,
              lastMessage: msg
            };
          }
        });
        setConversations(Object.values(conversationsMap));
      }
    } catch (e) {
      console.error(e);
    } finally {
      setInboxLoading(false);
    }
  };

  // Fetch active chat messages
  const fetchChatMessages = async (pid) => {
    if (!user || !pid) return;
    try {
      setChatLoading(true);
      
      // Fetch partner info
      const { data: partnerData } = await supabase
        .from('profiles')
        .select('*')
        .eq('id', pid)
        .single();
      
      if (partnerData) {
        setPartner(partnerData);
      }

      // Fetch last 80 messages between user and partner
      const { data: msgsData, error } = await supabase
        .from('messages')
        .select('*')
        .or(`and(sender_id.eq.${user.id},receiver_id.eq.${pid}),and(sender_id.eq.${pid},receiver_id.eq.${user.id})`)
        .order('timestamp', { ascending: true })
        .limit(80);

      if (!error && msgsData) {
        setMessages(msgsData);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setChatLoading(false);
      setTimeout(scrollToBottom, 100);
    }
  };

  useEffect(() => {
    if (user) {
      if (partnerId) {
        fetchChatMessages(partnerId);
      } else {
        setPartner(null);
        setMessages([]);
        fetchConversations();
      }
    }
  }, [user, partnerId]);

  // Real-time PostgreSQL subscription for new messages
  useEffect(() => {
    if (!user) return;

    const subscription = supabase
      .channel('messages-realtime')
      .on('postgres_changes', {
        event: 'INSERT',
        schema: 'public',
        table: 'messages',
      }, (payload) => {
        const newMsg = payload.new;
        if (partnerId && (
          (newMsg.sender_id === user.id && newMsg.receiver_id === partnerId) ||
          (newMsg.sender_id === partnerId && newMsg.receiver_id === user.id)
        )) {
          setMessages(prev => {
            if (prev.some(m => m.id === newMsg.id)) return prev;
            return [...prev, newMsg];
          });
          setTimeout(scrollToBottom, 100);
        } else {
          fetchConversations();
        }
      })
      .on('postgres_changes', {
        event: 'UPDATE',
        schema: 'public',
        table: 'messages',
      }, (payload) => {
        const updatedMsg = payload.new;
        if (partnerId && (
          (updatedMsg.sender_id === user.id && updatedMsg.receiver_id === partnerId) ||
          (updatedMsg.sender_id === partnerId && updatedMsg.receiver_id === user.id)
        )) {
          setMessages(prev => prev.map(m => m.id === updatedMsg.id ? updatedMsg : m));
        }
      })
      .subscribe();

    return () => {
      supabase.removeChannel(subscription);
    };
  }, [user, partnerId]);

  // Set up Realtime Broadcast Channel for typing indicators
  useEffect(() => {
    if (!user || !partnerId) return;

    const channel = supabase.channel(`chat-typing-${partnerId}`);
    chatChannelRef.current = channel;

    channel
      .on('broadcast', { event: 'typing' }, ({ payload }) => {
        if (payload.userId === partnerId) {
          setIsPartnerTyping(payload.isTyping);
        }
      })
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, [user, partnerId]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const handleTypingInput = () => {
    if (chatChannelRef.current) {
      chatChannelRef.current.send({
        type: 'broadcast',
        event: 'typing',
        payload: { userId: user.id, isTyping: true }
      });
    }

    clearTimeout(typingTimeoutRef.current);
    typingTimeoutRef.current = setTimeout(() => {
      if (chatChannelRef.current) {
        chatChannelRef.current.send({
          type: 'broadcast',
          event: 'typing',
          payload: { userId: user.id, isTyping: false }
        });
      }
    }, 2000);
  };

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (uploadEvent) => {
      setAttachedImage(uploadEvent.target.result);
    };
    reader.readAsDataURL(file);
  };

  const handleSendMessage = async () => {
    if (!newMessageText.trim() && !attachedImage) return;

    try {
      const hasConvo = messages.length > 0;

      if (!hasConvo) {
        if (!profile?.has_used_free_whisper) {
          const { error: profileErr } = await supabase
            .from('profiles')
            .update({ has_used_free_whisper: true })
            .eq('id', user.id);

          if (profileErr) throw profileErr;
          await refreshProfile();
        } else {
          if ((profile?.wallet_balance || 0) < 500) {
            alert('Insufficient wallet balance. First conversation is free, subsequent initiations require ₦500.');
            return;
          }

          const { error: profileErr } = await supabase
            .from('profiles')
            .update({ wallet_balance: (profile.wallet_balance - 500) })
            .eq('id', user.id);

          if (profileErr) throw profileErr;

          await supabase
            .from('payments')
            .insert({
              type: 'whisper_init',
              payer_id: user.id,
              recipient_id: partnerId,
              amount: 500
            });

          await refreshProfile();
        }
      }

      const { data: sentMsg, error: sendErr } = await supabase
        .from('messages')
        .insert({
          sender_id: user.id,
          receiver_id: partnerId,
          text: newMessageText.trim(),
          image_url: attachedImage,
          is_read: false
        })
        .select()
        .single();

      if (sendErr) throw sendErr;

      await supabase
        .from('notifications')
        .insert({
          user_id: partnerId,
          type: 'whisper',
          message: `@${profile?.username || 'Someone'} whispered to you! 💬`
        });

      setNewMessageText('');
      setAttachedImage(null);
      scrollToBottom();

      const partnerNameLower = partner?.username?.toLowerCase() || '';
      const isBot = partnerNameLower.includes('sandra') || partnerNameLower.includes('rita') || partnerNameLower.includes('west') || partner?.is_verified;
      if (isBot) {
        setTimeout(async () => {
          const botReplies = [
            "Oh, you're bold... I like that. 💋",
            "Whisper a little closer...",
            "Are you always this straightforward? Makes me wonder what else you're capable of. 🔥",
            "Mmm, tell me more. I'm listening.",
            "That's one way to get my attention. Don't stop."
          ];
          const randomReply = botReplies[Math.floor(Math.random() * botReplies.length)];
          
          await supabase
            .from('messages')
            .insert({
              sender_id: partnerId,
              receiver_id: user.id,
              text: randomReply,
              is_read: false
            });

          await supabase
            .from('notifications')
            .insert({
              user_id: user.id,
              type: 'whisper',
              message: 'New response in your whispers 💋'
            });

        }, 2000);
      }

    } catch (e) {
      alert(e.message || 'Error sending message');
    }
  };

  const handleUnlockMessage = async (msg) => {
    if (msg.sender_id === user.id || msg.is_read) return;

    const confirmUnlock = confirm('Unlock this whisper? (First read is free, subsequent unlocks cost ₦200).');
    if (!confirmUnlock) return;

    try {
      if (!profile?.has_used_free_read) {
        const { error: profileErr } = await supabase
          .from('profiles')
          .update({ has_used_free_read: true })
          .eq('id', user.id);

        if (profileErr) throw profileErr;
        await refreshProfile();
      } else {
        if ((profile?.wallet_balance || 0) < 200) {
          alert('Insufficient wallet balance. Unlock requires ₦200.');
          return;
        }

        const { error: profileErr } = await supabase
          .from('profiles')
          .update({ wallet_balance: (profile.wallet_balance - 200) })
          .eq('id', user.id);

        if (profileErr) throw profileErr;

        await supabase
          .from('payments')
          .insert({
            type: 'whisper_read',
            payer_id: user.id,
            recipient_id: partnerId,
            amount: 200
          });

        await refreshProfile();
      }

      const { error: msgErr } = await supabase
        .from('messages')
        .update({ is_read: true })
        .eq('id', msg.id);

      if (msgErr) throw msgErr;

      setMessages(prev => prev.map(m => m.id === msg.id ? { ...m, is_read: true } : m));
      alert('Whisper unlocked! 💋');

    } catch (e) {
      alert(e.message || 'Error unlocking message.');
    }
  };

  const handleReactToMessage = async (msgId, emoji) => {
    try {
      const { error } = await supabase
        .from('messages')
        .update({ reaction: emoji })
        .eq('id', msgId);

      if (error) throw error;
      setMessages(prev => prev.map(m => m.id === msgId ? { ...m, reaction: emoji } : m));
      setActiveMsgIdForReaction(null);
    } catch (e) {
      alert('Failed to react to message');
    }
  };

  const handleContextMenu = (e, msgId) => {
    e.preventDefault();
    setActiveMsgIdForReaction(msgId);
    setReactionPosition({
      x: Math.min(e.clientX, window.innerWidth - 220),
      y: Math.max(e.clientY - 60, 20)
    });
  };

  const renderGroupedMessages = () => {
    let lastDate = null;

    return messages.map(msg => {
      const isMine = msg.sender_id === user.id;
      const msgDate = new Date(msg.timestamp).toDateString();
      const showDivider = msgDate !== lastDate;
      lastDate = msgDate;

      const isLocked = !isMine && !msg.is_read;

      return (
        <div key={msg.id}>
          {showDivider && (
            <div className="date-divider">
              {new Date(msg.timestamp).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}
            </div>
          )}

          <div style={{ display: 'flex', justifyContent: isMine ? 'flex-end' : 'flex-start', gap: '8px', alignItems: 'flex-end', marginBottom: '8px' }}>
            {!isMine && (
              <img 
                src={partner?.avatar || `https://ui-avatars.com/api/?name=${partner?.username || 'user'}&background=8e1a1a&color=fff`} 
                style={{ width: '28px', height: '28px', borderRadius: '50%', objectFit: 'cover' }} 
                alt="" 
              />
            )}

            <div>
              <div 
                className={`msg-bubble ${isMine ? 'msg-mine' : 'msg-theirs'}`}
                style={{ 
                  cursor: isLocked ? 'pointer' : 'default',
                  border: isLocked ? '1px dashed var(--neon-pink)' : '',
                  background: isLocked ? 'rgba(255, 42, 109, 0.05)' : ''
                }}
                onClick={() => isLocked && handleUnlockMessage(msg)}
                onContextMenu={(e) => !isLocked && handleContextMenu(e, msg.id)}
              >
                {isLocked ? (
                  <span style={{ color: 'var(--text-secondary)' }}>
                    🔒 Locked Whisper (Click to unlock)
                  </span>
                ) : (
                  <>
                    {msg.image_url && (
                      <img src={msg.image_url} alt="" style={{ maxWidth: '220px', borderRadius: '14px', display: 'block', marginBottom: '4px' }} />
                    )}
                    {msg.text && <span>{msg.text}</span>}
                    {msg.reaction && (
                      <span className="msg-reaction">{msg.reaction}</span>
                    )}
                  </>
                )}
              </div>

              <div className={`read-receipt ${isMine && msg.is_read ? 'seen' : ''}`}>
                {isMine ? (msg.is_read ? '✓ Seen' : '✓ Sent') : ''}{' '}
                {new Date(msg.timestamp).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}
              </div>
            </div>

            {isMine && (
              <img 
                src={profile?.avatar || `https://ui-avatars.com/api/?name=${profile?.username || 'user'}&background=8e1a1a&color=fff`} 
                style={{ width: '28px', height: '28px', borderRadius: '50%', objectFit: 'cover' }} 
                alt="" 
              />
            )}
          </div>
        </div>
      );
    });
  };

  if (partnerId && partner) {
    return (
      <div id="chatWrapper">
        {/* Header */}
        <div className="chat-header">
          <Link href="/messages" style={{ color: 'var(--neon-pink)', marginRight: '10px' }}>
            <i className="fas fa-arrow-left" style={{ fontSize: '1.1rem' }}></i>
          </Link>
          
          <div style={{ position: 'relative' }} onClick={() => router.push(`/profile/${partner.id}`)}>
            <img 
              src={partner.avatar || `https://ui-avatars.com/api/?name=${partner.username}&background=8e1a1a&color=fff`} 
              style={{ width: '42px', height: '42px', borderRadius: '50%', objectFit: 'cover', cursor: 'pointer', border: `2px solid ${partner.is_online ? '#2ecc71' : 'var(--glass-border)'}` }} 
              alt="" 
            />
            {partner.is_online && (
              <div style={{ position: 'absolute', bottom: 0, right: 0, width: '11px', height: '11px', background: '#2ecc71', borderRadius: '50%', border: '2px solid #0a0406' }}></div>
            )}
          </div>
          
          <div style={{ flex: 1, minWidth: 0, cursor: 'pointer' }} onClick={() => router.push(`/profile/${partner.id}`)}>
            <div style={{ fontWeight: '700', color: 'white', fontSize: '1rem', display: 'flex', alignItems: 'center', gap: '6px' }}>
              {partner.username}
              {partner.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.75rem' }}></i>}
            </div>
            <div style={{ fontSize: '0.75rem', color: partner.is_online ? '#2ecc71' : 'var(--text-secondary)' }}>
              {partner.is_online ? '● Active now' : `Last seen ${new Date(partner.last_seen || Date.now()).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`}
            </div>
          </div>
          
          <div style={{ display: 'flex', gap: '18px', color: 'var(--neon-pink)', fontSize: '1.15rem' }}>
            <i className="fas fa-phone" style={{ cursor: 'pointer' }} onClick={() => alert('Voice calls coming soon! 🔮')}></i>
            <i className="fas fa-video" style={{ cursor: 'pointer' }} onClick={() => alert('Video calls coming soon! 🔮')}></i>
          </div>
        </div>

        {/* Messages list */}
        <div id="messagesArea" style={{ overflowY: 'auto', flex: 1, padding: '16px 12px' }}>
          {chatLoading ? (
            <div style={{ textAlign: 'center', padding: '40px' }}>
              <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
            </div>
          ) : (
            renderGroupedMessages()
          )}
          {isPartnerTyping && (
            <div id="typingIndicator" style={{ display: 'block' }}>{partner.username} is typing<span className="typing-dots">...</span></div>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Reaction picker */}
        {activeMsgIdForReaction && (
          <div 
            id="reactionPicker" 
            style={{ 
              display: 'flex', 
              position: 'fixed', 
              left: `${reactionPosition.x}px`, 
              top: `${reactionPosition.y}px`, 
              background: 'rgba(20, 10, 15, 0.98)', 
              border: '1px solid rgba(255, 42, 109, 0.3)', 
              borderRadius: '16px', 
              padding: '12px', 
              fontSize: '1.6rem', 
              gap: '8px', 
              zIndex: 9999, 
              boxShadow: '0 8px 32px rgba(0, 0, 0, 0.5)', 
              backdropFilter: 'blur(12px)' 
            }}
          >
            {reactionEmojis.map(em => (
              <span 
                key={em} 
                onClick={() => handleReactToMessage(activeMsgIdForReaction, em)} 
                style={{ cursor: 'pointer', transition: 'transform 0.2s' }}
              >
                {em}
              </span>
            ))}
          </div>
        )}

        {/* Floating image preview */}
        {attachedImage && (
          <div style={{ position: 'absolute', bottom: '75px', left: '14px', zIndex: 100 }}>
            <img src={attachedImage} style={{ width: '70px', height: '70px', objectFit: 'cover', borderRadius: '10px', border: '2px solid var(--neon-pink)' }} alt="" />
            <span 
              onClick={() => setAttachedImage(null)} 
              style={{ position: 'absolute', top: '-5px', right: '-5px', background: 'var(--neon-pink)', borderRadius: '50%', width: '18px', height: '18px', display: 'flex', alignItems: 'center', justifycontent: 'center', color: 'white', fontSize: '0.65rem', cursor: 'pointer' }}
            >
              ✕
            </span>
          </div>
        )}

        {/* Emoji Panel */}
        {showEmojiPanel && (
          <div style={{ position: 'fixed', bottom: '80px', left: '10px', right: '10px', zIndex: 9000 }}>
            <div className="emoji-grid">
              {emojis.map(em => (
                <button 
                  key={em} 
                  onClick={() => { setNewMessageText(prev => prev + em); setShowEmojiPanel(false); }} 
                  style={{ background: 'none', border: 'none', fontSize: '1.4rem', cursor: 'pointer', padding: '5px' }}
                >
                  {em}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Input Bar */}
        <div className="chat-input-bar">
          <button onClick={() => document.getElementById('chatImgInput').click()} style={{ background: 'none', border: 'none', color: 'var(--neon-pink)', fontSize: '1.3rem', cursor: 'pointer' }}>
            <i className="fas fa-image"></i>
          </button>
          <input type="file" id="chatImgInput" accept="image/*" style={{ display: 'none' }} onChange={handleImageChange} />
          
          <button onClick={() => setShowEmojiPanel(!showEmojiPanel)} style={{ background: 'none', border: 'none', fontSize: '1.3rem', cursor: 'pointer' }}>
            😊
          </button>
          
          <textarea 
            id="chatInput" 
            placeholder="Whisper something..."
            value={newMessageText}
            onChange={(e) => { setNewMessageText(e.target.value); handleTypingInput(); }}
            onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(); } }}
            rows="1"
            style={{ flex: 1, background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255, 42, 109, 0.25)', borderRadius: '22px', padding: '10px 16px', color: 'white', fontSize: '0.9rem', outline: 'none', resize: 'none', maxHeight: '100px', overflowY: 'auto' }}
          />
          
          <button className="chat-send-btn" onClick={handleSendMessage}>
            <i className="fas fa-paper-plane"></i>
          </button>
        </div>
      </div>
    );
  }

  // RENDER INBOX CONVERSATIONS LIST
  return (
    <div style={{ padding: '0 15px 20px' }}>
      <h2 style={{ fontSize: '1.4rem', marginBottom: '15px', color: 'white' }}>
        Whispers <span style={{ color: 'var(--neon-pink)' }}>💬</span>
      </h2>
      
      {/* Search Input */}
      <div style={{ position: 'relative', marginBottom: '20px' }}>
        <i className="fas fa-search" style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)', fontSize: '0.9rem' }}></i>
        <input 
          type="text" 
          placeholder="Search whispers..." 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          style={{ width: '100%', padding: '11px 14px 11px 38px', borderRadius: '25px', border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem' }}
        />
      </div>
      
      {/* Inbox List */}
      <div id="conversationsList">
        {inboxLoading ? (
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
          </div>
        ) : conversations.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '60px 20px' }}>
            <div style={{ fontSize: '3rem', marginBottom: '15px' }}>💬</div>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem' }}>No whispers yet. Explore the temple and start a conversation!</p>
            <button onClick={() => router.push('/explore')} className="btn-primary" style={{ marginTop: '15px', width: 'auto', padding: '12px 24px' }}>Explore Desires</button>
          </div>
        ) : (
          conversations
            .filter(c => c.partner?.username?.toLowerCase().includes(searchQuery.toLowerCase().trim()))
            .map(c => {
              const pid = c.partner.id;
              const pname = c.partner.username;
              const pavatar = c.partner.avatar || `https://ui-avatars.com/api/?name=${pname}&background=8e1a1a&color=fff`;
              const isReceiver = c.lastMessage.receiver_id === user.id;
              const isUnread = isReceiver && !c.lastMessage.is_read;
              const lastText = isUnread ? '🔒 Locked Whisper' : ((c.lastMessage.sender_id === user.id ? 'You: ' : '') + c.lastMessage.text?.substring(0, 50));

              return (
                <div 
                  key={pid}
                  className="convo-card glass-panel"
                  onClick={() => router.push(`/messages?partner=${pid}`)}
                  style={{ 
                    marginBottom: '10px', 
                    display: 'flex', 
                    alignItems: 'center', 
                    gap: '14px', 
                    padding: '14px 16px', 
                    borderRadius: '16px', 
                    cursor: 'pointer', 
                    border: `1px solid ${isUnread ? 'var(--neon-pink)' : 'var(--glass-border)'}`, 
                    transition: 'all 0.2s' 
                  }}
                >
                  {/* Avatar */}
                  <div style={{ position: 'relative', flexShrink: 0 }}>
                    <img 
                      src={pavatar} 
                      style={{ width: '54px', height: '54px', borderRadius: '50%', objectFit: 'cover', border: `2px solid ${c.partner.is_online ? '#2ecc71' : (isUnread ? 'var(--neon-pink)' : 'var(--glass-border)')}` }} 
                      alt="" 
                    />
                    {c.partner.is_online && (
                      <div style={{ position: 'absolute', bottom: '1px', right: '1px', width: '12px', height: '12px', background: '#2ecc71', borderRadius: '50%', border: '2px solid var(--velvet-bg)', boxShadow: '0 0 5px #2ecc71' }}></div>
                    )}
                    {isUnread && (
                      <div style={{ position: 'absolute', top: '-2px', left: '-2px', width: '12px', height: '12px', background: 'var(--neon-pink)', borderRadius: '50%', border: '2px solid var(--velvet-bg)', boxShadow: '0 0 6px var(--neon-pink)' }}></div>
                    )}
                  </div>

                  {/* Conversation Info */}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                      <h3 style={{ margin: 0, fontSize: '1rem', color: isUnread ? 'var(--neon-pink)' : 'white', fontWeight: isUnread ? '700' : '600' }}>
                        {pname}
                      </h3>
                      {c.partner.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.75rem' }}></i>}
                    </div>
                    <p style={{ margin: '3px 0 0', fontSize: '0.82rem', color: isUnread ? 'var(--text-primary)' : 'var(--text-secondary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {lastText}
                    </p>
                  </div>

                  {/* Date & Badge */}
                  <div style={{ textalign: 'right', flexShrink: 0 }}>
                    <div style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
                      {new Date(c.lastMessage.timestamp).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: false })}
                    </div>
                    {isUnread && (
                      <div style={{ width: '8px', height: '8px', background: 'var(--neon-pink)', border_radius: '50%', margin: '4px auto 0', boxShadow: '0 0 5px var(--neon-pink)' }}></div>
                    )}
                  </div>
                </div>
              );
            })
        )}
      </div>

      <div style={{ marginTop: '20px' }}>
        <button onClick={() => router.push('/explore')} className="btn-primary" style={{ width: '100%' }}>
          <i className="fas fa-plus"></i> Start New Whisper
        </button>
      </div>
    </div>
  );
}

export default function MessagesPage() {
  return (
    <Suspense fallback={
      <div style={{ minHeight: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', color: 'var(--neon-pink)' }}>
        <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem' }}></i>
      </div>
    }>
      <MessagesContent />
    </Suspense>
  );
}
