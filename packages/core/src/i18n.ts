import en, { type Dict } from './locales/en';
import ja from './locales/ja';
import type { Localized, Options } from './types';

type StringMap<T> = { [key: string]: T };

/** A map without inherited keys such as `constructor`. */
const emptyMap = <T>(): StringMap<T> => Object.create(null);

const locales = emptyMap<Partial<Dict>>();
locales.en = en;
locales.ja = ja;

export function normLang(code: string | null | undefined): string {
  return String(code || '').trim().toLowerCase().replace(/_/g, '-');
}

/** `pt-br` → `['pt-br', 'pt']` */
export function langChain(code: string): string[] {
  const parts = normLang(code).split('-').filter(Boolean);
  return parts.map((_, i) => parts.slice(0, parts.length - i).join('-'));
}

export function registerLocale(code: string, dict: Partial<Dict>): void {
  const key = normLang(code);
  if (key) locales[key] = { ...locales[key], ...dict };
}

export interface LangSources {
  lang?: string | null;
  htmlLang?: string | null;
  navLang?: string | null;
}

export interface ResolvedLang {
  /** Language of the page, whether or not a dictionary exists for it. */
  page: string;
  /** Language of the dictionary used for the UI. */
  ui: string;
}

/** The page language wins over the visitor's browser language. */
export function resolveLang(src: LangSources): ResolvedLang {
  const explicit = normLang(src.lang);
  const page =
    [explicit === 'auto' ? '' : explicit, normLang(src.htmlLang), normLang(src.navLang)].find(Boolean) || 'en';
  const ui = langChain(page).find((code) => code in locales) || 'en';
  return { page, ui };
}

/**
 * Picks the value for `lang` from a per-language map, or returns a shared value as is.
 * A map is searched for the page language, then its base language, then `en`.
 */
export function pickLocalized<T>(
  value: Localized<T> | null | undefined,
  lang: string,
  isShared: (v: unknown) => v is T,
): T | undefined {
  if (value == null) return undefined;
  if (isShared(value)) return value;
  if (typeof value !== 'object') return undefined;
  const map = emptyMap<unknown>();
  Object.keys(value).forEach((code) => (map[normLang(code)] = (value as StringMap<unknown>)[code]));
  const hit = langChain(lang).concat('en').find((code) => isShared(map[code]));
  return hit ? (map[hit] as T) : undefined;
}

export const isString = (v: unknown): v is string => typeof v === 'string';

/**
 * Dictionary for the UI: English, then the UI language, then the site's own labels.
 * Per-language labels apply from `en` up to the most specific page language;
 * `en` labels are skipped when the UI itself is in another language.
 */
export function buildDict(lang: ResolvedLang, labels?: Options['labels']): Dict {
  const dict: StringMap<string> = { ...en, ...locales[lang.ui] };
  const perLang = emptyMap<StringMap<unknown>>();
  const source: StringMap<unknown> = labels && typeof labels === 'object' ? labels : {};
  Object.keys(source).forEach((key) => {
    const value = source[key];
    if (typeof value === 'string') dict[key] = value;
    else if (value && typeof value === 'object') perLang[normLang(key)] = value as StringMap<unknown>;
  });
  (lang.ui === 'en' ? ['en'] : []).concat(langChain(lang.page).reverse()).forEach((code) => {
    const map = perLang[code] || {};
    Object.keys(map).forEach((key) => {
      const value = map[key];
      if (typeof value === 'string') dict[key] = value;
    });
  });
  return dict as Dict;
}

export function format(text: string, vars: { [name: string]: string }): string {
  return text.replace(/\{(\w+)\}/g, (match, name: string) =>
    Object.prototype.hasOwnProperty.call(vars, name) ? vars[name] : match,
  );
}
