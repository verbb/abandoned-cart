// The parts of Craft's control panel globals this plugin uses.
export type ActionResponse<T = Record<string, unknown>> = { data: T & { message?: string } };

export type CraftGlobal = {
    t(category: string, message: string, params?: Record<string, unknown>): string;
    getActionUrl(action: string, params?: Record<string, unknown>): string;
    getCpUrl(path: string, params?: Record<string, unknown>): string;
    sendActionRequest<T = Record<string, unknown>>(method: string, action: string, options?: { data?: unknown; params?: Record<string, unknown> }): Promise<ActionResponse<T>>;
    cp: { displaySuccess(message?: string): void; displayError(message?: string): void; displayNotice(message?: string): void };
    escapeHtml(text: string): string;
    Slideout: new (content: HTMLElement | JQuery, settings?: Record<string, unknown>) => CraftSlideout;
};

export type CraftSlideout = {
    open(): void;
    close(): void;
    destroy(): void;
    on(event: string, handler: () => void): void;
    $container: JQuery;
};

type JQuery = { 0: HTMLElement; find(selector: string): JQuery; on(event: string, handler: (...args: unknown[]) => void): JQuery };

declare global {
    const Craft: CraftGlobal;
    const Garnish: {
        DragSort: new (items: HTMLElement[] | JQuery, settings: Record<string, unknown>) => { $items: JQuery };
    };
    const $: (target: unknown) => JQuery;
}

export const t = (message: string, params?: Record<string, unknown>): string => Craft.t('abandoned-cart', message, params);

export function errorMessage(error: unknown): string | undefined {
    return (error as { response?: { data?: { message?: string } } }).response?.data?.message;
}
