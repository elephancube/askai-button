// @vitest-environment happy-dom
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { resolveOptions } from '../src/config';
import type { Options, SendEvent } from '../src/types';
import { Widget, type WidgetState } from '../src/widget';

const PAGE = 'https://hogehoge.com/service/cutting/?utm_source=x#price';
const QUESTION = '切削加工の料金は？';
const JA_TEXT = QUESTION + '\n\nこの質問について、以下のURLを参照して回答してください。\nhttps://hogehoge.com/';

declare global {
  interface Window {
    happyDOM: { setURL(url: string): void };
  }
}

let widget: Widget | undefined;
let writeText: ReturnType<typeof vi.fn>;

function mount(options: Options = {}, state?: WidgetState): Widget {
  widget = new Widget(resolveOptions(options, { htmlLang: document.documentElement.lang, navLang: 'en-US' }), state);
  return widget;
}

const host = () => document.querySelector<HTMLElement>('[data-askai-button]')!;
const shadow = () => host().shadowRoot!;
const $ = <T extends HTMLElement = HTMLElement>(selector: string) => shadow().querySelector<T>(selector)!;
const $$ = <T extends HTMLElement = HTMLElement>(selector: string) => Array.from(shadow().querySelectorAll<T>(selector));
const launcher = () => $<HTMLButtonElement>('.launcher');
const input = () => $<HTMLTextAreaElement>('textarea');
const send = () => $<HTMLAnchorElement>('.send');

function type(text: string): void {
  input().value = text;
  input().dispatchEvent(new Event('input', { bubbles: true, composed: true }));
}

function pick(id: string): void {
  const radio = $<HTMLInputElement>(`input[value="${id}"]`);
  radio.checked = true;
  radio.dispatchEvent(new Event('change', { bubbles: true }));
}

function key(target: EventTarget, name: string, shiftKey = false): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, shiftKey, bubbles: true, composed: true, cancelable: true });
  target.dispatchEvent(event);
  return event;
}

function press(target: EventTarget): void {
  target.dispatchEvent(new Event('pointerdown', { bubbles: true, composed: true }));
}

// happy-dom would try to load the AI page when a link is followed.
const stopNavigation = (event: Event) => event.preventDefault();

beforeEach(() => {
  window.happyDOM.setURL(PAGE);
  document.documentElement.lang = 'ja';
  document.title = 'Cutting';
  localStorage.clear();
  writeText = vi.fn(() => Promise.resolve());
  Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
  window.addEventListener('click', stopNavigation);
});

