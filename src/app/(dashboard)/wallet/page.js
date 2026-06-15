'use client';

import { useEffect, useState } from 'react';
import { useApp } from '@/context/AppContext';
import { createClient } from '@/utils/supabase/client';
import { useRouter } from 'next/navigation';

export default function WalletPage() {
  const { user, profile, refreshProfile } = useApp();
  const supabase = createClient();
  const router = useRouter();

  const [payments, setPayments] = useState([]);
  const [loadingHistory, setLoadingHistory] = useState(true);
  
  // Modals state
  const [showFundModal, setShowFundModal] = useState(false);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);
  
  // Input states
  const [fundAmount, setFundAmount] = useState('');
  const [withdrawAmount, setWithdrawAmount] = useState('');
  const [withdrawBank, setWithdrawBank] = useState('');
  const [withdrawAccount, setWithdrawAccount] = useState('');
  const [submittingWithdrawal, setSubmittingWithdrawal] = useState(false);

  // Load Monnify SDK Script
  useEffect(() => {
    const script = document.createElement('script');
    script.src = 'https://sdk.monnify.com/plugin/monnify.js';
    script.async = true;
    document.body.appendChild(script);

    return () => {
      document.body.removeChild(script);
    };
  }, []);

  // Fetch transaction/payment history
  const fetchHistory = async () => {
    if (!user) return;
    try {
      setLoadingHistory(true);
      const { data, error } = await supabase
        .from('payments')
        .select('*')
        .or(`payer_id.eq.${user.id},recipient_id.eq.${user.id}`)
        .order('created_at', { ascending: false })
        .limit(30);

      if (!error && data) {
        setPayments(data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoadingHistory(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchHistory();
    }
  }, [user]);

  // Copy Account Number
  const copyAccountNumber = () => {
    if (profile?.monnify_account_number) {
      navigator.clipboard.writeText(profile.monnify_account_number);
      alert('Virtual account number copied! 📋');
    }
  };

  // Quick Fund Action
  const handleQuickFund = (amount) => {
    setFundAmount(amount.toString());
    setShowFundModal(true);
  };

  // Trigger Monnify Payment Inline
  const handleInitiateFunding = () => {
    const amountNum = parseFloat(fundAmount);
    if (!amountNum || amountNum < 100) {
      alert('Minimum funding amount is ₦100');
      return;
    }

    if (typeof window.MonnifySDK === 'undefined') {
      // Fallback message
      alert(`Monnify payment plugin is not loaded. Please transfer ₦${amountNum.toLocaleString()} to your virtual account shown below.`);
      setShowFundModal(false);
      return;
    }

    const apiKey = process.env.NEXT_PUBLIC_MONNIFY_API_KEY || 'MK_PROD_Z1N1VE409T';
    const contractCode = process.env.NEXT_PUBLIC_MONNIFY_CONTRACT_CODE || '479854013966';

    window.MonnifySDK.initialize({
      amount: amountNum,
      currency: 'NGN',
      reference: 'EMZ-' + Date.now(),
      customerFullName: profile?.fullname || profile?.username || 'EazyMUZE User',
      customerEmail: profile?.email || user?.email || 'user@eazymuze.com',
      apiKey: apiKey,
      contractCode: contractCode,
      paymentDescription: 'EazyMUZE Wallet Funding',
      isTestMode: true,
      onLoadStart: () => {},
      onLoadComplete: () => {},
      onComplete: async (res) => {
        if (res.status === 'SUCCESS' || res.paymentStatus === 'PAID') {
          try {
            // 1. Insert transaction record
            const { error: txErr } = await supabase.from('transactions').insert({
              user_id: user.id,
              type: 'credit',
              amount: amountNum,
              reference: res.transactionReference || res.paymentReference,
              status: 'success'
            });

            if (txErr) throw txErr;

            // 2. Insert payment record
            const { error: payErr } = await supabase.from('payments').insert({
              type: 'wallet_funding',
              payer_id: user.id,
              amount: amountNum
            });

            if (payErr) throw payErr;

            // 3. Update profile wallet balance
            const currentBal = parseFloat(profile?.wallet_balance || 0);
            const { error: profErr } = await supabase
              .from('profiles')
              .update({ wallet_balance: currentBal + amountNum })
              .eq('id', user.id);

            if (profErr) throw profErr;

            // 4. Send system notification
            await supabase.from('notifications').insert({
              user_id: user.id,
              type: 'system',
              message: `Wallet successfully funded with ₦${amountNum.toLocaleString()}`
            });

            alert(`Wallet successfully funded with ₦${amountNum.toLocaleString()}! 💰`);
            setShowFundModal(false);
            setFundAmount('');
            await refreshProfile();
            fetchHistory();
          } catch (err) {
            console.error('Error funding wallet database updates:', err);
            alert('Payment completed, but there was an error updating your balance. Please contact support.');
          }
        } else {
          alert('Payment was not successful. Status: ' + res.status);
        }
      },
      onClose: () => {}
    });
  };

  // Submit Withdrawal Request
  const handleSubmitWithdrawal = async (e) => {
    e.preventDefault();
    const amountNum = parseFloat(withdrawAmount);
    const currentBal = parseFloat(profile?.wallet_balance || 0);

    if (!amountNum || amountNum < 500) {
      alert('Minimum withdrawal amount is ₦500');
      return;
    }

    if (amountNum > currentBal) {
      alert('Insufficient wallet balance');
      return;
    }

    if (!withdrawBank.trim() || !withdrawAccount.trim()) {
      alert('Please fill in bank name and account number');
      return;
    }

    setSubmittingWithdrawal(true);
    try {
      // 1. Insert transaction record (debit pending)
      const ref = 'WD-' + Date.now();
      const { error: txErr } = await supabase.from('transactions').insert({
        user_id: user.id,
        type: 'debit',
        amount: amountNum,
        reference: ref,
        status: 'pending'
      });

      if (txErr) throw txErr;

      // 2. Insert payment record
      const { error: payErr } = await supabase.from('payments').insert({
        type: 'withdrawal',
        payer_id: user.id,
        amount: amountNum
      });

      if (payErr) throw payErr;

      // 3. Update profiles wallet balance
      const { error: profErr } = await supabase
        .from('profiles')
        .update({ wallet_balance: currentBal - amountNum })
        .eq('id', user.id);

      if (profErr) throw profErr;

      // 4. Send notification
      await supabase.from('notifications').insert({
        user_id: user.id,
        type: 'system',
        message: `Withdrawal request of ₦${amountNum.toLocaleString()} submitted.`
      });

      alert('Withdrawal request submitted! We will process it within 24 hours. 💌');
      setShowWithdrawModal(false);
      setWithdrawAmount('');
      setWithdrawBank('');
      setWithdrawAccount('');
      await refreshProfile();
      fetchHistory();
    } catch (err) {
      console.error(err);
      alert(err.message || 'Error processing withdrawal');
    } finally {
      setSubmittingWithdrawal(false);
    }
  };

  const getTxnLabel = (type) => {
    const typeLabels = {
      wallet_funding: 'Wallet Top-up',
      whisper_init: 'Whisper Fee',
      market_purchase: 'Market Purchase',
      market_sale: 'Market Sale',
      subscription: 'Subscription',
      withdrawal: 'Withdrawal',
      invite_join: 'Invite Joined',
      invite_creation: 'Invite Created'
    };
    return typeLabels[type] || type.replace('_', ' ');
  };

  const getTxnIcon = (type) => {
    const typeIcons = {
      wallet_funding: 'fa-wallet',
      whisper_init: 'fa-comment-dots',
      market_purchase: 'fa-shopping-bag',
      market_sale: 'fa-store',
      subscription: 'fa-crown',
      withdrawal: 'fa-arrow-up',
      invite_join: 'fa-envelope-open',
      invite_creation: 'fa-heart'
    };
    return typeIcons[type] || 'fa-exchange-alt';
  };

  return (
    <div style={{ padding: '0 15px 30px' }}>
      <h2 style={{ fontSize: '1.4rem', marginBottom: '20px', color: 'white', display: 'flex', alignItems: 'center', gap: '8px' }}>
        My Wallet <i className="fas fa-wallet" style={{ color: 'var(--neon-pink)' }}></i>
      </h2>

      {/* Balance Card */}
      <div 
        style={{ 
          background: 'linear-gradient(135deg, #1a0b12, #2d0f1e)', 
          border: '1px solid rgba(255, 42, 109, 0.4)', 
          borderRadius: '20px', 
          padding: '24px', 
          marginBottom: '20px', 
          position: 'relative', 
          overflow: 'hidden' 
        }}
      >
        <div style={{ position: 'absolute', top: '-20px', right: '-20px', width: '120px', height: '120px', background: 'radial-gradient(circle, rgba(255,42,109,0.3) 0%, transparent 70%)', borderRadius: '50%' }}></div>
        
        <p style={{ margin: '0 0 6px', fontSize: '0.78rem', color: 'var(--text-secondary)', textTransform: 'uppercase', letterSpacing: '1px' }}>Available Balance</p>
        <h1 style={{ margin: '0 0 18px', fontSize: '2.4rem', fontWeight: '800', color: 'white' }}>
          ₦{parseFloat(profile?.wallet_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
        </h1>
        
        <div style={{ display: 'flex', gap: '10px', position: 'relative', zIndex: 2 }}>
          <button className="btn-primary" style={{ flex: 1, padding: '12px', fontSize: '0.88rem' }} onClick={() => setShowFundModal(true)}>
            <i className="fas fa-plus-circle" style={{ marginRight: '6px' }}></i> Fund Wallet
          </button>
          <button 
            className="btn-primary" 
            style={{ flex: 1, padding: '12px', fontSize: '0.88rem', background: 'rgba(255,255,255,0.08)', boxShadow: 'none', border: '1px solid rgba(255,255,255,0.15)' }} 
            onClick={() => setShowWithdrawModal(true)}
          >
            <i className="fas fa-arrow-up" style={{ marginRight: '6px' }}></i> Withdraw
          </button>
        </div>
      </div>

      {/* Virtual Account Info */}
      {profile?.monnify_account_number && (
        <div className="glass-panel" style={{ borderRadius: '16px', padding: '18px', marginBottom: '20px' }}>
          <h3 style={{ margin: '0 0 14px', fontSize: '0.9rem', color: 'var(--neon-pink)', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <i className="fas fa-university"></i> Your Dedicated Virtual Account
          </h3>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
            <div>
              <p style={{ margin: 0, fontSize: '0.78rem', color: 'var(--text-secondary)' }}>Bank</p>
              <p style={{ margin: '3px 0 0', fontSize: '0.95rem', color: 'white', fontWeight: '600' }}>{profile.monnify_bank_name}</p>
            </div>
            <div style={{ textAlign: 'right' }}>
              <p style={{ margin: 0, fontSize: '0.78rem', color: 'var(--text-secondary)', textAlign: 'right' }}>Account Number</p>
              <p style={{ margin: '3px 0 0', fontSize: '1.1rem', color: 'white', fontWeight: '700', letterSpacing: '1px', textAlign: 'right' }}>{profile.monnify_account_number}</p>
            </div>
          </div>
          <button 
            onClick={copyAccountNumber} 
            style={{ 
              width: '100%', 
              background: 'rgba(255,42,109,0.1)', 
              border: '1px dashed rgba(255,42,109,0.4)', 
              color: 'var(--neon-pink)', 
              padding: '10px', 
              borderRadius: '10px', 
              cursor: 'pointer', 
              fontSize: '0.85rem', 
              display: 'flex', 
              alignItems: 'center', 
              justifyContent: 'center', 
              gap: '8px',
              fontWeight: '600'
            }}
          >
            <i className="fas fa-copy"></i> Copy Account Number
          </button>
          <p style={{ margin: '10px 0 0', fontSize: '0.75rem', color: 'var(--text-muted)', textAlign: 'center', lineSpread: '1.4' }}>
            Transfer any amount to this dedicated account to fund your wallet instantly via bank transfer.
          </p>
        </div>
      )}

      {/* Quick Fund Options */}
      <div className="glass-panel" style={{ borderRadius: '16px', padding: '18px', marginBottom: '20px' }}>
        <h3 style={{ margin: '0 0 14px', fontSize: '0.9rem', color: 'white' }}>Quick Top-up</h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '10px' }}>
          {[500, 1000, 2000, 5000, 10000, 20000].map((amount) => (
            <button 
              key={amount} 
              onClick={() => handleQuickFund(amount)}
              className="quick-fund-btn"
              style={{ 
                background: 'rgba(255, 42, 109, 0.1)', 
                border: '1px solid rgba(255, 42, 109, 0.25)', 
                color: 'var(--neon-pink)', 
                padding: '12px 8px', 
                borderRadius: '10px', 
                cursor: 'pointer', 
                fontSize: '0.85rem', 
                fontWeight: '600', 
                transition: 'all 0.2s'
              }}
            >
              ₦{amount.toLocaleString()}
            </button>
          ))}
        </div>
      </div>

      {/* Transaction History */}
      <div className="glass-panel" style={{ borderRadius: '16px', padding: '18px' }}>
        <h3 style={{ margin: '0 0 16px', fontSize: '0.9rem', color: 'white' }}>Transaction History</h3>
        {loadingHistory ? (
          <div style={{ textAlign: 'center', padding: '20px 0', color: 'var(--text-secondary)' }}>
            <i className="fas fa-spinner fa-spin"></i>
          </div>
        ) : payments.length === 0 ? (
          <p style={{ color: 'var(--text-secondary)', textAlign: 'center', fontSize: '0.85rem', padding: '20px 0' }}>No transactions yet.</p>
        ) : (
          <div>
            {payments.map((txn) => {
              const isIncoming = txn.recipient_id === user.id || txn.type === 'wallet_funding' || txn.type === 'market_sale';
              return (
                <div 
                  key={txn.id} 
                  style={{ 
                    display: 'flex', 
                    alignItems: 'center', 
                    gap: '14px', 
                    padding: '12px 0', 
                    borderBottom: '1px solid rgba(255,255,255,0.06)' 
                  }}
                >
                  <div 
                    style={{ 
                      width: '40px', 
                      height: '40px', 
                      borderRadius: '50%', 
                      background: isIncoming ? 'rgba(46,204,113,0.15)' : 'rgba(255,42,109,0.12)', 
                      display: 'flex', 
                      alignItems: 'center', 
                      justifyContent: 'center', 
                      flexShrink: 0 
                    }}
                  >
                    <i className={`fas ${getTxnIcon(txn.type)}`} style={{ color: isIncoming ? '#2ecc71' : 'var(--neon-pink)', fontSize: '0.9rem' }}></i>
                  </div>
                  
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <p style={{ margin: 0, fontSize: '0.88rem', color: 'white', fontWeight: '600' }}>{getTxnLabel(txn.type)}</p>
                    <p style={{ margin: '2px 0 0', fontSize: '0.73rem', color: 'var(--text-muted)' }}>
                      {new Date(txn.created_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })} at{' '}
                      {new Date(txn.created_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
                    </p>
                  </div>
                  
                  <div style={{ textAlign: 'right', fontWeight: '700', fontSize: '0.95rem', color: isIncoming ? '#2ecc71' : 'var(--neon-pink)', flexShrink: 0 }}>
                    {isIncoming ? '+' : '-'}₦{parseFloat(txn.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Fund Modal */}
      {showFundModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10,4,6,0.96)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div className="glass-panel" style={{ background: 'linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '400px', width: '90%', padding: '25px' }}>
            <h3 style={{ color: 'var(--neon-pink)', marginBottom: '18px', textAlign: 'center', fontSize: '1.1rem' }}>
              <i className="fas fa-wallet"></i> Fund Wallet
            </h3>
            
            <input 
              type="number" 
              placeholder="Enter amount (₦)" 
              min="100"
              value={fundAmount}
              onChange={(e) => setFundAmount(e.target.value)}
              style={{ 
                width: '100%', 
                padding: '14px', 
                borderRadius: '12px', 
                border: '1px solid rgba(255,42,109,0.3)', 
                background: 'rgba(255,255,255,0.05)', 
                color: 'white', 
                fontSize: '1rem', 
                marginBottom: '16px', 
                boxSizing: 'border-box', 
                textAlign: 'center', 
                fontWeight: '700',
                outline: 'none'
              }}
            />
            
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.82rem', textAlign: 'center', marginBottom: '18px', lineSize: '1.5' }}>
              You will be redirected to the secure Monnify payment gateway to complete your transaction.
            </p>
            
            <div style={{ display: 'flex', gap: '10px' }}>
              <button className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={() => setShowFundModal(false)}>Cancel</button>
              <button className="btn-primary" style={{ flex: 1 }} onClick={handleInitiateFunding}>Pay Now 🔒</button>
            </div>
          </div>
        </div>
      )}

      {/* Withdraw Modal */}
      {showWithdrawModal && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(10,4,6,0.96)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div className="glass-panel" style={{ background: 'linear-gradient(135deg,rgba(20,10,15,0.98),rgba(45,15,30,0.98))', border: '2px solid var(--neon-pink)', borderRadius: '20px', maxWidth: '400px', width: '90%', padding: '25px' }}>
            <h3 style={{ color: 'var(--neon-pink)', marginBottom: '18px', textAlign: 'center', fontSize: '1.1rem' }}>
              <i className="fas fa-arrow-up"></i> Withdraw Funds
            </h3>
            
            <p style={{ color: 'var(--text-muted)', textAlign: 'center', fontSize: '0.82rem', marginBottom: '14px' }}>
              Available Balance: <strong style={{ color: 'var(--neon-pink)' }}>₦{parseFloat(profile?.wallet_balance || 0).toLocaleString()}</strong>
            </p>
            
            <form onSubmit={handleSubmitWithdrawal}>
              <input 
                type="number" 
                placeholder="Amount to withdraw (₦)" 
                min="500"
                value={withdrawAmount}
                onChange={(e) => setWithdrawAmount(e.target.value)}
                required
                style={{ width: '100%', padding: '12px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', marginBottom: '10px', boxSizing: 'border-box' }}
              />
              <input 
                type="text" 
                placeholder="Bank Name" 
                value={withdrawBank}
                onChange={(e) => setWithdrawBank(e.target.value)}
                required
                style={{ width: '100%', padding: '12px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', marginBottom: '10px', boxSizing: 'border-box' }}
              />
              <input 
                type="text" 
                placeholder="Account Number" 
                value={withdrawAccount}
                onChange={(e) => setWithdrawAccount(e.target.value)}
                required
                style={{ width: '100%', padding: '12px 14px', borderRadius: '10px', border: '1px solid rgba(255,42,109,0.3)', background: 'rgba(255,255,255,0.05)', color: 'white', marginBottom: '16px', boxSizing: 'border-box' }}
              />
              
              <div style={{ display: 'flex', gap: '10px' }}>
                <button type="button" className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={() => setShowWithdrawModal(false)}>Cancel</button>
                <button type="submit" className="btn-primary" style={{ flex: 1 }} disabled={submittingWithdrawal}>
                  {submittingWithdrawal ? 'Requesting...' : 'Request Withdrawal'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      
      {/* CSS Hover styling for quick fund buttons */}
      <style jsx global>{`
        .quick-fund-btn:hover {
          background: rgba(255, 42, 109, 0.25) !important;
        }
      `}</style>
    </div>
  );
}
