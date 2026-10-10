import { afterEach, describe, expect, it, vi } from 'vitest';
import en from '../src/locales/en';
import ja from '../src/locales/ja';
import { isColor, MAX_PRESETS, resolveOptions } from '../src/config';
import type { Options } from '../src/types';

const env = { htmlLang: 'en', navLang: 'en' };
const opts = (v: unknown): Options => v as Options;

describe('resolveOptions defaults', () => {
  it('fills in defaults', () => {
    const r = resolveOptions({}, env);
    expect(r.maxLength).toBe(500);
    expect(r.maxUrlLength).toBe(8000);
    expect(r.position).toBe('bottom-right');
    expect(r.presetAction).toBe('fill');
    expect(r.presets).toEqual([]);
    expect(r.providers.map((p) => p.id)).toEqual(['chatgpt', 'claude', 'googleai']);
    expect(r.offset).toBeUndefined();
    expect(r.zIndex).toBeUndefined();
    expect(r.accentColor).toBeUndefined();
    expect(r.queryTemplate).toBe(en.queryTemplate);
    expect(r.lang).toEqual({ page: 'en', ui: 'en' });
  });

  it('follows the page language', () => {
    const r = resolveOptions({}, { htmlLang: 'ja', navLang: 'en' });
    expect(r.lang.ui).toBe('ja');
    expect(r.dict.buttonLabel).toBe(ja.buttonLabel);
    expect(r.queryTemplate).toBe(ja.queryTemplate);
    expect(r.providers[2].name).toBe('Google AIモード');
  });

  it('validates enum-like options', () => {
    expect(resolveOptions({ position: 'bottom-left' }, env).position).toBe('bottom-left');
    expect(resolveOptions(opts({ position: 'top' }), env).position).toBe('bottom-right');
    expect(resolveOptions({ presetAction: 'send' }, env).presetAction).toBe('send');
    expect(resolveOptions(opts({ presetAction: 'x' }), env).presetAction).toBe('fill');
  });

  it('sanitises numbers', () => {
    expect(resolveOptions({ maxLength: 120.7 }, env).maxLength).toBe(120);
    expect(resolveOptions({ maxLength: 0 }, env).maxLength).toBe(500);
    expect(resolveOptions({ maxLength: -5 }, env).maxLength).toBe(500);
    expect(resolveOptions(opts({ maxLength: '10' }), env).maxLength).toBe(500);
    expect(resolveOptions({ maxUrlLength: 2000 }, env).maxUrlLength).toBe(2000);
    expect(resolveOptions({ offset: 0 }, env).offset).toBe(0);
    expect(resolveOptions({ offset: -1 }, env).offset).toBeUndefined();
    expect(resolveOptions({ zIndex: 10 }, env).zIndex).toBe(10);
  });

  it('rejects lengths that are not finite or below one', () => {
    expect(resolveOptions({ maxLength: Infinity }, env).maxLength).toBe(500);
    expect(resolveOptions({ maxLength: NaN }, env).maxLength).toBe(500);
    expect(resolveOptions({ maxLength: 0.5 }, env).maxLength).toBe(500);
    expect(resolveOptions({ maxLength: 1.9 }, env).maxLength).toBe(1);
    expect(resolveOptions({ maxUrlLength: Infinity }, env).maxUrlLength).toBe(8000);
    expect(resolveOptions({ maxUrlLength: 0.2 }, env).maxUrlLength).toBe(8000);
    expect(resolveOptions(opts({ maxUrlLength: '2000' }), env).maxUrlLength).toBe(8000);
  });

  it('rejects a zIndex or offset that is not a finite number', () => {
    expect(resolveOptions({ zIndex: NaN }, env).zIndex).toBeUndefined();
    expect(resolveOptions({ zIndex: Infinity }, env).zIndex).toBeUndefined();
    expect(resolveOptions(opts({ zIndex: '5' }), env).zIndex).toBeUndefined();
    expect(resolveOptions({ zIndex: 10.6 }, env).zIndex).toBe(11);
    expect(resolveOptions({ zIndex: -1 }, env).zIndex).toBe(-1);
    expect(resolveOptions({ offset: NaN }, env).offset).toBeUndefined();
    expect(resolveOptions({ offset: Infinity }, env).offset).toBeUndefined();
    expect(resolveOptions(opts({ offset: '8' }), env).offset).toBeUndefined();
    expect(resolveOptions({ offset: 12.5 }, env).offset).toBe(12.5);
  });

  it('survives option values of the wrong type', () => {
    const wrong = {
      presets: 'Hello',
      queryTemplate: { en: 5 },
      labels: { en: { send: 5 } },
      providers: 'claude',
      defaultProvider: 3,
      accentColor: 7,
      siteUrl: {},
      url: 1,
      mdUrl: [],
      siteName: true,
      onSend: 'no',
    };
    expect(() => resolveOptions(opts(wrong), env)).not.toThrow();
    const r = resolveOptions(opts(wrong), env);
    expect(r.presets).toEqual([]);
    expect(r.queryTemplate).toBe(en.queryTemplate);
    expect(r.dict.send).toBe(en.send);
    expect(r.providers).toHaveLength(3);
    expect(r.defaultProvider).toBeUndefined();
    expect(r.accentColor).toBeUndefined();
    expect(r.siteUrl).toBeUndefined();
    expect(r.url).toBeUndefined();
    expect(r.mdUrl).toBeUndefined();
    expect(r.siteName).toBeUndefined();
    expect(r.onSend).toBeUndefined();
    expect(resolveOptions(opts({ presets: { en: 'x' } }), env).presets).toEqual([]);
  });
});

describe('accentColor', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('accepts only hex colours where CSS.supports is missing', () => {
    vi.stubGlobal('CSS', undefined);
    ['#06c', '#06cf', '#0066cc', '#0066ccff', ' #ABCDEF '].forEach((value) => expect(isColor(value), value).toBe(true));
    [
      'url(https://x/t.gif)',
      'red',
      '#06',
      '#06c0f',
      '#0066cg',
      '#06c;background:url(https://x/t.gif)',
      'var(--x)',
      '',
      '   ',
      null,
      undefined,
      5,
      {},
    ].forEach((value) => expect(isColor(value), String(value)).toBe(false));
  });

  it('asks the browser where it can', () => {
    const supports = vi.fn((_property: string, value: string) => value === 'rebeccapurple');
    vi.stubGlobal('CSS', { supports });
    expect(isColor('rebeccapurple')).toBe(true);
    expect(isColor('url(https://x/t.gif)')).toBe(false);
    expect(isColor('#06c')).toBe(false);
    expect(supports).toHaveBeenCalledWith('color', 'url(https://x/t.gif)');
    expect(isColor(5)).toBe(false);
    expect(supports).toHaveBeenCalledTimes(3);
  });

  it('drops anything that is not a colour from the options', () => {
    vi.stubGlobal('CSS', undefined);
    expect(resolveOptions({ accentColor: '#06c' }, env).accentColor).toBe('#06c');
    expect(resolveOptions({ accentColor: ' #06c ' }, env).accentColor).toBe('#06c');
    expect(resolveOptions({ accentColor: 'url(https://x/t.gif)' }, env).accentColor).toBeUndefined();
  });

  it('applies label overrides to the dictionary', () => {
    expect(resolveOptions({ labels: { buttonLabel: 'Help' } }, env).dict.buttonLabel).toBe('Help');
  });
});

