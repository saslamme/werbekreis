import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {JSDOM} from 'jsdom';
const script = readFileSync(new URL('../assets/content-video.js', import.meta.url), 'utf8');
test('external video has no iframe until clicked, and uses the privacy host', () => {
  const dom = new JSDOM('<div data-content-video="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" data-video-title="Test"><button data-load-video>Video laden</button></div>', {runScripts:'outside-only'});
  dom.window.eval(script); assert.equal(dom.window.document.querySelector('iframe'), null);
  dom.window.document.querySelector('button').click();
  assert.equal(dom.window.document.querySelector('iframe').src, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
  assert.equal(dom.window.document.querySelector('iframe').referrerPolicy, 'no-referrer');
});
test('arbitrary iframe origins remain blocked after a click', () => {
  const dom = new JSDOM('<div data-content-video="https://evil.test/embed/test"><button data-load-video>Video laden</button></div>', {runScripts:'outside-only'});
  dom.window.eval(script); dom.window.document.querySelector('button').click(); assert.equal(dom.window.document.querySelector('iframe'), null);
});
