const cookieName = 'wk_cookie_notice';
const maxAge = 180 * 24 * 60 * 60;

export function initializeCookieNotice(document) {
    const notice = document.querySelector('[data-cookie-notice]');
    if (!notice) return;

    // This preference acknowledges the notice only; map loading stays separate.
    const acknowledged = document.cookie.split(';').some(cookie => cookie.trim() === `${cookieName}=1`);
    if (acknowledged) {
        notice.hidden = true;
        return;
    }

    const button = notice.querySelector('[data-cookie-notice-dismiss]');
    button.hidden = false;
    button.addEventListener('click', () => {
        const secure = document.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${cookieName}=1; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
        notice.hidden = true;
    });
}

if (typeof document !== 'undefined') initializeCookieNotice(document);
