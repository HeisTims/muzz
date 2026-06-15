// EazyMUZE v2.5 - Feed Module
window.feed = {
    render: () => {
        window.feed.renderStories();
        window.feed.renderPosts();
    },
    
    renderStories: () => {
        const tray = document.getElementById('storiesTray');
        const stories = window.dataManager.cache.stories || [];
        const currentUser = window.globals.currentUser;
        
        let html = `
            <div class="story-wrapper" onclick="window.feed.addStory('story')">
                <div class="story-circle active">
                    <img src="${currentUser ? currentUser.avatar : 'https://via.placeholder.com/75'}" alt="Add Story">
                    <div class="story-add-icon"><i class="fas fa-plus"></i></div>
                </div>
                <span class="story-name">add story</span>
            </div>
        `;
        
        stories.forEach((s, i) => {
            html += `
                <div class="story-wrapper" onclick="window.feed.openStory(${i})">
                    <div class="story-circle">
                        <img src="${s.image}" alt="Story">
                    </div>
                    <span class="story-name">${s.username.substring(0, 10)}</span>
                </div>
            `;
        });
        
        if (tray) tray.innerHTML = html;
    },
    
    renderPosts: () => {
        const container = document.getElementById('feedContainer');
        const posts = window.dataManager.cache.posts || [];
        
        let html = '';
        posts.forEach(p => {
            const hasMusic = p.music ? '<i class="fas fa-music" style="color:var(--neon-pink); font-size: 0.8rem;"></i>' : '';
            const imgHtml = p.images && p.images.length > 0 
                ? p.images.map(img => `<img src="${img}">`).join('')
                : `<img src="${p.image_fallback}">`;

            const isLiked = p.likes?.includes(window.globals.currentUser?.id);

            html += `
                <div class="post-card glass-panel" data-music="${p.music || ''}">
                    <div class="post-header">
                        <img src="${p.avatar || 'https://via.placeholder.com/45'}" alt="Avatar">
                        <div class="post-user-info">
                            <div class="post-username">
                                ${p.username} 
                                ${p.is_verified ? '<i class="fas fa-check-circle" style="color:var(--neon-pink); font-size: 0.7rem;"></i>' : ''}
                                <span class="pref-badge">${p.preference || 'muze'}</span>
                            </div>
                            <div class="post-location">
                                <i class="fas fa-map-marker-alt"></i> ${p.location_data || 'Unknown'}
                                ${hasMusic}
                            </div>
                        </div>
                    </div>
                    
                    <div class="post-media" style="display:flex; overflow-x:auto; scroll-snap-type: x mandatory;">
                        ${imgHtml}
                    </div>
                    
                    <div class="post-footer">
                        <div class="post-caption">
                            <strong>${p.username}</strong> ${p.caption}
                        </div>
                        
                        <div class="post-actions-bar">
                            <button class="action-btn" onclick="window.feed.likePost('${p.id}')">
                                <i class="${isLiked ? 'fas' : 'far'} fa-heart" style="color:${isLiked ? 'var(--neon-pink)' : 'inherit'}"></i> 
                                ${p.likes?.length || 0}
                            </button>
                            <button class="action-btn" onclick="window.feed.toggleComments('${p.id}')">
                                <i class="fas fa-comment"></i> ${p.comments ? p.comments.length : 0}
                            </button>
                            <button class="action-btn" onclick="window.messages.startWhisper(${p.user_id}, '${p.username}')">
                                <i class="fas fa-comment-medical" style="color: #2ecc71;"></i> whisper
                            </button>
                            <button class="action-btn" onclick="window.utils.showToast('Adored! ✨')">
                                <i class="fas fa-star" style="color: #f1c40f;"></i> adore
                            </button>
                        </div>
                        
                        <div id="comments_${p.id}" style="display:${window.feed.openComments && window.feed.openComments[p.id] ? 'block' : 'none'}; margin-top:15px; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;">
                            <div style="max-height:100px; overflow-y:auto; margin-bottom:10px; font-size:0.8rem; color:grey;">
                                ${(p.comments || []).map(c => `<div><strong style="color:var(--text-secondary);">${c.username}:</strong> ${c.text}</div>`).join('')}
                            </div>
                            <div style="display:flex; gap:10px;">
                                <input type="text" id="commentInput_${p.id}" placeholder="Whisper a comment..." style="padding:8px; font-size:0.8rem; border-radius:20px;">
                                <button class="btn-primary" style="padding:8px 15px;" onclick="window.feed.addComment('${p.id}')">Post</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        if (container) {
            container.innerHTML = html || '<p style="text-align:center; color: grey;">The temple is quiet...</p>';
            window.feed.initMusicObserver();
        }
    },
    
    initMusicObserver: () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const musicId = entry.target.getAttribute('data-music');
                if (!musicId) return;
                
                let audio = window.globals.audioContexts[musicId];
                if (!audio && window.globals.songUrls[musicId]) {
                    audio = new Audio(window.globals.songUrls[musicId]);
                    audio.loop = true;
                    window.globals.audioContexts[musicId] = audio;
                }
                
                if (audio) {
                    if (entry.isIntersecting && entry.intersectionRatio > 0.6) {
                        audio.play().catch(e => console.log('Audio autoplay blocked'));
                    } else {
                        audio.pause();
                    }
                }
            });
        }, { threshold: [0.6] });

        document.querySelectorAll('.post-card').forEach(card => {
            observer.observe(card);
        });
    },
    
    likePost: async (id) => {
        const post = window.dataManager.cache.posts.find(p => String(p.id) === String(id));
        const currentUserId = window.globals.currentUser?.id;
        
        if (post && currentUserId) {
            if (!post.likes) post.likes = [];
            
            const index = post.likes.indexOf(currentUserId);
            const isLiking = index === -1;
            
            if (isLiking) {
                post.likes.push(currentUserId);
            } else {
                post.likes.splice(index, 1);
            }
            
            // Render optimistically
            window.feed.renderPosts();
            window.utils.showToast(isLiking ? 'Adored 💋' : 'Desire removed');
            
            // DB Sync
            try {
                const res = await window.api.post('feed.php?action=like_post', { post_id: id });
                if (res.status === 'success') {
                    // Update Cache
                    localStorage.setItem('eazymuze_cache', JSON.stringify(window.dataManager.cache));
                    await window.dataManager.loadAll();
                } else {
                    // Rollback on error
                    if (isLiking) {
                        post.likes = post.likes.filter(uid => uid !== currentUserId);
                    } else {
                        post.likes.push(currentUserId);
                    }
                    window.feed.renderPosts();
                }
            } catch (err) {
                // Rollback on network failure
                if (isLiking) {
                    post.likes = post.likes.filter(uid => uid !== currentUserId);
                } else {
                    post.likes.push(currentUserId);
                }
                window.feed.renderPosts();
            }
        }
    },
    
    addStory: (defaultType = 'moment') => {
        document.getElementById('createPostModal').style.display = 'flex';
        window.feed.setPostType(defaultType);
    },
    
    setPostType: (type) => {
        window.feed.activePostType = type;
        const toggleMoment = document.getElementById('togglePostTypeMoment');
        const toggleStory = document.getElementById('togglePostTypeStory');
        const momentFields = document.getElementById('momentOnlyFields');
        const modalTitle = document.getElementById('postModalTitle');
        
        if (type === 'story') {
            toggleMoment.style.background = '#222';
            toggleStory.style.background = 'var(--neon-pink)';
            if (momentFields) momentFields.style.display = 'none';
            if (modalTitle) modalTitle.innerText = 'Add a Story 💋';
        } else {
            toggleMoment.style.background = 'var(--neon-pink)';
            toggleStory.style.background = '#222';
            if (momentFields) momentFields.style.display = 'block';
            if (modalTitle) modalTitle.innerText = 'Share a Moment 💋';
        }
    },
    
    closePostModal: () => {
        document.getElementById('createPostModal').style.display = 'none';
        document.getElementById('postCaption').value = '';
        document.getElementById('postImagesPreview').innerHTML = '';
        window.feed.postImagesBase64 = [];
    },
    
    handlePostImages: (input) => {
        const files = Array.from(input.files).slice(0, 3);
        const container = document.getElementById('postImagesPreview');
        container.innerHTML = '';
        window.feed.postImagesBase64 = [];
        
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                window.feed.postImagesBase64.push(e.target.result);
                container.innerHTML += `<img src="${e.target.result}" style="width:60px; height:60px; object-fit:cover; border-radius:5px; border:1px solid var(--neon-pink);">`;
            };
            reader.readAsDataURL(file);
        });
    },

    submitDynamicPost: async () => {
        const type = window.feed.activePostType || 'moment';
        const caption = document.getElementById('postCaption').value;
        const images = window.feed.postImagesBase64 || [];
        
        if (type === 'story') {
            if (images.length === 0) return window.utils.showToast('Please upload an image for your story.', 'error');
            const res = await window.api.post('feed.php?action=create_story', {
                image: images[0],
                media_type: 'image',
                caption: caption
            });
            if (res.status === 'success') {
                window.utils.showToast('Story posted successfully! 💋');
                window.feed.closePostModal();
                await window.dataManager.loadAll();
                window.feed.render();
            } else {
                window.utils.showToast(res.message || 'Failed to post story', 'error');
            }
        } else {
            // Moment
            const music = document.getElementById('postMusic').value;
            if(!caption && images.length === 0) return window.utils.showToast('Say something or upload a slide.', 'error');
            const res = await window.api.post('feed.php?action=create_post', {
                caption,
                music,
                images,
                image_fallback: images[0] || 'https://via.placeholder.com/400',
                location_data: window.globals.currentUser?.location || 'Unknown'
            });
            if (res.status === 'success') {
                window.utils.showToast('Moment posted 💋');
                window.feed.closePostModal();
                await window.dataManager.loadAll();
                window.feed.render();
            } else {
                window.utils.showToast(res.message || 'Failed to post moment', 'error');
            }
        }
    },

    addComment: async (postId) => {
        const text = document.getElementById(`commentInput_${postId}`).value.trim();
        if(!text) return;
        
        const post = window.dataManager.cache.posts.find(p => String(p.id) === String(postId));
        const currentUsername = window.globals.currentUser?.username || 'You';
        
        const newComment = {
            user_id: window.globals.currentUser?.id,
            username: currentUsername,
            text: text,
            timestamp: new Date().toISOString()
        };
        
        if (post) {
            if (!post.comments) post.comments = [];
            
            // Optimistic update
            post.comments.push(newComment);
            
            // Clear input box immediately
            document.getElementById(`commentInput_${postId}`).value = '';
            
            // Keep comment section open on render
            if (!window.feed.openComments) window.feed.openComments = {};
            window.feed.openComments[postId] = true;
            
            // Render immediately
            window.feed.renderPosts();
            window.utils.showToast('Comment whispered 💬');
            
            // Quietly request background database sync
            window.api.post('feed.php?action=comment_post', { post_id: postId, text }).then(res => {
                if (res.status === 'success') {
                    // Update cache in LocalStorage quietly
                    localStorage.setItem('eazymuze_cache', JSON.stringify(window.dataManager.cache));
                } else {
                    // Rollback on error
                    post.comments = post.comments.filter(c => c !== newComment);
                    window.feed.renderPosts();
                    window.utils.showToast('Failed to post comment', 'error');
                }
            }).catch(() => {
                // Rollback on network failure
                post.comments = post.comments.filter(c => c !== newComment);
                window.feed.renderPosts();
            });
        }
    },
    
    toggleComments: (postId) => {
        const el = document.getElementById(`comments_${postId}`);
        if (!el) return;
        if (!window.feed.openComments) window.feed.openComments = {};
        
        if (el.style.display === 'none' || !el.style.display) {
            el.style.display = 'block';
            window.feed.openComments[postId] = true;
        } else {
            el.style.display = 'none';
            window.feed.openComments[postId] = false;
        }
    },
    
    // Swipeable Story Logic
    openStory: (index) => {
        const stories = window.dataManager.cache.stories || [];
        if(!stories[index]) return;
        window.feed.activeStoryIndex = index;
        
        const s = stories[index];
        window.feed.storyViewerUserId = s.user_id;
        document.getElementById('storyViewer').style.display = 'block';
        document.getElementById('storyAvatar').src = s.avatar || 'https://via.placeholder.com/40';
        document.getElementById('storyUsername').innerText = s.username;
        document.getElementById('storyImage').src = s.image;
        
        const pCont = document.getElementById('storyProgress');
        pCont.innerHTML = stories.map((_, i) => `<div style="flex:1; height:3px; background:${i===index?'white':'rgba(255,255,255,0.3)'}; border-radius:3px;"></div>`).join('');
        
        const touchArea = document.getElementById('storyTouchArea');
        let startX = 0;
        touchArea.ontouchstart = e => startX = e.touches[0].clientX;
        touchArea.ontouchend = e => {
            const endX = e.changedTouches[0].clientX;
            if (startX - endX > 50) window.feed.nextStory();
            else if (endX - startX > 50) window.feed.prevStory();
            else window.feed.nextStory(); // simple tap
        };
    },

    nextStory: () => {
        const stories = window.dataManager.cache.stories || [];
        if (window.feed.activeStoryIndex + 1 < stories.length) {
            window.feed.openStory(window.feed.activeStoryIndex + 1);
        } else {
            window.feed.closeStory();
        }
    },

    prevStory: () => {
        if (window.feed.activeStoryIndex - 1 >= 0) {
            window.feed.openStory(window.feed.activeStoryIndex - 1);
        }
    },

    closeStory: () => {
        document.getElementById('storyViewer').style.display = 'none';
    },

    viewStoryUserProfile: () => {
        if (window.feed.storyViewerUserId) {
            window.location.href = 'profile.php?user_id=' + window.feed.storyViewerUserId;
        }
    }
};
