'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function AdminPage() {
  const { user } = useApp();
  const supabase = createClient();
  const router = useRouter();

  // Authentication Pin Gate
  const [pin, setPin] = useState('');
  const [isAuthorized, setIsAuthorized] = useState(false);
  const [authError, setAuthError] = useState('');

  // Dashboard Data
  const [stats, setStats] = useState({
    totalUsers: 0,
    verifiedUsers: 0,
    totalPosts: 0,
    totalRevenue: 0,
    totalOrders: 0,
    totalAds: 0
  });

  // Data Lists
  const [usersList, setUsersList] = useState([]);
  const [adsList, setAdsList] = useState([]);
  const [ordersList, setOrdersList] = useState([]);
  
  const [activeTab, setActiveTab] = useState('verifications'); // 'verifications', 'ads', 'orders'
  const [loading, setLoading] = useState(false);

  // New Ad Form State
  const [adCaption, setAdCaption] = useState('');
  const [adLink, setAdLink] = useState('');
  const [adImage, setAdImage] = useState('');
  const [creatingAd, setCreatingAd] = useState(false);

  // Load state from local storage on mount
  useEffect(() => {
    const savedPin = localStorage.getItem('emz_admin_pin');
    if (savedPin === 'admin123') {
      setIsAuthorized(true);
    }
  }, []);

  // Handle PIN check
  const handleAuthorize = (e) => {
    e.preventDefault();
    if (pin === 'admin123') {
      localStorage.setItem('emz_admin_pin', pin);
      setIsAuthorized(true);
      setAuthError('');
    } else {
      setAuthError('Invalid Admin PIN. Access Denied. 💋');
    }
  };

  // Fetch Dashboard Stats & Data Lists
  const fetchAdminData = async () => {
    if (!isAuthorized) return;
    try {
      setLoading(true);

      // Fetch profiles
      const { data: profiles, error: profErr } = await supabase
        .from('profiles')
        .select('*')
        .order('created_at', { ascending: false });

      // Fetch posts count
      const { count: postsCount } = await supabase
        .from('posts')
        .select('*', { count: 'exact', head: true });

      // Fetch black market orders
      const { data: orders, error: ordErr } = await supabase
        .from('black_market_orders')
        .select('*')
        .order('created_at', { ascending: false });

      // Fetch ads
      const { data: ads, error: adsErr } = await supabase
        .from('ads')
        .select('*')
        .order('created_at', { ascending: false });

      // Fetch total revenue (sum of payments)
      const { data: payments } = await supabase
        .from('payments')
        .select('amount');

      const totalRevenue = payments?.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0) || 0;

      if (!profErr && profiles) {
        setUsersList(profiles);
        const verifiedCount = profiles.filter(p => p.is_verified).length;
        
        setStats({
          totalUsers: profiles.length,
          verifiedUsers: verifiedCount,
          totalPosts: postsCount || 0,
          totalRevenue: totalRevenue,
          totalOrders: orders?.length || 0,
          totalAds: ads?.length || 0
        });
      }

      if (!ordErr && orders) {
        setOrdersList(orders);
      }

      if (!adsErr && ads) {
        setAdsList(ads);
      }

    } catch (e) {
      console.error('Error fetching admin data:', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthorized) {
      fetchAdminData();
    }
  }, [isAuthorized]);

  // Toggle Verification status of a user
  const handleToggleVerification = async (userId, currentStatus) => {
    try {
      const { error } = await supabase
        .from('profiles')
        .update({ is_verified: !currentStatus })
        .eq('id', userId);

      if (error) throw error;

      // Update local state
      setUsersList(prev => prev.map(u => u.id === userId ? { ...u, is_verified: !currentStatus } : u));
      
      // Update stats
      setStats(prev => ({
        ...prev,
        verifiedUsers: prev.verifiedUsers + (currentStatus ? -1 : 1)
      }));

      // Insert system notification for user
      await supabase.from('notifications').insert({
        user_id: userId,
        type: 'system',
        message: currentStatus 
          ? 'Your profile verification status has been revoked.' 
          : 'Your profile has been officially VERIFIED by the Admin! 💋'
      });

      alert(`User verification status updated!`);
    } catch (err) {
      alert(err.message || 'Error toggling verification');
    }
  };

  // Image Upload helper for Ad creation (base64)
  const handleAdImageChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (uploadEvent) => {
      setAdImage(uploadEvent.target.result);
    };
    reader.readAsDataURL(file);
  };

  // Publish sponsored advertisement
  const handleCreateAd = async (e) => {
    e.preventDefault();
    if (!adCaption || !adImage) {
      alert('Caption and image are required');
      return;
    }

    setCreatingAd(true);
    try {
      const { error } = await supabase.from('ads').insert({
        caption: adCaption,
        link: adLink || '#',
        image: adImage,
        is_active: true
      });

      if (error) throw error;

      alert('Sponsored Ad published! 📢');
      setAdCaption('');
      setAdLink('');
      setAdImage('');
      fetchAdminData();
    } catch (err) {
      alert(err.message || 'Error creating Ad');
    } finally {
      setCreatingAd(false);
    }
  };

  // Toggle active status of Ad
  const handleToggleAdStatus = async (adId, currentStatus) => {
    try {
      const { error } = await supabase
        .from('ads')
        .update({ is_active: !currentStatus })
        .eq('id', adId);

      if (error) throw error;

      setAdsList(prev => prev.map(a => a.id === adId ? { ...a, is_active: !currentStatus } : a));
      alert('Ad status toggled!');
    } catch (err) {
      alert(err.message || 'Error toggling ad status');
    }
  };

  // Delete advertisement
  const handleDeleteAd = async (adId) => {
    if (confirm('Delete this ad permanently?')) {
      try {
        const { error } = await supabase
          .from('ads')
          .delete()
          .eq('id', adId);

        if (error) throw error;

        setAdsList(prev => prev.filter(a => a.id !== adId));
        alert('Ad deleted successfully.');
      } catch (err) {
        alert(err.message || 'Error deleting ad');
      }
    }
  };

  // Approve Black Market Order
  const handleApproveOrder = async (order) => {
    try {
      const { error } = await supabase
        .from('black_market_orders')
        .update({
          status: 'Approved',
          tracking_step: 2,
          seller: 'Escrow-Verified Courier',
          escrow_status: 'funded'
        })
        .eq('id', order.id);

      if (error) throw error;

      // Update state
      setOrdersList(prev => prev.map(o => o.id === order.id ? { ...o, status: 'Approved', tracking_step: 2, seller: 'Escrow-Verified Courier', escrow_status: 'funded' } : o));

      // Send notifications to buyer
      await supabase.from('notifications').insert({
        user_id: order.user_id,
        type: 'order_approved',
        message: 'Your Black Market request has been APPROVED by the Admin! Check order tracking.'
      });

      alert('Order approved! Buyer has been notified and courier assigned. 📦');
    } catch (err) {
      alert(err.message || 'Error approving order');
    }
  };

  // Delete Order
  const handleDeleteOrder = async (orderId) => {
    if (confirm('Delete this order from history?')) {
      try {
        const { error } = await supabase
          .from('black_market_orders')
          .delete()
          .eq('id', orderId);

        if (error) throw error;

        setOrdersList(prev => prev.filter(o => o.id !== orderId));
        alert('Order deleted.');
      } catch (err) {
        alert(err.message || 'Error deleting order');
      }
    }
  };

  // Logout Admin Portal
  const handleAdminLogout = () => {
    localStorage.removeItem('emz_admin_pin');
    setIsAuthorized(false);
  };

  // Render PIN Gate if unauthorized
  if (!isAuthorized) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '65vh', padding: '0 20px' }}>
        <div className="glass-panel" style={{ background: 'linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '400px', width: '100%', padding: '30px' }}>
          <div style={{ textAlign: 'center', fontSize: '3rem', marginBottom: '10px' }}>🕯️</div>
          <h2 style={{ color: 'white', textTransform: 'uppercase', fontSize: '1.2rem', letterSpacing: '1px', textAlign: 'center', marginBottom: '8px' }}>Temple Control Room</h2>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.8rem', textAlign: 'center', marginBottom: '20px' }}>Enter the secret Admin PIN to manage verifications and transactions.</p>
          
          <form onSubmit={handleAuthorize}>
            <input 
              type="password" 
              placeholder="Enter PIN"
              value={pin}
              onChange={(e) => setPin(e.target.value)}
              style={{
                width: '100%',
                padding: '14px',
                borderRadius: '12px',
                border: '1px solid rgba(255, 42, 109, 0.3)',
                background: 'rgba(255,255,255,0.05)',
                color: 'white',
                fontSize: '1.2rem',
                textAlign: 'center',
                letterSpacing: '5px',
                marginBottom: '15px',
                outline: 'none',
                boxSizing: 'border-box'
              }}
            />
            {authError && (
              <p style={{ color: 'var(--neon-pink)', fontSize: '0.8rem', textAlign: 'center', marginBottom: '15px' }}>{authError}</p>
            )}
            <button type="submit" className="btn-primary">Unlock Portal</button>
          </form>
        </div>
      </div>
    );
  }

  return (
    <div style={{ padding: '0 15px 30px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h2 style={{ fontSize: '1.3rem', color: 'white', display: 'flex', alignItems: 'center', gap: '8px' }}>
          Temple Control 🕯️
        </h2>
        <button 
          onClick={handleAdminLogout} 
          style={{ 
            background: 'rgba(255, 42, 109, 0.1)', 
            border: '1px solid rgba(255, 42, 109, 0.3)', 
            color: 'var(--neon-pink)', 
            padding: '6px 12px', 
            borderRadius: '20px', 
            fontSize: '0.85rem', 
            cursor: 'pointer',
            fontWeight: '600'
          }}
        >
          Lock Control
        </button>
      </div>

      {/* Dashboard Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', marginBottom: '20px' }}>
        <div className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
          <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-secondary)', textTransform: 'uppercase' }}>Total / Verified Users</p>
          <h3 style={{ margin: '4px 0 0', fontSize: '1.25rem', color: 'white' }}>{stats.totalUsers} <span style={{ fontSize: '0.8rem', color: 'var(--neon-pink)' }}>/ {stats.verifiedUsers}</span></h3>
        </div>
        <div className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
          <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-secondary)', textTransform: 'uppercase' }}>Total Revenue</p>
          <h3 style={{ margin: '4px 0 0', fontSize: '1.25rem', color: '#2ecc71' }}>₦{stats.totalRevenue.toLocaleString()}</h3>
        </div>
        <div className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
          <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-secondary)', textTransform: 'uppercase' }}>Moments Shared</p>
          <h3 style={{ margin: '4px 0 0', fontSize: '1.25rem', color: 'white' }}>{stats.totalPosts}</h3>
        </div>
        <div className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
          <p style={{ margin: 0, fontSize: '0.7rem', color: 'var(--text-secondary)', textTransform: 'uppercase' }}>Black Market Orders</p>
          <h3 style={{ margin: '4px 0 0', fontSize: '1.25rem', color: '#f1c40f' }}>{stats.totalOrders}</h3>
        </div>
      </div>

      {/* Admin Tabs */}
      <div className="profile-tabs" style={{ display: 'flex', borderBottom: '1px solid rgba(255,255,255,0.08)', marginBottom: '15px' }}>
        <div 
          className={`profile-tab ${activeTab === 'verifications' ? 'active' : ''}`}
          onClick={() => setActiveTab('verifications')}
          style={{ flex: 1, padding: '12px 4px', fontSize: '0.8rem', color: activeTab === 'verifications' ? 'var(--neon-pink)' : 'var(--text-secondary)', cursor: 'pointer', textAlign: 'center', borderBottom: activeTab === 'verifications' ? '2px solid var(--neon-pink)' : '2px solid transparent', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '3px' }}
        >
          <i className="fas fa-check-double"></i>
          <span>KYC Verifications</span>
        </div>
        <div 
          className={`profile-tab ${activeTab === 'ads' ? 'active' : ''}`}
          onClick={() => setActiveTab('ads')}
          style={{ flex: 1, padding: '12px 4px', fontSize: '0.8rem', color: activeTab === 'ads' ? 'var(--neon-pink)' : 'var(--text-secondary)', cursor: 'pointer', textAlign: 'center', borderBottom: activeTab === 'ads' ? '2px solid var(--neon-pink)' : '2px solid transparent', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '3px' }}
        >
          <i className="fas fa-bullhorn"></i>
          <span>Sponsored Ads</span>
        </div>
        <div 
          className={`profile-tab ${activeTab === 'orders' ? 'active' : ''}`}
          onClick={() => setActiveTab('orders')}
          style={{ flex: 1, padding: '12px 4px', fontSize: '0.8rem', color: activeTab === 'orders' ? 'var(--neon-pink)' : 'var(--text-secondary)', cursor: 'pointer', textAlign: 'center', borderBottom: activeTab === 'orders' ? '2px solid var(--neon-pink)' : '2px solid transparent', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '3px' }}
        >
          <i className="fas fa-shopping-cart"></i>
          <span>Escrow Orders</span>
        </div>
      </div>

      {loading && (
        <div style={{ textAlign: 'center', padding: '20px' }}>
          <i className="fas fa-spinner fa-spin" style={{ color: 'var(--neon-pink)' }}></i>
        </div>
      )}

      {/* Verifications panel */}
      {!loading && activeTab === 'verifications' && (
        <div>
          {usersList.length === 0 ? (
            <p style={{ color: 'grey', textAlign: 'center', padding: '20px' }}>No users registered.</p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              {usersList.map((usr) => (
                <div key={usr.id} className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <img 
                      src={usr.avatar || `https://ui-avatars.com/api/?name=${usr.username}&background=8e1a1a&color=fff`} 
                      style={{ width: '40px', height: '40px', borderRadius: '50%', objectFit: 'cover', border: '1px solid var(--glass-border)' }} 
                      alt=""
                    />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontWeight: '700', color: 'white', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.92rem' }}>
                        {usr.fullname || usr.username}
                        {usr.is_verified && <i className="fas fa-check-circle" style={{ color: 'var(--neon-pink)', fontSize: '0.8rem' }}></i>}
                      </div>
                      <p style={{ margin: '2px 0 0', fontSize: '0.76rem', color: 'var(--text-secondary)' }}>@{usr.username} &bull; {usr.location || 'Unknown'}</p>
                    </div>
                    <div>
                      <button 
                        onClick={() => handleToggleVerification(usr.id, usr.is_verified)}
                        className="btn-secondary"
                        style={{
                          fontSize: '0.78rem',
                          background: usr.is_verified ? 'rgba(231, 76, 60, 0.15)' : 'rgba(46, 204, 113, 0.15)',
                          color: usr.is_verified ? '#e74c3c' : '#2ecc71',
                          borderColor: usr.is_verified ? 'rgba(231, 76, 60, 0.3)' : 'rgba(46, 204, 113, 0.3)'
                        }}
                      >
                        {usr.is_verified ? 'Revoke' : 'Verify'}
                      </button>
                    </div>
                  </div>

                  {/* KYC attachments preview if they exist */}
                  {(usr.kyc_id || usr.kyc_selfie) && (
                    <div style={{ marginTop: '12px', borderTop: '1px solid rgba(255,255,255,0.06)', paddingTop: '10px' }}>
                      <p style={{ fontSize: '0.75rem', color: 'white', fontWeight: '600', marginBottom: '6px' }}>KYC Submissions:</p>
                      <div style={{ display: 'flex', gap: '8px' }}>
                        {usr.kyc_id && (
                          <div style={{ flex: 1 }}>
                            <p style={{ fontSize: '0.62rem', color: 'var(--text-muted)' }}>ID Document</p>
                            <img src={usr.kyc_id} style={{ width: '100%', height: '80px', objectFit: 'cover', borderRadius: '8px', border: '1px solid var(--glass-border)' }} alt="ID" />
                          </div>
                        )}
                        {usr.kyc_selfie && (
                          <div style={{ flex: 1 }}>
                            <p style={{ fontSize: '0.62rem', color: 'var(--text-muted)' }}>KYC Selfie</p>
                            <img src={usr.kyc_selfie} style={{ width: '100%', height: '80px', objectFit: 'cover', borderRadius: '8px', border: '1px solid var(--glass-border)' }} alt="Selfie" />
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Ads panel */}
      {!loading && activeTab === 'ads' && (
        <div>
          {/* Create Ad Form */}
          <div className="glass-panel" style={{ padding: '20px', borderRadius: '16px', marginBottom: '20px' }}>
            <h3 style={{ margin: '0 0 14px', fontSize: '0.95rem', color: 'var(--neon-pink)' }}>Publish Sponsored Ad</h3>
            <form onSubmit={handleCreateAd}>
              <div style={{ marginBottom: '12px' }}>
                <label style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '4px' }}>Caption / Title</label>
                <input 
                  type="text" 
                  value={adCaption} 
                  onChange={(e) => setAdCaption(e.target.value)}
                  placeholder="E.g. VIP Club Pool Party this Friday!"
                  required
                />
              </div>

              <div style={{ marginBottom: '12px' }}>
                <label style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '4px' }}>Target Link (URL)</label>
                <input 
                  type="text" 
                  value={adLink} 
                  onChange={(e) => setAdLink(e.target.value)}
                  placeholder="https://t.me/vipgroup"
                />
              </div>

              <div style={{ marginBottom: '16px' }}>
                <label style={{ fontSize: '0.78rem', color: 'var(--text-secondary)', display: 'block', marginBottom: '4px' }}>Banner Image</label>
                <div 
                  onClick={() => document.getElementById('adImageInput').click()}
                  style={{ border: '2px dashed rgba(255,42,109,0.3)', padding: '15px', textAlign: 'center', borderRadius: '10px', cursor: 'pointer' }}
                >
                  <i className="fas fa-images" style={{ fontSize: '1.5rem', color: 'var(--neon-pink)' }}></i>
                  <p style={{ fontSize: '0.75rem', marginTop: '6px', color: 'var(--text-secondary)' }}>Click to upload Banner (base64)</p>
                  <input type="file" id="adImageInput" accept="image/*" style={{ display: 'none' }} onChange={handleAdImageChange} />
                </div>
                {adImage && (
                  <img src={adImage} style={{ width: '100%', height: '90px', objectFit: 'cover', borderRadius: '8px', marginTop: '10px', border: '1px solid var(--neon-pink)' }} alt="Ad Preview" />
                )}
              </div>

              <button type="submit" className="btn-primary" disabled={creatingAd}>
                {creatingAd ? 'Publishing...' : 'Publish Ad Banner 📢'}
              </button>
            </form>
          </div>

          {/* Active ads list */}
          <div>
            <h3 style={{ margin: '0 0 12px', fontSize: '0.95rem', color: 'white' }}>Current Banner Ads</h3>
            {adsList.length === 0 ? (
              <p style={{ color: 'grey', fontSize: '0.8rem', textAlign: 'center' }}>No active sponsored banners.</p>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                {adsList.map((ad) => (
                  <div key={ad.id} className="glass-panel" style={{ padding: '15px', borderRadius: '16px', display: 'flex', gap: '12px' }}>
                    <img src={ad.image} style={{ width: '80px', height: '60px', objectFit: 'cover', borderRadius: '8px', border: '1px solid var(--glass-border)' }} alt="" />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <p style={{ margin: 0, fontSize: '0.88rem', fontWeight: '600', color: 'white' }}>{ad.caption}</p>
                      <a href={ad.link} target="_blank" rel="noreferrer" style={{ fontSize: '0.72rem', color: 'var(--neon-pink)', display: 'block', marginTop: '3px', wordBreak: 'break-all' }}>{ad.link}</a>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', justifyContent: 'center' }}>
                      <button 
                        onClick={() => handleToggleAdStatus(ad.id, ad.is_active)}
                        className="btn-secondary"
                        style={{
                          fontSize: '0.72rem',
                          padding: '4px 8px',
                          background: ad.is_active ? 'rgba(46, 204, 113, 0.15)' : 'rgba(255, 255, 255, 0.05)',
                          color: ad.is_active ? '#2ecc71' : 'grey',
                          borderColor: ad.is_active ? 'rgba(46, 204, 113, 0.3)' : 'rgba(255, 255, 255, 0.15)'
                        }}
                      >
                        {ad.is_active ? 'Active' : 'Paused'}
                      </button>
                      <button 
                        onClick={() => handleDeleteAd(ad.id)}
                        className="btn-secondary"
                        style={{ fontSize: '0.72rem', padding: '4px 8px', background: 'rgba(231, 76, 60, 0.15)', color: '#e74c3c', borderColor: 'rgba(231, 76, 60, 0.3)' }}
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Black market orders panel */}
      {!loading && activeTab === 'orders' && (
        <div>
          {ordersList.length === 0 ? (
            <p style={{ color: 'grey', textAlign: 'center', padding: '20px' }}>No orders in queue.</p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              {ordersList.map((ord) => {
                // Parse items list
                let parsedItems = [];
                try {
                  parsedItems = typeof ord.items === 'string' ? JSON.parse(ord.items) : (ord.items || []);
                } catch(e) {
                  parsedItems = [];
                }

                return (
                  <div key={ord.id} className="glass-panel" style={{ padding: '15px', borderRadius: '16px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', borderBottom: '1px solid rgba(255,255,255,0.06)', paddingBottom: '10px', marginBottom: '10px' }}>
                      <div>
                        <p style={{ margin: 0, fontSize: '0.78rem', color: 'var(--text-secondary)' }}>Buyer: <strong>@{ord.username}</strong></p>
                        <p style={{ margin: '2px 0 0', fontSize: '0.65rem', color: 'var(--text-muted)' }}>Ref: {ord.id.substring(0, 8)}</p>
                      </div>
                      <div style={{ textAlign: 'right' }}>
                        <span 
                          style={{
                            fontSize: '0.7rem',
                            padding: '3px 8px',
                            borderRadius: '10px',
                            fontWeight: '600',
                            background: ord.status === 'Approved' ? 'rgba(46, 204, 113, 0.15)' : 'rgba(241, 196, 15, 0.15)',
                            color: ord.status === 'Approved' ? '#2ecc71' : '#f1c40f'
                          }}
                        >
                          {ord.status}
                        </span>
                      </div>
                    </div>

                    {/* Items */}
                    <div style={{ marginBottom: '12px' }}>
                      {parsedItems.map((item, index) => (
                        <div key={index} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: '4px' }}>
                          <span style={{ color: 'white' }}>{item.title} <span style={{ color: 'var(--text-muted)' }}>x{item.qty || 1}</span></span>
                          <span style={{ color: 'var(--text-secondary)' }}>₦{parseFloat(item.price || 0).toLocaleString()}</span>
                        </div>
                      ))}
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid rgba(255,255,255,0.06)', paddingTop: '10px' }}>
                      <div>
                        <p style={{ margin: 0, fontSize: '0.68rem', color: 'var(--text-muted)' }}>Total Paid Escrow</p>
                        <p style={{ margin: '2px 0 0', fontSize: '1.05rem', fontWeight: '800', color: '#f1c40f' }}>₦{parseFloat(ord.total_price).toLocaleString()}</p>
                      </div>
                      <div style={{ display: 'flex', gap: '8px' }}>
                        {ord.status === 'Placed' && (
                          <button 
                            onClick={() => handleApproveOrder(ord)}
                            className="btn-primary"
                            style={{ padding: '8px 14px', fontSize: '0.76rem', width: 'auto' }}
                          >
                            Approve & Dispatch
                          </button>
                        )}
                        <button 
                          onClick={() => handleDeleteOrder(ord.id)}
                          className="btn-secondary"
                          style={{ padding: '8px 12px', fontSize: '0.76rem', background: 'rgba(231, 76, 60, 0.15)', color: '#e74c3c', borderColor: 'rgba(231, 76, 60, 0.3)' }}
                        >
                          Delete
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
