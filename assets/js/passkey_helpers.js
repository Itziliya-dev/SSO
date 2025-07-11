// File: OldSSO/assets/js/passkey_helpers.js

/**
 * یک رشته Base64-URL را به ArrayBuffer تبدیل می‌کند.
 * @param {string} str
 * @returns {Uint8Array}
 */
function bufferDecode(str) {
    str = str.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(str);
    const buffer = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        buffer[i] = binary.charCodeAt(i);
    }
    return buffer;
}

/**
 * یک ArrayBuffer را به رشته Base64-URL تبدیل می‌کند.
 * @param {ArrayBuffer} buffer
 * @returns {string}
 */
function bufferEncode(buffer) {
    const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

/**
 * یک شیء credential را پیمایش کرده و تمام ArrayBuffer ها را به Base64-URL تبدیل می‌کند.
 * این کار برای ارسال داده به سرور با فرمت JSON ضروری است.
 * @param {object} obj
 * @returns {object}
 */
function credentialToJson(obj) {
    if (obj instanceof ArrayBuffer) {
        return bufferEncode(obj);
    }
    if (Array.isArray(obj)) {
        return obj.map(credentialToJson);
    }
    if (typeof obj === 'object' && obj !== null) {
        const newObj = {};
        for (const key in obj) {
            newObj[key] = credentialToJson(obj[key]);
        }
        return newObj;
    }
    return obj;
}