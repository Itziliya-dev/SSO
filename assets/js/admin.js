


function showNotification(message, type = 'success', position = 'top-right', duration = 5000) {
    const container = document.querySelector(`.notification-container.${position}`) || createNotificationContainer(position);
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    let icon;
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'error':
            icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        default:
            icon = '<i class="fas fa-info-circle"></i>';
    }
    
    notification.innerHTML = `
        ${icon}
        <span>${message}</span>
        <span class="notification-close">&times;</span>
    `;
    
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.remove();
    });
    
    container.appendChild(notification);
    
    if (duration > 0) {
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
    
    return notification;
}

function createNotificationContainer(position) {
    const container = document.createElement('div');
    container.className = `notification-container ${position}`;
    document.body.appendChild(container);
    return container;
}


function toggleButton(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال پردازش...';
    } else {
        button.disabled = false;
        button.innerHTML = button.dataset.originalText || 'ذخیره';
    }
}


function setupModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
    });

    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}


function setupForms() {
    
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                showNotification('رمزهای عبور وارد شده مطابقت ندارند!', 'error');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            toggleButton(submitBtn, true);
            
            fetch('reset_password.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('رمز عبور با موفقیت تغییر یافت', 'success');
                    document.getElementById('resetPasswordModal').style.display = 'none';
                    this.reset();
                } else {
                    showNotification(data.message || 'خطا در تغییر رمز عبور', 'error');
                }
            })
            .catch(error => {
                showNotification('خطا در ارتباط با سرور', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                toggleButton(submitBtn, false);
            });
        });
    }

    
    const suspendUserForm = document.getElementById('suspendUserForm');
    if (suspendUserForm) {
        suspendUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            toggleButton(submitBtn, true);
            
            fetch('suspend_user.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('کاربر با موفقیت غیرفعال شد', 'success');
                    document.getElementById('suspendUserModal').style.display = 'none';
                    location.reload();
                } else {
                    showNotification(data.message || 'خطا در غیرفعال کردن کاربر', 'error');
                }
            })
            .catch(error => {
                showNotification('خطا در ارتباط با سرور', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                toggleButton(submitBtn, false);
            });
        });
    }
}


function setupButtons() {
    
    document.querySelectorAll('.reset-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            const username = this.dataset.username;
            
            document.getElementById('resetUserId').value = userId;
            document.getElementById('modalUsername').textContent = username;
            document.getElementById('resetPasswordModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    
    document.querySelectorAll('.suspend-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            document.getElementById('suspendUserId').value = userId;
            document.getElementById('suspendUserModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    
    document.querySelectorAll('.activate-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            
            if (confirm('آیا از فعال کردن این کاربر اطمینان دارید؟')) {
                fetch('activate_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('کاربر با موفقیت فعال شد', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message || 'خطا در فعال کردن کاربر', 'error');
                    }
                })
                .catch(error => {
                    showNotification('خطا در ارتباط با سرور', 'error');
                    console.error('Error:', error);
                });
            }
        });
    });

    
    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id;
            const row = this.closest('tr');
            
            if (confirm('آیا از حذف این کاربر اطمینان دارید؟')) {
                row.style.opacity = '0.5';
                
                fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        showNotification('کاربر با موفقیت حذف شد', 'success');
                    } else {
                        showNotification(data.message || 'خطا در حذف کاربر', 'error');
                        row.style.opacity = '1';
                    }
                })
                .catch(error => {
                    showNotification('خطا در ارتباط با سرور', 'error');
                    console.error('Error:', error);
                    row.style.opacity = '1';
                });
            }
        });
    });
}





function setupRequestActions() {
    
    document.querySelectorAll('.view-request').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.dataset.id;
            
            fetch(`get_request_details.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        
                        document.getElementById('detail-tracking-code').textContent = data.request.tracking_code;
                        document.getElementById('detail-fullname').textContent = data.request.fullname;
                        document.getElementById('detail-username').textContent = data.request.username;
                        document.getElementById('detail-email').textContent = data.request.email;
                        document.getElementById('detail-phone').textContent = data.request.phone;
                        document.getElementById('detail-age').textContent = data.request.age;
                        document.getElementById('detail-discord').textContent = data.request.discord_id;
                        document.getElementById('detail-steam').textContent = data.request.steam_id;
                        document.getElementById('detail-created-at').textContent = new Date(data.request.created_at).toLocaleString('fa-IR');
                        document.getElementById('detail-status').textContent = 
                            data.request.status === 'pending' ? 'در حال بررسی' : 
                            (data.request.status === 'approved' ? 'تایید شده' : 'رد شده');
                        
                        document.getElementById('requestDetailsModal').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        showNotification('خطا در دریافت اطلاعات درخواست', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('خطا در ارتباط با سرور', 'error');
                });
        });
    });

    
    document.querySelectorAll('.approve-request').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.dataset.id;
            const row = this.closest('tr');
            
            if (confirm('آیا از تایید این درخواست اطمینان دارید؟')) {
                fetch('process_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=approve&id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('درخواست با موفقیت تایید شد', 'success');
                        row.querySelector('.status-badge').className = 'status-badge approved';
                        row.querySelector('.status-badge').textContent = 'تایید شده';
                    } else {
                        showNotification(data.message || 'خطا در تایید درخواست', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('خطا در ارتباط با سرور', 'error');
                });
            }
        });
    });


    

    
    document.querySelectorAll('.staff-request').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.dataset.id;
            const row = this.closest('tr');
            
            if (confirm('آیا از تایید این درخواست به عنوان استف اطمینان دارید؟ اطلاعات به سیستم مدیریت استف ها منتقل خواهد شد.')) {
                fetch('process_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=staff&id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('اطلاعات با موفقیت به سیستم استف ها منتقل شد', 'success');
                        row.querySelector('.status-badge').className = 'status-badge staff';
                        row.querySelector('.status-badge').textContent = 'Staff';
                        
                        
                        row.querySelectorAll('.approve-request, .reject-request, staff-request').forEach(btn => {
                            btn.disabled = true;
                        });
                    } else {
                        showNotification(data.message || 'خطا در انتقال به سیستم استف ها', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('خطا در ارتباط با سرور', 'error');
                });
            }
        });
    });
}
    
    document.querySelectorAll('.reject-request').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.dataset.id;
            const row = this.closest('tr');
            
            if (confirm('آیا از رد این درخواست اطمینان دارید؟')) {
                fetch('process_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reject&id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('درخواست با موفقیت رد شد', 'success');
                        row.querySelector('.status-badge').className = 'status-badge rejected';
                        row.querySelector('.status-badge').textContent = 'رد شده';
                    } else {
                        showNotification(data.message || 'خطا در رد درخواست', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('خطا در ارتباط با سرور', 'error');
                });
            }
        });
    });





document.addEventListener('DOMContentLoaded', function() {
    setupModals();
    setupForms();
    setupButtons();
    setupRequestActions(); 
    createNotificationContainer('top-right');
    createNotificationContainer('bottom-right');
});