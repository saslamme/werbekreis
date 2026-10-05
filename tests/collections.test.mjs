import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const collection = (id, index) => `<div id="${id}" data-collection data-index="${index}" data-prototype="&lt;input name='${id}[__name__][name]' id='${id}___name___name'&gt;"><div data-collection-items></div><button data-collection-add type="button"><span>Hinzufügen</span></button></div>`;

test('collection additions, removal and sparse indices remain independent', () => {
    const dom = new JSDOM(collection('hours', 3) + collection('contacts', 0), { runScripts: 'outside-only' });
    dom.window.eval(readFileSync(new URL('../assets/collections.js', import.meta.url), 'utf8'));
    const doc = dom.window.document;
    const hours = doc.getElementById('hours');
    const contacts = doc.getElementById('contacts');
    hours.querySelector('span').click();
    assert.equal(hours.querySelector('input').name, 'hours[3][name]');
    hours.querySelector('[data-collection-remove]').click();
    assert.equal(hours.querySelectorAll('input').length, 0);
    hours.querySelector('[data-collection-add]').click();
    assert.equal(hours.querySelector('input').name, 'hours[4][name]');
    contacts.querySelector('[data-collection-add]').click();
    assert.equal(contacts.querySelector('input').name, 'contacts[0][name]');
    assert.equal(hours.querySelectorAll('input').length, 1);
    dom.window.close();
});