describe('presets', () => {
  it('trims, drops blanks and caps at the maximum', () => {
    const presets = [' a ', '', '   ', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
    const r = resolveOptions({ presets }, env);
    expect(r.presets).toEqual(['a', 'b', 'c', 'd', 'e', 'f']);
    expect(r.presets).toHaveLength(MAX_PRESETS);
  });

  it('caps after dropping blanks', () => {
    const r = resolveOptions({ presets: ['', '', '1', '2', '3', '4', '5', '6', '7'] }, env);
    expect(r.presets).toEqual(['1', '2', '3', '4', '5', '6']);
  });

  it('ignores non-string items', () => {
    expect(resolveOptions(opts({ presets: ['a', 1, null, 'b'] }), env).presets).toEqual(['a', 'b']);
  });

  it('picks per-language presets by page language', () => {
    const presets = { en: ['Price?'], ja: ['料金は？', '場所は？'] };
    expect(resolveOptions({ presets }, { htmlLang: 'ja', navLang: 'en' }).presets).toEqual(['料金は？', '場所は？']);
    expect(resolveOptions({ presets }, { htmlLang: 'en', navLang: 'ja' }).presets).toEqual(['Price?']);
    expect(resolveOptions({ presets }, { htmlLang: 'fr', navLang: 'ja' }).presets).toEqual(['Price?']);
    expect(resolveOptions({ presets }, { htmlLang: 'ja-JP', navLang: 'en' }).presets).toEqual(['料金は？', '場所は？']);
  });

  it('shows no presets when neither the page language nor en has any', () => {
    const presets = { ja: ['料金は？'] };
    expect(resolveOptions({ presets }, { htmlLang: 'en', navLang: 'ja' }).presets).toEqual([]);
    expect(resolveOptions({ presets }, { htmlLang: 'fr', navLang: 'ja' }).presets).toEqual([]);
  });

  it('uses the explicit lang option over the page', () => {
    const presets = { en: ['E'], ja: ['J'] };
    expect(resolveOptions({ presets, lang: 'ja' }, env).presets).toEqual(['J']);
  });
});

describe('queryTemplate', () => {
  it('overrides the dictionary template with a string', () => {
    expect(resolveOptions({ queryTemplate: '{question} @ {url}' }, env).queryTemplate).toBe('{question} @ {url}');
  });

  it('overrides per language', () => {
    const queryTemplate = { en: 'EN {question}', ja: 'JA {question}' };
    expect(resolveOptions({ queryTemplate }, { htmlLang: 'ja' }).queryTemplate).toBe('JA {question}');
    expect(resolveOptions({ queryTemplate }, { htmlLang: 'en' }).queryTemplate).toBe('EN {question}');
    expect(resolveOptions({ queryTemplate }, { htmlLang: 'fr' }).queryTemplate).toBe('EN {question}');
  });

  it('uses the dictionary when the map has nothing for the page language or en', () => {
    const queryTemplate = { ja: 'JA {question}' };
    expect(resolveOptions({ queryTemplate }, { htmlLang: 'en' }).queryTemplate).toBe(en.queryTemplate);
  });

  it('falls back to the dictionary for null or empty', () => {
    expect(resolveOptions({ queryTemplate: null }, env).queryTemplate).toBe(en.queryTemplate);
    expect(resolveOptions({ queryTemplate: '' }, env).queryTemplate).toBe(en.queryTemplate);
  });

  it('uses a label override of queryTemplate when no option is given', () => {
    expect(resolveOptions({ labels: { queryTemplate: '{question}!' } }, env).queryTemplate).toBe('{question}!');
  });
});

describe('pass-through', () => {
  it('keeps url settings and callbacks', () => {
    const onSend = (): void => {};
    const r = resolveOptions(
      {
        siteUrl: 'https://a/',
        url: 'https://a/b',
        mdUrl: 'https://a/b.md',
        siteName: 'A',
        defaultProvider: 'claude',
        onSend,
      },
      env,
    );
    expect(r.siteUrl).toBe('https://a/');
    expect(r.url).toBe('https://a/b');
    expect(r.mdUrl).toBe('https://a/b.md');
    expect(r.siteName).toBe('A');
    expect(r.defaultProvider).toBe('claude');
    expect(r.onSend).toBe(onSend);
  });
});