afterEach(() => {
  if (widget) widget.destroy();
  widget = undefined;
  window.removeEventListener('click', stopNavigation);
  document.head.replaceChildren();
  document.body.replaceChildren();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('mounting', () => {
  it('renders a Japanese launcher on a Japanese page', () => {
    mount();
    expect(launcher().textContent).toBe('AIに質問');
    expect(launcher().getAttribute('aria-haspopup')).toBe('dialog');
    expect(launcher().getAttribute('aria-expanded')).toBe('false');
    expect($('.root').lang).toBe('ja');
    expect($('.root').getAttribute('data-pos')).toBe('bottom-right');
  });

  it('renders an English launcher on an English page', () => {
    document.documentElement.lang = 'en';
    mount();
    expect(launcher().textContent).toBe('Ask AI');
  });

  it('adopts one constructed stylesheet and adds no style element', () => {
    mount();
    expect(shadow().adoptedStyleSheets).toHaveLength(1);
    expect(shadow().querySelector('style')).toBeNull();
    const first = shadow().adoptedStyleSheets[0];
    widget!.destroy();
    mount();
    expect(shadow().adoptedStyleSheets[0]).toBe(first);
  });

  it('falls back to a style element where constructed stylesheets do not work', async () => {
    vi.spyOn(CSSStyleSheet.prototype, 'replaceSync').mockImplementation(() => {
      throw new Error('unsupported');
    });
    // The stylesheet is cached per module, so take a module that has not made one yet.
    vi.resetModules();
    const fresh = await import('../src/widget');
    widget = new fresh.Widget(resolveOptions({}, { htmlLang: 'ja', navLang: 'en' })) as unknown as Widget;
    expect(shadow().adoptedStyleSheets || []).toHaveLength(0);
    const styles = shadow().querySelectorAll('style');
    expect(styles).toHaveLength(1);
    expect(styles[0].textContent).toContain(':host');
    expect(styles[0].textContent).toContain('.launcher');
    expect(shadow().firstElementChild).toBe(styles[0]);
  });

  it('sets custom properties only for usable option values', () => {
    vi.stubGlobal('CSS', undefined);
    mount();
    expect(host().getAttribute('style') || '').toBe('');
    widget!.destroy();
    mount({ accentColor: '#06c', offset: 40, zIndex: 5, position: 'bottom-left' });
    expect(host().style.getPropertyValue('--askai-accent')).toBe('#06c');
    expect(host().style.getPropertyValue('--askai-offset')).toBe('40px');
    expect(host().style.getPropertyValue('--askai-z-index')).toBe('5');
    expect($('.root').getAttribute('data-pos')).toBe('bottom-left');
    widget!.destroy();
    mount({ accentColor: 'url(https://x.example/t.gif)', offset: NaN, zIndex: Infinity });
    expect(host().getAttribute('style') || '').toBe('');
  });

  it('removes a button left on the page by another copy of the script', () => {
    const stale = document.createElement('div');
    stale.setAttribute('data-askai-button', '');
    document.body.appendChild(stale);
    mount();
    expect(document.querySelectorAll('[data-askai-button]')).toHaveLength(1);
    expect(stale.isConnected).toBe(false);
    expect(launcher().textContent).toBe('AIに質問');
  });

  it('does not build the panel before it is first opened', () => {
    mount();
    expect($('.panel')).toBeNull();
    launcher().click();
    expect($('.panel')).not.toBeNull();
  });
});

describe('panel markup', () => {
  it('builds every part from the dictionary', () => {
    mount({ presets: ['料金は？', '納期は？'] });
    widget!.open();
    const panel = $('.panel');
    expect(panel.getAttribute('role')).toBe('dialog');
    expect(panel.getAttribute('aria-labelledby')).toBe($('.title').id);
    expect($('.title').textContent).toBe('このサイトについてAIに聞く');
    expect($('.close').getAttribute('aria-label')).toBe('閉じる');
    expect($<HTMLLabelElement>('label.sr').htmlFor).toBe(input().id);
    expect(input().placeholder).toBe('質問を入力してください');
    expect(input().hasAttribute('maxlength')).toBe(false);
    expect($('.chips').getAttribute('role')).toBe('group');
    expect($$('.chip').map((chip) => chip.textContent)).toEqual(['料金は？', '納期は？']);
    expect($$('.ai').map((label) => label.textContent)).toEqual(['ChatGPT', 'Claude', 'Google AIモード']);
    expect($$('.ai.on').length).toBe(1);
    expect($('.ai.on input').getAttribute('value')).toBe('chatgpt');
    expect($('.note').textContent).toBe(
      'このサイトのURLだけを渡します。ChatGPT はURLをその場で開いて読みます。 質問は自動で送信されます。',
    );
    expect($('.fallback summary').textContent).toBe('AIがこのサイトを読めない場合');
    expect($('.msg').getAttribute('role')).toBe('status');
    expect(send().target).toBe('_blank');
    expect(send().rel).toBe('noopener');
    expect(send().textContent).toBe('ChatGPT で質問する ↗');
  });

  it('keeps the title and the send link outside the part that scrolls', () => {
    mount({ presets: ['料金は？'] });
    widget!.open();
    const names = (parent: Element): string[] =>
      Array.from(parent.children).map((node) => node.tagName.toLowerCase() + (node.className ? '.' + node.className : ''));
    expect(names($('.panel'))).toEqual(['div.head', 'div.body', 'div.foot']);
    expect(names($('.head'))).toEqual(['h2.title', 'button.close']);
    expect(names($('.body'))).toEqual([
      'p.sub',
      'label.sr',
      'textarea',
      'div.chips',
      'fieldset.ais',
      'p.note',
      'details.fallback',
    ]);
    expect(names($('.foot'))).toEqual(['p.msg', 'a.send']);
  });

  it('omits the chips without presets and still shows a single provider', () => {
    mount({ providers: ['claude'] });
    widget!.open();
    expect($('.chips')).toBeNull();
    expect($$('.ai').length).toBe(1);
    expect(send().textContent).toBe('Claude で質問する ↗');
  });

  it('treats markup in options as plain text', () => {
    mount({ presets: ['<img src=x onerror=alert(1)>'], labels: { panelTitle: '<b>x</b>' } });
    widget!.open();
    expect(shadow().querySelector('img')).toBeNull();
    expect(shadow().querySelector('b')).toBeNull();
    expect($('.chip').textContent).toBe('<img src=x onerror=alert(1)>');
    type('<script>alert(1)</script>');
    expect(shadow().querySelector('script')).toBeNull();
  });
});

describe('opening and closing', () => {
  it('toggles with the launcher and focuses the textarea', () => {
    const opened = vi.fn();
    document.addEventListener('askai:open', opened);
    mount();
    launcher().click();
    expect(launcher().getAttribute('aria-expanded')).toBe('true');
    expect($('.panel').hidden).toBe(false);
    expect(shadow().activeElement).toBe(input());
    expect(opened).toHaveBeenCalledTimes(1);
    launcher().click();
    expect(launcher().getAttribute('aria-expanded')).toBe('false');
    expect($('.panel').hidden).toBe(true);
    document.removeEventListener('askai:open', opened);
  });

  it('closes with the close button and returns focus to the launcher', () => {
    mount();
    widget!.open();
    $('.close').click();
    expect($('.panel').hidden).toBe(true);
    expect(launcher().getAttribute('aria-expanded')).toBe('false');
    expect(shadow().activeElement).toBe(launcher());
  });

  it('closes with Escape and returns focus to the launcher', () => {
    mount();
    widget!.open();
    key(input(), 'Escape');
    expect($('.panel').hidden).toBe(true);
    expect(shadow().activeElement).toBe(launcher());
  });

  it('leaves Escape alone while it cancels an IME conversion', () => {
    mount();
    widget!.open();
    const escape = (props: { [name: string]: unknown }): void => {
      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, composed: true, cancelable: true });
      Object.keys(props).forEach((name) => Object.defineProperty(event, name, { value: props[name] }));
      input().dispatchEvent(event);
    };
    escape({ keyCode: 229 });
    expect($('.panel').hidden).toBe(false);
    escape({ isComposing: true });
    expect($('.panel').hidden).toBe(false);
    escape({ keyCode: 27 });
    expect($('.panel').hidden).toBe(true);
  });

  it('leaves Escape alone when the page has already handled it', () => {
    const handled = (event: Event) => event.preventDefault();
    document.body.addEventListener('keydown', handled);
    mount();
    widget!.open();
    key(document.body, 'Escape');
    expect($('.panel').hidden).toBe(false);
    document.body.removeEventListener('keydown', handled);
    key(document.body, 'Escape');
    expect($('.panel').hidden).toBe(true);
  });

  it('closes on a press outside without moving focus', () => {
    mount();
    widget!.open();
    press(input());
    expect($('.panel').hidden).toBe(false);
    press(document.body);
    expect($('.panel').hidden).toBe(true);
    expect(launcher().getAttribute('aria-expanded')).toBe('false');
    expect(shadow().activeElement).not.toBe(launcher());
  });

  it('keeps Tab inside the panel', () => {
    mount({ presets: ['a'] });
    widget!.open();
    const summary = $('.fallback summary');
    summary.focus();
    // Empty question: the copy button is in a closed <details> and the send link has no href.
    expect(key(summary, 'Tab').defaultPrevented).toBe(true);
    expect(shadow().activeElement).toBe($('.close'));
    expect(key($('.close'), 'Tab', true).defaultPrevented).toBe(true);
    expect(shadow().activeElement).toBe(summary);

    type('q');
    send().focus();
    key(send(), 'Tab');
    expect(shadow().activeElement).toBe($('.close'));
    input().focus();
    expect(key(input(), 'Tab').defaultPrevented).toBe(false);
  });

  it('restores the open state and the question', () => {
    mount();
    widget!.open();
    type(QUESTION);
    const state = widget!.getState();
    expect(state).toEqual({
      open: true,
      question: QUESTION,
      provider: 'chatgpt',
      preset: null,
      fallback: false,
      focus: true,
    });
    widget!.destroy();
    mount({}, state);
    expect($('.panel').hidden).toBe(false);
    expect(input().value).toBe(QUESTION);
    expect(launcher().getAttribute('aria-expanded')).toBe('true');
    expect(send().hasAttribute('href')).toBe(true);
  });

  it('restores the chosen AI, the tapped preset and the unfolded steps', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    const opened = vi.fn();
    document.addEventListener('askai:open', opened);
    localStorage.setItem('askai-button:provider', 'googleai');

    mount({}, { open: true, question: QUESTION, provider: 'claude', preset: QUESTION, fallback: true, focus: true });
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
    expect($<HTMLDetailsElement>('.fallback').open).toBe(true);
    expect(shadow().activeElement).toBe(input());
    expect(opened).not.toHaveBeenCalled();
    send().click();
    expect(events.map((event) => event.source)).toEqual(['preset']);
    expect(widget!.getState()).toMatchObject({ provider: 'claude', preset: QUESTION, fallback: true });

    widget!.destroy();
    mount({ providers: ['chatgpt', 'googleai'] }, { open: false, question: '', provider: 'claude', fallback: true });
    expect($('.panel')).toBeNull();
    expect(widget!.getState()).toMatchObject({ open: false, provider: 'googleai', fallback: true, focus: false });
    widget!.open();
    expect($<HTMLDetailsElement>('.fallback').open).toBe(true);

    document.removeEventListener('askai:send', listener);
    document.removeEventListener('askai:open', opened);
  });

  it('removes the host and its document listeners on destroy', () => {
    const add = vi.spyOn(document, 'addEventListener');
    const remove = vi.spyOn(document, 'removeEventListener');
    const w = mount();
    w.open();
    w.destroy();
    expect(document.querySelector('[data-askai-button]')).toBeNull();
    add.mock.calls.forEach(([name, listener]) => {
      expect(remove.mock.calls.some((call) => call[0] === name && call[1] === listener)).toBe(true);
    });
    expect(() => {
      key(document.body, 'Escape');
      press(document.body);
    }).not.toThrow();
    expect(w.getState().open).toBe(false);
    w.open();
    expect(document.querySelector('[data-askai-button]')).toBeNull();
  });
});

