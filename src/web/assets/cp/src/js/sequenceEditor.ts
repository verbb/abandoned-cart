import { errorMessage, t, type CraftSlideout } from './craft.js';

type EmailData = {
    id: number | null;
    sequenceId: number;
    position: number;
    delayMinutes: number;
    subject: string;
    previewText: string | null;
    heading: string | null;
    message: string | null;
    buttonLabel: string | null;
    discountCode: string | null;
    emailTemplateId: number | null;
};

type StartPreset = { label: string; subject?: string; previewText?: string; heading?: string; message?: string; buttonLabel?: string; discountCode?: string; delayMinutes?: number };

type EditorConfig = {
    sequenceId: number;
    isFallback: boolean;
    maxEmails: number;
    testEmail: string | null;
    tokens: Record<string, string>;
    starts: Record<string, StartPreset>;
    templates: { id: number; name: string; isDefault: boolean }[];
    abandonedAfter: string;
};

type Cart = { id: number; label: string };

let cartsPromise: Promise<Cart[]> | null = null;

function loadCarts(): Promise<Cart[]> {
    cartsPromise ??= Craft.sendActionRequest<{ carts: Cart[] }>('GET', 'abandoned-cart/sequences/search-carts')
        .then(response => response.data.carts)
        .catch(() => []);

    return cartsPromise;
}

function el<K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Record<string, string> = {}, ...children: (Node | string)[]): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
        node.setAttribute(name, value);
    }

    node.append(...children);

    return node;
}

function field(label: string, input: HTMLElement, instructions?: string): HTMLElement {
    const id = input.id || `ac-${Math.random().toString(36).slice(2)}`;
    input.id = id;

    return el('div', { class: 'field' },
        el('div', { class: 'heading' }, el('label', { for: id }, label)),
        ...(instructions ? [el('div', { class: 'instructions' }, el('p', {}, instructions))] : []),
        el('div', { class: 'input ltr' }, input),
    );
}

function splitDelay(minutes: number): { value: number; unit: string } {
    if (minutes > 0 && minutes % 1440 === 0) return { value: minutes / 1440, unit: 'days' };
    if (minutes > 0 && minutes % 60 === 0) return { value: minutes / 60, unit: 'hours' };

    return { value: minutes, unit: minutes ? 'minutes' : 'hours' };
}

class EmailPanel {
    private slideout: CraftSlideout | null = null;
    private form!: HTMLFormElement;
    private iframe!: HTMLIFrameElement;
    private inboxSubject!: HTMLElement;
    private inboxPreview!: HTMLElement;
    private cartSelect!: HTMLSelectElement;
    private status!: HTMLElement;
    private previewTimer = 0;
    private lastFocused: HTMLInputElement | HTMLTextAreaElement | null = null;

    constructor(private config: EditorConfig, private email: EmailData) {}

    open(): void {
        const isNew = !this.email.id;
        const panel = el('div', { class: 'ac-panel' });
        const title = isNew
            ? t('New Email · Email {position}', { position: this.email.position })
            : t('Email {position} · {subject}', { position: this.email.position, subject: this.email.subject });

        const devices = el('div', { class: 'btngroup ac-devices', role: 'group', 'aria-label': t('Preview size') },
            el('button', { type: 'button', class: 'btn small active', 'data-device': 'desktop', 'aria-pressed': 'true' }, t('Desktop')),
            el('button', { type: 'button', class: 'btn small', 'data-device': 'mobile', 'aria-pressed': 'false' }, t('Mobile')),
        );
        const close = el('button', { type: 'button', class: 'btn small', 'data-close': '' }, t('Close'));

        panel.append(el('header', { class: 'ac-panel__head' }, el('h2', {}, title), devices, close));

        this.form = el('form', { class: 'ac-panel__form', novalidate: '' });
        this.buildForm(isNew);

        this.cartSelect = el('select', { 'aria-label': t('Preview cart') }, el('option', { value: '' }, t('A sample cart')));
        loadCarts().then((carts) => {
            for (const cart of carts) this.cartSelect.append(el('option', { value: String(cart.id) }, cart.label));
        });
        this.cartSelect.addEventListener('change', () => this.schedulePreview(0));

        this.inboxSubject = el('strong', {});
        this.inboxPreview = el('span', { class: 'light' });
        this.iframe = el('iframe', { class: 'ac-preview__frame', title: t('Email preview'), sandbox: 'allow-same-origin' });
        this.status = el('p', { class: 'ac-preview__status light', 'aria-live': 'polite' });

        const preview = el('div', { class: 'ac-panel__preview' },
            el('div', { class: 'ac-inline' }, el('span', { class: 'light' }, t('Previewing with')), el('div', { class: 'select' }, this.cartSelect)),
            el('div', { class: 'ac-preview' },
                el('div', { class: 'ac-preview__inbox' }, this.inboxSubject, this.inboxPreview),
                this.iframe,
            ),
            this.status,
        );

        panel.append(el('div', { class: 'ac-panel__body' }, this.form, preview));
        panel.append(this.buildFooter(isNew));

        devices.addEventListener('click', (event) => {
            const button = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-device]');
            if (!button) return;
            devices.querySelectorAll('button').forEach(b => { b.classList.toggle('active', b === button); b.setAttribute('aria-pressed', String(b === button)); });
            preview.querySelector('.ac-preview')?.classList.toggle('ac-preview--mobile', button.dataset.device === 'mobile');
        });
        close.addEventListener('click', () => this.slideout?.close());

