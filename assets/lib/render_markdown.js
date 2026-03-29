import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import { escapeHtml } from './escape_html.js';

export function renderMarkdown(markdown, target, mode = 'rendered') {
    if (mode === 'raw') {
        target.innerHTML = `<pre class="answer-markdown-raw m-0 p-0 overflow-x-auto whitespace-pre-wrap break-words text-gray-300">${escapeHtml(markdown)}</pre>`;

        return;
    }

    const rawHtml = marked.parse(markdown, { gfm: true, breaks: false, silent: true }) ?? '';
    const safeHtml = DOMPurify.sanitize(String(rawHtml));
    target.innerHTML = `<div class="answer-markdown-rendered markdown-body">${safeHtml}</div>`;

    target.querySelectorAll('pre code').forEach((element) => {
        hljs.highlightElement(element);
    });
}
