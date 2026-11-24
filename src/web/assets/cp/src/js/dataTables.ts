import { html } from 'lit';
import {
    dataTableLabels,
    defaultDataTableQuery,
    type DataTableColumn,
    type DataTableQuery,
    type DataTableRow,
    type PkDataTable,
} from '@verbb/plugin-kit-web/components/data-table.js';

import { errorMessage, t } from './craft.js';

type Kind = 'carts' | 'customers';
type Choice = { value: string | number; label: string };
type TableConfig = {
    kind: Kind;
    userId?: string;
    counts: Record<string, number>;
    reasons?: Choice[];
    status?: string;
    sequenceId?: string | null;
    search?: string | null;
    canManage?: boolean;
};

const cartStates: Record<string, string> = { recovery: 'blue', recovered: 'green', not: 'gray', skipped: 'amber' };
const customerStates: Record<string, string> = { active: 'green', unsubscribed: 'gray', doNotEmail: 'red' };

// Cells render inside the table's shadow root, so their small layout rules travel with the markup.
const stack = 'display: grid; gap: 2px; line-height: 1.35;';
const sub = 'font-size: 12px; color: var(--pk-color-gray-550, #596673);';

function personCell(row: DataTableRow) {
    const name = row.customer as string | null;

    // Only the name is the link, so hovering doesn't underline the email beneath it too.
    return html`<span style=${stack}><a href=${String(row.url)} style="font-weight: 500; justify-self: start;">${name || row.email}</a>${name ? html`<span style=${sub}>${row.email}</span>` : null}</span>`;
}

function statusCell(tone: string, label: string, detail?: unknown) {
    return html`<span style=${stack}><pk-status-label status=${tone} style="white-space: nowrap; justify-self: start;">${label}</pk-status-label>${detail ? html`<span style=${sub}>${detail}</span>` : null}</span>`;
}

function progressCell(steps: string[]) {
    if (!steps.length) {
        return html`<span style=${sub}>—</span>`;
    }

    const colours: Record<string, string> = { sent: '#3b82f6', clicked: '#10b981', scheduled: 'transparent', failed: '#dc2626', cancelled: '#cdd8e4', sending: '#93c5fd' };
    const pips = steps.map((status) => html`<i style="display: block; width: 16px; height: 6px; border-radius: 3px; background: ${colours[status] ?? '#cdd8e4'}; ${status === 'scheduled' ? 'box-shadow: inset 0 0 0 1.5px #3b82f6;' : ''}"></i>`);
    const sent = steps.filter((status) => status === 'sent' || status === 'clicked').length;

    return html`<span style=${stack}><span style="display: inline-flex; gap: 3px;" aria-label=${t('{sent} of {total} emails sent', { sent, total: steps.length })}>${pips}</span></span>`;
}

function columns(config: TableConfig): DataTableColumn[] {
    if (config.kind === 'carts') {
        return [
            { id: 'customer', label: t('Customer'), type: 'custom', sortable: true, hideable: false, renderCell: ({ row }) => personCell(row) },
            { id: 'cartTotal', label: t('Cart Value'), type: 'custom', sortable: true, align: 'end', value: row => row.cartTotal as number, renderCell: ({ row }) => row.cartTotalLabel as string },
            { id: 'sequence', label: t('Sequence'), renderCell: ({ value }) => (value as string | null) ?? '—' },
            { id: 'progress', label: t('Emails'), type: 'custom', renderCell: ({ row }) => progressCell(row.progress as string[]) },
            { id: 'state', label: t('Status'), type: 'custom', renderCell: ({ row }) => statusCell(cartStates[row.state as string] ?? 'gray', String(row.stateLabel)) },
            { id: 'detail', label: t('Detail'), renderCell: ({ value }) => (value as string | null) ?? '—' },
            { id: 'abandonedAt', label: t('Abandoned'), sortable: true, wrap: false },
        ];
    }

    return [
        { id: 'customer', label: t('Customer'), type: 'custom', sortable: true, hideable: false, renderCell: ({ row }) => personCell(row) },
        { id: 'status', label: t('Status'), type: 'custom', renderCell: ({ row }) => statusCell(customerStates[row.status as string] ?? 'gray', String(row.statusLabel)) },
        { id: 'detail', label: t('Detail'), renderCell: ({ value }) => (value as string | null) ?? '—' },
        { id: 'carts', label: t('Carts Abandoned'), type: 'number', sortable: true },
        { id: 'recovered', label: t('Recovered'), type: 'number', sortable: true },
        { id: 'rate', label: t('Rate'), align: 'end' },
        { id: 'revenue', label: t('Recovered Revenue'), type: 'custom', sortable: true, align: 'end', value: row => row.revenue as number, renderCell: ({ row }) => row.revenueLabel as string },
        { id: 'lastAbandoned', label: t('Last Abandoned'), sortable: true, wrap: false },
    ];
}

/**
 * The sidebar's sources (see `_includes/sources.html`) are plain links. Take them over so a
 * click switches the table's view in place, keeping the URL in step for reloads and sharing.
 */