        this.slideout = new Craft.Slideout(panel, { containerAttributes: { class: 'ac-slideout' }, closeOnShadeClick: true });
        this.slideout.on('close', () => window.setTimeout(() => this.slideout?.destroy(), 300));
        this.schedulePreview(0);
    }

    private buildForm(isNew: boolean): void {
        const data = this.email;

        if (isNew) {
            const start = el('select', {});
            for (const [key, preset] of Object.entries(this.config.starts)) start.append(el('option', { value: key }, preset.label));
            start.value = data.position > 1 ? 'nudge' : 'reminder';
            start.addEventListener('change', () => this.applyStart(this.config.starts[start.value]));
            this.form.append(field(t('Start From'), el('div', { class: 'select' }, start), t('Fills in the fields below. Change anything you like.')));
        }

        const delay = splitDelay(data.delayMinutes);
        const delayValue = el('input', { type: 'number', class: 'text', min: '0', size: '6', name: 'delay[value]', value: String(delay.value) });
        const delayUnit = el('select', { name: 'delay[unit]' },
            el('option', { value: 'minutes' }, t('minutes')),
            el('option', { value: 'hours' }, t('hours')),
            el('option', { value: 'days' }, t('days')),
        );
        delayUnit.value = delay.unit;
        this.form.append(field(t('Send'), el('div', { class: 'ac-inline' }, delayValue, el('div', { class: 'select' }, delayUnit),
            el('span', { class: 'light' }, data.position === 1 ? t('after the cart is abandoned ({time} without changes)', { time: this.config.abandonedAfter }) : t('after the previous email')))));

        const text = (name: keyof EmailData, label: string, instructions?: string, multiline = false) => {
            const input = multiline
                ? el('textarea', { class: 'text fullwidth', name, rows: '6' })
                : el('input', { type: 'text', class: 'text fullwidth', name });
            input.value = String(data[name] ?? '');
            input.addEventListener('focus', () => { this.lastFocused = input; });
            this.form.append(field(label, input, instructions));

            return input;
        };

        text('subject', t('Subject'));
        text('previewText', t('Preview Text'), t('Shown after the subject in most inboxes.'));
        text('heading', t('Heading'));
        text('message', t('Message'), t('Blank lines start a new paragraph.'), true);

        const tokens = el('div', { class: 'ac-tokens' }, el('span', { class: 'light' }, t('Insert:')));
        for (const [token, label] of Object.entries(this.config.tokens)) {
            const button = el('button', { type: 'button', class: 'ac-chip ac-chip--button' }, label);
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => this.insertToken(`{${token}}`));
            tokens.append(button);
        }
        this.form.append(tokens);

        text('buttonLabel', t('Button Label'));
        const discount = text('discountCode', t('Discount Code'), t('A Commerce coupon code to apply when the customer returns. Discounts work best in the last email.'));
        discount.classList.add('code');

        const template = el('select', { name: 'emailTemplateId' }, el('option', { value: '' }, t('Default template')));
        for (const option of this.config.templates) template.append(el('option', { value: String(option.id) }, option.name + (option.isDefault ? ` (${t('default')})` : '')));
        template.value = data.emailTemplateId ? String(data.emailTemplateId) : '';
        this.form.append(field(t('Email Template'), el('div', { class: 'select' }, template), t('Manage templates in Settings → Email Templates.')));

        this.form.addEventListener('input', () => this.schedulePreview());
        this.form.addEventListener('change', () => this.schedulePreview());
        this.form.addEventListener('submit', event => { event.preventDefault(); void this.save(); });
    }

    private buildFooter(isNew: boolean): HTMLElement {
        const to = el('input', { type: 'email', class: 'text', value: this.config.testEmail ?? '', 'aria-label': t('Send test to'), placeholder: t('you@example.com') });
        const test = el('button', { type: 'button', class: 'btn' }, t('Send Test'));
        test.addEventListener('click', async () => {
            test.classList.add('loading');
            try {
                const response = await Craft.sendActionRequest('POST', 'abandoned-cart/emails/send-test', { data: { ...this.values(), to: to.value } });
                Craft.cp.displaySuccess(response.data.message);
            } catch (error) {
                Craft.cp.displayError(errorMessage(error));
            } finally {
                test.classList.remove('loading');
            }
        });

        const note = el('span', { class: 'light ac-panel__note' }, isNew
            ? t('Carts already in this sequence keep their emails. New carts get this one.')
            : t('Wording changes reach emails that haven’t been sent yet.'));
        const save = el('button', { type: 'button', class: 'btn submit' }, isNew ? t('Add Email') : t('Save'));
        save.addEventListener('click', () => void this.save());

        const footer = el('footer', { class: 'ac-panel__foot' }, note, to, test);

        if (!isNew) {
            const remove = el('button', { type: 'button', class: 'btn', 'data-icon': 'trash' }, t('Delete'));
            remove.addEventListener('click', () => void this.remove());
            footer.append(remove);
        }

        footer.append(save);

        return footer;
    }

    private applyStart(preset: StartPreset): void {
        for (const name of ['subject', 'previewText', 'heading', 'message', 'buttonLabel', 'discountCode'] as const) {
            const input = this.form.elements.namedItem(name) as HTMLInputElement | null;
            if (input) input.value = String(preset[name] ?? '');
        }
        this.schedulePreview(0);
    }

    private insertToken(token: string): void {
        const input = this.lastFocused ?? this.form.elements.namedItem('message') as HTMLTextAreaElement;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        input.setRangeText(token, start, end, 'end');
        input.focus();
        this.schedulePreview();
    }

    private values(): Record<string, unknown> {
        const data = new FormData(this.form);
        const values: Record<string, unknown> = {
            id: this.email.id,
            sequenceId: this.email.sequenceId,
            position: this.email.position,
            orderId: this.cartSelect?.value || null,
            delay: { value: data.get('delay[value]'), unit: data.get('delay[unit]') },
        };

        for (const name of ['subject', 'previewText', 'heading', 'message', 'buttonLabel', 'discountCode', 'emailTemplateId']) {
            values[name] = data.get(name) ?? '';
        }

        return values;
    }

    private schedulePreview(delay = 350): void {
        window.clearTimeout(this.previewTimer);
        this.previewTimer = window.setTimeout(() => void this.preview(), delay);
    }

    private async preview(): Promise<void> {
        try {
            const response = await Craft.sendActionRequest<{ subject: string; previewText: string; html: string }>('POST', 'abandoned-cart/emails/preview', { data: this.values() });
            this.inboxSubject.textContent = response.data.subject || t('(no subject)');
            this.inboxPreview.textContent = response.data.previewText;
            this.iframe.srcdoc = response.data.html;
            this.status.textContent = '';
        } catch (error) {
            this.status.textContent = errorMessage(error) ?? t('The preview couldn’t be rendered.');
        }
    }

    private async save(): Promise<void> {
        try {
            const response = await Craft.sendActionRequest('POST', 'abandoned-cart/emails/save', { data: this.values() });
            Craft.cp.displaySuccess(response.data.message);
            location.reload();
        } catch (error) {
            Craft.cp.displayError(errorMessage(error) ?? t('Couldn’t save email.'));
        }
    }

    private async remove(): Promise<void> {
        if (!confirm(t('Delete this email? Carts already in the sequence won’t get it.'))) {
            return;
        }

        try {
            await Craft.sendActionRequest('POST', 'abandoned-cart/emails/delete', { data: { id: this.email.id } });
            location.reload();
        } catch (error) {
            Craft.cp.displayError(errorMessage(error));
        }
    }
}

