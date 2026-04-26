import DOMPurify from 'dompurify';
import hljs from 'highlight.js/lib/core';
import css from 'highlight.js/lib/languages/css';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import { marked } from 'marked';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('css', css);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('json', json);

marked.setOptions({
    breaks: true,
    gfm: true,
});

const renderer = new marked.Renderer();
renderer.code = ({ text, lang }) => {
    const language = lang && hljs.getLanguage(lang) ? lang : 'plaintext';
    const highlighted =
        language !== 'plaintext'
            ? hljs.highlight(text, { language }).value
            : text;
    return `<pre><code class="hljs language-${language}">${highlighted}</code></pre>`;
};

marked.use({ renderer });

export function renderMarkdown(markdown: string): string {
    const html = marked.parse(markdown, { async: false }) as string;
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: [
            'p', 'br', 'strong', 'em', 'a', 'code', 'pre', 'blockquote',
            'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'span', 'del', 'hr', 'img',
        ],
        ALLOWED_ATTR: ['href', 'target', 'class', 'src', 'alt'],
    });
}