function bindSources(onSelect: (params: { status?: string; sequenceId?: string | number }) => void): void {
    const links = Array.from(document.querySelectorAll<HTMLAnchorElement>('#sidebar a[data-ac-source]'));

    for (const link of links) {
        link.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            event.preventDefault();

            for (const other of links) {
                other.classList.toggle('sel', other === link);

                if (other === link) {
                    other.setAttribute('aria-current', 'page');
                } else {
                    other.removeAttribute('aria-current');
                }
            }

            history.replaceState(history.state, '', link.href);
            onSelect(JSON.parse(link.dataset.acSource ?? '{}'));
        });
    }
}

function addAddressButton(table: PkDataTable): HTMLElement {
    const button = document.createElement('pk-button');
    button.slot = 'toolbar-end';
    button.textContent = t('Add Address');
    button.addEventListener('click', async () => {
        const email = prompt(t('Email address to add to Do Not Email'));

        if (!email) {
            return;
        }

        try {
            const response = await Craft.sendActionRequest('POST', 'abandoned-cart/customers/add-address', { data: { email } });
            Craft.cp.displaySuccess(response.data.message);
            await table.refresh();
        } catch (error) {
            Craft.cp.displayError(errorMessage(error));
        }
    });

    return button;
}

function mount(container: HTMLElement): void {
    const config = JSON.parse(container.dataset.acTable ?? '{}') as TableConfig;
    const table = document.createElement('pk-data-table') as PkDataTable;
    // A `pk-data-table-toolbar` in the page header (see the index templates) renders this table's toolbar.
    table.id = `ac-${config.kind}-table`;
    const toolbar = document.querySelector<HTMLElement>(`pk-data-table-toolbar[for="${table.id}"]`) ?? table;
    const state = { status: config.status ?? 'all', sequenceId: config.sequenceId ?? '' };

    table.mode = 'remote';
    table.layout = 'index';
    table.label = config.kind === 'carts' ? t('Carts') : t('Customers');
    table.columns = columns(config);
    table.defaultSort = [{ id: config.kind === 'carts' ? 'abandonedAt' : 'carts', direction: 'desc' }];
    table.labels = {
        ...Object.fromEntries(Object.entries(dataTableLabels).map(([key, value]) => [key, t(value)])),
        search: config.kind === 'carts' ? t('Search by email') : t('Search by email or name'),
        empty: config.kind === 'carts' ? t('No abandoned carts yet') : t('No customers have abandoned a cart yet'),
        noResults: t('Nothing matches those filters'),
    };
    table.query = { ...defaultDataTableQuery(), search: config.search ?? '', sort: table.defaultSort, pageSize: 50 } as DataTableQuery;

    const header = document.querySelector<HTMLElement>('#header');

    if (header) {
        const observer = new ResizeObserver(() => table.style.setProperty('--pk-data-table-header-offset', `${header.offsetHeight}px`));
        observer.observe(header);
    }

    let addAddress: HTMLElement | null = null;

    bindSources((params) => {
        state.status = params.status ?? 'all';
        state.sequenceId = params.sequenceId ? String(params.sequenceId) : '';

        if (addAddress) {
            addAddress.hidden = state.status !== 'doNotEmail';
        }

        void table.refresh();
    });

    if (config.kind === 'customers' && config.canManage) {
        table.selectable = true;
        addAddress = addAddressButton(table);
        addAddress.hidden = state.status !== 'doNotEmail';
        toolbar.append(addAddress);

        const bulk = document.createElement('pk-button');
        bulk.slot = 'selection-actions';
        bulk.textContent = t('Add to Do Not Email');
        bulk.addEventListener('click', async () => {
            const ids = Array.from(table.selectedKeys ?? []);

            if (!ids.length || !confirm(t('Add {count} customers to Do Not Email? Their emails stop straight away.', { count: ids.length }))) {
                return;
            }

            try {
                const response = await Craft.sendActionRequest('POST', 'abandoned-cart/customers/do-not-email', { data: { ids } });
                Craft.cp.displaySuccess(response.data.message);
                await table.refresh();
            } catch (error) {
                Craft.cp.displayError(errorMessage(error));
            }
        });
        table.append(bulk);
    }

    table.dataSource = async (query, { signal }) => {
        const url = new URL(Craft.getActionUrl(`abandoned-cart/${config.kind}/list`), location.href);
        url.searchParams.set('page', String(query.pageIndex + 1));
        url.searchParams.set('per_page', String(query.pageSize));
        url.searchParams.set('search', query.search);
        url.searchParams.set('status', state.status);

        if (state.sequenceId) {
            url.searchParams.set('sequenceId', String(state.sequenceId));
        }

        if (query.sort[0]) {
            url.searchParams.set('sort[0][sortField]', query.sort[0].id);
            url.searchParams.set('sort[0][direction]', query.sort[0].direction);
        }

        const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

        if (!response.ok) {
            throw new Error(`Abandoned Cart table request failed (${response.status}).`);
        }

        const result = await response.json() as { data: DataTableRow[]; pagination: { total: number } };

        return { rows: result.data, total: result.pagination.total };
    };

    container.replaceChildren(table);
}

export function mountDataTables(): void {
    document.querySelectorAll<HTMLElement>('[data-ac-table]').forEach(mount);
}
