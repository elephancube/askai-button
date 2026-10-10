import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProviderDef } from '../src/types';

type Providers = typeof import('../src/providers');
let p: Providers;

beforeEach(async () => {
  vi.resetModules();
  p = await import('../src/providers');
});

const asDef = (v: unknown): ProviderDef => v as ProviderDef;

describe('default providers', () => {
  it('lists chatgpt, claude, googleai with badges and the url method', () => {
    const list = p.resolveProviders(undefined, 'en');
    expect(list.map((x) => x.id)).toEqual(['chatgpt', 'claude', 'googleai']);
    expect(list.map((x) => x.badge)).toEqual(['GP', 'CL', 'AI']);
    expect(list.every((x) => x.method === 'url')).toBe(true);
    expect(list.every((x) => /^https:\/\//.test(x.urlTemplate) && x.urlTemplate.includes('{query}'))).toBe(true);
  });

  it('localizes the Google name', () => {
    expect(p.resolveProviders(undefined, 'ja')[2].name).toBe('Google AIモード');
    expect(p.resolveProviders(undefined, 'ja-jp')[2].name).toBe('Google AIモード');
    expect(p.resolveProviders(undefined, 'en')[2].name).toBe('Google AI Mode');
    expect(p.resolveProviders(undefined, 'fr')[2].name).toBe('Google AI Mode');
  });

  it('treats null like the default list', () => {
    expect(p.resolveProviders(null, 'en')).toHaveLength(3);
  });
});

describe('resolveProviders', () => {
  it('honours the given order', () => {
    expect(p.resolveProviders(['claude', 'chatgpt'], 'en').map((x) => x.id)).toEqual(['claude', 'chatgpt']);
  });

  it('skips unknown ids and removes duplicates', () => {
    const ids = p.resolveProviders(['nope', 'claude', 'claude', 'chatgpt', 'claude'], 'en').map((x) => x.id);
    expect(ids).toEqual(['claude', 'chatgpt']);
  });

  it('returns an empty list for an empty array', () => {
    expect(p.resolveProviders([], 'en')).toEqual([]);
  });

  it('lets an object entry override fields of a built-in', () => {
    const [claude] = p.resolveProviders([asDef({ id: 'claude', method: 'copy_and_open' })], 'en');
    expect(claude.method).toBe('copy_and_open');
    expect(claude.urlTemplate).toBe('https://claude.ai/new?q={query}');
    expect(claude.name).toBe('Claude');
    expect(claude.badge).toBe('CL');
  });

  it('lets an object entry replace the urlTemplate', () => {
    const [c] = p.resolveProviders([asDef({ id: 'claude', urlTemplate: 'https://example.com/ask?x={query}' })], 'en');
    expect(c.urlTemplate).toBe('https://example.com/ask?x={query}');
  });

  it('does not change the registered provider when overriding', () => {
    p.resolveProviders([asDef({ id: 'claude', method: 'copy_and_open' })], 'en');
    expect(p.resolveProviders(['claude'], 'en')[0].method).toBe('url');
  });

  it('accepts a brand new definition with a derived badge', () => {
    const [x] = p.resolveProviders([{ id: 'foo', name: 'Foo', urlTemplate: 'https://foo.example/?q={query}' }], 'en');
    expect(x).toEqual({
      id: 'foo',
      name: 'Foo',
      urlTemplate: 'https://foo.example/?q={query}',
      method: 'url',
      badge: 'FO',
    });
  });

  it('skips entries whose urlTemplate is not https or lacks {query}', () => {
    const list = p.resolveProviders(
      [
        asDef({ id: 'claude', urlTemplate: 'http://claude.ai/new?q={query}' }),
        asDef({ id: 'chatgpt', urlTemplate: 'https://chatgpt.com/' }),
        { id: 'bad', name: 'Bad', urlTemplate: 'javascript:alert({query})' },
        'googleai',
      ],
      'en',
    );
    expect(list.map((x) => x.id)).toEqual(['googleai']);
  });

  it('skips entries that are null or have no usable id', () => {
    const list = p.resolveProviders([asDef(null), asDef({ name: 'x' }), 'chatgpt'], 'en');
    expect(list.map((x) => x.id)).toEqual(['chatgpt']);
  });

  it('accepts ids that are also names of object members', () => {
    const def = (id: string): ProviderDef => ({ id, name: id.toUpperCase(), urlTemplate: 'https://x.example/?q={query}' });
    const list = p.resolveProviders([def('constructor'), def('__proto__'), def('toString'), def('constructor')], 'en');
    expect(list.map((x) => x.id)).toEqual(['constructor', '__proto__', 'toString']);
    p.registerProvider(def('hasOwnProperty'));
    expect(p.resolveProviders(['hasOwnProperty', 'valueOf'], 'en').map((x) => x.id)).toEqual(['hasOwnProperty']);
  });

  it('falls back to the id when the name map has no usable entry', () => {
    const urlTemplate = 'https://x.example/?q={query}';
    const [a] = p.resolveProviders([{ id: 'mine', name: { ja: 'わたしのAI' }, urlTemplate }], 'fr');
    expect(a.name).toBe('mine');
    expect(a.badge).toBe('MI');
    const [b] = p.resolveProviders([{ id: 'mine', name: { ja: 'わたしのAI', en: 'My AI' }, urlTemplate }], 'fr');
    expect(b.name).toBe('My AI');
    const [c] = p.resolveProviders([asDef({ id: 'mine', name: 5, badge: 7, urlTemplate })], 'en');
    expect(c.name).toBe('mine');
    expect(c.badge).toBe('MI');
  });

  it('uses the default list when the option is not an array', () => {
    expect(p.resolveProviders('claude' as never, 'en').map((x) => x.id)).toEqual(['chatgpt', 'claude', 'googleai']);
    expect(p.resolveProviders({ claude: true } as never, 'en')).toHaveLength(3);
  });

  it('normalises an unknown method to url', () => {
    const [c] = p.resolveProviders([asDef({ id: 'claude', method: 'weird' })], 'en');
    expect(c.method).toBe('url');
  });
});

describe('registerProvider', () => {
  it('rejects invalid definitions with a TypeError', () => {
    const bad = [
      { id: 'a b', name: 'A', urlTemplate: 'https://a.example/?q={query}' },
      { id: '', name: 'A', urlTemplate: 'https://a.example/?q={query}' },
      { id: 'a', name: '', urlTemplate: 'https://a.example/?q={query}' },
      { id: 'a', name: 'A', urlTemplate: 'http://a.example/?q={query}' },
      { id: 'a', name: 'A', urlTemplate: 'https://a.example/' },
      null,
      undefined,
    ];
    bad.forEach((def) => expect(() => p.registerProvider(asDef(def))).toThrow(TypeError));
  });

  it('appends a valid provider to the default list', () => {
    p.registerProvider({ id: 'perplexity', name: 'Perplexity', urlTemplate: 'https://www.perplexity.ai/?q={query}' });
    const ids = p.resolveProviders(undefined, 'en').map((x) => x.id);
    expect(ids).toEqual(['chatgpt', 'claude', 'googleai', 'perplexity']);
  });

  it('replaces a built-in with the same id', () => {
    p.registerProvider({ id: 'claude', name: 'Claude', urlTemplate: 'https://example.com/?q={query}' });
    const list = p.resolveProviders(undefined, 'en');
    expect(list).toHaveLength(3);
    expect(list[1].urlTemplate).toBe('https://example.com/?q={query}');
  });

  it('does not leak into a fresh module instance', async () => {
    p.registerProvider({ id: 'tmp', name: 'Tmp', urlTemplate: 'https://tmp.example/?q={query}' });
    vi.resetModules();
    const fresh = await import('../src/providers');
    expect(fresh.resolveProviders(undefined, 'en').map((x) => x.id)).not.toContain('tmp');
  });
});

describe('isValidProvider', () => {
  it('accepts valid and rejects invalid shapes', () => {
    expect(p.isValidProvider({ id: 'a-b_1', name: 'A', urlTemplate: 'HTTPS://a.example/?q={query}' })).toBe(true);
    expect(p.isValidProvider(null)).toBe(false);
    expect(p.isValidProvider({ id: 'a', name: 'A', urlTemplate: 'https://a.example/' })).toBe(false);
  });
});
