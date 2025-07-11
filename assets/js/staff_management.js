

document.addEventListener('DOMContentLoaded', function() {
    
    const modals = document.querySelectorAll('.staff-modal');
    const closeButtons = document.querySelectorAll('.staff-modal-close, .close-modal');
    
    
    setupModals();
    
    
    setupSearch();
    
    
    setupStaffTable();
    
    
    setupForms();
});

function showStaffModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    
    const modalBody = modal.querySelector('.staff-modal-body');
    modalBody.scrollTop = 0;
    
    
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
}

function closeStaffModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    
    document.body.style.position = '';
    document.body.style.width = '';
}


function setupModals() {
    
    document.querySelectorAll('.staff-modal-close, .close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.staff-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });
    
    
    document.querySelectorAll('.staff-modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    });
}


function setupSearch() {
    const searchInput = document.getElementById('staffSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.staff-table tbody tr');
        
        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            row.style.display = rowText.includes(searchTerm) ? '' : 'none';
        });
    });
}


function setupStaffTable() {
    
    document.querySelectorAll('.staff-action-btn.view').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            fetchStaffDetails(staffId);
        });
    });
    
    
    document.querySelectorAll('.staff-action-btn.edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            fetchStaffForEdit(staffId);
        });
    });
    
    
    document.querySelectorAll('.staff-action-btn.reset').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            const row = this.closest('tr');
            const username = row.querySelector('td:nth-child(3)').textContent;
            
            document.getElementById('reset-staff-id').value = staffId;
            document.getElementById('resetPasswordModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
    
    
    document.querySelectorAll('.staff-action-btn.suspend').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            if (confirm('آیا از غیرفعال کردن این استف اطمینان دارید؟')) {
                toggleStaffStatus(staffId, false);
            }
        });
    });
    
    
    document.querySelectorAll('.staff-action-btn.activate').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            if (confirm('آیا از فعال کردن این استف اطمینان دارید؟')) {
                toggleStaffStatus(staffId, true);
            }
        });
    });
        document.querySelectorAll('.staff-action-btn.verify').forEach(btn => {
        btn.addEventListener('click', function() {
            
            if (this.classList.contains('disabled')) {
                return;
            }
            const staffId = this.dataset.id;
            if (confirm('آیا از تایید هویت این استف اطمینان دارید؟')) {
                verifyStaff(staffId);
            }
        });
    });
    
    
    document.querySelectorAll('.staff-action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const staffId = this.dataset.id;
            document.getElementById('delete-staff-id').value = staffId;
            document.getElementById('deleteStaffModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
}


function setupForms() {
    
    const editForm = document.getElementById('editStaffForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitStaffForm(this, '/staff_manage/edit_staff.php');
        });
    }
    
    
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPass = this.querySelector('input[name="new_password"]').value;
            const confirmPass = this.querySelector('input[name="confirm_password"]').value;
            
            if (newPass !== confirmPass) {
                showNotification('رمزهای عبور وارد شده مطابقت ندارند!', 'error');
                return;
            }
            
            submitStaffForm(this, '/staff_manage/reset_staff_password.php');
        });
    }
    
    
    const deleteForm = document.getElementById('deleteStaffForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitStaffForm(this, '/staff_manage/delete_staff.php');
        });
    }
}




