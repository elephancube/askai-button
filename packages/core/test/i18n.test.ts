import { beforeEach, describe, expect, it, vi } from 'vitest';
import en from '../src/locales/en';
import ja from '../src/locales/ja';

type I18n = typeof import('../src/i18n');
let i18n: I18n;

beforeEach(async () => {
  vi.resetModules();
  i18n = await import('../src/i18n');
});

describe('resolveLang', () => {
  it('lets an explicit lang win', () => {
    expect(i18n.resolveLang({ lang: 'ja', htmlLang: 'en', navLang: 'en' })).toEqual({ page: 'ja', ui: 'ja' });
  });

  it('falls to htmlLang, then navLang, then en for auto or empty', () => {
    expect(i18n.resolveLang({ lang: 'auto', htmlLang: 'ja', navLang: 'en' }).ui).toBe('ja');
    expect(i18n.resolveLang({ lang: '', htmlLang: '', navLang: 'ja-JP' }).ui).toBe('ja');
    expect(i18n.resolveLang({ lang: null, htmlLang: null, navLang: null })).toEqual({ page: 'en', ui: 'en' });
    expect(i18n.resolveLang({})).toEqual({ page: 'en', ui: 'en' });
    expect(i18n.resolveLang({ lang: 'AUTO', htmlLang: 'ja' }).ui).toBe('ja');
  });

  it('lets the page language decide, not the browser', () => {
    expect(i18n.resolveLang({ lang: 'auto', htmlLang: 'fr', navLang: 'ja' })).toEqual({ page: 'fr', ui: 'en' });
  });

  it('handles region codes', () => {
    expect(i18n.resolveLang({ htmlLang: 'pt-BR' })).toEqual({ page: 'pt-br', ui: 'en' });
    expect(i18n.resolveLang({ htmlLang: 'ja-JP' })).toEqual({ page: 'ja-jp', ui: 'ja' });
    expect(i18n.resolveLang({ htmlLang: 'en_US' })).toEqual({ page: 'en-us', ui: 'en' });
    expect(i18n.resolveLang({ htmlLang: 'ja_JP' }).ui).toBe('ja');
  });
});

describe('registerLocale', () => {
  it('makes a new language resolvable', () => {
    expect(i18n.resolveLang({ htmlLang: 'ko' }).ui).toBe('en');
    i18n.registerLocale('ko', { buttonLabel: 'AI에게 질문' });
    expect(i18n.resolveLang({ htmlLang: 'ko' }).ui).toBe('ko');
    expect(i18n.resolveLang({ htmlLang: 'ko-KR' }).ui).toBe('ko');
  });

  it('falls back to English for missing keys', () => {
    i18n.registerLocale('ko', { buttonLabel: 'AI에게 질문' });
    const dict = i18n.buildDict(i18n.resolveLang({ htmlLang: 'ko' }));
    expect(dict.buttonLabel).toBe('AI에게 질문');
    expect(dict.close).toBe(en.close);
  });

  it('merges into an existing locale', () => {
    i18n.registerLocale('ja', { buttonLabel: '聞く' });
    const dict = i18n.buildDict(i18n.resolveLang({ htmlLang: 'ja' }));
    expect(dict.buttonLabel).toBe('聞く');
    expect(dict.close).toBe(ja.close);
  });
});

describe('buildDict', () => {
  const enLang = { page: 'en', ui: 'en' };
  const jaLang = { page: 'ja', ui: 'ja' };

  it('uses the UI language dictionary', () => {
    expect(i18n.buildDict(jaLang).buttonLabel).toBe(ja.buttonLabel);
    expect(i18n.buildDict(enLang).buttonLabel).toBe(en.buttonLabel);
  });

  it('applies flat overrides', () => {
    expect(i18n.buildDict(jaLang, { buttonLabel: 'X' }).buttonLabel).toBe('X');
  });

  it('applies per-language overrides for the page language, then en', () => {
    const labels = { ja: { buttonLabel: 'JA' }, en: { buttonLabel: 'EN' } };
    expect(i18n.buildDict(jaLang, labels).buttonLabel).toBe('JA');
    expect(i18n.buildDict(enLang, labels).buttonLabel).toBe('EN');
    expect(i18n.buildDict({ page: 'fr', ui: 'en' }, labels).buttonLabel).toBe('EN');
    expect(i18n.buildDict({ page: 'fr', ui: 'en' }, { ja: { buttonLabel: 'JA' } }).buttonLabel).toBe(en.buttonLabel);
  });

  it('applies en labels on a page in another language', () => {
    expect(i18n.buildDict({ page: 'fr', ui: 'en' }, { en: { buttonLabel: 'X' } }).buttonLabel).toBe('X');
  });

  it('lets the more specific language win: en, then pt, then pt-br', () => {
    const labels = {
      'pt-BR': { buttonLabel: 'BR' },
      pt: { buttonLabel: 'PT', close: 'PT close' },
      en: { buttonLabel: 'EN', close: 'EN close', copy: 'EN copy' },
    };
    const dict = i18n.buildDict({ page: 'pt-br', ui: 'en' }, labels);
    expect(dict.buttonLabel).toBe('BR');
    expect(dict.close).toBe('PT close');
    expect(dict.copy).toBe('EN copy');
  });

  it('copies only string values', () => {
    const labels = { send: 5, close: null, en: { send: 5, copy: ['x'], buttonLabel: 'OK' }, ja: 7 } as never;
    const dict = i18n.buildDict(enLang, labels);
    expect(dict.send).toBe(en.send);
    expect(dict.close).toBe(en.close);
    expect(dict.copy).toBe(en.copy);
    expect(dict.buttonLabel).toBe('OK');
    expect(() => i18n.buildDict(enLang, 'labels' as never)).not.toThrow();
    expect(i18n.buildDict(enLang, 'labels' as never).send).toBe(en.send);
  });

  it('applies region fallbacks for per-language overrides', () => {
    const labels = { ja: { buttonLabel: 'JA' } };
    expect(i18n.buildDict({ page: 'ja-jp', ui: 'ja' }, labels).buttonLabel).toBe('JA');
  });

  it('supports a mix of flat and per-language labels', () => {
    const labels = { close: 'Shut', ja: { buttonLabel: 'JA' } };
    const j = i18n.buildDict(jaLang, labels);
    expect(j.close).toBe('Shut');
    expect(j.buttonLabel).toBe('JA');
    const e = i18n.buildDict(enLang, labels);
    expect(e.close).toBe('Shut');
    expect(e.buttonLabel).toBe(en.buttonLabel);
  });

  it('does not modify the built-in dictionaries', () => {
    i18n.buildDict(enLang, { buttonLabel: 'X' });
    expect(en.buttonLabel).toBe('Ask AI');
  });
});

