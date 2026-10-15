import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
import { initializeCookieNotice } from '../assets/cookie-notice.js';

const markup = readFileSync(new URL('../templates/partials/_cookie_notice.html.twig', import.meta.url), 'utf8');
function fixture(url = 'https://portal.example/') {
    return new JSDOM(`${markup}<button data-map-load>Karte laden</button>`, { url });
}

test('notice is readable without JS and initialization neither writes cookies nor loads maps', () => {
    const dom = fixture();
    const { document } = dom.window;
    assert.equal(document.querySelector('[data-cookie-notice]').hidden, false);
    assert.equal(document.querySelector('[data-cookie-notice-dismiss]').hidden, true);
    let mapClicks = 0;
    document.querySelector('[data-map-load]').addEventListener('click', () => mapClicks++);
    initializeCookieNotice(document);
    assert.equal(document.querySelector('[data-cookie-notice]').hidden, false);
    assert.equal(document.querySelector('[data-cookie-notice-dismiss]').hidden, false);
    assert.equal(document.cookie, '');
    document.querySelector('[data-cookie-notice-dismiss]').click();
    assert.equal(mapClicks, 0);
    dom.window.close();
});

test('only an exact acknowledgement hides the notice on subsequent pages', () => {
    for (const value of ['wk_cookie_notice=1', 'other=1; wk_cookie_notice=1', 'wk_cookie_notice=0', 'wk_cookie_notice=10', 'other_wk_cookie_notice=1']) {
        const dom = fixture();
        for (const cookie of value.split(';')) dom.window.document.cookie = cookie;
        initializeCookieNotice(dom.window.document);
        assert.equal(dom.window.document.querySelector('[data-cookie-notice]').hidden, value.split(';').some(cookie => cookie.trim() === 'wk_cookie_notice=1'));
        dom.window.close();
    }
});

test('acknowledgement writes only a 180-day first-party preference with HTTPS protection', async () => {
    for (const protocol of ['https:', 'http:']) {
        const dom = fixture(`${protocol}//portal.example/unternehmen`);
        const { document } = dom.window;
        initializeCookieNotice(document);
        assert.equal(document.cookie, '');
        document.querySelector('[data-cookie-notice-dismiss]').click();
        assert.equal(document.querySelector('[data-cookie-notice]').hidden, true);
        assert.equal(document.cookie, 'wk_cookie_notice=1');
        const cookies = await dom.cookieJar.getCookies(`${protocol}//portal.example/datenschutz`);
        assert.equal(cookies.length, 1);
        const [cookie] = cookies;
        assert.equal(cookie.key, 'wk_cookie_notice');
        assert.equal(cookie.value, '1');
        assert.equal(cookie.path, '/');
        assert.equal(cookie.maxAge, 180 * 24 * 60 * 60);
        assert.equal(cookie.sameSite, 'lax');
        assert.equal(cookie.secure, protocol === 'https:');
        assert.equal(cookie.hostOnly, true);
        const nextPage = new JSDOM(markup, { url: `${protocol}//portal.example/datenschutz`, cookieJar: dom.cookieJar });
        initializeCookieNotice(nextPage.window.document);
        assert.equal(nextPage.window.document.querySelector('[data-cookie-notice]').hidden, true);
        nextPage.window.close();
        dom.window.close();
    }
});

test('module safely ignores admin and member pages without a notice', () => {
    const dom = new JSDOM('<main>Mitgliederbereich</main>');
    assert.doesNotThrow(() => initializeCookieNotice(dom.window.document));
    assert.equal(dom.window.document.cookie, '');
    dom.window.close();
});
