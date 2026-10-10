import css from './styles.css?inline';
import type { Resolved } from './config';
import { format } from './i18n';
import type { ResolvedProvider } from './providers';
import {
  buildProviderUrl,
  buildQuery,
  cleanUrl,
  countCodePoints,
  fitEdit,
  isHttpUrl,
  isTooLong,
  siteRoot,
  truncateCodePoints,
  type QueryVars,
} from './query';
import type { SendEvent } from './types';

/** What is carried over when the widget is rebuilt with new options. */
export interface WidgetState {
  open: boolean;
  question: string;
  provider?: string;
  /** The preset tapped last, if any. */
  preset?: string | null;
  /** Whether the manual steps are unfolded. */
  fallback?: boolean;
  /** Whether focus was inside the panel. */
  focus?: boolean;
}

interface PanelParts {
  panel: HTMLElement;
  input: HTMLTextAreaElement;
  radios: HTMLInputElement[];
  note: HTMLElement;
  fallback: HTMLDetailsElement;
  copy: HTMLButtonElement;
  msg: HTMLElement;
  send: HTMLAnchorElement;
}

const STORE_KEY = 'askai-button:provider';
const PANEL_ID = 'askai-panel';
const TITLE_ID = 'askai-title';
const INPUT_ID = 'askai-question';
const FOCUSABLE = 'button,textarea,input,summary,a[href]';
const COPIED_MS = 2000;
const HOST_ATTR = 'data-askai-button';

let sheet: CSSStyleSheet | undefined;

/** One constructed stylesheet shared by every instance; a `<style>` element only where that is unsupported. */
function applyStyles(root: ShadowRoot): void {
  try {
    if (!sheet) {
      const created = new CSSStyleSheet();
      created.replaceSync(css);
      sheet = created;
    }
    root.adoptedStyleSheets = [sheet];
  } catch {
    el('style', '', css, root);
  }
}

function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  className?: string,
  text?: string,
  parent?: Node,
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text != null) node.textContent = text;
  if (parent) parent.appendChild(node);
  return node;
}

function button(className: string, text: string, parent: Node, onClick: () => void): HTMLButtonElement {
  const node = el('button', className, text, parent);
  node.type = 'button';
  node.addEventListener('click', onClick);
  return node;
}

const SVG_NS = 'http://www.w3.org/2000/svg';

/** A decorative sparkle: a large four-pointed star with concave sides and a small one at its upper end. */
function sparkle(): SVGSVGElement {
  const svg = document.createElementNS(SVG_NS, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'currentColor');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const path = document.createElementNS(SVG_NS, 'path');
  path.setAttribute(
    'd',
    'M10 5Q11.5 12.5 19 14Q11.5 15.5 10 23Q8.5 15.5 1 14Q8.5 12.5 10 5ZM19 1Q19.7 4.3 23 5Q19.7 5.7 19 9Q18.3 5.7 15 5Q18.3 4.3 19 1Z',
  );
  svg.appendChild(path);
  return svg;
}

function absoluteUrl(value: string): string {
  try {
    return new URL(value, location.href).href;
  } catch {
    return value;
  }
}

function readStored(): string | null {
  try {
    return localStorage.getItem(STORE_KEY);
  } catch {
    return null;
  }
}

function store(id: string): void {
  try {
    localStorage.setItem(STORE_KEY, id);
  } catch {
    // storage is unavailable; the choice just is not remembered
  }
}

export class Widget {
  private readonly r: Resolved;
  private readonly host: HTMLElement;
  private readonly shadow: ShadowRoot;
  private readonly root: HTMLElement;
  private readonly launcher: HTMLButtonElement;
  private ui?: PanelParts;
  private provider: ResolvedProvider;
  private isOpen = false;
  private question = '';
  private lastPreset: string | null = null;
  private fallbackOpen = false;
  private hint = '';
  private href = '';
  private timer?: ReturnType<typeof setTimeout>;
  private dead = false;