describe('pickLocalized', () => {
  const isArr = (v: unknown): v is string[] => Array.isArray(v);

  it('returns shared values as is', () => {
    expect(i18n.pickLocalized('hello', 'ja', i18n.isString)).toBe('hello');
    const arr = ['a', 'b'];
    expect(i18n.pickLocalized(arr, 'ja', isArr)).toBe(arr);
  });

  it('returns undefined for null or undefined', () => {
    expect(i18n.pickLocalized(null, 'ja', i18n.isString)).toBeUndefined();
    expect(i18n.pickLocalized(undefined, 'ja', i18n.isString)).toBeUndefined();
  });

  it('picks the exact language', () => {
    expect(i18n.pickLocalized({ en: 'E', ja: 'J' }, 'ja', i18n.isString)).toBe('J');
  });

  it('falls back from region to base language', () => {
    expect(i18n.pickLocalized({ en: 'E', ja: 'J' }, 'ja-JP', i18n.isString)).toBe('J');
    expect(i18n.pickLocalized({ 'pt-BR': 'BR', pt: 'PT', en: 'E' }, 'pt-br', i18n.isString)).toBe('BR');
    expect(i18n.pickLocalized({ pt: 'PT', en: 'E' }, 'pt_BR', i18n.isString)).toBe('PT');
  });

  it('falls back to en, then to nothing', () => {
    expect(i18n.pickLocalized({ en: 'E', ja: 'J' }, 'fr', i18n.isString)).toBe('E');
    expect(i18n.pickLocalized({ ja: 'J', ko: 'K' }, 'fr', i18n.isString)).toBeUndefined();
    expect(i18n.pickLocalized({ ja: ['a'] }, 'en', isArr)).toBeUndefined();
  });

  it('returns nothing for values of the wrong type', () => {
    expect(i18n.pickLocalized('Hello' as never, 'en', isArr)).toBeUndefined();
    expect(i18n.pickLocalized(5 as never, 'en', i18n.isString)).toBeUndefined();
    expect(i18n.pickLocalized({ en: 'x' } as never, 'en', isArr)).toBeUndefined();
    expect(i18n.pickLocalized({ en: 5 } as never, 'en', i18n.isString)).toBeUndefined();
    expect(i18n.pickLocalized({ ja: 5, en: 'E' } as never, 'ja', i18n.isString)).toBe('E');
  });

  it('does not take inherited object keys for languages', () => {
    expect(i18n.pickLocalized({ en: 'E' }, 'constructor', i18n.isString)).toBe('E');
    expect(i18n.pickLocalized({ ja: 'J' }, 'toString', i18n.isString)).toBeUndefined();
    expect(i18n.resolveLang({ lang: 'constructor' })).toEqual({ page: 'constructor', ui: 'en' });
    expect(i18n.resolveLang({ htmlLang: '__proto__' }).ui).toBe('en');
    expect(i18n.buildDict({ page: 'constructor', ui: 'en' }, { en: { close: 'Shut' } }).close).toBe('Shut');
  });

  it('picks per-language arrays', () => {
    expect(i18n.pickLocalized({ en: ['a'], ja: ['b'] }, 'ja', isArr)).toEqual(['b']);
  });
});

describe('format', () => {
  it('replaces known placeholders and keeps unknown ones', () => {
    expect(i18n.format('{a} {b} {c}', { a: '1', b: '2' })).toBe('1 2 {c}');
  });
});

describe('locale dictionaries', () => {
  it('have exactly the same keys', () => {
    expect(Object.keys(ja).sort()).toEqual(Object.keys(en).sort());
  });

  it('use the same placeholders in every language', () => {
    const names = (s: string): string[] => (s.match(/\{\w+\}/g) || []).sort();
    const enDict = en as unknown as { [k: string]: string };
    const jaDict = ja as unknown as { [k: string]: string };
    Object.keys(enDict).forEach((key) => {
      expect(names(jaDict[key]), key).toEqual(names(enDict[key]));
    });
  });

  it('has no empty strings', () => {
    Object.values(en).forEach((v) => expect(v).not.toBe(''));
    Object.values(ja).forEach((v) => expect(v).not.toBe(''));
  });
});

describe('buildDict with en labels on a page that has its own dictionary', () => {
  it('keeps the built-in Japanese text when only en labels are given', async () => {
    const { buildDict, resolveLang } = await import('../src/i18n');
    const dict = buildDict(resolveLang({ htmlLang: 'ja' }), { en: { buttonLabel: 'Ask our AI' } });
    expect(dict.buttonLabel).toBe('AIに質問');
  });
});
