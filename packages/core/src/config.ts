import { isColor } from './color';
import { buildDict, isString, pickLocalized, resolveLang, type LangSources, type ResolvedLang } from './i18n';
import { resolveProviders, type ResolvedProvider } from './providers';
import { DEFAULT_MAX_URL_LENGTH } from './query';
import type { Dict, Options, SendEvent } from './types';

export { isColor };

export const MAX_PRESETS = 6;

export interface Resolved {
  lang: ResolvedLang;
  dict: Dict;
  providers: ResolvedProvider[];
  defaultProvider?: string;
  presets: string[];
  presetAction: 'fill' | 'send';
  maxLength: number;
  maxUrlLength: number;
  position: 'bottom-right' | 'bottom-left';
  offset?: number;
  zIndex?: number;
  accentColor?: string;
  queryTemplate: string;
  siteUrl?: string;
  url?: string;
  mdUrl?: string;
  siteName?: string;
  onSend?: (event: SendEvent) => void;
}

const isStringArray = (v: unknown): v is string[] => Array.isArray(v);

const finite = (value: unknown): value is number => typeof value === 'number' && isFinite(value);

const positive = (value: unknown, fallback: number): number =>
  finite(value) && Math.floor(value) >= 1 ? Math.floor(value) : fallback;

const text = (value: unknown): string | undefined => (isString(value) && value ? value : undefined);

/** Fills in defaults and picks the language-specific values. */
export function resolveOptions(raw: Options, env: Pick<LangSources, 'htmlLang' | 'navLang'>): Resolved {
  const lang = resolveLang({ lang: raw.lang, htmlLang: env.htmlLang, navLang: env.navLang });
  const dict = buildDict(lang, raw.labels);
  const presets = (pickLocalized(raw.presets, lang.page, isStringArray) || [])
    .filter(isString)
    .map((text) => text.trim())
    .filter(Boolean)
    .slice(0, MAX_PRESETS);

  return {
    lang,
    dict,
    providers: resolveProviders(raw.providers, lang.page),
    defaultProvider: text(raw.defaultProvider),
    presets,
    presetAction: raw.presetAction === 'send' ? 'send' : 'fill',
    maxLength: positive(raw.maxLength, 500),
    maxUrlLength: positive(raw.maxUrlLength, DEFAULT_MAX_URL_LENGTH),
    position: raw.position === 'bottom-left' ? 'bottom-left' : 'bottom-right',
    offset: finite(raw.offset) && raw.offset >= 0 ? raw.offset : undefined,
    zIndex: finite(raw.zIndex) ? Math.round(raw.zIndex) : undefined,
    accentColor: isColor(raw.accentColor) ? raw.accentColor.trim() : undefined,
    queryTemplate: pickLocalized(raw.queryTemplate, lang.page, isString) || dict.queryTemplate,
    siteUrl: text(raw.siteUrl),
    url: text(raw.url),
    mdUrl: text(raw.mdUrl),
    siteName: text(raw.siteName),
    onSend: typeof raw.onSend === 'function' ? raw.onSend : undefined,
  };
}
