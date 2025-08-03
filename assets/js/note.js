function showNotification(message, type = 'success') {
    const container = document.querySelector('.notification-container');
    if (!container) return;
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    notification.innerHTML = `<i class="fas ${iconClass}"></i><span>${message}</span>`;
    container.style.display = 'block';
    container.appendChild(notification);
    setTimeout(() => {
        notification.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => {
            notification.remove();
            if (container.children.length === 0) {
                 container.style.display = 'none';
            }
        }, 500);
    }, 4000);
}

    window.openTab = function(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        document.querySelectorAll('.tab-link').forEach(tl => tl.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }