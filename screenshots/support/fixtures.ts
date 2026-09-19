import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

type AbandonedCartFixture = {
    cartsRoute: string;
    settingsRoute: string;
    emailRoute: string;
    cartCount: number;
};

const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-abandoned-cart.php'), 'utf8');

/** Seed current cart activity, reminder settings and an actual reminder-email preview. */
export async function seedAbandonedCartFixture(context: ScreenshotSetupContext): Promise<AbandonedCartFixture> {
    const output = await context.runCraftScript(seedScript, { label: 'seed-abandoned-cart' });
    const fixture = JSON.parse(output.trim()) as AbandonedCartFixture;

    if (!fixture.cartsRoute || !fixture.settingsRoute || !fixture.emailRoute || fixture.cartCount < 6) {
        throw new Error(`Invalid Abandoned Cart fixture payload: ${output}`);
    }

    return fixture;
}