describe('send link', () => {
  it('is disabled while the question is empty', () => {
    mount();
    widget!.open();
    expect(send().hasAttribute('href')).toBe(false);
    expect(send().getAttribute('aria-disabled')).toBe('true');
    expect(send().getAttribute('role')).toBe('link');
    type('   \n ');
    expect(send().hasAttribute('href')).toBe(false);
    const sent = vi.fn();
    document.addEventListener('askai:send', sent);
    send().click();
    expect(sent).not.toHaveBeenCalled();
    document.removeEventListener('askai:send', sent);
  });

  it('carries the question, the fixed sentence and the site root', () => {
    mount();
    widget!.open();
    type(QUESTION);
    expect(send().getAttribute('href')).toBe('https://chatgpt.com/?q=' + encodeURIComponent(JA_TEXT));
    expect(send().getAttribute('href')).toContain('%0Ahttps%3A%2F%2Fhogehoge.com%2F');
    expect(send().getAttribute('href')).not.toContain('utm_source');
    expect(send().hasAttribute('aria-disabled')).toBe(false);
    expect(send().hasAttribute('role')).toBe(false);
  });

  it('follows the selected provider and remembers it', () => {
    mount();
    widget!.open();
    type(QUESTION);
    pick('claude');
    expect(send().getAttribute('href')).toBe('https://claude.ai/new?q=' + encodeURIComponent(JA_TEXT));
    expect(send().textContent).toBe('Claude で質問する ↗');
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
    expect($('.note').textContent).toContain('ログインが必要です');
    expect(localStorage.getItem('askai-button:provider')).toBe('claude');
    expect(localStorage.length).toBe(1);

    widget!.destroy();
    mount({ defaultProvider: 'googleai' });
    widget!.open();
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
  });

  it('falls back to the default provider, then to the first one', () => {
    localStorage.setItem('askai-button:provider', 'gone');
    mount({ defaultProvider: 'googleai' });
    widget!.open();
    expect($('.ai.on input').getAttribute('value')).toBe('googleai');
    widget!.destroy();
    mount({ defaultProvider: 'nope' });
    widget!.open();
    expect($('.ai.on input').getAttribute('value')).toBe('chatgpt');
  });

  it('works when storage is blocked', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    mount();
    widget!.open();
    expect(() => pick('claude')).not.toThrow();
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
  });

  it('uses the siteUrl option instead of the origin', () => {
    mount({ siteUrl: 'https://example.org/shop/' });
    widget!.open();
    type(QUESTION);
    expect(send().getAttribute('href')).toBe(
      'https://chatgpt.com/?q=' + encodeURIComponent(JA_TEXT.replace('https://hogehoge.com/', 'https://example.org/shop/')),
    );
  });

  it('fills the other variables from the page', () => {
    const canonical = document.createElement('link');
    canonical.rel = 'canonical';
    canonical.setAttribute('href', 'https://hogehoge.com/cutting/?utm_medium=m&id=1#top');
    const meta = document.createElement('meta');
    meta.setAttribute('property', 'og:site_name');
    meta.setAttribute('content', 'Hoge');
    document.head.append(canonical, meta);
    mount({ queryTemplate: '{question}|{url}|{md_url}|{title}|{site_name}|{site_url}' });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(
      'https://chatgpt.com/?q=' +
        encodeURIComponent('q|https://hogehoge.com/cutting/?id=1|https://hogehoge.com/cutting/?id=1|Cutting|Hoge|https://hogehoge.com/'),
    );
  });

  it('picks up a client-side navigation at send time', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    mount({ queryTemplate: '{question} {url}' });
    widget!.open();
    type('q');
    window.happyDOM.setURL('https://hogehoge.com/next/');
    send().click();
    expect(send().getAttribute('href')).toBe('https://chatgpt.com/?q=' + encodeURIComponent('q https://hogehoge.com/next/'));
    expect(events[0].url).toBe('https://hogehoge.com/next/');
    document.removeEventListener('askai:send', listener);
  });

  it('resolves a relative url option against the page', () => {
    mount({ url: '/docs/page?utm_source=a&x=1%20y#top', queryTemplate: '{question} {url}' });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(
      'https://chatgpt.com/?q=' + encodeURIComponent('q https://hogehoge.com/docs/page?x=1%20y'),
    );
    widget!.destroy();
    mount({ url: '../milling/', queryTemplate: '{question} {url}' });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(
      'https://chatgpt.com/?q=' + encodeURIComponent('q https://hogehoge.com/service/milling/'),
    );
  });

  it('is refreshed before it can be opened without a click', () => {
    mount({ queryTemplate: '{question} {url}' });
    widget!.open();
    type('q');
    ['pointerdown', 'focus', 'contextmenu'].forEach((name, i) => {
      window.happyDOM.setURL('https://hogehoge.com/page-' + i + '/');
      send().dispatchEvent(new Event(name, { cancelable: true }));
      expect(send().getAttribute('href'), name).toBe(
        'https://chatgpt.com/?q=' + encodeURIComponent('q https://hogehoge.com/page-' + i + '/'),
      );
    });
  });

  it('treats a middle click like a click', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    const aux = (button: number): MouseEvent => {
      const event = new MouseEvent('auxclick', { button, bubbles: true, composed: true, cancelable: true });
      send().dispatchEvent(event);
      return event;
    };
    mount({ queryTemplate: '{question} {url}' });
    widget!.open();
    expect(aux(1).defaultPrevented).toBe(true);
    expect(events).toEqual([]);

    type('q');
    window.happyDOM.setURL('https://hogehoge.com/next/');
    expect(aux(2).defaultPrevented).toBe(false);
    expect(events).toEqual([]);
    expect(aux(1).defaultPrevented).toBe(false);
    expect(send().getAttribute('href')).toBe('https://chatgpt.com/?q=' + encodeURIComponent('q https://hogehoge.com/next/'));
    expect(events).toEqual([{ provider: 'chatgpt', source: 'free', url: 'https://hogehoge.com/next/' }]);
    document.removeEventListener('askai:send', listener);
  });

  it('is disabled with a message when the URL gets too long', () => {
    mount({ maxUrlLength: 500 });
    widget!.open();
    type('あ'.repeat(40));
    expect(send().hasAttribute('href')).toBe(false);
    expect(send().getAttribute('aria-disabled')).toBe('true');
    expect($('.msg').textContent).toBe('質問が長すぎます');
    expect($('.msg').classList.contains('error')).toBe(true);
    type('a');
    expect(send().hasAttribute('href')).toBe(true);
    expect($('.msg').textContent).toBe('');
    expect($('.msg').classList.contains('error')).toBe(false);
  });

  it('does not touch the status message while it stays the same', () => {
    mount({ maxUrlLength: 500 });
    widget!.open();
    type('あ'.repeat(40));
    const msg = $('.msg');
    expect(msg.textContent).toBe('質問が長すぎます');
    const observer = new MutationObserver(() => {});
    observer.observe(msg, { childList: true, characterData: true, attributes: true, subtree: true });
    type('あ'.repeat(41));
    type('あ'.repeat(42));
    send().dispatchEvent(new Event('focus'));
    expect(observer.takeRecords()).toEqual([]);
    type('a');
    expect(msg.textContent).toBe('');
    expect(observer.takeRecords().length).toBeGreaterThan(0);
    type('ab');
    expect(observer.takeRecords()).toEqual([]);
    observer.disconnect();
  });

  it('reports a send without the question and keeps the panel as it is', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    const onSend = vi.fn();
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    mount({ onSend });
    widget!.open();
    type(QUESTION);
    send().click();

    const expected = { provider: 'chatgpt', source: 'free', url: 'https://hogehoge.com/service/cutting/' };
    expect(events).toEqual([expected]);
    expect(onSend).toHaveBeenCalledWith(expected);
    expect(JSON.stringify(events[0])).not.toContain(QUESTION);
    expect(open).not.toHaveBeenCalled();
    expect($('.panel').hidden).toBe(false);
    expect(input().value).toBe(QUESTION);
    expect(writeText).not.toHaveBeenCalled();
    document.removeEventListener('askai:send', listener);
  });

  it('still sends when onSend throws', () => {
    const sent = vi.fn();
    document.addEventListener('askai:send', sent);
    mount({
      onSend: () => {
        throw new Error('boom');
      },
    });
    widget!.open();
    type(QUESTION);
    const click = new MouseEvent('click', { bubbles: true, composed: true, cancelable: true });
    expect(() => send().dispatchEvent(click)).not.toThrow();
    expect(sent).toHaveBeenCalledTimes(1);
    document.removeEventListener('askai:send', sent);
  });
});

