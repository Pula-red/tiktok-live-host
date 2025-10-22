// Live Seller Panel JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize live seller specific functionality
    initLiveSellerDashboard();
    
    // Stream management functions
    initStreamManagement();
    
    // Live status monitoring
    initLiveStatusMonitoring();
    
    // Performance tracking
    initPerformanceTracking();
});

function initLiveSellerDashboard() {
    // Add animation to stream items
    const streamItems = document.querySelectorAll('.stream-item');
    streamItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.classList.add('fade-in-stream');
    });

    // Add hover effects to performance stats
    const perfItems = document.querySelectorAll('.perf-item');
    perfItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(255, 255, 255, 0.05)';
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.background = 'transparent';
            this.style.transform = 'translateX(0)';
        });
    });
}

function initStreamManagement() {
    // Stream action buttons
    const streamActions = document.querySelectorAll('.action-btn');
    streamActions.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (this.classList.contains('live')) {
                // Handle live stream management
                handleLiveStreamAction(this);
            } else if (this.classList.contains('edit')) {
                // Handle stream editing
                handleStreamEdit(this);
            }
        });
    });

    // Quick action buttons
    const quickActions = document.querySelectorAll('.quick-action');
    quickActions.forEach(action => {
        action.addEventListener('click', function(e) {
            // Add ripple effect
            createRippleEffect(this, e);
        });
    });
}

function initLiveStatusMonitoring() {
    const liveStatus = document.querySelector('.live-status');
    
    // Simulate live status updates (replace with real API calls)
    function updateLiveStatus() {
        if (!liveStatus) return; // no live-status element on this page
        // This would be replaced with actual API calls to check stream status
        const isLive = Math.random() > 0.8; // Simulate random live status
        
        if (isLive) {
            liveStatus.classList.remove('offline');
            liveStatus.classList.add('live');
            liveStatus.querySelector('.status-text').textContent = 'Live';
        } else {
            liveStatus.classList.remove('live');
            liveStatus.classList.add('offline');
            liveStatus.querySelector('.status-text').textContent = 'Offline';
        }
    }

    // Check status every 30 seconds
    setInterval(updateLiveStatus, 30000);
}

function initPerformanceTracking() {
    // Animate performance stats on load
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            const statValue = card.querySelector('h3');
            if (statValue) {
                animateNumber(statValue, parseInt(statValue.textContent) || 0);
            }
        }, index * 200);
    });
}

function handleLiveStreamAction(button) {
    const streamItem = button.closest('.stream-item');
    streamItem.classList.add('stream-loading');
    
    // Simulate API call
    setTimeout(() => {
        streamItem.classList.remove('stream-loading');
        showLiveSellerNotification('Stream management opened', 'success');
    }, 1500);
}

function handleStreamEdit(button) {
    const streamItem = button.closest('.stream-item');
    const streamTitle = streamItem.querySelector('h4').textContent;
    
    showLiveSellerNotification(`Editing "${streamTitle}"`, 'info');
}

function createRippleEffect(element, event) {
    const ripple = document.createElement('div');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        background: rgba(37, 244, 238, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
        z-index: 1;
    `;
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

function animateNumber(element, targetNumber) {
    const startNumber = 0;
    const duration = 2000;
    const startTime = performance.now();
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function
        const easeOutCubic = 1 - Math.pow(1 - progress, 3);
        const currentNumber = Math.floor(startNumber + (targetNumber - startNumber) * easeOutCubic);
        
        element.textContent = currentNumber.toLocaleString();
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        } else {
            element.textContent = targetNumber.toLocaleString();
        }
    }
    
    requestAnimationFrame(updateNumber);
}

function showLiveSellerNotification(message, type = 'info', duration = 4000) {
    const notification = document.createElement('div');
    notification.className = `live-seller-notification notification-${type}`;
    
    const icons = {
        'success': '✅',
        'error': '❌',
        'warning': '⚠️',
        'info': 'ℹ️',
        'live': '🔴'
    };
    
    const icon = icons[type] || icons.info;
    
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${icon}</span>
            <span class="notification-message">${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--card-bg);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-left: 4px solid var(--secondary-color);
        color: var(--light-text);
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: var(--shadow-card);
        z-index: 10000;
        min-width: 320px;
        max-width: 400px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        animation: slideInRight 0.3s ease-out;
    `;
    
    if (type === 'live') {
        notification.style.borderLeftColor = '#f44336';
        notification.style.background = 'rgba(244, 67, 54, 0.1)';
    }
    
    document.body.appendChild(notification);
    
    // Auto remove
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }, duration);
}

// Stream status simulator (replace with real WebSocket connection)
function simulateStreamUpdates() {
    const streamItems = document.querySelectorAll('.stream-item');
    
    streamItems.forEach(item => {
        const statusElement = item.querySelector('.stream-status');
        const statusBadge = item.querySelector('.status-badge');
        
        // Simulate random status changes
        setInterval(() => {
            if (Math.random() > 0.95) { // 5% chance of status change
                const statuses = ['live', 'scheduled', 'ended'];
                const currentStatus = statusElement.className.split(' ').find(cls => statuses.includes(cls));
                const newStatus = statuses[Math.floor(Math.random() * statuses.length)];
                
                if (currentStatus !== newStatus) {
                    statusElement.className = `stream-status ${newStatus}`;
                    statusBadge.className = `status-badge status-${newStatus}`;
                    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    
                    // Show notification for live status
                    if (newStatus === 'live') {
                        showLiveSellerNotification('Stream is now live!', 'live');
                    }
                }
            }
        }, 5000);
    });
}

// Initialize stream updates on load
setTimeout(simulateStreamUpdates, 2000);

// Keyboard shortcuts for live sellers
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + L: Quick go live
    if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
        e.preventDefault();
        const goLiveBtn = document.querySelector('[href="stream-new.php"]');
        if (goLiveBtn) {
            showLiveSellerNotification('Opening Go Live dialog...', 'info');
            goLiveBtn.click();
        }
    }
    
    // Ctrl/Cmd + D: Go to dashboard
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        window.location.href = 'dashboard.php';
    }
    
    // Ctrl/Cmd + A: View analytics
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        window.location.href = 'analytics.php';
    }
});

// Add ripple animation CSS
const liveSellerStyles = document.createElement('style');
liveSellerStyles.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: var(--gray-text);
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0.25rem;
        border-radius: 4px;
        transition: color 0.3s ease;
    }
    
    .notification-close:hover {
        color: var(--light-text);
        background: rgba(255, 255, 255, 0.1);
    }
`;
document.head.appendChild(liveSellerStyles);