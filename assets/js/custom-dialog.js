// File: assets/js/custom-dialog.js

class CustomDialog {
    constructor() {
        this._createDialogHtml();
        this.dialog = document.getElementById('customDialog');
        this.titleElement = document.getElementById('customDialogTitle');
        this.bodyElement = document.getElementById('customDialogBody');
        this.confirmButton = document.getElementById('customDialogConfirm');
        this.cancelButton = document.getElementById('customDialogCancel');
    }

    _createDialogHtml() {
        // این متد فقط یک بار اجرا می‌شود تا ساختار HTML مودال را به صفحه اضافه کند
        if (document.getElementById('customDialog')) return;

        const dialogHtml = `
            <div class="dialog-overlay" id="customDialog">
                <div class="dialog-box">
                    <div class="dialog-header">
                        <h3 class="dialog-title" id="customDialogTitle"></h3>
                    </div>
                    <div class="dialog-body" id="customDialogBody"></div>
                    <div class="dialog-footer">
                        <button class="dialog-btn confirm" id="customDialogConfirm">تایید</button>
                        <button class="dialog-btn cancel" id="customDialogCancel">انصراف</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', dialogHtml);
    }

    _show(title, body) {
        this.titleElement.textContent = title;
        this.bodyElement.textContent = body;
        this.dialog.classList.add('active');
    }

    _hide() {
        this.dialog.classList.remove('active');
    }

    /**
     * یک دیالوگ تایید (Confirm) با دکمه‌های تایید و انصراف نمایش می‌دهد.
     * @param {string} title - عنوان دیالوگ
     * @param {string} body - متن اصلی دیالوگ
     * @returns {Promise<boolean>} - یک Promise که اگر کاربر "تایید" را بزند true و در غیر این صورت false می‌شود.
     */
    confirm(title, body) {
        this._show(title, body);
        this.cancelButton.style.display = 'inline-block';

        return new Promise((resolve) => {
            // از { once: true } استفاده می‌کنیم تا event listener پس از اولین اجرا به طور خودکار حذف شود
            this.confirmButton.addEventListener('click', () => {
                this._hide();
                resolve(true);
            }, { once: true });

            this.cancelButton.addEventListener('click', () => {
                this._hide();
                resolve(false);
            }, { once: true });
        });
    }

    /**
     * یک دیالوگ اطلاع‌رسانی (Alert) با یک دکمه تایید نمایش می‌دهد.
     * @param {string} title - عنوان دیالوگ
     * @param {string} body - متن اصلی دیالوگ
     */
    alert(title, body) {
        this._show(title, body);
        this.cancelButton.style.display = 'none';

        return new Promise((resolve) => {
            this.confirmButton.addEventListener('click', () => {
                this._hide();
                resolve(true);
            }, { once: true });
        });
    }
}

// یک نمونه از کلاس ساخته می‌شود تا در تمام پروژه از همین یک نمونه استفاده شود
const Dialog = new CustomDialog();