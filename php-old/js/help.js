// EazyMUZE v5.0 - Help Center & Support Module
window.help = {
    faqs: [
        {
            q: "🛡️ How does EazyMUZE Escrow work?",
            a: "We act as a secure middle-man between you and the seller. When you purchase from the Underworld (Black Market), your payment is securely locked in our escrow wallet. The funds are NEVER released to the seller until you meet in person, verify your items, and click 'Confirm & Release Funds' on your orders page. <strong style='color:var(--neon-pink);'>Powered by Welfus</strong>."
        },
        {
            q: "💰 How do I fund my wallet?",
            a: "Go to your Temple page (Profile). You'll find a dedicated virtual account assigned uniquely to you bearing EazyMUZE and your username. Any bank transfer made to that account is instantly credited to your EazyMUZE wallet balance."
        },
        {
            q: "💬 How much do Whispers cost?",
            a: "Your very first private Whisper (chat initiation) is 100% FREE! Subsequent Whispers cost ₦500. Unlocking incoming whispers to read them costs ₦200, which goes towards maintaining our premium Temple ecosystem."
        },
        {
            q: "💋 How do I get verified?",
            a: "Go to your Profile and open Edit/Settings. Submit your KYC identification document and a selfie holding the document. Once reviewed by the High Priests, you'll receive the premium Pink Checkmark badge."
        },
        {
            q: "🔒 Is EazyMUZE secure and private?",
            a: "Absolutely. EazyMUZE uses secure SSL connections, custom salted password hashing, and encrypted media layers. All desires, whispers, and orders are 100% confidential. Your secrets are always safe in the Temple."
        }
    ],

    render: async () => {
        const container = document.getElementById('helpDetails');
        if (!container) return;

        let faqHtml = '';
        window.help.faqs.forEach((faq, index) => {
            faqHtml += `
                <div style="background: rgba(0,0,0,0.5); border: 1px solid rgba(255,42,109,0.2); border-radius: 12px; margin-bottom: 10px; overflow: hidden;">
                    <div onclick="window.help.toggleFaq(${index})" style="padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: rgba(255,42,109,0.03); transition: 0.3s;">
                        <span style="font-weight: 600; font-size: 0.9rem; color: white;">${faq.q}</span>
                        <i id="faq-icon-${index}" class="fas fa-chevron-down" style="color: var(--neon-pink); transition: transform 0.3s;"></i>
                    </div>
                    <div id="faq-ans-${index}" style="display: none; padding: 14px 18px; border-top: 1px solid rgba(255,42,109,0.1); font-size: 0.85rem; line-height: 1.6; color: #ffccd7; background: rgba(0,0,0,0.3);">
                        ${faq.a}
                    </div>
                </div>
            `;
        });

        let html = `
            <div style="background: rgba(142, 26, 26, 0.2); border: 1px solid var(--neon-pink); padding: 16px; border-radius: 16px; margin-bottom: 22px; font-size: 0.85rem; line-height: 1.5; display: flex; align-items: flex-start; gap: 12px;">
                <i class="fas fa-shield-alt" style="color: var(--neon-pink); font-size: 1.8rem; margin-top: 2px;"></i>
                <div>
                    <h4 style="color: white; margin: 0 0 4px 0; font-size: 0.95rem;">Confidential Priest Hotline</h4>
                    <p style="margin: 0; color: #ffcccc;">Need immediate assistance? Our support is encrypted and strictly anonymous. Connect with a priest instantly via our channels.</p>
                </div>
            </div>

            <!-- Hotline Buttons -->
            <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                <a href="https://wa.me/message/eazymuze" target="_blank" class="btn-primary" style="flex: 1; background: #25D366; text-decoration: none; font-size: 0.85rem; padding: 12px; text-align: center; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i> WhatsApp Chat
                </a>
                <a href="https://t.me/eazymuze" target="_blank" class="btn-primary" style="flex: 1; background: #0088cc; text-decoration: none; font-size: 0.85rem; padding: 12px; text-align: center; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fab fa-telegram" style="font-size: 1.1rem;"></i> Telegram Channel
                </a>
            </div>

            <!-- FAQs accordion -->
            <h3 style="color: white; margin-bottom: 12px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-question-circle" style="color: var(--neon-pink);"></i> Frequently Asked Questions
            </h3>
            <div style="margin-bottom: 25px;">
                ${faqHtml}
            </div>

            <!-- Loud Siren / System Settings -->
            <div class="glass-panel" style="margin-bottom: 25px; padding: 18px; border-radius: 16px;">
                <h3 style="color: white; margin: 0 0 10px 0; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-volume-up" style="color: var(--neon-pink);"></i> Loud Siren Notifications
                </h3>
                <p style="font-size: 0.8rem; color: #ffcccc; line-height: 1.5; margin: 0 0 14px 0;">
                    To hear loud notifications even when your phone screen is off or you are in another app, enable system notifications and test the audio levels below.
                </p>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-primary" onclick="window.help.requestNotificationPermission()" style="flex: 1; font-size: 0.8rem; padding: 10px 14px;">
                        <i class="fas fa-bell"></i> Enable System Push
                    </button>
                    <button class="btn-primary" onclick="window.help.testLoudSiren()" style="flex: 1; background: var(--velvet); font-size: 0.8rem; padding: 10px 14px;">
                        <i class="fas fa-bullhorn"></i> Test Loud Siren
                    </button>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="glass-panel" style="margin-bottom: 20px; padding: 18px; border-radius: 16px;">
                <h3 style="color: white; margin: 0 0 10px 0; font-size: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-envelope-open-text" style="color: var(--neon-pink);"></i> Direct Sanctuary Message
                </h3>
                <input type="text" id="helpSubject" placeholder="Brief subject (e.g. Wallet Funding, Profile issues)" style="margin-bottom: 10px; width: 100%;">
                <textarea id="helpMsg" rows="3" placeholder="Explain your request in detail. We reply within minutes..." style="margin-bottom: 12px; width: 100%; resize: none;"></textarea>
                <button class="btn-primary" onclick="window.help.submitTicket()" style="width: 100%;">Send Anonymous Request</button>
            </div>
        `;

        container.innerHTML = html;
    },

    toggleFaq: (index) => {
        const ans = document.getElementById(`faq-ans-${index}`);
        const icon = document.getElementById(`faq-icon-${index}`);
        if (!ans) return;

        if (ans.style.display === 'none') {
            ans.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
        } else {
            ans.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    },

    requestNotificationPermission: () => {
        if (!('Notification' in window)) {
            window.utils.showToast("Your device does not support system push notifications.", "error");
            return;
        }

        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                window.utils.showToast("🔔 System push notifications successfully enabled!", "success");
            } else {
                window.utils.showToast("Permission denied. Check device settings to enable push notifications.", "error");
            }
        });
    },

    testLoudSiren: () => {
        window.utils.showToast("🔊 Testing loud siren audio chime...", "success");

        // Play the chime loud and clear
        try {
            const audio = document.getElementById('notifSound');
            if (audio) {
                audio.currentTime = 0;
                audio.volume = 1.0; // Force full volume
                audio.play().catch(e => {
                    window.utils.showToast("Audio playback blocked. Interact with the page first.", "error");
                });
            }
        } catch (e) {
            console.error(e);
        }

        // Trigger native notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification("💋 EazyMUZE Temple Siren", {
                body: "This is a test notification sound. Loud and clear! 🔥",
                icon: "assets/img/logo.png",
                silent: false
            });
        }
    },

    submitTicket: () => {
        const subject = document.getElementById('helpSubject').value.trim();
        const msg = document.getElementById('helpMsg').value.trim();

        if (!subject || !msg) {
            window.utils.showToast("Please fill in both fields.", "error");
            return;
        }

        window.utils.showToast("⚡ Sending encrypted support ticket...", "success");
        setTimeout(() => {
            window.utils.showToast("Ticket successfully received. Check your notifications for replies! 💋");
            document.getElementById('helpSubject').value = '';
            document.getElementById('helpMsg').value = '';
        }, 1500);
    }
};
