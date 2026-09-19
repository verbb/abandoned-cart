import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedAbandonedCartFixture } from '../../support/fixtures';

let emailRoute = '/';

export default defineScreenshotScenario({
    id: 'abandoned-cart-feature-tour-recovery-email',
    output: 'feature-tour/recovery-email.png',
    route: () => emailRoute,
    viewport: { width: 920, height: 1000, deviceScaleFactor: 2 },
    async setup(context) {
        emailRoute = (await seedAbandonedCartFixture(context)).emailRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '.discount_heading' },
        { type: 'selector', selector: '.purchase_item' },
        { type: 'selector', selector: '.body-action .button' },
    ],
    target: { type: 'clip', x: 80, y: 0, width: 760, height: 940 },
    caption: 'The bundled recovery email rendered with real cart items, a live discount code and restore link.',
    intent: 'Show the genuine Abandoned Cart email template and the useful context customers receive.',
});
