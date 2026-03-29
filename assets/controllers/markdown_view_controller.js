import { Controller } from '@hotwired/stimulus';
import { renderMarkdown } from '../lib/render_markdown.js';

export default class extends Controller {
    static targets = ['body', 'copyButton', 'source', 'toggleButton'];

    connect() {
        this.mode = 'rendered';
        this.copyResetTimer = null;
        this.render();
        this.updateToggleLabel();
    }

    disconnect() {
        if (this.copyResetTimer) {
            window.clearTimeout(this.copyResetTimer);
            this.copyResetTimer = null;
        }
    }

    toggleMode() {
        this.mode = this.mode === 'rendered' ? 'raw' : 'rendered';
        this.render();
        this.updateToggleLabel();
    }

    async copyRaw() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.copyButtonTarget.textContent = 'Copied';
        } catch {
            this.copyButtonTarget.textContent = 'Copy failed';
        }

        if (this.copyResetTimer) {
            window.clearTimeout(this.copyResetTimer);
        }

        this.copyResetTimer = window.setTimeout(() => {
            this.copyButtonTarget.textContent = 'Copy';
        }, 1800);
    }

    render() {
        renderMarkdown(this.sourceTarget.value, this.bodyTarget, this.mode);
    }

    updateToggleLabel() {
        this.toggleButtonTarget.textContent = this.mode === 'rendered' ? 'Raw' : 'Rendered';
    }
}
