import type { Dict } from './locales/en';

export type { Dict };

/** How the question reaches the AI: in the URL, or via the clipboard. */
export type SendMethod = 'url' | 'copy_and_open';

/** A value shared by every language, or one value per language code. */
export type Localized<T> = T | { [lang: string]: T };

export interface ProviderDef {
  id: string;
  name: Localized<string>;
  /** Must start with `https://` and contain `{query}`. */
  urlTemplate: string;
  method?: SendMethod;
  /** Short text shown instead of a logo. Defaults to the first two letters of the name. */
  badge?: string;
}

export interface SendEvent {
  provider: string;
  source: 'preset' | 'free';
  /** URL of the page the visitor was on. The question itself is never included. */
  url: string;
}

export interface Options {
  /** `'auto'` follows the page language. */
  lang?: string;
  position?: 'bottom-right' | 'bottom-left';
  /** Distance from the viewport edges, in px. */
  offset?: number;
  zIndex?: number;
  /** Colour of the launcher and send buttons. */
  accentColor?: string;
  /** Provider ids, or full definitions that override the registered ones. */
  providers?: Array<string | ProviderDef>;
  defaultProvider?: string;
  presets?: Localized<string[]>;
  presetAction?: 'fill' | 'send';
  /** Maximum question length, counted in code points. */
  maxLength?: number;
  /** Longest AI URL that may be opened. */
  maxUrlLength?: number;
  /** Value of `{site_url}`. Defaults to the root of the current origin. */
  siteUrl?: string | null;
  /** Value of `{url}`. Defaults to the canonical URL, then `location.href`. */
  url?: string | null;
  /** Value of `{md_url}`. Defaults to `{url}`. */
  mdUrl?: string | null;
  siteName?: string | null;
  queryTemplate?: Localized<string> | null;
  /** `{ key: text }`, `{ lang: { key: text } }`, or a mix of both. */
  labels?: { [keyOrLang: string]: string | { [key: string]: string } };
  onSend?: (event: SendEvent) => void;
}