describe('input length', () => {
  it('counts code points, not UTF-16 units', () => {
    mount({ maxLength: 5 });
    widget!.open();
    type('😀😀😀😀😀');
    expect(input().value).toBe('😀😀😀😀😀');
    type('😀😀😀😀😀😀a');
    expect(input().value).toBe('😀😀😀😀😀');
  });

  it('waits for the end of an IME composition before cutting', () => {
    mount({ maxLength: 3 });
    widget!.open();
    input().value = 'かきくけこ';
    input().dispatchEvent(new InputEvent('input', { bubbles: true, isComposing: true }));
    expect(input().value).toBe('かきくけこ');
    input().dispatchEvent(new Event('compositionend', { bubbles: true }));
    expect(input().value).toBe('かきく');
  });

  const edit = (value: string, caret: number): void => {
    input().value = value;
    input().setSelectionRange(caret, caret);
    input().dispatchEvent(new Event('input', { bubbles: true, composed: true }));
  };

  it('undoes typing in the middle of a full field and puts the caret back', () => {
    mount({ maxLength: 5 });
    widget!.open();
    edit('abcde', 5);
    edit('abXcde', 3);
    expect(input().value).toBe('abcde');
    expect([input().selectionStart, input().selectionEnd]).toEqual([2, 2]);
    edit('Xabcde', 1);
    expect(input().value).toBe('abcde');
    expect([input().selectionStart, input().selectionEnd]).toEqual([0, 0]);
  });

  it('accepts as much of a paste as fits', () => {
    mount({ maxLength: 5 });
    widget!.open();
    edit('abcdefgh', 8);
    expect(input().value).toBe('abcde');
    edit('', 0);
    edit('xz', 2);
    edit('x12345z', 6);
    expect(input().value).toBe('x123z');
    expect([input().selectionStart, input().selectionEnd]).toEqual([4, 4]);
    expect(send().getAttribute('href')).toContain(encodeURIComponent('x123z\n'));
  });

  it('puts an IME conversion that does not fit back to what was there', () => {
    mount({ maxLength: 5 });
    widget!.open();
    edit('あいうえお', 5);
    input().value = 'あいかきうえお';
    input().setSelectionRange(4, 4);
    input().dispatchEvent(new InputEvent('input', { bubbles: true, isComposing: true }));
    expect(input().value).toBe('あいかきうえお');
    input().dispatchEvent(new Event('compositionend', { bubbles: true }));
    expect(input().value).toBe('あいうえお');
    expect([input().selectionStart, input().selectionEnd]).toEqual([2, 2]);
  });
});

