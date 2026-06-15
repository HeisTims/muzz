// EazyMUZE v4.0 - Data Cache Layer (Stale-While-Revalidate Performance Edition)
window.dataManager = {
    cache: {
        posts: [],
        stories: [],
        messages: [],
        orders: [],
        users: []
    },
    
    loadAll: async () => {
        // Step 1: Immediately load from LocalStorage cache for absolute instant load speed
        const cached = localStorage.getItem('eazymuze_cache');
        if (cached) {
            window.dataManager.cache = JSON.parse(cached);
            console.log("Instant Cache Render Active");
        }

        // Failsafe Mock Feed to ensure posts are ALWAYS showing instantly
        if (!window.dataManager.cache.posts || window.dataManager.cache.posts.length === 0) {
            window.dataManager.cache.posts = [
                {
                    id: 9001,
                    user_id: 2,
                    username: 'Sensual_Sandra',
                    avatar: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80',
                    is_verified: true,
                    preference: 'straight',
                    location_data: 'Lekki Phase 1',
                    caption: 'Loving the evening vibe tonight... who is up for a chill conversation? 💋🥂',
                    music: 'wizkid',
                    likes: [1, 2],
                    comments: [],
                    image_fallback: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80'
                },
                {
                    id: 9002,
                    user_id: 3,
                    username: 'SugarMummy_Rita',
                    avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=600&q=80',
                    is_verified: true,
                    preference: 'sugar_mummy',
                    location_data: 'Victoria Island',
                    caption: 'Living life without rules. DM is open to all open-minded partners in the area. 😈🔥',
                    music: 'burna',
                    likes: [3],
                    comments: [],
                    image_fallback: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80'
                },
                {
                    id: 9003,
                    user_id: 4,
                    username: 'Wild_West',
                    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=600&q=80',
                    is_verified: true,
                    preference: 'bisexual',
                    location_data: 'Yaba District',
                    caption: 'Tonight feels wild. Looking for a charming companion to share penthouse vibes. 🍷✨',
                    music: 'davido',
                    likes: [4, 5],
                    comments: [],
                    image_fallback: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80'
                }
            ];
        }

        // Failsafe Mock Stories to ensure statuses are ALWAYS showing instantly
        if (!window.dataManager.cache.stories || window.dataManager.cache.stories.length === 0) {
            window.dataManager.cache.stories = [
                {
                    id: 9101,
                    username: 'Sensual_Sandra',
                    image: 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=600&q=80',
                    caption: 'Getting ready for tonight! 💅'
                },
                {
                    id: 9102,
                    username: 'SugarMummy_Rita',
                    image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=80',
                    caption: 'Poolside lounging 💦'
                },
                {
                    id: 9103,
                    username: 'Wild_West',
                    image: 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=600&q=80',
                    caption: 'Cocktails are served 🍸'
                }
            ];
        }

        // Render immediately so feed is never blank
        if (window.feed && typeof window.feed.render === 'function') {
            window.feed.render();
        }

        // Revalidate all quietly in parallel
        await Promise.allSettled([
            window.dataManager.loadFeed(),
            window.dataManager.loadMessages()
        ]);
    },

    loadFeed: async () => {
        const fetchPromise = Promise.all([
            window.api.get('feed.php?action=posts'),
            window.api.get('feed.php?action=stories')
        ]);
        
        const timeoutPromise = new Promise((_, reject) => setTimeout(() => reject(new Error('Feed Revalidation Timeout')), 4000));
        
        try {
            const [posts, stories] = await Promise.race([fetchPromise, timeoutPromise]);
            let updated = false;
            
            if (posts && posts.status === 'success' && posts.data && posts.data.length > 0) {
                window.dataManager.cache.posts = posts.data;
                updated = true;
            }
            if (stories && stories.status === 'success' && stories.data && stories.data.length > 0) {
                window.dataManager.cache.stories = stories.data;
                updated = true;
            }
            
            if (updated) {
                localStorage.setItem('eazymuze_cache', JSON.stringify(window.dataManager.cache));
                
                // Silent refresh of active feed
                const activeSection = document.querySelector('.view-section.active');
                if (activeSection && activeSection.id === 'view-feed' && window.feed) {
                    window.feed.render();
                }
            }
        } catch (e) {
            console.warn('Feed revalidation deferred:', e.message);
        }
    },

    loadMessages: async () => {
        try {
            const res = await window.api.get('messages.php?action=inbox');
            if (res && res.status === 'success') {
                window.dataManager.cache.messages = res.data;
                localStorage.setItem('eazymuze_cache', JSON.stringify(window.dataManager.cache));
                
                // Silent refresh of active messages view
                const activeSection = document.querySelector('.view-section.active');
                if (activeSection && activeSection.id === 'view-messages' && window.messages) {
                    window.messages.render();
                }
            }
        } catch (e) {
            console.warn('Messages sync deferred:', e.message);
        }
    }
};