function fetchStaffDetails(staffId) {
    toggleLoading(true);
    
    fetch(`/staff_manage/get_staff_details.php?id=${staffId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateStaffDetails(data.staff);
                showStaffModal('staffDetailsModal'); 
            } else {
                showNotification(data.message || 'خطا در دریافت اطلاعات استف', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        })
        .finally(() => toggleLoading(false));
}


function populateStaffDetails(staff) {
    document.getElementById('detail-fullname').textContent = staff.fullname || '---';
    document.getElementById('detail-username').textContent = staff.username || '---';
    document.getElementById('detail-email').textContent = staff.email || '---';
    document.getElementById('detail-phone').textContent = staff.phone || '---';
    document.getElementById('detail-age').textContent = staff.age || '---';
    document.getElementById('detail-discord').textContent = staff.discord_id || '---';
    document.getElementById('detail-steam').textContent = staff.steam_id || '---';
    document.getElementById('detail-created-at').textContent = staff.created_at ? 
        new Date(staff.created_at).toLocaleString('fa-IR') : '---';
    document.getElementById('detail-status').textContent = staff.is_active ? 'فعال' : 'غیرفعال';
}


function fetchStaffForEdit(staffId) {
    toggleLoading(true);
    
    fetch(`/staff_manage/get_staff_details.php?id=${staffId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEditForm(data.staff);
                document.getElementById('editStaffModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                showNotification(data.message || 'خطا در دریافت اطلاعات استف', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        })
        .finally(() => toggleLoading(false));
}


function populateEditForm(staff) {
    document.getElementById('edit-staff-id').value = staff.id;
    document.getElementById('edit-fullname').value = staff.fullname || '';
    document.getElementById('edit-username').value = staff.username || '';
    document.getElementById('edit-email').value = staff.email || '';
    document.getElementById('edit-phone').value = staff.phone || '';
    document.getElementById('edit-discord').value = staff.discord_id || '';
    document.getElementById('edit-steam').value = staff.steam_id || '';
}


function toggleStaffStatus(staffId, isActive) {
    toggleLoading(true);
    
    fetch('/staff_manage/toggle_staff_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `staff_id=${staffId}&is_active=${isActive ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`استف با موفقیت ${isActive ? 'فعال' : 'غیرفعال'} شد`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message || `خطا در ${isActive ? 'فعال' : 'غیرفعال'} کردن استف`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    })
    .finally(() => toggleLoading(false));
}

function verifyStaff(staffId) {
    toggleLoading(true);

    fetch('/staff_manage/verify_staff_ajax.php', { 
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `staff_id=${staffId}`
    })
    .then(response => {
        if (!response.ok) throw new Error(`Server responded with ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('استف با موفقیت تایید شد!', 'success');
            setTimeout(() => location.reload(), 1500); 
        } else {
            showNotification(data.message || 'خطا در تایید هویت استف', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور: ' + error.message, 'error');
    })
    .finally(() => toggleLoading(false));
}



function submitStaffForm(form, actionUrl) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال پردازش...';
    
    fetch(actionUrl, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message || 'عملیات با موفقیت انجام شد', 'success');
            if (form.id === 'deleteStaffForm') {
                setTimeout(() => location.reload(), 1500);
            } else {
                document.querySelector('.staff-modal').style.display = 'none';
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            showNotification(data.message || 'خطا در انجام عملیات', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}


function showNotification(message, type = 'success') {
    const container = document.querySelector('.notification-container') || createNotificationContainer();
    
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
    
    container.appendChild(notification);
    
    
    setTimeout(() => {
        notification.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => notification.remove(), 500);
    }, 5000);
    
    
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.remove();
    });
}


function createNotificationContainer() {
    const container = document.createElement('div');
    container.className = 'notification-container top-right';
    document.body.appendChild(container);
    return container;
}


function toggleLoading(show) {
    const loader = document.getElementById('loading-overlay') || createLoader();
    loader.style.display = show ? 'flex' : 'none';
}


function createLoader() {
    const loader = document.createElement('div');
    loader.id = 'loading-overlay';
    loader.style.display = 'none';
    loader.style.position = 'fixed';
    loader.style.top = '0';
    loader.style.left = '0';
    loader.style.width = '100%';
    loader.style.height = '100%';
    loader.style.backgroundColor = 'rgba(0,0,0,0.7)';
    loader.style.justifyContent = 'center';
    loader.style.alignItems = 'center';
    loader.style.zIndex = '9999';
    
    loader.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: white;"></i>
        </div>
    `;
    
    document.body.appendChild(loader);
    return loader;
}