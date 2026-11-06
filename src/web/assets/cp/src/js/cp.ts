import '../css/cp.css';
import './safeCustomElementDefine.js';
import { registerPluginKit } from './pluginKit.js';
import { mountSequenceList } from './sequences.js';
import { mountSequenceEditor } from './sequenceEditor.js';
import { mountDataTables } from './dataTables.js';

registerPluginKit();

function mountRowToggles(): void {
    document.querySelectorAll<HTMLButtonElement>('[data-ac-toggle-rows]').forEach(button => button.addEventListener('click', () => {
        const open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(open));
        document.querySelectorAll<HTMLElement>(`[data-ac-row-group="${button.dataset.acToggleRows}"]`).forEach((row) => {
            row.hidden = !open;
        });
    }));
}

function mount(): void {
    mountRowToggles();
    mountSequenceList();
    mountSequenceEditor();
    mountDataTables();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
    mount();
}