  constructor(resolved: Resolved, state?: WidgetState) {
    const r = (this.r = resolved);
    const find = (id: string | null | undefined) => r.providers.filter((p) => p.id === id)[0];
    this.provider =
      find(state && state.provider) || find(readStored()) || find(r.defaultProvider) || r.providers[0];

    const host = (this.host = el('div'));
    host.setAttribute(HOST_ATTR, '');
    if (r.accentColor) host.style.setProperty('--askai-accent', r.accentColor);
    if (r.offset != null) host.style.setProperty('--askai-offset', r.offset + 'px');
    if (r.zIndex != null) host.style.setProperty('--askai-z-index', String(r.zIndex));

    this.shadow = host.attachShadow({ mode: 'open' });
    applyStyles(this.shadow);

    const root = (this.root = el('div', 'root', undefined, this.shadow));
    root.setAttribute('data-pos', r.position);
    root.lang = r.lang.ui;
    // The page direction is inherited; `dir` keeps it even if a page rule changes it on the host.
    const dir = getComputedStyle(document.body).direction;
    if (dir) root.dir = dir;
    root.addEventListener('keydown', (event) => this.trapFocus(event));

    this.launcher = button('launcher', r.dict.buttonLabel, root, () => (this.isOpen ? this.hide(false) : this.open()));
    this.launcher.insertBefore(sparkle(), this.launcher.firstChild);
    this.launcher.setAttribute('aria-haspopup', 'dialog');
    this.launcher.setAttribute('aria-expanded', 'false');

    if (state) {
      this.question = truncateCodePoints(state.question, r.maxLength);
      this.lastPreset = state.preset == null ? null : state.preset;
      this.fallbackOpen = !!state.fallback;
      if (state.open) this.show(false);
    }

    // A second copy of the script may have left its own button on the page.
    Array.from(document.querySelectorAll('[' + HOST_ATTR + ']')).forEach((node) => node.remove());
    document.body.appendChild(host);
    if (state && state.open && state.focus) this.ui!.input.focus();
  }

  open(): void {
    if (!this.isOpen && !this.dead) this.show(true);
  }

  close(): void {
    this.hide(false);
  }

  getState(): WidgetState {
    const ui = this.ui;
    return {
      open: this.isOpen,
      question: ui ? ui.input.value : this.question,
      provider: this.provider.id,
      preset: this.lastPreset,
      fallback: ui ? ui.fallback.open : this.fallbackOpen,
      focus: !!ui && this.isOpen && ui.panel.contains(this.shadow.activeElement),
    };
  }

  destroy(): void {
    this.dead = true;
    this.isOpen = false;
    this.unlisten();
    clearTimeout(this.timer);
    this.host.remove();
  }

  private show(byUser: boolean): void {
    const ui = this.ui || (this.ui = this.build());
    ui.panel.hidden = false;
    this.isOpen = true;
    this.launcher.setAttribute('aria-expanded', 'true');
    document.addEventListener('keydown', this.onKey);
    document.addEventListener('pointerdown', this.onPress, true);
    this.refresh();
    if (byUser) {
      ui.input.focus();
      document.dispatchEvent(new CustomEvent('askai:open'));
    }
  }

  private hide(refocus: boolean): void {
    if (!this.isOpen || !this.ui) return;
    this.ui.panel.hidden = true;
    this.isOpen = false;
    this.launcher.setAttribute('aria-expanded', 'false');
    this.unlisten();
    if (refocus) this.launcher.focus();
  }

  private unlisten(): void {
    document.removeEventListener('keydown', this.onKey);
    document.removeEventListener('pointerdown', this.onPress, true);
  }

  private onKey = (event: KeyboardEvent): void => {
    // Safari reports the Escape that cancels an IME conversion with keyCode 229.
    if (event.key !== 'Escape' || event.isComposing || event.keyCode === 229 || event.defaultPrevented) return;
    // Leave focus alone when the visitor is working somewhere else on the page.
    this.hide(document.activeElement === this.host);
  };

  private onPress = (event: Event): void => {
    if (event.composedPath().indexOf(this.host) < 0) this.hide(false);
  };

