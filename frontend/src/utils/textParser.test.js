import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { parseMessageText } from './textParser.js';

const REMARK = '— Доброй ночи. — Он поправил очки. — Позвольте представиться.';

describe('parseMessageText', () => {
    it('renders the action between two em dashes as an action beat and leaves the dialogue plain', () => {
        const html = parseMessageText(REMARK);

        assert.match(html, /<span class="action-beat">Он поправил очки\.<\/span>/);
        assert.equal(html.includes('action-beat"> Доброй ночи'), false);
        assert.equal(html.includes('action-beat">Позвольте'), false);
        assert.match(html, /^— Доброй ночи\. — <span class="action-beat">Он поправил очки\.<\/span> — Позвольте представиться\.$/);
    });

    it('styles an inline remark and keeps the surrounding sentence', () => {
        const html = parseMessageText('Я кивнул — едва заметно — и отвернулся.');

        assert.equal(
            html,
            'Я кивнул — <span class="action-beat">едва заметно</span> — и отвернулся.',
        );
    });

    it('does not treat a trailing clause without a closing em dash as a remark', () => {
        const html = parseMessageText('— Доброй ночи. — Он поправил очки.');

        assert.equal(html.includes('action-beat'), false);
        assert.equal(html, '— Доброй ночи. — Он поправил очки.');
    });

    it('keeps line breaks and does not pull the next line into a remark', () => {
        const html = parseMessageText('первая строка\n— Речь. — кивок. — ещё\nпросто текст');

        assert.equal(
            html,
            'первая строка\n— Речь. — <span class="action-beat">кивок.</span> — ещё\nпросто текст',
        );
    });

    it('leaves ordinary text, hyphens and en dashes unchanged', () => {
        const plain = 'как-то - не ремарка - и тире–короче.\nвторая строка';

        assert.equal(parseMessageText(plain), plain);
    });

    it('highlights an @mention without swallowing punctuation or the next line', () => {
        const html = parseMessageText('Смотри, @Вентру.\nи @Жан-Клод тоже');

        assert.equal(
            html,
            'Смотри, <span class="mention">@Вентру</span>.\nи <span class="mention">@Жан-Клод</span> тоже',
        );
    });

    it('does not highlight an email address', () => {
        assert.equal(parseMessageText('пиши на story@haven.com'), 'пиши на story@haven.com');
    });

    it('highlights a mention inside a remark', () => {
        const html = parseMessageText('— Речь. — Он смотрит на @Вентру. — Конец.');

        assert.equal(
            html,
            '— Речь. — <span class="action-beat">Он смотрит на <span class="mention">@Вентру</span>.</span> — Конец.',
        );
    });

    it('escapes HTML so the message text is not markup', () => {
        const html = parseMessageText('<script>alert(1)</script> & "цитата" — <b>тихо</b> — конец');

        assert.equal(html.includes('<script>'), false);
        assert.equal(html.includes('<b>'), false);
        assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt; &amp; &quot;цитата&quot;/);
        assert.match(html, /<span class="action-beat">&lt;b&gt;тихо&lt;\/b&gt;<\/span>/);
    });

    it('returns an empty string for an empty message', () => {
        assert.equal(parseMessageText(''), '');
        assert.equal(parseMessageText(null), '');
    });
});
