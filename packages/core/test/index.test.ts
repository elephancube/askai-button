// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as api from '../src/index';

declare global {
  interface Window {
    happyDOM: { setURL(url: string): void };
  }
}

const hosts = () => document.querySelectorAll('[data-askai-button]');
// happy-dom would try to load the AI page when a link is followed.
const stopNavigation = (event: Event) => event.preventDefault();
const shadow = () => hosts()[0].shadowRoot!;
const $ = <T extends HTMLElement = HTMLElement>(selector: string) => shadow().querySelector<T>(selector)!;
const $$ = (selector: string) => Array.from(shadow().querySelectorAll<HTMLElement>(selector));

function type(text: string): void {
  const input = $<HTMLTextAreaElement>('textarea');
  input.value = text;
  input.dispatchEvent(new Event('input', { bubbles: true }));
}

beforeEach(() => {
  window.happyDOM.setURL('https://hogehoge.com/service/');
  document.documentElement.lang = 'ja';
  localStorage.clear();
});

afterEach(() => {
  api.destroy();
  document.head.replaceChildren();
  document.body.replaceChildren();
  vi.restoreAllMocks();
});

describe('public API', () => {
  it('exposes the seven functions', () => {
    expect(Object.keys(api).sort()).toEqual(
      ['close', 'destroy', 'init', 'open', 'registerLocale', 'registerProvider', 'update'].sort(),
    );
  });

  it('never leaves two launchers', () => {
    api.init();
    api.init({ lang: 'en' });
    expect(hosts().length).toBe(1);
    expect($('.launcher').textContent).toBe('Ask AI');
  });

  it('opens and closes from code', () => {
    api.init();
    api.open();
    expect($('.panel').hidden).toBe(false);
    api.close();
    expect($('.panel').hidden).toBe(true);
  });

  it('leaves nothing behind after destroy', () => {
    api.init();
    api.open();
    api.destroy();
    expect(hosts().length).toBe(0);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true, composed: true }));
    api.open();
    api.update({ presets: ['a'] });
    expect(hosts().length).toBe(0);
  });

  it('does not render when the page opts out', () => {
    const meta = document.createElement('meta');
    meta.name = 'askai';
    meta.content = 'off';
    document.head.appendChild(meta);
    api.init();
    expect(hosts().length).toBe(0);
  });

  it('does not render without a usable provider', () => {
    api.init({ providers: ['unknown'] });
    expect(hosts().length).toBe(0);
  });

  it('swaps presets while keeping the typed text and the open panel', () => {
    api.init({ presets: ['a', 'b'] });
    api.open();
    type('入力中の質問');
    api.update({ presets: ['c'] });
    expect(hosts().length).toBe(1);
    expect($$('.chip').map((chip) => chip.textContent)).toEqual(['c']);
    expect($('.panel').hidden).toBe(false);
    expect($<HTMLTextAreaElement>('textarea').value).toBe('入力中の質問');
    expect($('.launcher').getAttribute('aria-expanded')).toBe('true');
    expect($('.send').hasAttribute('href')).toBe(true);
  });

  it('keeps a closed panel closed on update', () => {
    api.init();
    api.update({ accentColor: '#06c' });
    expect($('.panel')).toBeNull();
    expect((hosts()[0] as HTMLElement).style.getPropertyValue('--askai-accent')).toBe('#06c');
  });

  it('shows a registered language and falls back to English for missing keys', () => {
    api.init({ lang: 'fr' });
    expect($('.launcher').textContent).toBe('Ask AI');
    api.open();
    api.registerLocale('fr', { buttonLabel: 'Demander à l’IA', close: 'Fermer' });
    expect(hosts().length).toBe(1);
    expect($('.launcher').textContent).toBe('Demander à l’IA');
    expect($('.root').lang).toBe('fr');
    expect($('.panel').hidden).toBe(false);
    expect($('.close').getAttribute('aria-label')).toBe('Fermer');
    expect($('.title').textContent).toBe('Ask AI about this site');
  });

  it('keeps working when option values have the wrong type', () => {
    const wrong = [
      { presets: 'Hello' },
      { presets: { en: 'x' } },
      { queryTemplate: { en: 5 } },
      { labels: { en: { send: 5 } } },
      { providers: 'claude' },
    ];
    wrong.forEach((options) => {
      expect(() => api.init(options as never), JSON.stringify(options)).not.toThrow();
      expect(hosts().length).toBe(1);
      expect(() => api.open()).not.toThrow();
      expect($('.send').textContent).toBe('ChatGPT で質問する ↗');
    });
    expect(() => api.init(null as never)).not.toThrow();
    expect(hosts().length).toBe(1);
  });

  it('keeps the current button when the new options cannot be used at all', () => {
    api.init({ presets: ['a'] });
    api.open();
    type('入力中');
    const before = hosts()[0];
    const broken = {
      get id(): string {
        throw new Error('boom');
      },
    };
    expect(() => api.update({ providers: [broken as never] })).not.toThrow();
    expect(hosts().length).toBe(1);
    expect(hosts()[0]).toBe(before);
    expect($('.panel').hidden).toBe(false);
    expect($<HTMLTextAreaElement>('textarea').value).toBe('入力中');
    api.close();
    expect($('.panel').hidden).toBe(true);
  });

  it('carries the chosen AI, the tapped preset and the open steps over an update', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    const events: Array<{ source: string; provider: string }> = [];
    const listener = (event: Event) => events.push((event as CustomEvent).detail);
    document.addEventListener('askai:send', listener);
    window.addEventListener('click', stopNavigation);

    api.init({ presets: ['料金は？'] });
    api.open();
    const opened = vi.fn();
    document.addEventListener('askai:open', opened);
    const claude = $<HTMLInputElement>('input[value="claude"]');
    claude.checked = true;
    claude.dispatchEvent(new Event('change', { bubbles: true }));
    $('.chip').click();
    $<HTMLDetailsElement>('.fallback').open = true;

    api.update({ position: 'bottom-left' });
    expect($('.root').getAttribute('data-pos')).toBe('bottom-left');
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
    expect($<HTMLDetailsElement>('.fallback').open).toBe(true);
    expect(opened).not.toHaveBeenCalled();
    $('.send').click();
    expect(events).toEqual([expect.objectContaining({ provider: 'claude', source: 'preset' })]);

    api.registerLocale('ja', { close: 'とじる' });
    api.registerProvider({ id: 'my-ai', name: 'My AI', urlTemplate: 'https://ai.example.com/?q={query}' });
    expect($('.ai.on input').getAttribute('value')).toBe('claude');
    expect($<HTMLDetailsElement>('.fallback').open).toBe(true);
    expect(opened).not.toHaveBeenCalled();

    document.removeEventListener('askai:send', listener);
    document.removeEventListener('askai:open', opened);
    window.removeEventListener('click', stopNavigation);
  });

  it('puts focus back in the question box when it was inside the panel', () => {
    api.init();
    api.open();
    $('.fallback summary').focus();
    api.update({ presets: ['a'] });
    expect(shadow().activeElement).toBe($('textarea'));
    expect(document.activeElement).toBe(hosts()[0]);
  });

  it('does not take focus on an update when it was elsewhere', () => {
    const field = document.createElement('input');
    document.body.appendChild(field);
    api.init();
    api.open();
    field.focus();
    api.update({ presets: ['a'] });
    expect(document.activeElement).toBe(field);
    expect(shadow().activeElement).toBeNull();
    expect($('.panel').hidden).toBe(false);
  });

  it('adds a registered provider to the list', () => {
    api.init();
    api.open();
    api.registerProvider({ id: 'my-ai', name: 'My AI', urlTemplate: 'https://ai.example.com/?q={query}' });
    expect($$('.ai').map((label) => label.querySelector('input')!.value)).toEqual([
      'chatgpt',
      'claude',
      'googleai',
      'my-ai',
    ]);
    expect($('.panel').hidden).toBe(false);
    expect(() => api.registerProvider({ id: 'bad', name: 'Bad', urlTemplate: 'http://x/?q={query}' })).toThrow();
  });
});

describe('before the body exists', () => {
  // happy-dom always has a body, so hide it to stand in for a script running in <head>.
  const hideBody = () => Object.defineProperty(document, 'body', { get: () => null, configurable: true });
  const showBody = () => delete (document as unknown as { body?: unknown }).body;
  const ready = () => document.dispatchEvent(new Event('DOMContentLoaded'));

  afterEach(showBody);

  it('waits for DOMContentLoaded and uses the latest options', () => {
    hideBody();
    api.init({ lang: 'ja' });
    api.init({ lang: 'en' });
    showBody();
    expect(hosts().length).toBe(0);
    ready();
    expect(hosts().length).toBe(1);
    expect($('.launcher').textContent).toBe('Ask AI');
    ready();
    expect(hosts().length).toBe(1);
  });

  it('lets a destroy before DOMContentLoaded win', () => {
    hideBody();
    api.init();
    api.destroy();
    showBody();
    ready();
    expect(hosts().length).toBe(0);
  });

  it('takes its DOMContentLoaded listener away on destroy', () => {
    const add = vi.spyOn(document, 'addEventListener');
    const remove = vi.spyOn(document, 'removeEventListener');
    hideBody();
    api.init();
    const added = add.mock.calls.filter((call) => call[0] === 'DOMContentLoaded');
    expect(added).toHaveLength(1);
    api.destroy();
    expect(remove.mock.calls.some((call) => call[0] === 'DOMContentLoaded' && call[1] === added[0][1])).toBe(true);

    // A later init waits again and renders once.
    api.init({ lang: 'en' });
    showBody();
    ready();
    expect(hosts().length).toBe(1);
    expect($('.launcher').textContent).toBe('Ask AI');
  });
});