  private trapFocus(event: KeyboardEvent): void {
    const ui = this.ui;
    if (event.key !== 'Tab' || !this.isOpen || !ui) return;
    const items = Array.from(ui.panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((node) => {
      const details = node.closest('details');
      return (
        !(node as HTMLButtonElement).disabled &&
        (node.tagName !== 'INPUT' || (node as HTMLInputElement).checked) &&
        (!details || details.open || node.tagName === 'SUMMARY')
      );
    });
    const last = items.length - 1;
    const index = items.indexOf(this.shadow.activeElement as HTMLElement);
    if (index < 0 || index === (event.shiftKey ? 0 : last)) {
      event.preventDefault();
      items[event.shiftKey ? last : 0].focus();
    }
  }

  private build(): PanelParts {
    const r = this.r;
    const d = r.dict;

    const panel = el('section', 'panel');
    panel.id = PANEL_ID;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-labelledby', TITLE_ID);
    this.launcher.setAttribute('aria-controls', PANEL_ID);

    const head = el('div', 'head', undefined, panel);
    el('h2', 'title', d.panelTitle, head).id = TITLE_ID;
    button('close', '×', head, () => this.hide(true)).setAttribute('aria-label', d.close);

    // Only the body scrolls; the title and the send link stay in view.
    const body = el('div', 'body', undefined, panel);
    el('p', 'sub', d.panelSubtitle, body);

    el('label', 'sr', d.inputLabel, body).htmlFor = INPUT_ID;
    const input = el('textarea', undefined, undefined, body);
    input.id = INPUT_ID;
    input.rows = 3;
    input.placeholder = d.placeholder;
    input.value = this.question;
    input.addEventListener('input', (event) => {
      // Changing the text in the middle of an IME composition would break it.
      if (!(event as InputEvent).isComposing) this.fit();
      this.hint = '';
      this.refresh();
    });
    input.addEventListener('compositionend', () => {
      this.fit();
      this.refresh();
    });

    if (r.presets.length) {
      const chips = el('div', 'chips', undefined, body);
      chips.setAttribute('role', 'group');
      chips.setAttribute('aria-label', d.presetsLabel);
      r.presets.forEach((text) => button('chip', text, chips, () => this.usePreset(text)));
    }

    const fieldset = el('fieldset', 'ais', undefined, body);
    el('legend', 'sr', d.providersLabel, fieldset);
    const radios = r.providers.map((p) => {
      const label = el('label', 'ai', undefined, fieldset);
      const radio = el('input', undefined, undefined, label);
      radio.type = 'radio';
      radio.name = 'askai-provider';
      radio.value = p.id;
      radio.addEventListener('change', () => {
        this.provider = p;
        this.hint = '';
        store(p.id);
        this.refresh();
      });
      if (p.color) label.style.setProperty('--c', p.color);
      label.appendChild(document.createTextNode(p.name));
      return radio;
    });

    const note = el('p', 'note', undefined, body);

    const fallback = el('details', 'fallback', undefined, body);
    fallback.open = this.fallbackOpen;
    el('summary', undefined, d.fallbackTitle, fallback);
    el('p', undefined, d.fallbackSteps, fallback);
    const copy = button('copy', d.copy, fallback, () => {
      this.copyText(this.text()).then((ok) => {
        if (!ok || this.dead) return;
        copy.textContent = d.copied;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (copy.textContent = d.copy), COPIED_MS);
      });
    });

    const foot = el('div', 'foot', undefined, panel);
    const msg = el('p', 'msg', undefined, foot);
    msg.setAttribute('role', 'status');
    msg.setAttribute('aria-live', 'polite');

    const send = el('a', 'send', undefined, foot);
    send.target = '_blank';
    send.rel = 'noopener';
    const go = (event: Event): void => {
      // The page URL may have changed since the last edit (client-side navigation).
      this.refresh();
      if (this.href) this.sent();
      else event.preventDefault();
    };
    send.addEventListener('click', go);
    // A middle click opens the link without a click event.
    send.addEventListener('auxclick', (event) => {
      if (event.button === 1) go(event);
    });
    // Keep the link current for "open in new tab" and "copy link address" as well.
    ['pointerdown', 'focus', 'contextmenu'].forEach((name) => send.addEventListener(name, () => this.refresh()));

    this.root.insertBefore(panel, this.launcher);
    return { panel, input, radios, note, fallback, copy, msg, send };
  }

  /** Keeps the question within `maxLength` without moving the caret or dropping text at the end. */
  private fit(): void {
    const input = this.ui!.input;
    const max = this.r.maxLength;
    if (countCodePoints(input.value) > max) {
      const fitted = fitEdit(this.question, input.value, input.selectionStart, max);
      input.value = fitted.value;
      input.setSelectionRange(fitted.caret, fitted.caret);
    }
    this.question = input.value;
  }

  private usePreset(text: string): void {
    const input = this.ui!.input;
    input.value = this.question = this.lastPreset = truncateCodePoints(text, this.r.maxLength);
    this.hint = '';
    this.refresh();
    input.focus();
    if (this.r.presetAction === 'send' && this.href) {
      const href = this.href;
      // Copying comes first: the clipboard is out of reach once the new tab has focus.
      this.sent();
      // Must stay synchronous inside the click, or the popup blocker steps in.
      window.open(href, '_blank', 'noopener');
    }
  }

