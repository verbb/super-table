import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedSuperTableFixture } from '../../support/fixtures';
import { frameSuperTableField } from '../../support/presets';
let route = '/admin/entries';
export default defineScreenshotScenario({
    id: 'super-table-craft4-static-layout', output: 'feature-tour/static-layout.png', route: () => route,
    viewport: { width: 1240, height: 620, deviceScaleFactor: 2 },
    expectedOutput: { width: 1340, height: 456 },
    async setup(context) { route = (await seedSuperTableFixture(context, 'static')).entryEditRoute; },
    waitFor: [{ type: 'loadState', state: 'networkidle' }, { type: 'selector', selector: '[name^="fields[docsSuperTableStatic]"]', state: 'attached' }],
    preSteps: [frameSuperTableField('docsSuperTableStatic', 670), { type: 'wait', waitFor: { type: 'timeout', ms: 250 } }],
    target: { type: 'selector', selector: '#super-table-screenshot-frame', padding: 0 },
    caption: 'A static Super Table field in Craft 4.', intent: 'Shows a genuine non-repeatable group of related fields.',
});
