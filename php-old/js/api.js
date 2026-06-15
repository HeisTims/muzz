// EazyMUZE v2.5 - API Wrapper
window.api = {
    baseUrl: 'api/',
    
    get: async (endpoint) => {
        try {
            const res = await fetch(window.api.baseUrl + endpoint);
            return await res.json();
        } catch (e) {
            console.error('API GET Error:', e);
            return { status: 'error', message: 'Network error' };
        }
    },
    
    post: async (endpoint, body) => {
        try {
            const res = await fetch(window.api.baseUrl + endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            return await res.json();
        } catch (e) {
            console.error('API POST Error:', e);
            return { status: 'error', message: 'Network error' };
        }
    }
};
