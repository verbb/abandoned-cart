// The preset editor under Settings → Sequence Presets: its emails can be added, removed and reordered.

function renumber(list: HTMLElement): void {
    const blocks = Array.from(list.querySelectorAll<HTMLElement>(':scope > [data-ac-preset-email]'));
    const max = Number(list.dataset.max ?? 5);

    blocks.forEach((block, index) => {
        const number = block.querySelector('[data-ac-email-number]');
        const after = block.querySelector<HTMLElement>('[data-ac-delay-after]');

        if (number) number.textContent = String(index + 1);
        if (after) after.textContent = (index === 0 ? after.dataset.first : after.dataset.later) ?? '';

        block.querySelector<HTMLButtonElement>('[data-ac-email-move="-1"]')!.disabled = index === 0;
        block.querySelector<HTMLButtonElement>('[data-ac-email-move="1"]')!.disabled = index === blocks.length - 1;
        block.querySelector<HTMLButtonElement>('[data-ac-email-remove]')!.disabled = blocks.length === 1;
    });

    const add = document.querySelector<HTMLButtonElement>('[data-ac-preset-add-email]');
    if (add) add.disabled = blocks.length >= max;
}

function bindBlock(list: HTMLElement, block: HTMLElement): void {
    block.querySelectorAll<HTMLButtonElement>('[data-ac-email-move]').forEach(button => button.addEventListener('click', () => {
        const sibling = button.dataset.acEmailMove === '-1' ? block.previousElementSibling : block.nextElementSibling?.nextElementSibling ?? null;

        if (button.dataset.acEmailMove === '-1' && !sibling) return;
        list.insertBefore(block, button.dataset.acEmailMove === '-1' ? sibling : sibling);
        renumber(list);
        button.focus();
    }));

    block.querySelector('[data-ac-email-remove]')?.addEventListener('click', () => {
        block.remove();
        renumber(list);
    });
}

export function mountPresetEditor(): void {
    const list = document.querySelector<HTMLElement>('[data-ac-preset-emails]');
    const audience = document.querySelector<HTMLSelectElement>('[data-ac-preset-audience]');

    if (audience) {
        const detail = document.querySelector<HTMLElement>('[data-ac-preset-cart-value]');
        const operator = document.querySelector<HTMLSelectElement>('[data-ac-preset-operator]');
        const between = document.querySelector<HTMLElement>('[data-ac-preset-between]');

        audience.addEventListener('change', () => { if (detail) detail.hidden = audience.value !== 'cartValue'; });
        operator?.addEventListener('change', () => { if (between) between.hidden = operator.value !== 'between'; });
    }

    if (!list) {
        return;
    }

    list.querySelectorAll<HTMLElement>(':scope > [data-ac-preset-email]').forEach(block => bindBlock(list, block));
    renumber(list);

    const template = document.querySelector<HTMLTemplateElement>('[data-ac-preset-email-template]');
    let added = 0;

    document.querySelector('[data-ac-preset-add-email]')?.addEventListener('click', () => {
        if (!template) return;

        // A fresh key keeps the new email's fields apart; the server reads the emails in page order.
        const html = template.innerHTML.replaceAll('__KEY__', `new${++added}`);
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const block = holder.firstElementChild as HTMLElement;

        list.append(block);
        Craft.initUiElements($(block));
        bindBlock(list, block);
        renumber(list);
        block.querySelector<HTMLInputElement>('input[name$="[subject]"]')?.focus();
    });
}
