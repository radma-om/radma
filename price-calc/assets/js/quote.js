/* ============================================================
   ردما — منطق إرسال طلب عرض السعر المشترك بين الحاسبتين
   الإرسال يتم عبر Backend الموقع (MazBot) — لا يفتح WhatsApp لدى العميل
   ============================================================ */

(function (window) {
    'use strict';

    var SUCCESS_MESSAGE = '✅ تم إرسال طلب عرض السعر بنجاح، سيتواصل معك فريقنا قريبًا.';
    var ERROR_MESSAGE = 'تعذر إرسال الطلب حاليًا. يرجى المحاولة مرة أخرى أو التواصل مع خدمة العملاء على 90660001.';
    var PENDING_MESSAGE = 'جاري إرسال الطلب...';

    /**
     * التحقق من رقم الهاتف العماني وتوحيد صيغته.
     * الصيغ المقبولة: 9XXXXXXX أو 7XXXXXXX (8 أرقام محلية)،
     * 968XXXXXXXX، +968XXXXXXXX.
     * يعيد الرقم بصيغة 968XXXXXXXX أو null إذا كان غير صالح.
     */
    function normalizeOmaniPhone(raw) {
        if (!raw) { return null; }
        var digits = String(raw).replace(/\D+/g, '');
        if (digits.indexOf('00968') === 0) {
            digits = digits.slice(2);
        }
        if (/^968[79]\d{7}$/.test(digits)) {
            return digits;
        }
        if (/^[79]\d{7}$/.test(digits)) {
            return '968' + digits;
        }
        return null;
    }

    function setStatus(statusEl, kind, text) {
        if (!statusEl) { return; }
        statusEl.textContent = text || '';
        statusEl.className = 'submit-status' + (kind ? ' status-' + kind : '');
    }

    /**
     * إرسال طلب عرض السعر إلى Backend الموحد.
     * options: { payload, button, buttonLabel, statusEl }
     */
    function submitQuote(options) {
        var button = options.button;
        var statusEl = options.statusEl;
        var originalLabel = options.buttonLabel || (button ? button.innerHTML : '');

        if (button && button.dataset.submitting === '1') {
            return Promise.resolve(false); // منع الإرسال المزدوج
        }

        if (button) {
            button.dataset.submitting = '1';
            button.disabled = true;
            button.textContent = PENDING_MESSAGE;
        }
        setStatus(statusEl, 'pending', PENDING_MESSAGE);

        return fetch('../api/submit-quote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(options.payload)
        })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data && result.data.success) {
                    setStatus(statusEl, 'success', SUCCESS_MESSAGE);
                    return true;
                }
                setStatus(statusEl, 'error', ERROR_MESSAGE);
                return false;
            })
            .catch(function () {
                setStatus(statusEl, 'error', ERROR_MESSAGE);
                return false;
            })
            .finally(function () {
                if (button) {
                    button.dataset.submitting = '0';
                    button.disabled = false;
                    button.innerHTML = originalLabel;
                }
            });
    }

    window.RadmaQuote = {
        normalizeOmaniPhone: normalizeOmaniPhone,
        submitQuote: submitQuote,
        setStatus: setStatus
    };
})(window);
