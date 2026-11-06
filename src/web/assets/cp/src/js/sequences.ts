import { errorMessage } from './craft.js';

/**
 * The Sequences list: drag to change the match order, and switch sequences on or off in place.
 */
export function mountSequenceList(): void {
    const list = document.querySelector<HTMLElement>('[data-ac-sequences]');

    if (!list) {
        return;
    }

    list.querySelectorAll<HTMLElement>('[data-ac-toggle]').forEach((container) => {
        const id = container.dataset.acToggle;
        const input = container.querySelector<HTMLInputElement>('input');

        // Craft's lightswitch fires a jQuery change event on its container when toggled.
        $(container).on('change', async () => {
            const enabled = container.classList.contains('on');

            try {
                const response = await Craft.sendActionRequest('POST', 'abandoned-cart/sequences/toggle', { data: { id, enabled: enabled ? 1 : 0 } });
                Craft.cp.displaySuccess(response.data.message);
            } catch (error) {
                Craft.cp.displayError(errorMessage(error));
            }

            input?.setAttribute('aria-label', enabled ? 'on' : 'off');
        });
    });

    if (list.hasAttribute('data-readonly')) {
        return;
    }

    const items = Array.from(list.querySelectorAll<HTMLElement>('.ac-seq:not(.ac-seq--fallback)'));

    if (items.length < 2) {
        return;
    }

    new Garnish.DragSort(items, {
        handle: '.ac-seq__grip',
        axis: 'y',
        collapseDraggees: false,
        magnetStrength: 4,
        onSortChange: async () => {
            const ids = Array.from(list.querySelectorAll<HTMLElement>('.ac-seq:not(.ac-seq--fallback)')).map(item => item.dataset.id);

            list.querySelectorAll<HTMLElement>('.ac-seq:not(.ac-seq--fallback) .ac-seq__order').forEach((order, index) => {
                order.textContent = String(index + 1);
            });

            try {
                const response = await Craft.sendActionRequest('POST', 'abandoned-cart/sequences/reorder', { data: { ids } });
                Craft.cp.displaySuccess(response.data.message);
            } catch (error) {
                Craft.cp.displayError(errorMessage(error));
            }
        },
    });
}