  /** Read from the live document every time, so client-side navigations are picked up. */
  private vars(): QueryVars {
    const r = this.r;
    const canonical = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    const meta = document.querySelector('meta[property="og:site_name"]');
    // A local path (file://) says nothing to the AI and should not leave the visitor's computer.
    const found = [canonical && canonical.getAttribute('href') ? canonical.href : '', location.href].filter(isHttpUrl)[0];
    const url = r.url ? cleanUrl(absoluteUrl(r.url)) : found ? cleanUrl(found) : '';
    return {
      question: this.ui ? this.ui.input.value : this.question,
      url,
      site_url: r.siteUrl || siteRoot(location.href),
      md_url: r.mdUrl || url,
      title: document.title,
      site_name: r.siteName || (meta && meta.getAttribute('content')) || location.hostname,
    };
  }

  /** The text handed to the AI: question, fixed sentence and URL. */
  private text(): string {
    return buildQuery(this.r.queryTemplate, this.vars());
  }

  private refresh(): void {
    const ui = this.ui;
    if (!ui) return;
    const d = this.r.dict;
    const p = this.provider;
    const name = { provider: p.name };

    const empty = !ui.input.value.trim();
    const href = buildProviderUrl(p.urlTemplate, p.method === 'copy_and_open' ? '' : this.text());
    const tooLong = !empty && isTooLong(href, this.r.maxUrlLength);
    this.href = empty || tooLong ? '' : href;

    const send = ui.send;
    if (p.color) send.style.setProperty('--c', p.color);
    else send.style.removeProperty('--c');
    send.textContent = format(d.send, name);
    if (this.href) {
      send.href = this.href;
      send.removeAttribute('aria-disabled');
      send.removeAttribute('role');
    } else {
      send.removeAttribute('href');
      send.setAttribute('aria-disabled', 'true');
      send.setAttribute('role', 'link');
    }

    ui.radios.forEach((radio) => {
      radio.checked = radio.value === p.id;
      radio.parentElement!.classList.toggle('on', radio.checked);
    });
    const extra = (d as { [key: string]: string })['note.' + p.id];
    ui.note.textContent = format(d.note, name) + (extra ? ' ' + extra : '');
    ui.copy.disabled = empty;
    this.say(tooLong ? d.tooLong : this.hint, tooLong);
  }

  /** The message is a live region: writing the same text again would make it be read out again. */
  private say(text: string, isError: boolean): void {
    const msg = this.ui!.msg;
    if (msg.textContent !== text) msg.textContent = text;
    if (msg.classList.contains('error') !== isError) msg.classList.toggle('error', isError);
  }

  private sent(): void {
    const p = this.provider;
    const ui = this.ui!;
    if (p.method === 'copy_and_open') {
      // Not awaited: the new tab has to open within the same user gesture.
      this.hint = format(this.r.dict.copyHint, { provider: p.name });
      this.copyText(this.text()).then((ok) => {
        if (ok || this.dead) return;
        this.hint = '';
        ui.fallback.open = true;
        this.refresh();
      });
      this.say(this.hint, false);
    }
    const detail: SendEvent = {
      provider: p.id,
      source: ui.input.value === this.lastPreset ? 'preset' : 'free',
      url: this.vars().url,
    };
    try {
      if (this.r.onSend) this.r.onSend(detail);
    } catch {
      // a failing callback must not get in the way of sending
    }
    document.dispatchEvent(new CustomEvent('askai:send', { detail }));
  }

  private copyText(text: string): Promise<boolean> {
    try {
      if (navigator.clipboard) {
        return navigator.clipboard.writeText(text).then(
          () => true,
          () => false,
        );
      }
      // Pages served over plain http have no async clipboard.
      const active = (this.shadow.activeElement || document.activeElement) as HTMLElement | null;
      const box = el('textarea', 'sr', undefined, this.root);
      try {
        box.value = text;
        box.focus();
        box.setSelectionRange(0, text.length);
        return Promise.resolve(document.execCommand('copy'));
      } finally {
        box.remove();
        if (active && active.focus) active.focus();
      }
    } catch {
      return Promise.resolve(false);
    }
  }
}
