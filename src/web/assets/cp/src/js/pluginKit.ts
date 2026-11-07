import '@verbb/plugin-kit-web/plugin-kit.css';

import { PkAlert } from '@verbb/plugin-kit-web/components/alert.js';
import { PkButton } from '@verbb/plugin-kit-web/components/button.js';
import { PkCombobox } from '@verbb/plugin-kit-web/components/combobox.js';
import { PkCopyButton } from '@verbb/plugin-kit-web/components/copy-button.js';
import { PkBarChart, PkDonutChart, PkFunnelChart, PkLineChart, PkMeter, PkSparkline } from '@verbb/plugin-kit-web/components/chart.js';
import { PkDataTable, PkDataTableToolbar } from '@verbb/plugin-kit-web/components/data-table.js';
import { PkDialog } from '@verbb/plugin-kit-web/components/dialog.js';
import { PkOption, PkSelect } from '@verbb/plugin-kit-web/components/select.js';
import { PkStatePanel } from '@verbb/plugin-kit-web/components/state-panel.js';
import { PkStatus } from '@verbb/plugin-kit-web/components/status/pk-status.js';
import { PkStatusLabel } from '@verbb/plugin-kit-web/components/status-label.js';

// Importing a component registers it. Listing the constructors keeps them from being tree-shaken out of the bundle.
const constructors = [
    PkAlert, PkBarChart, PkButton, PkCombobox, PkCopyButton, PkDataTable, PkDataTableToolbar, PkDialog, PkDonutChart, PkFunnelChart, PkLineChart, PkMeter, PkOption, PkSelect,
    PkSparkline, PkStatePanel, PkStatus, PkStatusLabel,
] as const;

export function registerPluginKit(): void {
    for (const constructor of constructors) {
        if (typeof constructor !== 'function') {
            throw new Error('Abandoned Cart Plugin Kit constructor missing from bundle.');
        }
    }
}
