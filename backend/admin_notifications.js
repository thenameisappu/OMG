// Notification Sound System for Admin Panel
(function () {
    let lastCounts = {
        orders: null,
        inquiries: null,
        customisations: null
    };

    const soundUrl = 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3'; // Standard notification sound
    const audio = new Audio(soundUrl);

    // Create UI for notification toggle
    function createNotificationUI() {
        const container = document.querySelector('.header-row');
        if (!container) return;

        const toggleDiv = document.createElement('div');
        toggleDiv.id = 'notif-toggle-container';
        toggleDiv.style.cssText = 'margin-left: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.9em; cursor: pointer; background: #eee; padding: 5px 12px; border-radius: 20px;';

        let notifEnabled = localStorage.getItem('admin_notif_enabled') !== 'false';

        const updateUI = () => {
            toggleDiv.innerHTML = `
                <span style="font-size: 1.2em;">${notifEnabled ? '🔔' : '🔕'}</span>
                <span>Notifications: <strong>${notifEnabled ? 'ON' : 'OFF'}</strong></span>
            `;
            toggleDiv.style.background = notifEnabled ? '#e8f5e9' : '#ffebee';
            toggleDiv.style.color = notifEnabled ? '#2e7d32' : '#c62828';
        };

        toggleDiv.onclick = () => {
            notifEnabled = !notifEnabled;
            localStorage.setItem('admin_notif_enabled', notifEnabled);
            updateUI();

            // Try playing sound once to unlock browser audio
            if (notifEnabled) {
                audio.play().catch(e => console.log('Audio unlock failed:', e));
            }
        };

        updateUI();
        container.appendChild(toggleDiv);
    }

    async function checkNotifications() {
        try {
            const response = await fetch('admin_notifications.php');
            if (!response.ok) return;

            const data = await response.json();
            const isEnabled = localStorage.getItem('admin_notif_enabled') !== 'false';

            if (lastCounts.orders !== null) {
                let hasNew = false;
                let message = "";

                if (data.orders > lastCounts.orders) {
                    hasNew = true;
                    message += "New Order! ";
                }
                if (data.inquiries > lastCounts.inquiries) {
                    hasNew = true;
                    message += "New Inquiry! ";
                }
                if (data.customisations > lastCounts.customisations) {
                    hasNew = true;
                    message += "New Customisation! ";
                }

                if (hasNew && isEnabled) {
                    console.log('Notification:', message);
                    audio.play().catch(e => console.error('Error playing sound:', e));
                    // Optional: show a small toast or visual indicator
                    showNotificationToast(message);
                }
            }

            lastCounts = data;
        } catch (error) {
            console.error('Polling error:', error);
        }
    }

    function showNotificationToast(msg) {
        let toast = document.getElementById('notif-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'notif-toast';
            toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: #333; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; transition: all 0.5s; opacity: 0; transform: translateY(20px); pointer-events: none;';
            document.body.appendChild(toast);
        }

        toast.innerText = msg;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
        }, 5000);
    }

    // Initialize
    window.addEventListener('DOMContentLoaded', () => {
        createNotificationUI();
        checkNotifications();
        setInterval(checkNotifications, 10000);
    });
})();
