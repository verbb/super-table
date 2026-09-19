import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedSuperTableFixture } from '../../support/fixtures';
import { frameSuperTableField } from '../../support/presets';
let route = '/admin/entries';
export default defineScreenshotScenario({
    id: 'super-table-craft4-matrix-layout', output: 'feature-tour/matrix-layout.png', route: () => route,
    viewport: { width: 1240, height: 840, deviceScaleFactor: 2 },
    expectedOutput: { width: 1340, height: 916 },
    async setup(context) { route = (await seedSuperTableFixture(context, 'matrix')).entryEditRoute; },
    waitFor: [{ type: 'loadState', state: 'networkidle' }, { type: 'selector', selector: '[name^="fields[docsSuperTableMatrix]"]', state: 'attached' }],
    preSteps: [frameSuperTableField('docsSuperTableMatrix', 670), { type: 'wait', waitFor: { type: 'timeout', ms: 250 } }],
    target: { type: 'selector', selector: '#super-table-screenshot-frame', padding: 0 },
    caption: 'Super Table Matrix-style layout in Craft 4.', intent: 'Shows genuine repeatable component-like blocks using Super Table.',
});
