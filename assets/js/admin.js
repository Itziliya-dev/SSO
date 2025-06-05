// فایل کامل و به‌روز شده: assets/js/admin.js

function showNotification(message, type = 'success', position = 'top-right', duration = 5000) {
    const container = document.querySelector(`.notification-container.${position}`) || createNotificationContainer(position);
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    let icon;
    switch(type) {
        case 'success': icon = '<i class="fas fa-check-circle"></i>'; break;
        case 'error': icon = '<i class="fas fa-exclamation-circle"></i>'; break;
        case 'warning': icon = '<i class="fas fa-exclamation-triangle"></i>'; break;
        default: icon = '<i class="fas fa-info-circle"></i>';
    }
    
    notification.innerHTML = `${icon} <span>${message}</span> <span class="notification-close">&times;</span>`;
    
    notification.querySelector('.notification-close').addEventListener('click', () => notification.remove());
    container.appendChild(notification);
    
    if (duration > 0) {
        setTimeout(() => notification.remove(), duration);
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
        button.dataset.originalText = button.innerHTML; // ذخیره متن اصلی دکمه
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
        resetPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (document.getElementById('newPassword').value !== document.getElementById('confirmPassword').value) {
                await Dialog.alert('خطا', 'رمزهای عبور وارد شده مطابقت ندارند!');
                return;
            }
            const submitBtn = this.querySelector('button[type="submit"]');
            toggleButton(submitBtn, true);
            try {
                const response = await fetch('/admin/actions/reset_password.php', { method: 'POST', body: new FormData(this) });
                const data = await response.json();
                if (data.success) {
                    await Dialog.alert('موفقیت', 'رمز عبور با موفقیت تغییر یافت.');
                    document.getElementById('resetPasswordModal').style.display = 'none';
                    this.reset();
                } else {
                    await Dialog.alert('خطا', data.message || 'خطا در تغییر رمز عبور');
                }
            } catch (error) {
                await Dialog.alert('خطای سرور', 'خطا در ارتباط با سرور');
                console.error('Error:', error);
            } finally {
                toggleButton(submitBtn, false);
            }
        });
    }

    const suspendUserForm = document.getElementById('suspendUserForm');
    if (suspendUserForm) {
        suspendUserForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            toggleButton(submitBtn, true);
            try {
                const response = await fetch('suspend_user.php', { method: 'POST', body: new FormData(this) });
                const data = await response.json();
                if (data.success) {
                    await Dialog.alert('موفقیت', 'کاربر با موفقیت غیرفعال شد.');
                    document.getElementById('suspendUserModal').style.display = 'none';
                    location.reload();
                } else {
                    await Dialog.alert('خطا', data.message || 'خطا در غیرفعال کردن کاربر');
                }
            } catch (error) {
                await Dialog.alert('خطای سرور', 'خطا در ارتباط با سرور');
                console.error('Error:', error);
            } finally {
                toggleButton(submitBtn, false);
            }
        });
    }
}

function setupButtons() {
    document.querySelectorAll('.reset-password').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('resetUserId').value = this.dataset.id;
            document.getElementById('modalUsername').textContent = this.dataset.username;
            document.getElementById('resetPasswordModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    document.querySelectorAll('.suspend-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('suspendUserId').value = this.dataset.id;
            document.getElementById('suspendUserModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });

    document.querySelectorAll('.activate-user').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.id;
            if (await Dialog.confirm('فعال کردن کاربر', 'آیا از فعال کردن این کاربر اطمینان دارید؟')) {
                try {
                    const response = await fetch('activate_user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${userId}`
                    });
                    const data = await response.json();
                    if (data.success) {
                        showNotification('کاربر با موفقیت فعال شد', 'success');
                        location.reload();
                    } else {
                        showNotification(data.message || 'خطا در فعال کردن کاربر', 'error');
                    }
                } catch (error) {
                    showNotification('خطا در ارتباط با سرور', 'error');
                    console.error('Error:', error);
                }
            }
        });
    });

    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.id;
            const row = this.closest('tr');
            if (await Dialog.confirm('حذف کاربر', 'آیا از حذف دائمی این کاربر اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
                row.style.opacity = '0.5';
                try {
                    const response = await fetch('delete_user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `user_id=${userId}`
                    });
                    const data = await response.json();
                    if (data.success) {
                        row.remove();
                        showNotification('کاربر با موفقیت حذف شد', 'success');
                    } else {
                        showNotification(data.message || 'خطا در حذف کاربر', 'error');
                        row.style.opacity = '1';
                    }
                } catch (error) {
                    showNotification('خطا در ارتباط با سرور', 'error');
                    console.error('Error:', error);
                    row.style.opacity = '1';
                }
            }
        });
    });
}

