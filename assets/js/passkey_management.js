// File: OldSSO/assets/js/passkey_management.js

document.addEventListener('DOMContentLoaded', () => {
    const addPasskeyBtn = document.getElementById('add-passkey-btn');
    const passkeyList = document.getElementById('passkey-list');
    const loadingPlaceholder = document.getElementById('loading-placeholder');

    // المان‌های مودال
    const confirmModal = document.getElementById('confirm-modal');
    const btnCancel = document.getElementById('modal-btn-cancel');
    const btnConfirmDelete = document.getElementById('modal-btn-confirm-delete');
    let credentialIdToDelete = null;

    // --- توابع ---

    // بارگذاری لیست کلیدهای عبور از سرور
    const loadPasskeys = async () => {
        try {
            const response = await fetch('../../passkey/list.php');
            if (!response.ok) throw new Error('Failed to fetch passkeys.');
            
            const keys = await response.json();
            passkeyList.innerHTML = ''; // پاک کردن لیست فعلی

            if (keys.length === 0) {
                passkeyList.innerHTML = '<li>هیچ کلید عبوری ثبت نشده است.</li>';
            } else {
                keys.forEach(key => {
                    const date = new Date(key.created_at).toLocaleDateString('fa-IR');
                    const listItem = document.createElement('li');
                    listItem.className = 'passkey-item';
                    listItem.innerHTML = `
                        <div class="passkey-info">
                            <i class="fas fa-fingerprint"></i>
                            <div class="passkey-details">
                                <span class="name">${escapeHTML(key.friendly_name)}</span>
                                <span class="date">ایجاد شده در: ${date}</span>
                            </div>
                        </div>
                        <button class="delete-passkey-btn" data-credential-id="${escapeHTML(key.credential_id)}">
                            <i class="fas fa-trash-alt"></i> حذف
                        </button>
                    `;
                    passkeyList.appendChild(listItem);
                });
            }
        } catch (error) {
            console.error(error);
            passkeyList.innerHTML = '<li>خطا در بارگذاری کلیدها.</li>';
        }
    };

    // فرآیند افزودن کلید عبور جدید
    const handleAddPasskey = async () => {
    try {
        // ۱. دریافت چالش از سرور
        const response = await fetch('../passkey/register_challenge.php');
        const creationOptions = await response.json();

        // تبدیل مقادیر از Base64-URL به ArrayBuffer برای مرورگر
        creationOptions.challenge = bufferDecode(creationOptions.challenge);
        creationOptions.user.id = bufferDecode(creationOptions.user.id);

        // ۲. درخواست از کاربر برای ساخت کلید
        const credential = await navigator.credentials.create({
            publicKey: creationOptions
        });
        
        // ۳. دریافت نام دوستانه از کاربر
        const friendlyName = prompt('یک نام برای این کلید وارد کنید (مثلا: گوشی من):', 'Passkey جدید');
        if (friendlyName === null) { // اگر کاربر انصراف داد
            alert('ثبت کلید عبور لغو شد.');
            return;
        }

        // ۴. ساخت دستی و مطمئن آبجکت برای ارسال به سرور
        const attestationResponse = {
            id: credential.id,
            rawId: bufferEncode(credential.rawId), // ArrayBuffer به Base64-URL
            type: credential.type,
            response: {
                attestationObject: bufferEncode(credential.response.attestationObject), // ArrayBuffer به Base64-URL
                clientDataJSON: bufferEncode(credential.response.clientDataJSON), // ArrayBuffer به Base64-URL
            },
            friendly_name: friendlyName, // اضافه کردن نام دوستانه
        };

        // ۵. ارسال اطلاعات بسته‌بندی شده به سرور برای تایید نهایی
        const verifyResponse = await fetch('../passkey/register_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(attestationResponse),
        });

        const verificationResult = await verifyResponse.json();

        if (verificationResult.success) {
            alert('کلید عبور با موفقیت ثبت شد!');
            loadPasskeys(); // به‌روزرسانی لیست کلیدها
        } else {
            throw new Error(verificationResult.error || 'Verification failed.');
        }
    } catch (err) {
        console.error('Error during passkey registration:', err);
        alert(`خطا در ثبت کلید عبور: ${err.message}`);
    }
};

    // مدیریت کلیک روی دکمه حذف
    const handleDeleteClick = (e) => {
        // با استفاده از event delegation، فقط روی دکمه‌های حذف عمل می‌کنیم
        if (e.target.closest('.delete-passkey-btn')) {
            credentialIdToDelete = e.target.closest('.delete-passkey-btn').dataset.credentialId;
            confirmModal.style.display = 'flex';
        }
    };
    
    // تایید و اجرای عملیات حذف
// File: OldSSO/assets/js/passkey_management.js

const confirmDelete = async () => {
    if (!credentialIdToDelete) return;

    const originalButtonText = btnConfirmDelete.innerHTML;
    btnConfirmDelete.innerHTML = 'در حال پردازش... <i class="fas fa-spinner fa-spin"></i>';
    btnConfirmDelete.disabled = true;

    try {
        // ۱. دریافت چالش لاگین از سرور
        const challengeResponse = await fetch('../../passkey/login_challenge.php', { method: 'POST' });
        if (!challengeResponse.ok) throw new Error('Could not get challenge from server.');
        
        const options = await challengeResponse.json();

        // تبدیل مقادیر لازم از Base64 به ArrayBuffer
        options.challenge = bufferDecode(options.challenge);
        if (options.allowCredentials) {
            options.allowCredentials.forEach(cred => {
                cred.id = bufferDecode(cred.id);
            });
        }

        // ۲. درخواست تایید هویت از کاربر
        const assertion = await navigator.credentials.get({
            publicKey: options
        });

        // ۳. ساخت آبجکت برای ارسال به سرور
        const verificationData = {
            id: assertion.id,
            rawId: bufferEncode(assertion.rawId),
            type: assertion.type,
            response: {
                authenticatorData: bufferEncode(assertion.response.authenticatorData),
                clientDataJSON: bufferEncode(assertion.response.clientDataJSON),
                signature: bufferEncode(assertion.response.signature),
                userHandle: assertion.response.userHandle ? bufferEncode(assertion.response.userHandle) : null,
            },
            // کلید مورد نظر برای حذف را هم به درخواست اضافه می‌کنیم
            credential_id_to_delete: credentialIdToDelete
        };

        // ۴. ارسال تاییدیه به سرور برای بررسی و حذف نهایی
        const deleteResponse = await fetch('../../passkey/verify_and_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(verificationData)
        });

        const result = await deleteResponse.json();

        if (result.success) {
            alert('کلید عبور با موفقیت حذف شد.');
            loadPasskeys(); // رفرش کردن لیست
        } else {
            throw new Error(result.error || 'Verification and deletion failed.');
        }

    } catch (error) {
        alert(`عملیات حذف ناموفق بود: ${error.message}`);
    } finally {
        closeModal(); // بستن مودال در هر صورت
        btnConfirmDelete.innerHTML = originalButtonText;
        btnConfirmDelete.disabled = false;
    }
};

    // بستن مودال تایید
    const closeModal = () => {
        confirmModal.style.display = 'none';
        credentialIdToDelete = null;
    };

    // تابع کمکی برای جلوگیری از حملات XSS
    const escapeHTML = (str) => str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            "'": '&#39;', '"': '&quot;'
        }[tag] || tag));

    // --- ثبت Event Listener ها ---
    addPasskeyBtn.addEventListener('click', handleAddPasskey);
    passkeyList.addEventListener('click', handleDeleteClick);
    btnCancel.addEventListener('click', closeModal);
    btnConfirmDelete.addEventListener('click', confirmDelete);
    
    // --- بارگذاری اولیه ---
    loadPasskeys();
});