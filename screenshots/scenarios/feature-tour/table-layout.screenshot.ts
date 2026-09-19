import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedSuperTableFixture } from '../../support/fixtures';
import { frameSuperTableField } from '../../support/presets';
let route = '/admin/entries';
export default defineScreenshotScenario({
    id: 'super-table-craft4-table-layout', output: 'feature-tour/table-layout.png', route: () => route,
    viewport: { width: 1240, height: 720, deviceScaleFactor: 2 },
    expectedOutput: { width: 1340, height: 568 },
    async setup(context) { route = (await seedSuperTableFixture(context, 'table')).entryEditRoute; },
    waitFor: [{ type: 'loadState', state: 'networkidle' }, { type: 'selector', selector: '[name^="fields[docsSuperTableTable]"]', state: 'attached' }],
    preSteps: [frameSuperTableField('docsSuperTableTable', 670), { type: 'wait', waitFor: { type: 'timeout', ms: 250 } }],
    target: { type: 'selector', selector: '#super-table-screenshot-frame', padding: 0 },
    caption: 'Super Table table layout in Craft 4.', intent: 'Shows a real populated table-layout field with multiple field types.',
});
