'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createClient } from '@/utils/supabase/client';
import Link from 'next/link';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const supabase = createClient();

  const handleLogin = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const { data, error: signInError } = await supabase.auth.signInWithPassword({
        email,
        password,
      });

      if (signInError) {
        setError(signInError.message);
      } else {
        router.push('/');
        router.refresh();
      }
    } catch (err) {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-container" style={{ minHeight: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', padding: '20px' }}>
      <div className="glass-panel auth-box fade-in-up" id="loginBox" style={{ width: '100%', maxWidth: '400px', textAlign: 'center', padding: '30px', borderRadius: '24px', position: 'relative' }}>
        <div style={{ position: 'relative', display: 'inline-block', marginBottom: '25px', marginTop: '10px' }}>
          {/* Logo */}
          <img src="/assets/img/logo1.png" alt="EazyMUZE Logo" style={{ width: '130px', display: 'block', margin: '0 auto', filter: 'drop-shadow(0 0 15px rgba(255, 42, 109, 0.5))' }} />
          {/* Slanted floating ring */}
          <img src="/assets/img/353997.png" alt="" style={{ position: 'absolute', width: '75px', right: '-40px', bottom: '-15px', transform: 'rotate(18deg)', filter: 'drop-shadow(0 0 10px rgba(255, 42, 109, 0.45))', animation: 'slantFloat 3.5s ease-in-out infinite' }} />
        </div>
        
        <h2 style={{ color: 'var(--neon-pink)', marginBottom: '5px', fontWeight: '800' }}>Enter the Temple</h2>
        <p style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', marginBottom: '25px' }}>Indulge your darkest desires...</p>
        
        {error && (
          <div style={{ background: 'rgba(192, 57, 43, 0.15)', border: '1px solid var(--blood-moon)', color: '#ff7675', padding: '12px', borderRadius: '8px', fontSize: '0.85rem', marginBottom: '20px', textAlign: 'left' }}>
            <i className="fas fa-exclamation-circle" style={{ marginRight: '8px' }}></i> {error}
          </div>
        )}

        <form onSubmit={handleLogin} style={{ textAlign: 'left' }}>
          <div className="form-group" style={{ marginBottom: '15px' }}>
            <input 
              type="email" 
              placeholder="Email Address" 
              required 
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
            />
          </div>
          
          <div className="form-group" style={{ marginBottom: '20px' }}>
            <input 
              type="password" 
              placeholder="Password" 
              required 
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
            />
          </div>
          
          <button type="submit" className="btn-primary" disabled={loading} style={{ width: '100%', marginTop: '5px' }}>
            {loading ? 'Entering...' : 'Unlock Desires 💋'}
          </button>
        </form>
        
        <p style={{ marginTop: '25px', fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
          <Link href="/auth/register" style={{ color: 'var(--text-secondary)', textDecoration: 'none' }}>New here? Join the Temple</Link>
        </p>
      </div>

      <style jsx global>{`
        @keyframes slantFloat {
          0% { transform: translateY(0) rotate(18deg); }
          50% { transform: translateY(-8px) rotate(15deg); }
          100% { transform: translateY(0) rotate(18deg); }
        }
      `}</style>
    </div>
  );
}
