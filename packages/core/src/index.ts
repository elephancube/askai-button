import { resolveOptions } from './config';
import { registerLocale as addLocale } from './i18n';
import { registerProvider as addProvider } from './providers';
import type { Dict, Options, ProviderDef } from './types';
import { Widget, type WidgetState } from './widget';

export type { Dict, Localized, Options, ProviderDef, SendEvent, SendMethod } from './types';

const hasDom = typeof document !== 'undefined';

let raw: Options = {};
let widget: Widget | undefined;
let active = false;
let waiting = false;

function isSwitchedOff(): boolean {
  const meta = document.querySelector('meta[name="askai"]');
  return !!meta && (meta.getAttribute('content') || '').trim().toLowerCase() === 'off';
}

function render(state?: WidgetState): void {
  let next: Widget | undefined;
  try {
    if (!isSwitchedOff()) {
      const resolved = resolveOptions(raw, { htmlLang: document.documentElement.lang, navLang: navigator.language });
      if (resolved.providers.length) next = new Widget(resolved, state);
    }
  } catch {
    // Unusable options: whatever is on the page stays as it is.
    return;
  }
  if (widget) widget.destroy();
  widget = next;
}

function onReady(): void {
  waiting = false;
  if (active) render();
}

function rebuild(): void {
  if (active && hasDom && document.body) render(widget && widget.getState());
}

/** Shows the button. Calling it again replaces the existing one. */
export function init(options: Options = {}): void {
  raw = options && typeof options === 'object' ? { ...options } : {};
  active = true;
  if (!hasDom) return;
  if (document.body) {
    render();
  } else if (!waiting) {
    waiting = true;
    document.addEventListener('DOMContentLoaded', onReady, { once: true });
  }
}

export function open(): void {
  if (widget) widget.open();
}

export function close(): void {
  if (widget) widget.close();
}

/** Changes some options, keeping the panel state and the text being typed. */
export function update(options: Options): void {
  raw = { ...raw, ...options };
  rebuild();
}

export function registerProvider(def: ProviderDef): void {
  addProvider(def);
  rebuild();
}

export function registerLocale(code: string, dict: Partial<Dict>): void {
  addLocale(code, dict);
  rebuild();
}

export function destroy(): void {
  active = false;
  if (waiting) document.removeEventListener('DOMContentLoaded', onReady);
  waiting = false;
  if (widget) widget.destroy();
  widget = undefined;
}

/** Options from the `data-*` attributes of the script tag. */
function readAttributes(script: Element): Options {
  const text = (name: string): string | undefined => script.getAttribute('data-' + name) || undefined;
  const num = (name: string): number | undefined => {
    const value = text(name);
    return value !== undefined && isFinite(Number(value)) ? Number(value) : undefined;
  };
  const list = (name: string, separator: string): string[] | undefined => {
    const value = text(name);
    return value === undefined
      ? undefined
      : value
          .split(separator)
          .map((item) => item.trim())
          .filter(Boolean);
  };
  return {
    lang: text('lang'),
    presets: list('presets', '|'),
    position: text('position') as Options['position'],
    offset: num('offset'),
    zIndex: num('z-index'),
    accentColor: text('accent-color'),
    providers: list('providers', ','),
    defaultProvider: text('default-provider'),
    presetAction: text('preset-action') as Options['presetAction'],
    maxLength: num('max-length'),
    siteUrl: text('site-url'),
    url: text('url'),
    mdUrl: text('md-url'),
    siteName: text('site-name'),
  };
}

// <script src="askai-button.min.js" data-askai></script>
const script = hasDom ? document.currentScript : null;
if (script && script.hasAttribute('data-askai')) init(readAttributes(script));