function setupRequestActions() {
    document.querySelectorAll('.view-request').forEach(btn => {
        btn.addEventListener('click', async function() {
            const requestId = this.dataset.id;
            try {
                const response = await fetch(`/admin/actions/get_request_details.php?id=${requestId}`);
                const data = await response.json();
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
                            data.request.status === 'staff' ? 'استف' : 
                            (data.request.status === 'approved' ? 'تایید شده' : 'رد شده');
                    document.getElementById('requestDetailsModal').style.display = 'block';
                    document.body.style.overflow = 'hidden';
                } else {
                    await Dialog.alert('خطا', 'خطا در دریافت اطلاعات درخواست');
                }
            } catch (error) {
                await Dialog.alert('خطای سرور', 'خطا در ارتباط با سرور');
                console.error('Error:', error);
            }
        });
    });

document.querySelectorAll('.approve-request').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.id;
        const row = this.closest('tr'); 

        if (!row) {
            console.error('ردیف والد برای دکمه approve-request پیدا نشد.');
            return;
        }

        if (await Dialog.confirm('تایید درخواست', 'آیا از تایید این درخواست و ایجاد کاربر اطمینان دارید؟')) {
            try {
                const response = await fetch('/admin/actions/process_request.php', { // مسیر صحیح را بررسی کنید
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=approve&id=${requestId}`
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('درخواست با موفقیت تایید شد', 'success');
                    const statusBadge = row.querySelector('.status-badge'); // بررسی وجود اِلِمان
                    if (statusBadge) { // اگر statusBadge وجود داشت، آن را آپدیت کن
                        statusBadge.className = 'status-badge approved';
                        statusBadge.textContent = 'تایید شده';
                    }
                    // در داشبورد، چون این اِلِمان نیست، این بخش اجرا نمی‌شود و خطایی هم نمی‌دهد
                } else {
                    showNotification(data.message || 'خطا در تایید درخواست', 'error');
                }
            } catch (error) {
                showNotification('خطا در ارتباط با سرور: ' + error.message, 'error');
                console.error('Error in approve-request:', error);
            }
        }
    });
});

document.querySelectorAll('.staff-request').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.id;
        const row = this.closest('tr');

        if (!row) {
            console.error('ردیف والد برای دکمه staff-request پیدا نشد.');
            return;
        }

        if (await Dialog.confirm('تایید به عنوان استف', 'آیا از تایید این درخواست به عنوان استف اطمینان دارید؟ اطلاعات به سیستم مدیریت استف ها منتقل خواهد شد.')) {
            try {
                const response = await fetch('/admin/actions/process_request.php', { // مسیر صحیح را بررسی کنید
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=staff&id=${requestId}`
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('اطلاعات با موفقیت به سیستم استف ها منتقل شد', 'success');
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) { // اگر statusBadge وجود داشت، آن را آپدیت کن
                        statusBadge.className = 'status-badge staff';
                        statusBadge.textContent = 'Staff';
                    }
                    // غیرفعال کردن سایر دکمه‌ها در همان ردیف (اگر وجود دارند)
                    row.querySelectorAll('.approve-request, .reject-request, .staff-request').forEach(b => {
                        if(b) b.disabled = true; // بررسی وجود دکمه قبل از غیرفعال کردن
                    });
                } else {
                    showNotification(data.message || 'خطا در انتقال به سیستم استف ها', 'error');
                }
            } catch (error) {
                showNotification('خطا در ارتباط با سرور: ' + error.message, 'error');
                console.error('Error in staff-request:', error);
            }
        }
    });
});

