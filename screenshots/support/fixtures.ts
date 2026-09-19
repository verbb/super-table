import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

export type SuperTableFixture = { entryEditRoute: string };

const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-super-table-entry.php'), 'utf8');

export async function seedSuperTableFixture(context: ScreenshotSetupContext, variant: 'table' | 'row' | 'static' | 'matrix'): Promise<SuperTableFixture> {
    const output = await context.runCraftScript(seedScript.replace('__VARIANT__', variant), { label: `seed-super-table-${variant}` });
    const fixture = JSON.parse(output.trim()) as SuperTableFixture;

    if (!fixture.entryEditRoute) throw new Error(`Invalid Super Table fixture payload: ${output}`);
    return fixture;
}
