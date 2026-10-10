import { isString, pickLocalized } from './i18n';
import type { ProviderDef, SendMethod } from './types';

export interface ResolvedProvider {
  id: string;
  name: string;
  urlTemplate: string;
  method: SendMethod;
  badge: string;
}

// The `q` parameter is not documented by any of these services and may change.
// These are defaults only: every field can be replaced through options.
const registry = new Map<string, ProviderDef>(
  (
    [
      { id: 'chatgpt', name: 'ChatGPT', urlTemplate: 'https://chatgpt.com/?q={query}', badge: 'GP' },
      { id: 'claude', name: 'Claude', urlTemplate: 'https://claude.ai/new?q={query}', badge: 'CL' },
      {
        id: 'googleai',
        name: { en: 'Google AI Mode', ja: 'Google AIモード' },
        urlTemplate: 'https://www.google.com/search?udm=50&q={query}',
        badge: 'AI',
      },
    ] as ProviderDef[]
  ).map((def) => [def.id, def]),
);

export function isValidProvider(def: ProviderDef | null | undefined): def is ProviderDef {
  return (
    !!def &&
    typeof def.id === 'string' &&
    /^[\w-]+$/.test(def.id) &&
    !!def.name &&
    typeof def.urlTemplate === 'string' &&
    /^https:\/\//i.test(def.urlTemplate) &&
    def.urlTemplate.indexOf('{query}') !== -1
  );
}

export function registerProvider(def: ProviderDef): void {
  if (!isValidProvider(def)) {
    throw new TypeError('askai-button: a provider needs an id, a name and an https:// urlTemplate containing {query}');
  }
  registry.set(def.id, { ...def });
}

/**
 * Turns the `providers` option into the list shown in the panel.
 * Unknown ids and invalid definitions are skipped.
 */
export function resolveProviders(list: Array<string | ProviderDef> | null | undefined, lang: string): ResolvedProvider[] {
  // No prototype, so that ids such as `constructor` are not taken for duplicates.
  const seen: { [id: string]: true } = Object.create(null);
  const out: ResolvedProvider[] = [];
  (Array.isArray(list) ? list : Array.from(registry.keys())).forEach((item) => {
    const base = registry.get(typeof item === 'string' ? item : item && item.id);
    const def = typeof item === 'string' ? base : ({ ...base, ...item } as ProviderDef);
    if (!isValidProvider(def) || seen[def.id]) return;
    seen[def.id] = true;
    const name = pickLocalized(def.name, lang, isString) || def.id;
    out.push({
      id: def.id,
      name,
      urlTemplate: def.urlTemplate,
      method: def.method === 'copy_and_open' ? 'copy_and_open' : 'url',
      badge: (isString(def.badge) && def.badge) || Array.from(name).slice(0, 2).join('').toUpperCase(),
    });
  });
  return out;
}