document.querySelectorAll('.reject-request').forEach(btn => {
    btn.addEventListener('click', async function() {
        const requestId = this.dataset.id;
        const row = this.closest('tr');

        if (!row) {
            console.error('ردیف والد برای دکمه reject-request پیدا نشد.');
            return;
        }

        if (await Dialog.confirm('رد درخواست', 'آیا از رد کردن این درخواست اطمینان دارید؟')) {
            try {
                const response = await fetch('/admin/actions/process_request.php', { // مسیر صحیح را بررسی کنید
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=reject&id=${requestId}`
                });
                const data = await response.json();
                if (data.success) {
                    showNotification('درخواست با موفقیت رد شد', 'success');
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) { // اگر statusBadge وجود داشت، آن را آپدیت کن
                        statusBadge.className = 'status-badge rejected';
                        statusBadge.textContent = 'رد شده';
                    }
                } else {
                    showNotification(data.message || 'خطا در رد درخواست', 'error');
                }
            } catch (error) {
                showNotification('خطا در ارتباط با سرور: ' + error.message, 'error');
                console.error('Error in reject-request:', error);
            }
        }
    });
});
}

document.addEventListener('DOMContentLoaded', function() {
    const securityAlertsBtn = document.getElementById('securityAlertsBtn');
    const alertBadge = document.getElementById('alertBadge');
    
    let unreadAlertsCount = 0;
    let alertsCheckInterval;

    const checkUnreadAlerts = async () => {
        try {
            const response = await fetch('/../includes/check_alerts.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            updateAlertCount(data.count || 0);
        } catch (error) {
            console.error('Error fetching alerts:', error);
        }
    };

    const updateAlertCount = (count) => {
        unreadAlertsCount = count;
        securityAlertsBtn.classList.remove('has-alerts', 'critical-alert-btn');
        alertBadge.classList.remove('new-alert', 'critical-alert');
        
        if (unreadAlertsCount > 0) {
            alertBadge.style.display = 'flex';
            alertBadge.textContent = unreadAlertsCount > 9 ? '9+' : unreadAlertsCount;
            
            if (unreadAlertsCount >= 5) {
                securityAlertsBtn.classList.add('critical-alert-btn');
                alertBadge.classList.add('critical-alert');
            } else {
                securityAlertsBtn.classList.add('has-alerts');
                alertBadge.classList.add('new-alert');
            }
        } else {
            alertBadge.style.display = 'none';
        }
    };

    const markAlertsAsRead = async () => {
        try {
            const response = await fetch('includes/mark_alerts_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ mark_as_read: true })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.success) {
                updateAlertCount(0);
            }
        } catch (error) {
            console.error('Error marking alerts as read:', error);
        }
    };

    securityAlertsBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        if (unreadAlertsCount > 0) {
            await markAlertsAsRead();
        }
        window.location.href = '/admin/actions/security_alerts.php';
    });

    checkUnreadAlerts();
    alertsCheckInterval = setInterval(checkUnreadAlerts, 10000);
    window.addEventListener('beforeunload', () => clearInterval(alertsCheckInterval));
});

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const body = document.body;
    const toggleIcon = sidebarToggleBtn ? sidebarToggleBtn.querySelector('i') : null;

    const SIDEBAR_COLLAPSED_PREF = 'adminSidebarCollapsed'; // کلید برای localStorage

    // تابع برای اعمال وضعیت ذخیره شده سایدبار
    function applySidebarState() {
        if (localStorage.getItem(SIDEBAR_COLLAPSED_PREF) === 'true') {
            body.classList.add('sidebar-is-collapsed');
            if (toggleIcon) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times'); // یا مثلا 'fa-arrow-left' و 'fa-arrow-right'
            }
        } else {
            body.classList.remove('sidebar-is-collapsed');
            if (toggleIcon) {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
        }
    }

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            body.classList.toggle('sidebar-is-collapsed');
            
            // ذخیره وضعیت در localStorage
            if (body.classList.contains('sidebar-is-collapsed')) {
                localStorage.setItem(SIDEBAR_COLLAPSED_PREF, 'true');
                if (toggleIcon) {
                    toggleIcon.classList.remove('fa-bars');
                    toggleIcon.classList.add('fa-times');
                }
            } else {
                localStorage.setItem(SIDEBAR_COLLAPSED_PREF, 'false');
                if (toggleIcon) {
                    toggleIcon.classList.remove('fa-times');
                    toggleIcon.classList.add('fa-bars');
                }
            }
        });
    }

    // اعمال وضعیت اولیه سایدبار هنگام بارگذاری صفحه
    applySidebarState();
});


document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی اولیه کانتینرهای نوتیفیکیشن
    createNotificationContainer('top-right');
    createNotificationContainer('bottom-right');

    // راه‌اندازی بخش‌های مختلف پنل
    setupModals();
    setupForms();
    setupButtons();
    setupRequestActions();
    
    // کد مربوط به بج هشدارهای امنیتی در سایدبار
    const securityAlertsBtn = document.getElementById('sidebarSecurityAlertsBtn');
    const alertBadge = document.getElementById('sidebarAlertBadge');
    
    if (securityAlertsBtn && alertBadge) {
        let alertsCheckInterval;
        const checkUnreadAlerts = async () => {
            try {
                // **مهم:** مسیر fetch را بر اساس ساختار پوشه‌های خود تنظیم کنید
                // مثال: اگر پوشه includes در ریشه باشد: '../includes/check_alerts.php'
                const response = await fetch('/../includes/check_alerts.php');
                if (!response.ok) return;
                const data = await response.json();
                updateAlertCount(data.count || 0);
            } catch (error) {
                console.error('Error fetching alerts:', error);
            }
        };

        const updateAlertCount = (count) => {
            securityAlertsBtn.classList.remove('has-alerts', 'critical-alert-btn');
            alertBadge.classList.remove('new-alert', 'critical-alert');
            
            if (count > 0) {
                alertBadge.style.display = 'flex';
                alertBadge.textContent = count > 9 ? '9+' : count;
                if (count >= 5) {
                    securityAlertsBtn.classList.add('critical-alert-btn');
                    alertBadge.classList.add('critical-alert');
                } else {
                    securityAlertsBtn.classList.add('has-alerts');
                    alertBadge.classList.add('new-alert');
                }
            } else {
                alertBadge.style.display = 'none';
            }
        };

        checkUnreadAlerts();
        alertsCheckInterval = setInterval(checkUnreadAlerts, 15000);
        window.addEventListener('beforeunload', () => clearInterval(alertsCheckInterval));
    }
});
document.addEventListener('DOMContentLoaded', function() {
    setupModals();
    setupForms();
    setupButtons();
    setupRequestActions(); 
    createNotificationContainer('top-right');
    createNotificationContainer('bottom-right');
});