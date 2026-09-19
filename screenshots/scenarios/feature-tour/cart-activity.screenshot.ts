import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedAbandonedCartFixture } from '../../support/fixtures';

let cartsRoute = '/admin/abandoned-cart/dashboard';

export default defineScreenshotScenario({
    id: 'abandoned-cart-feature-tour-cart-activity',
    output: 'feature-tour/cart-activity.png',
    route: () => cartsRoute,
    viewport: { width: 1480, height: 760, deviceScaleFactor: 2 },
    async setup(context) {
        cartsRoute = (await seedAbandonedCartFixture(context)).cartsRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#carts-vue-admin-table table', state: 'visible', timeout: 30000 },
        { type: 'text', text: 'maya.chen@example.com' },
        { type: 'text', text: 'Recovered' },
    ],
    steps: [
        { type: 'evaluate', expression: 'document.activeElement?.blur(); window.scrollTo(0, 0);' },
        { type: 'wait', waitFor: { type: 'timeout', ms: 250 } },
    ],
    target: { type: 'selector', selector: '#carts-vue-admin-table .tablepane' },
    caption: 'Cart activity showing reminder delivery, link clicks and recovered checkouts in one current Craft view.',
    intent: 'Show the real operational cart table without implying the removed reporting dashboard still exists.',
});
