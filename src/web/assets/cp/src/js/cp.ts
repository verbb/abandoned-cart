import '../css/cp.css';
import './safeCustomElementDefine.js';
import { registerPluginKit } from './pluginKit.js';
import { mountSequenceList } from './sequences.js';
import { mountSequenceEditor } from './sequenceEditor.js';
import { mountDataTables } from './dataTables.js';
import { mountPresetEditor } from './presets.js';

registerPluginKit();

function mountRowToggles(): void {
    document.querySelectorAll<HTMLButtonElement>('[data-ac-toggle-rows]').forEach((button) => {
        button.addEventListener('click', () => {
            const open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(open));
            document.querySelectorAll<HTMLElement>(`[data-ac-row-group="${button.dataset.acToggleRows}"]`).forEach((row) => {
                row.hidden = !open;
            });
        });

        // A click anywhere on the row toggles it too; the button stays the keyboard and screen-reader control.
        const row = button.closest('tr');
        row?.classList.add('ac-toggle-row-host');
        row?.addEventListener('click', (event) => {
            const target = event.target as HTMLElement;

            if (target.closest('button, a, input, label, .lightswitch') || getSelection()?.toString()) {
                return;
            }

            button.click();
        });
    });
}

function mount(): void {
    mountRowToggles();
    mountSequenceList();
    mountSequenceEditor();
    mountDataTables();
    mountPresetEditor();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
    mount();
}
