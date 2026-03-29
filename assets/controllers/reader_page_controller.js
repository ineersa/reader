import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'input', 'resultFrame', 'submitButton', 'submitLabel', 'spinner'];

    connect() {
        this.loading = false;
        this.finish = this.finish.bind(this);
        this.element.addEventListener('turbo:submit-end', this.finish);
        this.element.addEventListener('turbo:frame-load', this.finish);
    }

    disconnect() {
        this.element.removeEventListener('turbo:submit-end', this.finish);
        this.element.removeEventListener('turbo:frame-load', this.finish);
    }

    start(event) {
        if (this.inputTarget.value.trim() === '') {
            event.preventDefault();
            this.inputTarget.focus();

            return;
        }

        this.loading = true;
        this.submitButtonTarget.disabled = true;
        this.submitButtonTarget.classList.add('cursor-wait', 'opacity-75');
        this.submitLabelTarget.textContent = 'Reading';
        this.spinnerTarget.classList.remove('hidden');

        this.resultFrameTarget.innerHTML = `
            <section class="mt-8 overflow-hidden border border-[#333] bg-[#111]/90">
                <div class="flex items-center gap-3 border-b border-[#333] px-4 py-3 text-sm text-gray-400">
                    <span class="inline-block h-2.5 w-2.5 animate-pulse rounded-full bg-sky-400"></span>
                    Fetching and converting page content...
                </div>
            </section>
        `;
    }

    finish() {
        if (!this.loading) {
            return;
        }

        this.loading = false;
        this.submitButtonTarget.disabled = false;
        this.submitButtonTarget.classList.remove('cursor-wait', 'opacity-75');
        this.submitLabelTarget.textContent = 'Read';
        this.spinnerTarget.classList.add('hidden');

        if (this.resultFrameTarget.childElementCount > 0) {
            this.resultFrameTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}
