import { isString } from './i18n';

/** True for a CSS colour. Anything else, such as `url(...)`, must not reach the stylesheet. */
export function isColor(value: unknown): value is string {
  if (!isString(value) || !value.trim()) return false;
  return typeof CSS !== 'undefined' && CSS.supports
    ? CSS.supports('color', value)
    : /^#([\da-f]{3,4}|[\da-f]{6}|[\da-f]{8})$/i.test(value.trim());
}