describe('presets', () => {
  it('fills the textarea, replacing what was there', () => {
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    const sent = vi.fn();
    document.addEventListener('askai:send', sent);
    mount({ presets: ['料金は？', '納期は？'] });
    widget!.open();
    type('something else');
    $$('.chip')[1].click();
    expect(input().value).toBe('納期は？');
    expect(shadow().activeElement).toBe(input());
    expect(send().getAttribute('href')).toContain(encodeURIComponent('納期は？'));
    expect(open).not.toHaveBeenCalled();
    expect(sent).not.toHaveBeenCalled();
    document.removeEventListener('askai:send', sent);
  });

  it('marks an unedited preset as the source', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    mount({ presets: ['料金は？'] });
    widget!.open();
    $('.chip').click();
    send().click();
    type('料金は？いくら');
    send().click();
    expect(events.map((event) => event.source)).toEqual(['preset', 'free']);
    document.removeEventListener('askai:send', listener);
  });

  it('opens the AI straight away with presetAction "send"', () => {
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    mount({ presets: [QUESTION], presetAction: 'send' });
    widget!.open();
    $('.chip').click();
    expect(input().value).toBe(QUESTION);
    expect(open).toHaveBeenCalledTimes(1);
    expect(open).toHaveBeenCalledWith('https://chatgpt.com/?q=' + encodeURIComponent(JA_TEXT), '_blank', 'noopener');
    expect(events).toEqual([{ provider: 'chatgpt', source: 'preset', url: 'https://hogehoge.com/service/cutting/' }]);
    expect($('.panel').hidden).toBe(false);
    document.removeEventListener('askai:send', listener);
  });

  it('does not open anything when the preset cannot be sent', () => {
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    mount({ presets: ['あ'.repeat(60)], presetAction: 'send', maxUrlLength: 200 });
    widget!.open();
    $('.chip').click();
    expect(open).not.toHaveBeenCalled();
    expect($('.msg').textContent).toBe('質問が長すぎます');
  });
});

