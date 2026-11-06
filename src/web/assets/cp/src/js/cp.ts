import '../css/cp.css';
import './safeCustomElementDefine.js';
import { registerPluginKit } from './pluginKit.js';
import { mountSequenceList } from './sequences.js';
import { mountSequenceEditor } from './sequenceEditor.js';

registerPluginKit();

function mount(): void {
    mountSequenceList();
    mountSequenceEditor();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
    mount();
}
