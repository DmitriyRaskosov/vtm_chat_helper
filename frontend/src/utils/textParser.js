const EM_DASH = '\u2014';
const MENTION = /(^|[^\p{L}\p{N}_])@([\p{L}\p{N}_](?:[\p{L}\p{N}_-]*[\p{L}\p{N}_])?)/gu;

export function parseMessageText(input) {
    if (input == null || input === '') {
        return '';
    }

    return String(input).split('\n').map(parseLine).join('\n');
}

function parseLine(line) {
    if (!line.includes(EM_DASH)) {
        return highlightMentions(escapeHtml(line));
    }

    const parts = line.split(EM_DASH);
    const startsWithDash = /^\s*—/u.test(line);
    let html = '';

    for (let index = 0; index < parts.length; index += 1) {
        if (index > 0) {
            html += EM_DASH;
        }

        const segment = parts[index];
        const enclosed = index > 0 && index < parts.length - 1;
        const remarkSlot = startsWithDash ? index % 2 === 0 : true;

        if (enclosed && remarkSlot && segment.trim() !== '') {
            html += wrapRemark(segment);
        } else {
            html += highlightMentions(escapeHtml(segment));
        }
    }

    return html;
}

function wrapRemark(segment) {
    const lead = segment.match(/^\s*/u)[0];
    const trail = segment.match(/\s*$/u)[0];
    const core = segment.slice(lead.length, segment.length - trail.length);

    if (core === '') {
        return highlightMentions(escapeHtml(segment));
    }

    return `${lead}<span class="action-beat">${highlightMentions(escapeHtml(core))}</span>${trail}`;
}

function highlightMentions(escaped) {
    MENTION.lastIndex = 0;

    return escaped.replace(MENTION, '$1<span class="mention">@$2</span>');
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
