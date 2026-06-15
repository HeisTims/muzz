'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createClient } from '@/utils/supabase/client';
import Link from 'next/link';

export default function RegisterPage() {
  const [step, setStep] = useState(1);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [fullname, setFullname] = useState('');
  const [dob, setDob] = useState('');
  const [preference, setPreference] = useState('');
  const [bio, setBio] = useState('');
  const [consent, setConsent] = useState(false);
  
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const router = useRouter();
  const supabase = createClient();

  const handleNextStep = (e) => {
    e.preventDefault();
    if (!username || !password || !email || !phone || !fullname || !dob) {
      setError('Please fill in all credentials first');
      return;
    }
    if (password.length < 8) {
      setError('Password must be at least 8 characters long');
      return;
    }
    setError('');
    setStep(2);
  };

  const handleBackStep = () => {
    setError('');
    setStep(1);
  };

  const handleRegister = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (!consent) {
      setError('You must be 18+ and consent to mature content to proceed.');
      return;
    }

    if (!preference) {
      setError('Please select your preference.');
      return;
    }

    setLoading(true);

    try {
      const { data, error: signUpError } = await supabase.auth.signUp({
        email,
        password,
        options: {
          data: {
            username,
            fullname,
            phone,
            dob,
            preference,
            bio,
          },
        },
      });

      if (signUpError) {
        setError(signUpError.message);
      } else {
        setSuccess('Initiation initiated! Welcome to the Temple.');
        setTimeout(() => {
          router.push('/');
          router.refresh();
        }, 2000);
      }
    } catch (err) {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-container" style={{ minHeight: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', padding: '20px' }}>
      <div className="glass-panel auth-box fade-in-up" id="registerBox" style={{ width: '100%', maxWidth: '400px', textAlign: 'center', padding: '30px', borderRadius: '24px', position: 'relative' }}>
        <div style={{ position: 'relative', display: 'inline-block', marginBottom: '25px', marginTop: '10px' }}>
          <img src="/assets/img/logo1.png" alt="EazyMUZE Logo" style={{ width: '130px', display: 'block', margin: '0 auto', filter: 'drop-shadow(0 0 15px rgba(255, 42, 109, 0.5))' }} />
          <img src="/assets/img/353997.png" alt="" style={{ position: 'absolute', width: '75px', right: '-40px', bottom: '-15px', transform: 'rotate(18deg)', filter: 'drop-shadow(0 0 10px rgba(255, 42, 109, 0.45))', animation: 'slantFloat 3.5s ease-in-out infinite' }} />
        </div>
        
        <h2 style={{ color: 'var(--neon-pink)', marginBottom: '20px', fontWeight: '800' }}>Initiation</h2>
        
        {error && (
          <div style={{ background: 'rgba(192, 57, 43, 0.15)', border: '1px solid var(--blood-moon)', color: '#ff7675', padding: '12px', borderRadius: '8px', fontSize: '0.85rem', marginBottom: '20px', textAlign: 'left' }}>
            <i className="fas fa-exclamation-circle" style={{ marginRight: '8px' }}></i> {error}
          </div>
        )}

        {success && (
          <div style={{ background: 'rgba(46, 204, 113, 0.15)', border: '1px solid #2ecc71', color: '#2ecc71', padding: '12px', borderRadius: '8px', fontSize: '0.85rem', marginBottom: '20px', textAlign: 'left' }}>
            <i className="fas fa-check-circle" style={{ marginRight: '8px' }}></i> {success}
          </div>
        )}

        <form onSubmit={step === 1 ? handleNextStep : handleRegister} style={{ textAlign: 'left' }}>
          {step === 1 ? (
            <div id="step1">
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <input 
                  type="text" 
                  placeholder="Username" 
                  required 
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <input 
                  type="password" 
                  placeholder="Password (Min 8 Characters)" 
                  required 
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <input 
                  type="email" 
                  placeholder="Email" 
                  required 
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <input 
                  type="tel" 
                  placeholder="Phone Number (e.g. 08012345678)" 
                  required 
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <input 
                  type="text" 
                  placeholder="Full Name" 
                  required 
                  value={fullname}
                  onChange={(e) => setFullname(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <div className="form-group" style={{ marginBottom: '20px' }}>
                <label style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', marginBottom: '5px', display: 'block' }}>Date of Birth</label>
                <input 
                  type="date" 
                  required 
                  value={dob}
                  onChange={(e) => setDob(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                />
              </div>
              <button type="submit" className="btn-primary" style={{ width: '100%' }}>Next Step 💋</button>
            </div>
          ) : (
            <div id="step2">
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <select 
                  required 
                  value={preference}
                  onChange={(e) => setPreference(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: '#1a0b12', color: 'white', outline: 'none', fontSize: '0.95rem' }}
                >
                  <option value="">Select Preference</option>
                  <option value="straight">Straight</option>
                  <option value="gay">Gay</option>
                  <option value="lesbian">Lesbian</option>
                  <option value="bisexual">Bisexual</option>
                  <option value="sugar_mummy">Sugar Mummy</option>
                  <option value="sugar_daddy">Sugar Daddy</option>
                </select>
              </div>
              
              <div className="form-group" style={{ marginBottom: '15px' }}>
                <textarea 
                  placeholder="Short Bio (Describe your desires...)" 
                  rows="3" 
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  style={{ width: '100%', padding: '12px 15px', borderRadius: '10px', border: '1px solid var(--glass-border)', background: 'rgba(255, 255, 255, 0.04)', color: 'white', outline: 'none', fontSize: '0.95rem', resize: 'none' }}
                ></textarea>
              </div>
              
              <div className="form-group" style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '25px' }}>
                <input 
                  type="checkbox" 
                  id="ageConsent" 
                  required 
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                  style={{ width: 'auto', cursor: 'pointer', transform: 'scale(1.1)' }} 
                />
                <label htmlFor="ageConsent" style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', cursor: 'pointer', lineHeight: '1.4' }}>
                  I am over 18 years old. I consent to mature content and respect other members.
                </label>
              </div>
              
              <div style={{ display: 'flex', gap: '12px' }}>
                <button type="button" className="btn-primary" style={{ flex: 1, background: '#333', boxShadow: 'none' }} onClick={handleBackStep}>Back</button>
                <button type="submit" className="btn-primary" disabled={loading} style={{ flex: 1 }}>{loading ? 'Initiating...' : 'Register 💋'}</button>
              </div>
            </div>
          )}
        </form>
        
        <p style={{ marginTop: '25px', fontSize: '0.9rem', color: 'var(--text-secondary)' }}>
          <Link href="/auth/login" style={{ color: 'var(--text-secondary)', textDecoration: 'none' }}>Already initiated? Enter.</Link>
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