describe('clipboard', () => {
  const copyProvider = { id: 'claude', method: 'copy_and_open' as const, name: 'Claude', urlTemplate: 'https://claude.ai/new?q={query}' };

  it('copies instead of passing the text in the URL for copy_and_open', () => {
    mount({ providers: [copyProvider] });
    widget!.open();
    type(QUESTION);
    expect(send().getAttribute('href')).toBe('https://claude.ai/new?q=');
    send().click();
    expect(writeText).toHaveBeenCalledWith(JA_TEXT);
    expect($('.msg').textContent).toBe('質問をコピーしました。Claude の入力欄に貼り付けて送信してください。');
    expect($('.msg').classList.contains('error')).toBe(false);
    type(QUESTION + '?');
    expect($('.msg').textContent).toBe('');
  });

  it('copies before the tab opens', () => {
    const order: string[] = [];
    writeText.mockImplementation(() => {
      order.push('copy');
      return Promise.resolve();
    });
    vi.spyOn(window, 'open').mockImplementation(() => {
      order.push('open');
      return null;
    });
    mount({ providers: [copyProvider], presets: [QUESTION], presetAction: 'send' });
    widget!.open();
    $('.chip').click();
    expect(order).toEqual(['copy', 'open']);
    expect(writeText).toHaveBeenCalledWith(JA_TEXT);
    expect(window.open).toHaveBeenCalledWith('https://claude.ai/new?q=', '_blank', 'noopener');
    expect($('.msg').textContent).toBe('質問をコピーしました。Claude の入力欄に貼り付けて送信してください。');

    // The default action of the link, which opens the tab, runs after every click listener.
    const followed = () => order.push('open');
    window.addEventListener('click', followed);
    send().click();
    expect(order).toEqual(['copy', 'open', 'copy', 'open']);
    window.removeEventListener('click', followed);
  });

  it('copies on a middle click for copy_and_open', () => {
    mount({ providers: [copyProvider] });
    widget!.open();
    type(QUESTION);
    send().dispatchEvent(new MouseEvent('auxclick', { button: 1, bubbles: true, composed: true, cancelable: true }));
    expect(writeText).toHaveBeenCalledWith(JA_TEXT);
    expect($('.msg').textContent).toBe('質問をコピーしました。Claude の入力欄に貼り付けて送信してください。');
  });

  it('opens the manual steps when copying fails', async () => {
    writeText.mockImplementation(() => Promise.reject(new Error('denied')));
    mount({ providers: [copyProvider] });
    widget!.open();
    type(QUESTION);
    send().click();
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect($('.msg').textContent).toBe('');
    expect($<HTMLDetailsElement>('.fallback').open).toBe(true);
  });

  it('copies the full text from the fallback block and confirms for a moment', async () => {
    vi.useFakeTimers();
    mount();
    widget!.open();
    const copy = $<HTMLButtonElement>('.copy');
    expect(copy.disabled).toBe(true);
    type(QUESTION);
    expect(copy.disabled).toBe(false);
    copy.click();
    expect(writeText).toHaveBeenCalledWith(JA_TEXT);
    await vi.advanceTimersByTimeAsync(0);
    expect(copy.textContent).toBe('コピーしました');
    await vi.advanceTimersByTimeAsync(2100);
    expect(copy.textContent).toBe('質問とURLをコピー');
  });

  it('does not confirm when copying fails', async () => {
    writeText.mockImplementation(() => Promise.reject(new Error('denied')));
    mount();
    widget!.open();
    type(QUESTION);
    const copy = $<HTMLButtonElement>('.copy');
    copy.click();
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(copy.textContent).toBe('質問とURLをコピー');
  });

  describe('without the async clipboard', () => {
    type Doc = { execCommand?: (command: string) => boolean };
    let exec: ReturnType<typeof vi.fn>;
    const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

    function ready(): HTMLButtonElement {
      mount();
      widget!.open();
      type(QUESTION);
      $<HTMLDetailsElement>('.fallback').open = true;
      const copy = $<HTMLButtonElement>('.copy');
      copy.focus();
      return copy;
    }

    beforeEach(() => {
      Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
      exec = vi.fn(() => true);
      (document as unknown as Doc).execCommand = exec as unknown as Doc['execCommand'];
    });

    afterEach(() => {
      delete (document as unknown as Doc).execCommand;
    });

    it('copies from a temporary box with everything selected, then tidies up', async () => {
      let selected = '';
      exec.mockImplementation(() => {
        const box = shadow().activeElement as HTMLTextAreaElement;
        selected = box.value.slice(box.selectionStart, box.selectionEnd);
        return true;
      });
      const copy = ready();
      copy.click();
      expect(exec).toHaveBeenCalledWith('copy');
      expect(selected).toBe(JA_TEXT);
      expect($$('textarea')).toHaveLength(1);
      expect(shadow().activeElement).toBe(copy);
      await tick();
      expect(copy.textContent).toBe('コピーしました');
    });

    it('removes the box and restores focus when the command throws', async () => {
      exec.mockImplementation(() => {
        throw new Error('denied');
      });
      const copy = ready();
      expect(() => copy.click()).not.toThrow();
      expect($$('textarea')).toHaveLength(1);
      expect(shadow().activeElement).toBe(copy);
      await tick();
      expect(copy.textContent).toBe('質問とURLをコピー');
    });

    it('does not confirm when the command reports failure', async () => {
      exec.mockImplementation(() => false);
      const copy = ready();
      copy.click();
      await tick();
      expect($$('textarea')).toHaveLength(1);
      expect(copy.textContent).toBe('質問とURLをコピー');
    });
  });

  it('drops the pending confirmation timer on destroy', async () => {
    vi.useFakeTimers();
    mount();
    widget!.open();
    type(QUESTION);
    $('.copy').click();
    await vi.advanceTimersByTimeAsync(0);
    widget!.destroy();
    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('pages that are not on the web', () => {
  const template = '{question}|{site_url}|{url}|{md_url}';
  const asked = (text: string): string => 'https://chatgpt.com/?q=' + encodeURIComponent(text);

  beforeEach(() => {
    window.happyDOM.setURL('file:///C:/Users/me/site/index.html?utm_source=x#top');
  });

  it('hands over no local path', () => {
    const events: SendEvent[] = [];
    const listener = (event: Event) => events.push((event as CustomEvent<SendEvent>).detail);
    document.addEventListener('askai:send', listener);
    mount({ queryTemplate: template });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(asked('q|||'));
    send().click();
    expect(events).toEqual([{ provider: 'chatgpt', source: 'free', url: '' }]);
    document.removeEventListener('askai:send', listener);

    widget!.destroy();
    mount();
    widget!.open();
    type(QUESTION);
    expect(send().getAttribute('href')).toBe(asked(JA_TEXT.replace('https://hogehoge.com/', '')));
    expect(decodeURIComponent(send().getAttribute('href')!)).not.toMatch(/file:|Users/);
  });

  it('uses the siteUrl and url options when they are given', () => {
    mount({ queryTemplate: template, siteUrl: 'https://example.com/', url: 'https://example.com/a?utm_source=x' });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(asked('q|https://example.com/|https://example.com/a|https://example.com/a'));
  });

  it('uses a canonical URL that is on the web', () => {
    const canonical = document.createElement('link');
    canonical.rel = 'canonical';
    canonical.setAttribute('href', 'https://example.com/c');
    document.head.appendChild(canonical);
    mount({ queryTemplate: template });
    widget!.open();
    type('q');
    expect(send().getAttribute('href')).toBe(asked('q||https://example.com/c|https://example.com/c'));
    canonical.setAttribute('href', 'other.html');
    type('q2');
    expect(send().getAttribute('href')).toBe(asked('q2|||'));
  });
});

describe('stylesheet', () => {
  const file = 'styles.css';
  const css = '\n' + readFileSync(new URL('../src/' + file, import.meta.url), 'utf8');

  /** Declarations of the rule whose selector is exactly `selector`. */
  function rule(selector: string): string {
    const parts = css.split('\n' + selector + ' {').slice(1);
    expect(parts.length, selector).toBeGreaterThan(0);
    return parts.map((part) => part.slice(0, part.indexOf('}'))).join('');
  }

  it('lets only the body of the panel scroll', () => {
    expect(rule('.panel')).not.toContain('overflow');
    expect(rule('.panel')).toContain('max-block-size');
    expect(rule('.body')).toContain('overflow-y: auto');
    expect(rule('.body')).toContain('min-block-size: 0');
    expect(rule('.body > *')).toContain('flex: none');
    expect(rule('.foot')).toContain('flex: none');
    expect(rule('.head')).toContain('flex: none');
  });

  it('keeps the disabled send link and copy button readable', () => {
    const send = rule('.send[aria-disabled="true"]');
    expect(send).not.toContain('opacity');
    expect(send).toContain('background: #d2d2d2');
    expect(send).toContain('border-color: #d2d2d2');
    expect(send).toContain('color: #444');
    const copy = rule('.copy:disabled');
    expect(copy).not.toContain('opacity');
    expect(copy).toContain('border-color: #707070');
    expect(copy).toMatch(/\n\s*color: #707070/);
  });

  it('sets a readable placeholder colour', () => {
    const placeholder = rule('textarea::placeholder');
    expect(placeholder).toContain('color: #707070');
    expect(placeholder).toContain('opacity: 1');
  });

  it('shields the host element from page styles', () => {
    expect(rule(':host')).toContain('all: initial !important');
    expect(rule(':host')).toContain('display: block !important');
  });
});

describe('source hygiene', () => {
  it.each(['widget.ts', 'index.ts', 'config.ts', 'i18n.ts', 'providers.ts', 'query.ts'])('%s never writes HTML strings or talks to the network', (file) => {
    const source = readFileSync(new URL('../src/' + file, import.meta.url), 'utf8');
    expect(source).not.toMatch(/innerHTML|outerHTML|insertAdjacentHTML|document\.write/);
    expect(source).not.toMatch(/fetch\(|XMLHttpRequest|sendBeacon|WebSocket|EventSource/);
  });
});

describe('provider colours', () => {
  // happy-dom's CSS.supports accepts anything; the hex-only fallback is stricter.
  beforeEach(() => vi.stubGlobal('CSS', undefined));
  const open = (options: Options = {}) => {
    mount(options).open();
    type(QUESTION);
  };
  const labelOf = (id: string) => $(`input[value="${id}"]`).parentElement!;

  it('has no badge element', () => {
    open();
    expect($$('.badge')).toHaveLength(0);
    expect(labelOf('claude').textContent).toBe('Claude');
  });

  it('gives each label and the send link the colour of its provider', () => {
    open();
    expect(labelOf('chatgpt').style.getPropertyValue('--c')).toBe('#0d7d61');
    expect(labelOf('claude').style.getPropertyValue('--c')).toBe('#b3542f');
    expect(labelOf('googleai').style.getPropertyValue('--c')).toBe('#1a73e8');
    pick('claude');
    expect(labelOf('claude').classList.contains('on')).toBe(true);
    expect(send().style.getPropertyValue('--c')).toBe('#b3542f');
    pick('googleai');
    expect(labelOf('claude').classList.contains('on')).toBe(false);
    expect(send().style.getPropertyValue('--c')).toBe('#1a73e8');
  });

  it('writes no custom property for a provider without a colour or with an invalid one', () => {
    open({
      providers: [
        { id: 'plain', name: 'Plain', urlTemplate: 'https://x.example/?q={query}' },
        { id: 'bad', name: 'Bad', urlTemplate: 'https://y.example/?q={query}', color: 'url(https://x/y.gif)' },
      ],
    });
    ['plain', 'bad'].forEach((id) => {
      pick(id);
      expect(labelOf(id).getAttribute('style') || '').toBe('');
      expect(send().getAttribute('style') || '').toBe('');
    });
  });

  it('takes an overridden colour from the options', () => {
    open({ providers: [{ id: 'claude', color: '#000' } as never] });
    expect(labelOf('claude').style.getPropertyValue('--c')).toBe('#000');
    expect(send().style.getPropertyValue('--c')).toBe('#000');
  });

  it('keeps the send colour in sync when the state is restored', () => {
    mount({}, { open: true, question: QUESTION, provider: 'googleai' });
    expect($<HTMLInputElement>('input[value="googleai"]').checked).toBe(true);
    expect(send().style.getPropertyValue('--c')).toBe('#1a73e8');
  });

  it('ignores a badge in an old definition', () => {
    expect(() =>
      open({ providers: [{ id: 'old', name: 'Old', badge: 'OL', urlTemplate: 'https://x.example/?q={query}' } as never] }),
    ).not.toThrow();
    expect(labelOf('old').textContent).toBe('Old');
  });
});

describe('launcher icon', () => {
  it('has one decorative svg and keeps the label as its text', () => {
    mount();
    const svgs = launcher().querySelectorAll('svg');
    expect(svgs).toHaveLength(1);
    expect(svgs[0].getAttribute('aria-hidden')).toBe('true');
    expect(svgs[0].getAttribute('focusable')).toBe('false');
    expect(svgs[0].getAttribute('viewBox')).toBe('0 0 24 24');
    expect(launcher().textContent).toBe('AIに質問');
    expect(launcher().firstElementChild).toBe(svgs[0]);
  });

  it('is fill-only: no stroke attribute on the svg or its shapes', () => {
    mount();
    const svg = launcher().querySelector('svg') as SVGElement;
    expect(svg.getAttribute('fill')).toBe('currentColor');
    for (const node of [svg, ...Array.from(svg.querySelectorAll('*'))]) {
      expect(node.hasAttribute('stroke')).toBe(false);
    }
  });

  it('is only on the launcher', () => {
    mount().open();
    expect($$('svg')).toHaveLength(1);
  });
});