describe('auto-init', () => {
  async function load(attributes: { [name: string]: string } | null) {
    const script = document.createElement('script');
    Object.keys(attributes || {}).forEach((name) => script.setAttribute(name, attributes![name]));
    // happy-dom does not run scripts here, so point `currentScript` at the tag by hand.
    Object.defineProperty(document, 'currentScript', { get: () => (attributes ? script : null), configurable: true });
    vi.resetModules();
    const fresh: typeof api = await import('../src/index');
    delete (document as unknown as { currentScript?: unknown }).currentScript;
    return fresh;
  }

  it('does nothing without the data-askai attribute', async () => {
    const plain = await load({ 'data-lang': 'en' });
    expect(hosts().length).toBe(0);
    const none = await load(null);
    expect(hosts().length).toBe(0);
    plain.destroy();
    none.destroy();
  });

  it('reads the options from the data attributes of its script tag', async () => {
    window.happyDOM.setURL('https://hogehoge.com/a/?utm_source=x');
    const events: Array<{ url: string }> = [];
    const listener = (event: Event) => events.push((event as CustomEvent).detail);
    document.addEventListener('askai:send', listener);
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);

    const fresh = await load({
      'data-askai': '',
      'data-lang': 'en',
      'data-presets': 'How much? | How long?||',
      'data-position': 'bottom-left',
      'data-offset': '32',
      'data-z-index': '77',
      'data-accent-color': '#0a7',
      'data-providers': 'claude, chatgpt',
      'data-default-provider': 'chatgpt',
      'data-preset-action': 'send',
      'data-max-length': '9',
      'data-site-url': 'https://example.org/',
      'data-url': 'https://example.org/page',
      'data-md-url': 'https://example.org/page.md',
      'data-site-name': 'Example',
    });

    expect(hosts().length).toBe(1);
    const host = hosts()[0] as HTMLElement;
    expect($('.launcher').textContent).toBe('Ask AI');
    expect($('.root').getAttribute('data-pos')).toBe('bottom-left');
    expect(host.style.getPropertyValue('--askai-offset')).toBe('32px');
    expect(host.style.getPropertyValue('--askai-z-index')).toBe('77');
    expect(host.style.getPropertyValue('--askai-accent')).toBe('#0a7');

    fresh.open();
    expect($$('.chip').map((chip) => chip.textContent)).toEqual(['How much?', 'How long?']);
    expect($$('.ai input').map((radio) => (radio as HTMLInputElement).value)).toEqual(['claude', 'chatgpt']);
    expect($('.ai.on input').getAttribute('value')).toBe('chatgpt');

    $$('.chip')[0].click();
    expect(open).toHaveBeenCalledWith(
      'https://chatgpt.com/?q=' +
        encodeURIComponent('How much?\n\nPlease refer to the following URL to answer this question.\nhttps://example.org/'),
      '_blank',
      'noopener',
    );
    expect(events).toEqual([{ provider: 'chatgpt', source: 'preset', url: 'https://example.org/page' }]);

    type('0123456789abc');
    expect($<HTMLTextAreaElement>('textarea').value).toBe('012345678');

    fresh.destroy();
    document.removeEventListener('askai:send', listener);
  });

  it('leaves one launcher when the script is included twice, and the later one wins', async () => {
    const first = await load({ 'data-askai': '', 'data-lang': 'ja' });
    const second = await load({ 'data-askai': '', 'data-lang': 'en' });
    expect(hosts().length).toBe(1);
    expect($('.launcher').textContent).toBe('Ask AI');
    second.open();
    expect($('.panel').hidden).toBe(false);
    first.destroy();
    second.destroy();
    expect(hosts().length).toBe(0);
  });

  it('ignores numbers that are not numbers', async () => {
    const fresh = await load({ 'data-askai': '', 'data-offset': 'wide', 'data-z-index': '' });
    expect((hosts()[0] as HTMLElement).getAttribute('style') || '').toBe('');
    fresh.destroy();
  });
});

describe('provider colour and launcher icon through the API', () => {
  it('shows one icon after update() with new labels and recolours via registerProvider', () => {
    api.init();
    api.update({ labels: { ja: { buttonLabel: '質問する' } } });
    expect($$('.launcher svg')).toHaveLength(1);
    expect($('.launcher').textContent).toBe('質問する');
    api.open();
    api.registerProvider({ id: 'claude', name: 'Claude', urlTemplate: 'https://claude.ai/new?q={query}', color: '#123456' });
    const label = $<HTMLInputElement>('input[value="claude"]').parentElement!;
    expect(label.style.getPropertyValue('--c')).toBe('#123456');
    expect($$('.launcher svg')).toHaveLength(1);
  });
});
