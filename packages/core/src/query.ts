/** Provisional default. Each AI accepts a different length; override with `maxUrlLength`. */
export const DEFAULT_MAX_URL_LENGTH = 8000;

export interface QueryVars {
  question: string;
  url: string;
  site_url: string;
  md_url: string;
  title: string;
  site_name: string;
}

/** Length in code points, so that an emoji counts as one character. */
export function countCodePoints(text: string): number {
  return Array.from(text).length;
}

export function truncateCodePoints(text: string, max: number): string {
  const chars = Array.from(text);
  return chars.length > max ? chars.slice(0, max).join('') : text;
}

/** Replaces unpaired surrogates, which `encodeURIComponent` refuses to encode. */
function toWellFormed(text: string): string {
  let out = '';
  for (let i = 0; i < text.length; i++) {
    const c = text.charCodeAt(i);
    if (c >= 0xd800 && c <= 0xdbff) {
      const next = text.charCodeAt(i + 1);
      if (next >= 0xdc00 && next <= 0xdfff) {
        out += text.charAt(i) + text.charAt(i + 1);
        i++;
      } else {
        out += '�';
      }
    } else {
      out += c >= 0xdc00 && c <= 0xdfff ? '�' : text.charAt(i);
    }
  }
  return out;
}

/**
 * Shortens an edit that made the text longer than `max` code points.
 * Only the inserted part is cut, so the text around the caret stays where it was.
 */
export function fitEdit(prev: string, next: string, caret: number, max: number): { value: string; caret: number } {
  if (countCodePoints(next) <= max) return { value: next, caret };
  const isHigh = (c: number): boolean => c >= 0xd800 && c <= 0xdbff;
  // The text after the caret was there before the edit; fall back to comparing when it was not.
  let tail = next.length - caret;
  if (tail < 0 || tail > prev.length || prev.slice(prev.length - tail) !== next.slice(caret)) {
    tail = 0;
    const most = Math.min(prev.length, next.length);
    while (tail < most && prev.charAt(prev.length - 1 - tail) === next.charAt(next.length - 1 - tail)) tail++;
  }
  if (tail && isHigh(next.charCodeAt(next.length - tail - 1))) tail--;
  let head = 0;
  const most = Math.min(prev.length, next.length) - tail;
  while (head < most && prev.charAt(head) === next.charAt(head)) head++;
  if (head && isHigh(prev.charCodeAt(head - 1))) head--;

  const before = prev.slice(0, head);
  const after = prev.slice(prev.length - tail);
  const room = Math.max(0, max - countCodePoints(before) - countCodePoints(after));
  const kept = truncateCodePoints(next.slice(head, next.length - tail), room);
  return { value: before + kept + after, caret: head + kept.length };
}

export function isHttpUrl(href: string): boolean {
  try {
    return /^https?:$/.test(new URL(href).protocol);
  } catch {
    return false;
  }
}

/** Drops the hash and `utm_*` parameters. Returns the input unchanged if it is not a URL. */
export function cleanUrl(href: string): string {
  let u: URL;
  try {
    u = new URL(href);
  } catch {
    return href;
  }
  u.hash = '';
  const full = u.toString();
  const at = full.indexOf('?');
  if (at < 0) return full;
  // The remaining parameters are kept exactly as they were written.
  const kept = full
    .slice(at + 1)
    .split('&')
    .filter((part) => !/^utm_/i.test(part));
  return full.slice(0, at) + (kept.length ? '?' + kept.join('&') : '');
}

/** Root of the site that `href` belongs to, e.g. `https://example.com/`. Empty for anything but http(s). */
export function siteRoot(href: string): string {
  return isHttpUrl(href) ? new URL(href).origin + '/' : '';
}

/** Expands `{name}` placeholders in one pass, so values are never expanded again. */
export function expandTemplate(template: string, vars: { [name: string]: string }): string {
  return template.replace(/\{(\w+)\}/g, (match, name: string) =>
    Object.prototype.hasOwnProperty.call(vars, name) ? vars[name] : match,
  );
}

/** The text handed to the AI. */
export function buildQuery(template: string, vars: QueryVars): string {
  return expandTemplate(template, { ...vars, question: vars.question.trim() });
}

/** Puts the encoded query into a provider URL template. */
export function buildProviderUrl(urlTemplate: string, query: string): string {
  return urlTemplate.replace(/\{query\}/g, () => encodeURIComponent(toWellFormed(query)));
}

export function isTooLong(url: string, max: number = DEFAULT_MAX_URL_LENGTH): boolean {
  return url.length > max;
}
