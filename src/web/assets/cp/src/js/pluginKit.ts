import '@verbb/plugin-kit-web/plugin-kit.css';

import { PkStatus } from '@verbb/plugin-kit-web/components/status/pk-status.js';

// Importing a component registers it. Listing the constructors keeps them from being tree-shaken out of the bundle.
const constructors = [PkStatus] as const;

export function registerPluginKit(): void {
    for (const constructor of constructors) {
        if (typeof constructor !== 'function') {
            throw new Error('Abandoned Cart Plugin Kit constructor missing from bundle.');
        }
    }
}
