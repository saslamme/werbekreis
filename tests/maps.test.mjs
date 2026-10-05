import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import { initializeMaps } from '../assets/map-controller.js';

function fixture(markers) {
    const dom = new JSDOM('<article id="company-test"></article><div data-company-map><p data-map-placeholder>Consent</p><button data-map-load>Load</button><p class="visually-hidden" data-map-status></p></div>', { url: 'https://portal.example/' });
    dom.window.document.querySelector('[data-company-map]').dataset.map = JSON.stringify({ markers, tileUrl: 'https://tile.example/{z}/{x}/{y}' });
    return dom;
}
function library() {
    const state = { markers: [], tiles: null, fit: null };
    const map = { fitBounds: bounds => { state.fit = bounds; }, setView() {}, remove() {} };
    const tiles = { addTo: () => tiles, on: (event, handler) => { state.tiles = handler; } };
    const leaflet = {
        map: () => map, tileLayer: () => tiles, divIcon: value => value,
        marker: (coordinates, options) => {
            const marker = { coordinates, options, events: {}, addTo: () => marker, bindPopup: popup => { marker.popup = popup; return marker; }, on: (event, handler) => { marker.events[event] = handler; return marker; }, openPopup: () => marker.events.popupopen() };
            state.markers.push(marker);
            return marker;
        },
    };
    return { leaflet, state };
}
const valid = { latitude: 0, longitude: 0, slug: 'test', name: '<img src=x onerror=alert(1)>', categories: ['<script>'], description: '<svg onload=alert(1)>', url: '/unternehmen/test' };

test('map is opt-in, rejects invalid points and uses safe popup text', () => {
    const dom = fixture([valid, { ...valid, latitude: null }, { ...valid, latitude: 91 }, { ...valid, url: 'javascript:alert(1)' }]);
    const { leaflet, state } = library();
    initializeMaps(dom.window.document, leaflet);
    assert.equal(state.markers.length, 0);
    dom.window.document.querySelector('[data-map-load]').click();
    assert.equal(state.markers.length, 1);
    const popup = state.markers[0].popup;
    assert.equal(popup.querySelector('img, script, svg'), null);
    assert.match(popup.textContent, /<img src=x/);
    assert.equal(popup.querySelector('a').getAttribute('href'), '/unternehmen/test');
    assert.deepEqual(state.fit, [[0, 0]]);
    const card = dom.window.document.querySelector('article');
    state.markers[0].events.popupopen();
    assert.equal(card.classList.contains('is-map-focused'), true);
    state.markers[0].events.popupclose();
    assert.equal(card.classList.contains('is-map-focused'), false);
    state.tiles();
    assert.match(dom.window.document.querySelector('[data-map-status]').textContent, /Ergebnisliste/);
    dom.window.close();
});

test('empty or corrupt map data produces a readable fallback without throwing', () => {
    for (const data of ['not JSON', JSON.stringify({ markers: [] })]) {
        const dom = fixture([]);
        dom.window.document.querySelector('[data-company-map]').dataset.map = data;
        initializeMaps(dom.window.document, library().leaflet);
        dom.window.document.querySelector('[data-map-load]').click();
        assert.match(dom.window.document.querySelector('[data-map-status]').textContent, /nicht verfügbar/);
        assert.equal(dom.window.document.querySelector('[data-map-load]').disabled, true);
        dom.window.close();
    }
});


test('event markers reuse opt-in maps and reject lookalike or unsafe paths', () => {
    const dom = fixture([{ ...valid, url: '/veranstaltungen/test' }, { ...valid, url: '/veranstaltungen/../admin' }, { ...valid, url: '//evil.example/veranstaltungen/test' }, { ...valid, url: '/veranstaltungen/test?redirect=evil' }]);
    const { leaflet, state } = library();
    initializeMaps(dom.window.document, leaflet);
    dom.window.document.querySelector('[data-map-load]').click();
    assert.equal(state.markers.length, 1);
    assert.equal(state.markers[0].popup.querySelector('a').textContent, 'Veranstaltung ansehen');
    assert.equal(state.markers[0].popup.querySelector('a').getAttribute('href'), '/veranstaltungen/test');
    dom.window.close();
});