function mountAudiences(): void {
    const input = document.querySelector<HTMLInputElement>('[data-ac-audience-input]');
    const cards = document.querySelectorAll<HTMLButtonElement>('[data-ac-audience]');

    cards.forEach(card => card.addEventListener('click', () => {
        const type = card.dataset.acAudience!;
        if (input) input.value = type;
        cards.forEach(other => other.setAttribute('aria-checked', String(other === card)));
        document.querySelectorAll<HTMLElement>('[data-ac-audience-detail]').forEach((detail) => {
            detail.hidden = detail.dataset.acAudienceDetail !== type;
            detail.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((field) => {
                field.disabled = detail.hidden;
            });
        });
    }));

    // Only the chosen audience's fields are saved, so a hidden panel can't leak settings into another audience.
    input?.form?.addEventListener('submit', () => {
        document.querySelectorAll<HTMLElement>('[data-ac-audience-detail]').forEach((detail) => {
            detail.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((field) => {
                field.disabled = detail.hidden;
            });
        });
    });

    const operator = document.querySelector<HTMLSelectElement>('select[name="audienceSettings[operator]"]');
    operator?.addEventListener('change', () => {
        const between = document.querySelector<HTMLElement>('[data-ac-between]');
        if (between) between.hidden = operator.value !== 'between';
    });
}

