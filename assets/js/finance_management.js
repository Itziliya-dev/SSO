document.addEventListener('DOMContentLoaded', function() {
    const requestModal = document.getElementById('requestModal');
    const resourceModal = document.getElementById('resourceModal');
    const requestDetailsModal = document.getElementById('requestDetailsModal'); // مدال جدید


    const notificationData = sessionStorage.getItem('finance_notification');
    if (notificationData) {
        const { message, type } = JSON.parse(notificationData);
        showNotification(message, type);
        sessionStorage.removeItem('finance_notification'); // پاک کردن پیام برای جلوگیری از نمایش مجدد
    }
    // --- منطق عمومی ---
    function openTab(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        document.querySelectorAll('.tab-link').forEach(tl => tl.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }
    window.openTab = openTab;

    function showNotification(message, type = 'success') {
        const container = document.querySelector('.notification-container');
        if (!container) return;
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
        container.style.display = 'block';
        container.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.5s forwards';
            setTimeout(() => notification.remove(), 500);
        }, 4000);
    }
    window.showNotification = showNotification;

    // --- منطق مدال‌ها ---
    document.getElementById('newRequestBtn').onclick = () => { requestModal.style.display = 'flex'; };
    document.getElementById('newResourceBtn').onclick = () => { resourceModal.style.display = 'flex'; };

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.onclick = () => {
            requestModal.style.display = 'none';
            resourceModal.style.display = 'none';
            requestDetailsModal.style.display = 'none'; // بستن مدال جدید
        };
    });
    window.onclick = (event) => {
        if (event.target == requestModal) requestModal.style.display = "none";
        if (event.target == resourceModal) resourceModal.style.display = "none";
        if (event.target == requestDetailsModal) requestDetailsModal.style.display = "none"; // بستن مدال جدید
    };

    // --- منوی کشویی سفارشی برای "ارجاع به" ---
    const assigneeWrapper = document.getElementById('assignee-select');
    if (assigneeWrapper) {
        const assigneeButton = assigneeWrapper.querySelector('.custom-select-button');
        const assigneeButtonText = assigneeWrapper.querySelector('.button-text');
        const assigneePanel = assigneeWrapper.querySelector('.custom-select-panel');
        const assigneeIdInput = document.getElementById('assignee_id_hidden');
        const assigneeTypeInput = document.getElementById('assignee_type_hidden');
        const assigneeHiddenInput = document.getElementById('assignee_id_hidden');

        assigneeButton.addEventListener('click', (e) => {
            e.stopPropagation();
            assigneePanel.classList.toggle('active');
            assigneeButton.classList.toggle('open');
        });

        assigneePanel.querySelectorAll('.custom-select-option').forEach(option => {
            option.addEventListener('click', function() {
                assigneeHiddenInput.value = this.dataset.value;
                assigneeButtonText.textContent = this.textContent.trim();
                assigneePanel.querySelectorAll('.custom-select-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                assigneePanel.classList.remove('active');
                assigneeButton.classList.remove('open');
                assigneeIdInput.value = this.dataset.id;
                assigneeTypeInput.value = this.dataset.type; // <-- ذخیره نوع کاربر
                assigneeButtonText.textContent = this.textContent.trim();
            });
        });
        
        window.addEventListener('click', function(e) {
            if (!assigneeWrapper.contains(e.target)) {
                assigneePanel.classList.remove('active');
                assigneeButton.classList.remove('open');
            }
        });
    }
    
    // --- ارسال فرم درخواست منابع ---
    document.getElementById('requestForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_request');
        
        fetch('actions/finance_api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    requestModal.style.display = 'none';
                    this.reset();
                    // **بخش اصلاح شده**
                    // دوباره دکمه را پیدا کرده و متنش را ریست می‌کنیم
                    const assigneeButtonText = document.querySelector('#assignee-select .button-text');
                    if(assigneeButtonText) {
                        assigneeButtonText.textContent = 'یک کاربر را انتخاب کنید...';
                    }
                    // **پایان بخش اصلاح شده**
                    setTimeout(() => location.reload(), 1500);
                }
            });
    });

    // --- شروع بخش جدید: ارسال فرم ثبت منبع ---
    const resourceForm = document.getElementById('resourceForm');
    if (resourceForm) {
        resourceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_resource');

            fetch('actions/finance_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    showNotification(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        resourceModal.style.display = 'none';
                        this.reset();
                        // مخفی کردن تمام فیلدهای داینامیک بعد از ثبت موفق
                        document.querySelectorAll('.dynamic-fields').forEach(el => el.classList.remove('active'));
                        setTimeout(() => location.reload(), 1500);
                    }
                });
        });
    }
    
    // --- پایان بخش جدید ---
    function showRequestDetails(details) {
        document.getElementById('detail-topic').textContent = details.topic;
        document.getElementById('detail-amount').textContent = (details.amount == 0) ? 'رایگان' : `${Number(details.amount).toLocaleString()} تومان`;
        document.getElementById('detail-requester').textContent = details.requester_name;
        document.getElementById('detail-assignee').textContent = details.assignee_name;
        document.getElementById('detail-date').textContent = new Date(details.created_at).toLocaleDateString('fa-IR');
        document.getElementById('detail-reason').textContent = details.reason;
        requestDetailsModal.style.display = 'flex';
    }
    document.querySelector('#requests .table-container')?.addEventListener('click', async function(e) { // تابع async شد
        const targetButton = e.target.closest('.action-btn');
        if (!targetButton) return;

        const action = targetButton.classList;
        const id = targetButton.dataset.id;
        const topic = targetButton.closest('tr').querySelector('td').textContent; // گرفتن موضوع برای نمایش در دیالوگ

        if (action.contains('approve-request')) {
            // استفاده از دیالوگ سفارشی
            if (await Dialog.confirm('تایید درخواست', `آیا از تایید درخواست "${topic}" اطمینان دارید؟`)) {
                handleRequestAction('approve_request', id);
            }
        } else if (action.contains('reject-request')) {
            // استفاده از دیالوگ سفارشی
            if (await Dialog.confirm('رد درخواست', `آیا از رد کردن درخواست "${topic}" اطمینان دارید؟`)) {
                handleRequestAction('reject_request', id);
            }
        } else if (action.contains('view-details')) {
            const details = JSON.parse(targetButton.dataset.details);
            showRequestDetails(details);
        }
    });


    function handleRequestAction(action, requestId) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('request_id', requestId);

        fetch('actions/finance_api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            });
    }

    // --- منطق نمایش فیلدهای داینامیک در مدال ثبت منبع ---
    const resourceTypeSelect = document.getElementById('resourceTypeSelect');
    if (resourceTypeSelect) {
        resourceTypeSelect.addEventListener('change', function() {
            document.querySelectorAll('.dynamic-fields').forEach(el => el.classList.remove('active'));
            const selectedType = this.value;
            if (selectedType) {
                const fieldsToShow = document.getElementById(`${selectedType}-fields`);
                if (fieldsToShow) fieldsToShow.classList.add('active');
                
                const commonFields = document.getElementById('common-finance-fields');
                if (commonFields) commonFields.classList.add('active');
            }
        });
    }
});