import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedAbandonedCartFixture } from '../../support/fixtures';

let settingsRoute = '/admin/abandoned-cart/settings';

export default defineScreenshotScenario({
    id: 'abandoned-cart-feature-tour-reminder-settings',
    output: 'feature-tour/reminder-settings.png',
    route: () => settingsRoute,
    viewport: { width: 1500, height: 1300, deviceScaleFactor: 2 },
    async setup(context) {
        settingsRoute = (await seedAbandonedCartFixture(context)).settingsRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'text', text: 'Restore Link Expiry' },
        { type: 'text', text: '1st Reminder Delay' },
        { type: 'text', text: '2nd Reminder Delay' },
    ],
    steps: [
        { type: 'evaluate', expression: 'document.activeElement?.blur(); document.querySelector("input[name*=restoreExpiryHours]")?.scrollIntoView({ block: "start" }); window.scrollBy(0, -96);' },
        { type: 'wait', waitFor: { type: 'timeout', ms: 250 } },
    ],
    target: { type: 'anchoredClip', selector: 'input[name*=restoreExpiryHours]', x: -12, y: -60, width: 940, height: 720 },
    caption: 'Recovery expiry, two reminder delays and email templates configured in the current Craft 5 settings.',
    intent: 'Focus on the settings that shape the actual reminder cadence rather than infrastructure details.',
});
