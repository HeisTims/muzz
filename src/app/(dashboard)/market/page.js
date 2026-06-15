'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function MarketPage() {
  const { user, profile, refreshProfile } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const [listings, setListings] = useState([]);
  const [filteredListings, setFilteredListings] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('All');
  const [loading, setLoading] = useState(true);

  // Modal states
  const [isListingModalOpen, setIsListingModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  // New Listing Form state
  const [listingTitle, setListingTitle] = useState('');
  const [listingDesc, setListingDesc] = useState('');
  const [listingPrice, setListingPrice] = useState('');
  const [listingCategory, setListingCategory] = useState('');
  const [listingImage, setListingImage] = useState('');
  const [submittingListing, setSubmittingListing] = useState(false);
  const [purchasing, setPurchasing] = useState(false);

  const categories = ['All', 'Photos', 'Videos', 'Services', 'Meetups', 'Exclusive Content'];

  const fetchListings = async () => {
    try {
      setLoading(true);
      const { data, error } = await supabase
        .from('market')
        .select(`
          *,
          seller:seller_id (id, username, avatar, is_verified)
        `)
        .eq('status', 'active')
        .order('created_at', { ascending: false });

      if (!error && data) {
        setListings(data);
        setFilteredListings(data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchListings();
    }
  }, [user]);

  // Handle search and category filters
  useEffect(() => {
    let result = listings;

    if (activeCategory !== 'All') {
      result = result.filter(item => item.category?.toLowerCase() === activeCategory.toLowerCase());
    }

    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase().trim();
      result = result.filter(item => 
        item.title?.toLowerCase().includes(q) ||
        item.description?.toLowerCase().includes(q) ||
        item.category?.toLowerCase().includes(q)
      );
    }

    setFilteredListings(result);
  }, [searchQuery, activeCategory, listings]);

  // Handle Image upload (base64)
  const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (uploadEvent) => {
      setListingImage(uploadEvent.target.result);
    };
    reader.readAsDataURL(file);
  };

  // Create listing
  const handleCreateListing = async (e) => {
    e.preventDefault();
    if (!listingTitle || !listingDesc || !listingPrice || !listingCategory) {
      alert('All fields are required.');
      return;
    }

    setSubmittingListing(true);
    try {
      const { error } = await supabase
        .from('market')
        .insert({
          seller_id: user.id,
          title: listingTitle.trim(),
          description: listingDesc.trim(),
          price: parseFloat(listingPrice),
          category: listingCategory,
          image: listingImage || null,
          status: 'active'
        });

      if (error) throw error;

      alert('Listing created 🛒');
      setIsListingModalOpen(false);
      setListingTitle('');
      setListingDesc('');
      setListingPrice('');
      setListingCategory('');
      setListingImage('');
      fetchListings();
    } catch (err) {
      alert(err.message || 'Failed to create listing');
    } finally {
      setSubmittingListing(false);
    }
  };

  // Purchase Listing (Escrow Wallet Transaction)
  const handlePurchaseListing = async () => {
    if (!selectedItem) return;

    const price = parseFloat(selectedItem.price);
    const confirmPurchase = confirm(`Purchase "${selectedItem.title}" for ₦${price.toLocaleString()} from your wallet?`);
    if (!confirmPurchase) return;

    if ((profile?.wallet_balance || 0) < price) {
      alert('Insufficient wallet balance. Please fund your wallet.');
      return;
    }

    setPurchasing(true);
    try {
      // 1. Deduct from buyer
      const { error: buyerErr } = await supabase
        .from('profiles')
        .update({ wallet_balance: (profile.wallet_balance - price) })
        .eq('id', user.id);

      if (buyerErr) throw buyerErr;

      // 2. Fetch seller profile to get current balance
      const { data: sellerData, error: fetchErr } = await supabase
        .from('profiles')
        .select('wallet_balance')
        .eq('id', selectedItem.seller_id)
        .single();

      if (fetchErr) throw fetchErr;

      // Credit seller (90% payout)
      const sellerPayout = price * 0.90;
      const { error: sellerErr } = await supabase
        .from('profiles')
        .update({ wallet_balance: (parseFloat(sellerData.wallet_balance || 0) + sellerPayout) })
        .eq('id', selectedItem.seller_id);

      if (sellerErr) throw sellerErr;

      // 3. Mark listing as sold
      const { error: marketErr } = await supabase
        .from('market')
        .update({ status: 'sold' })
        .eq('id', selectedItem.id);

      if (marketErr) throw marketErr;

      // 4. Record transaction in payments
      await supabase
        .from('payments')
        .insert({
          type: 'market_purchase',
          payer_id: user.id,
          recipient_id: selectedItem.seller_id,
          amount: price
        });

      // 5. Notify seller
      await supabase
        .from('notifications')
        .insert({
          user_id: selectedItem.seller_id,
          type: 'market',
          message: `Someone purchased your listing: ${selectedItem.title} — ₦${sellerPayout.toLocaleString()} credited.`
        });

      // 6. Record order in black_market_orders
      await supabase
        .from('black_market_orders')
        .insert({
          user_id: user.id,
          username: profile?.username || 'buyer',
          items: [{ id: selectedItem.id, title: selectedItem.title, price: selectedItem.price }],
          total_price: price,
          status: 'Paid',
          seller: selectedItem.seller?.username || 'seller',
          tracking_step: 2,
          escrow_status: 'completed'
        });

      alert('Purchase successful! Check your profile for details 🎉');
      setSelectedItem(null);
      await refreshProfile();
      fetchListings();
    } catch (e) {
      alert(e.message || 'Error processing purchase');
    } finally {
      setPurchasing(false);
    }
  };

  return (
    <div style={{ padding: '0 15px 20px' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '16px' }}>
        <h2 style={{ fontSize: '1.4rem', margin: 0, color: 'white' }}>Desire Market 🛒</h2>
        <button className="btn-primary" style={{ padding: '9px 16px', fontSize: '0.8rem', width: 'auto' }} onClick={() => setIsListingModalOpen(true)}>
          <i className="fas fa-plus"></i> List
        </button>
      </div>
      
      {/* Search box */}
      <div style={{ position: 'relative', marginBottom: '16px' }}>
        <i className="fas fa-search" style={{ position: 'absolute', left: '14px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)', fontSize: '0.9rem' }}></i>
        <input 
          type="text" 
          placeholder="Search listings..." 
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          style={{ width: '100%', padding: '11px 14px 11px 38px', borderRadius: '25px', border: '1px solid var(--glass-border)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem', boxSizing: 'border-box' }}
        />
      </div>
      
      {/* Category Pills */}
      <div style={{ display: 'flex', gap: '8px', overflowX: 'auto', paddingBottom: '10px', scrollbarWidth: 'none', marginBottom: '20px' }}>
        {categories.map(cat => (
          <button 
            key={cat} 
            className={`filter-pill ${activeCategory === cat ? 'active' : ''}`} 
            onClick={() => setActiveCategory(cat)}
            style={{ whiteSpace: 'nowrap' }}
          >
            {cat}
          </button>
        ))}
      </div>
      
      {/* Listings Grid */}
      <div id="marketGrid" style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '12px' }}>
        {loading ? (
          <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '40px' }}>
            <i className="fas fa-fan fa-spin" style={{ fontSize: '2rem', color: 'var(--neon-pink)' }}></i>
          </div>
        ) : filteredListings.length === 0 ? (
          <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '50px 20px' }}>
            <div style={{ fontSize: '3rem', marginBottom: '12px' }}>🛒</div>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.9rem' }}>The market is quiet. Be the first to list something irresistible.</p>
            <button className="btn-primary" style={{ marginTop: '15px', width: 'auto', padding: '10px 20px' }} onClick={() => setIsListingModalOpen(true)}>List Something</button>
          </div>
        ) : (
          filteredListings.map(item => {
            const sellerAvatar = item.seller?.avatar || `https://ui-avatars.com/api/?name=${item.seller?.username}&background=8e1a1a&color=fff`;
            return (
              <div 
                key={item.id} 
                className="market-card glass-panel" 
                style={{ borderRadius: '16px', overflow: 'hidden', cursor: 'pointer', transition: 'transform 0.2s', padding: 0 }}
                onClick={() => setSelectedItem(item)}
              >
                {/* Image */}
                <div style={{ height: '130px', overflow: 'hidden', position: 'relative', background: '#1a0b12' }}>
                  {item.image ? (
                    <img src={item.image} style={{ width: '100%', height: '100%', objectFit: 'cover' }} alt="" />
                  ) : (
                    <div style={{ height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <i className="fas fa-shopping-bag" style={{ fontSize: '2rem', color: 'rgba(255,42,109,0.3)', margin: 'auto' }}></i>
                    </div>
                  )}
                  <div style={{ position: 'absolute', top: '8px', left: '8px', background: 'var(--neon-pink)', color: 'white', fontSize: '0.65rem', padding: '3px 8px', borderRadius: '8px', fontWeight: '700' }}>
                    {item.category}
                  </div>
                </div>
                
                {/* Info */}
                <div style={{ padding: '12px' }}>
                  <h3 style={{ margin: '0 0 4px', fontSize: '0.9rem', color: 'white', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {item.title}
                  </h3>
                  <p style={{ margin: '0 0 8px', fontSize: '0.75rem', color: 'var(--text-secondary)', height: '34px', overflow: 'hidden', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', lineClamp: 2 }}>
                    {item.description}
                  </p>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ color: 'var(--neon-pink)', fontWeight: '800', fontSize: '0.95rem' }}>
                      ₦{Number(item.price).toLocaleString()}
                    </span>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Detail Modal */}
      {selectedItem && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10,4,6,0.97)', zIndex: 9999, overflowY: 'auto', padding: '20px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <button onClick={() => setSelectedItem(null)} style={{ position: 'fixed', top: '15px', right: '15px', background: 'rgba(255,255,255,0.1)', border: 'none', color: 'white', fontSize: '1.3rem', width: '36px', height: '36px', borderRadius: '50%', cursor: 'pointer', zIndex: 10 }}>✕</button>
          
          <div className="glass-panel" style={{ maxWidth: '480px', width: '100%', borderRadius: '16px', overflow: 'hidden', padding: 0 }}>
            {selectedItem.image ? (
              <img src={selectedItem.image} style={{ width: '100%', height: '250px', objectFit: 'cover', display: 'block' }} alt="" />
            ) : (
              <div style={{ height: '200px', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#1a0b12' }}>
                <i className="fas fa-shopping-bag" style={{ fontSize: '3rem', color: 'rgba(255,42,109,0.3)', margin: 'auto' }}></i>
              </div>
            )}
            <div style={{ padding: '20px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '10px' }}>
                <h2 style={{ margin: 0, fontSize: '1.2rem', color: 'white' }}>{selectedItem.title}</h2>
                <span style={{ background: 'var(--neon-pink)', color: 'white', padding: '3px 10px', borderRadius: '10px', fontSize: '0.7rem', fontWeight: 700 }}>{selectedItem.category}</span>
              </div>
              <p style={{ color: 'var(--text-secondary)', fontSize: '0.88rem', lineHeight: '1.6', marginBottom: '16px' }}>{selectedItem.description}</p>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
                <span style={{ color: 'var(--neon-pink)', fontSize: '1.3rem', fontWeight: 800 }}>₦{Number(selectedItem.price).toLocaleString()}</span>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.78rem' }}>by @{selectedItem.seller?.username}</span>
              </div>
              
              <button className="btn-primary" style={{ width: '100%' }} disabled={purchasing} onClick={handlePurchaseListing}>
                <i className="fas fa-lock-open"></i> {purchasing ? 'Processing...' : 'Purchase with Wallet'}
              </button>
              
              <button 
                onClick={() => { setSelectedItem(null); router.push(`/messages?partner=${selectedItem.seller_id}`); }} 
                style={{ width: '100%', marginTop: '10px', background: 'none', border: '1px solid var(--glass-border)', color: 'var(--text-secondary)', padding: '12px', borderRadius: '10px', cursor: 'pointer', fontSize: '0.9rem' }}
              >
                <i className="fas fa-comment-dots"></i> Ask Seller
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Create Listing Modal */}
      {isListingModalOpen && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10,4,6,0.96)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div className="glass-panel" style={{ background: 'linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '420px', width: '90%', padding: '25px', maxHeight: '90vh', overflowY: 'auto' }}>
            <h3 style={{ color: 'var(--neon-pink)', marginBottom: '18px', textAlign: 'center' }}>List Your Desire 🛒</h3>
            
            <form onSubmit={handleCreateListing}>
              <div style={{ marginBottom: '12px' }}>
                <input 
                  type="text" 
                  placeholder="Title (e.g. Exclusive Photoshoot 📸)" 
                  value={listingTitle}
                  onChange={(e) => setListingTitle(e.target.value)}
                  required
                  style={{ width: '100%', padding: '11px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem' }}
                />
              </div>

              <div style={{ marginBottom: '12px' }}>
                <textarea 
                  rows="3" 
                  placeholder="Description..."
                  value={listingDesc}
                  onChange={(e) => setListingDesc(e.target.value)}
                  required
                  style={{ width: '100%', padding: '11px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem', resize: 'none' }}
                />
              </div>

              <div style={{ display: 'flex', gap: '10px', marginBottom: '12px' }}>
                <div style={{ flex: 1 }}>
                  <input 
                    type="number" 
                    placeholder="Price (₦)"
                    value={listingPrice}
                    onChange={(e) => setListingPrice(e.target.value)}
                    required
                    style={{ width: '100%', padding: '11px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', outline: 'none', fontSize: '0.88rem' }}
                  />
                </div>
                <div style={{ flex: 1 }}>
                  <select 
                    value={listingCategory} 
                    onChange={(e) => setListingCategory(e.target.value)}
                    required
                    style={{ width: '100%', padding: '11px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: '#1a0b12', color: 'white', outline: 'none', fontSize: '0.85rem' }}
                  >
                    <option value="">Category</option>
                    {categories.filter(c => c !== 'All').map(c => (
                      <option key={c} value={c}>{c}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div 
                onClick={() => document.getElementById('listingImageInput').click()} 
                style={{ border: '2px dashed rgba(255,42,109,0.4)', padding: '16px', textAlign: 'center', borderRadius: '12px', cursor: 'pointer', marginBottom: '16px' }}
              >
                <i className="fas fa-image" style={{ fontSize: '1.8rem', color: 'var(--neon-pink)' }}></i>
                <p style={{ fontSize: '0.8rem', marginTop: '8px', color: 'var(--text-secondary)' }}>Upload Preview Image</p>
                <input type="file" id="listingImageInput" accept="image/*" style={{ display: 'none' }} onChange={handleImageChange} />
              </div>

              {listingImage && (
                <img src={listingImage} style={{ width: '100%', height: '120px', objectFit: 'cover', borderRadius: '10px', marginBottom: '12px', border: '1px solid var(--neon-pink)' }} alt="" />
              )}

              <div style={{ display: 'flex', gap: '10px' }}>
                <button type="button" className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={() => setIsListingModalOpen(false)}>Cancel</button>
                <button type="submit" className="btn-primary" style={{ flex: 1 }} disabled={submittingListing}>
                  {submittingListing ? 'Listing...' : 'List Now 🛒'}
                </button>
              </div>
            </form>
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
