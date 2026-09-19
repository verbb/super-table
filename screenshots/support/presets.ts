import type { ScreenshotStep } from '@verbb/craft-screenshots/types';

export function frameSuperTableField(handle: string, width: number): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                document.getElementById('super-table-screenshot-frame')?.remove();
                const selectors = {
                    docsSuperTableTable: '.superTableContainer.columnLayout',
                    docsSuperTableRow: '.superTableContainer.rowLayout:not(.static-field)',
                    docsSuperTableStatic: '.superTableContainer.rowLayout.static-field',
                    docsSuperTableMatrix: '.matrix-field',
                };
                const container = document.querySelector(selectors['${handle}']);
                const field = container?.closest('.field') || container?.parentElement;
                if (!field) {
                    throw new Error('Super Table field ${handle} was not found.');
                }

                const frame = document.createElement('div');
                frame.id = 'super-table-screenshot-frame';
                frame.style.cssText = [
                    'position:fixed', 'left:0', 'top:0', 'width:${width}px', 'box-sizing:border-box',
                    'padding:28px', 'overflow:hidden', 'background:#ffffff', 'z-index:2147483646',
                ].join(';');

                field.style.margin = '0';
                frame.appendChild(field);
                document.body.appendChild(frame);
                frame.style.height = Math.ceil(field.getBoundingClientRect().height + 56) + 'px';
                document.documentElement.style.background = '#ffffff';
                document.body.style.margin = '0';
                document.body.style.overflow = 'hidden';
            })();
        `,
    };
}
