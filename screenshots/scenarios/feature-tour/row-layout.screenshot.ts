import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedSuperTableFixture } from '../../support/fixtures';
import { frameSuperTableField } from '../../support/presets';
let route = '/admin/entries';
export default defineScreenshotScenario({
    id: 'super-table-craft4-row-layout', output: 'feature-tour/row-layout.png', route: () => route,
    viewport: { width: 1240, height: 760, deviceScaleFactor: 2 },
    expectedOutput: { width: 1340, height: 852 },
    async setup(context) { route = (await seedSuperTableFixture(context, 'row')).entryEditRoute; },
    waitFor: [{ type: 'loadState', state: 'networkidle' }, { type: 'selector', selector: '[name^="fields[docsSuperTableRow]"]', state: 'attached' }],
    preSteps: [frameSuperTableField('docsSuperTableRow', 670), { type: 'wait', waitFor: { type: 'timeout', ms: 250 } }],
    target: { type: 'selector', selector: '#super-table-screenshot-frame', padding: 0 },
    caption: 'Super Table row layout in Craft 4.', intent: 'Shows real repeatable rows with labels and mixed fields.',
});