function mountCartTester(): void {
    const tester = document.querySelector<HTMLElement>('[data-ac-cart-tester]');
    const select = tester?.querySelector<HTMLSelectElement>('[data-ac-cart-select]');
    const result = tester?.querySelector<HTMLElement>('[data-ac-cart-result]');

    if (!tester || !select || !result) {
        return;
    }

    loadCarts().then((carts) => {
        for (const cart of carts) select.append(el('option', { value: String(cart.id) }, cart.label));
        if (!carts.length) result.textContent = t('No carts to try yet.');
    });

    select.addEventListener('change', async () => {
        if (!select.value) {
            result.textContent = '';
            return;
        }

        try {
            const response = await Craft.sendActionRequest<{ matches: boolean }>('GET', 'abandoned-cart/sequences/test-cart', { params: { sequenceId: tester.dataset.acCartTester, orderId: select.value } });
            result.replaceChildren(
                el('pk-status-label', { status: response.data.matches ? 'active' : 'inactive' }, response.data.matches ? t('Matches') : t('No match')),
                ' ',
                response.data.message ?? '',
            );
        } catch (error) {
            result.textContent = errorMessage(error) ?? '';
        }
    });
}

function mountDelete(): void {
    document.querySelector<HTMLButtonElement>('[data-ac-delete-sequence]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget as HTMLButtonElement;

        if (!confirm(button.dataset.confirm ?? '')) {
            return;
        }

        try {
            await Craft.sendActionRequest('POST', 'abandoned-cart/sequences/delete', { data: { id: button.dataset.acDeleteSequence } });
            location.href = Craft.getCpUrl('abandoned-cart/sequences');
        } catch (error) {
            Craft.cp.displayError(errorMessage(error));
        }
    });
}

export function mountSequenceEditor(): void {
    const root = document.querySelector<HTMLElement>('[data-ac-sequence-editor]');

    if (!root) {
        return;
    }

    const config = JSON.parse(root.dataset.acSequenceEditor ?? '{}') as EditorConfig;

    mountAudiences();
    mountCartTester();
    mountDelete();

    document.querySelectorAll<HTMLButtonElement>('[data-ac-edit-email]').forEach(button => button.addEventListener('click', async () => {
        try {
            const response = await Craft.sendActionRequest<{ email: EmailData }>('GET', 'abandoned-cart/emails/get', { params: { id: button.dataset.acEditEmail } });
            new EmailPanel(config, response.data.email).open();
        } catch (error) {
            Craft.cp.displayError(errorMessage(error));
        }
    }));

    document.querySelectorAll<HTMLButtonElement>('[data-ac-add-email]').forEach(button => button.addEventListener('click', () => {
        const position = Number(button.dataset.acAddEmail);
        const start = config.starts[position > 1 ? 'nudge' : 'reminder'];

        new EmailPanel(config, {
            id: null,
            sequenceId: config.sequenceId,
            position,
            delayMinutes: position > 1 ? 1440 : 0,
            subject: start.subject ?? '',
            previewText: start.previewText ?? '',
            heading: start.heading ?? '',
            message: start.message ?? '',
            buttonLabel: start.buttonLabel ?? '',
            discountCode: null,
            emailTemplateId: null,
        }).open();
    }));
}
