import { describe, expect, it } from 'vitest';
import en from '../src/locales/en';
import ja from '../src/locales/ja';
import {
  buildProviderUrl,
  buildQuery,
  cleanUrl,
  countCodePoints,
  DEFAULT_MAX_URL_LENGTH,
  fitEdit,
  isHttpUrl,
  isTooLong,
  siteRoot,
  truncateCodePoints,
  type QueryVars,
} from '../src/query';

const vars = (over: Partial<QueryVars> = {}): QueryVars => ({
  question: 'Q',
  url: 'https://example.com/a/',
  site_url: 'https://example.com/',
  md_url: 'https://example.com/a.md',
  title: 'Title',
  site_name: 'Site',
  ...over,
});

describe('buildQuery', () => {
  it('expands the Japanese default template exactly', () => {
    expect(buildQuery(ja.queryTemplate, vars({ question: '料金は？' }))).toBe(
      '料金は？\n\nこの質問について、以下のURLを参照して回答してください。\nhttps://example.com/',
    );
  });

  it('expands the English default template exactly', () => {
    expect(buildQuery(en.queryTemplate, vars({ question: 'Price?' }))).toBe(
      'Price?\n\nPlease refer to the following URL to answer this question.\nhttps://example.com/',
    );
  });

  it('trims the question', () => {
    expect(buildQuery('{question}', vars({ question: '  \n hello \t' }))).toBe('hello');
  });

  it('expands every variable', () => {
    expect(buildQuery('{question}|{url}|{site_url}|{md_url}|{title}|{site_name}', vars())).toBe(
      'Q|https://example.com/a/|https://example.com/|https://example.com/a.md|Title|Site',
    );
  });

  it('leaves unknown placeholders alone', () => {
    expect(buildQuery('{foo} {question} {}', vars())).toBe('{foo} Q {}');
  });

  it('does not expand placeholders contained in the question', () => {
    expect(buildQuery('{question}\n{site_url}', vars({ question: 'what is {url} and {site_url}?' }))).toBe(
      'what is {url} and {site_url}?\nhttps://example.com/',
    );
  });

  it('does not expand placeholders contained in other values', () => {
    expect(buildQuery('{title} {url}', vars({ title: '{url}' }))).toBe('{url} https://example.com/a/');
  });
});

describe('buildProviderUrl', () => {
  const tpl = 'https://chatgpt.com/?q={query}';
  const roundTrip = (query: string): string => new URL(buildProviderUrl(tpl, query)).searchParams.get('q') as string;

  const samples: { [name: string]: string } = {
    japanese: 'こんにちは、料金を教えてください。',
    newlines: 'line1\nline2\r\n\nline4',
    symbols: 'a&b#c?d%e+f=g h  i',
    percent: '100% %41 %',
    emoji: 'ok 👍 🎉',
    zwj: '👨‍👩‍👧‍👦 and 🏳️‍🌈',
    flags: '🇯🇵🇺🇸',
    mixed: '質問 & answer?\n#1 100%+ x=y 😀',
  };

  Object.keys(samples).forEach((name) => {
    it(`round-trips ${name}`, () => {
      expect(roundTrip(samples[name])).toBe(samples[name]);
    });
  });

  it('encodes newlines as %0A', () => {
    const url = buildProviderUrl(tpl, 'a\nb');
    expect(url).toBe('https://chatgpt.com/?q=a%0Ab');
  });

  it('keeps other params in the template', () => {
    const url = new URL(buildProviderUrl('https://www.google.com/search?udm=50&q={query}', 'a&b 質問'));
    expect(url.searchParams.get('udm')).toBe('50');
    expect(url.searchParams.get('q')).toBe('a&b 質問');
  });

  it('does not treat $ sequences in the query as replacement patterns', () => {
    expect(roundTrip("$& $1 $' $$")).toBe("$& $1 $' $$");
  });

  it('replaces a lone high surrogate with U+FFFD', () => {
    expect(() => buildProviderUrl(tpl, 'a\uD800b')).not.toThrow();
    expect(roundTrip('a\uD800b')).toBe('a\uFFFDb');
  });

  it('replaces a lone low surrogate with U+FFFD', () => {
    expect(roundTrip('a\uDC00b')).toBe('a\uFFFDb');
    expect(roundTrip('\uDC00')).toBe('\uFFFD');
  });

  it('handles consecutive and trailing lone surrogates', () => {
    expect(roundTrip('\uDC00\uDC00')).toBe('\uFFFD\uFFFD');
    expect(roundTrip('a\uD800\uD800')).toBe('a\uFFFD\uFFFD');
    expect(roundTrip('x\uD83D')).toBe('x\uFFFD');
    expect(roundTrip('😀\uDE00')).toBe('😀\uFFFD');
  });

  it('leaves valid surrogate pairs untouched', () => {
    expect(buildProviderUrl(tpl, '😀')).toBe('https://chatgpt.com/?q=%F0%9F%98%80');
  });
});

describe('countCodePoints / truncateCodePoints', () => {
  it('counts an emoji as one code point', () => {
    expect(countCodePoints('😀')).toBe(1);
    expect(countCodePoints('a😀b')).toBe(3);
    expect(countCodePoints('🇯🇵')).toBe(2);
  });

  it('counts a ZWJ family as its real number of code points', () => {
    expect(countCodePoints('👨‍👩‍👧‍👦')).toBe(7);
  });

  it('never splits a surrogate pair', () => {
    expect(truncateCodePoints('😀😀😀', 2)).toBe('😀😀');
    expect(truncateCodePoints('a😀b', 2)).toBe('a😀');
  });

  it('keeps exactly 500 and cuts 501 down to 500', () => {
    expect(truncateCodePoints('あ'.repeat(500), 500)).toBe('あ'.repeat(500));
    expect(countCodePoints(truncateCodePoints('あ'.repeat(501), 500))).toBe(500);
    expect(countCodePoints(truncateCodePoints('😀'.repeat(501), 500))).toBe(500);
  });

  it('returns shorter text as is', () => {
    expect(truncateCodePoints('abc', 500)).toBe('abc');
  });
});

describe('isTooLong', () => {
  it('uses the default limit', () => {
    expect(DEFAULT_MAX_URL_LENGTH).toBe(8000);
    expect(isTooLong('a'.repeat(8000))).toBe(false);
    expect(isTooLong('a'.repeat(8001))).toBe(true);
  });

  it('accepts a custom limit', () => {
    expect(isTooLong('abcdef', 5)).toBe(true);
    expect(isTooLong('abcde', 5)).toBe(false);
  });

  it('does not flag a 500-character Japanese question with the default limit', () => {
    const question = 'あ'.repeat(500);
    const query = buildQuery(ja.queryTemplate, vars({ question }));
    const url = buildProviderUrl('https://www.google.com/search?udm=50&q={query}', query);
    // Each Japanese character becomes 9 characters once percent-encoded.
    expect(url.length).toBeGreaterThan(4500);
    expect(isTooLong(url)).toBe(false);
  });
});

describe('cleanUrl', () => {
  it('removes the hash and every utm_* parameter', () => {
    expect(cleanUrl('https://example.com/p?utm_source=a&x=1&UTM_Medium=b&utm_campaign=c#frag')).toBe(
      'https://example.com/p?x=1',
    );
  });

  it('keeps other parameters', () => {
    expect(cleanUrl('https://example.com/p?a=1&b=2')).toBe('https://example.com/p?a=1&b=2');
  });

  it('drops the question mark when nothing is left', () => {
    expect(cleanUrl('https://example.com/p?utm_source=a')).toBe('https://example.com/p');
  });

  it('returns non-URL input unchanged', () => {
    expect(cleanUrl('not a url')).toBe('not a url');
    expect(cleanUrl('')).toBe('');
  });

  it('leaves the other parameters exactly as they were written', () => {
    expect(cleanUrl('https://example.com/p?q=a%20b&flag&utm_source=n')).toBe('https://example.com/p?q=a%20b&flag');
    expect(cleanUrl('https://example.com/p?a=1+2&&b=%E3%81%82&utm_x=1&c=#top')).toBe(
      'https://example.com/p?a=1+2&&b=%E3%81%82&c=',
    );
    expect(cleanUrl('https://example.com/p?next=%2Fa%3Futm_source%3Dx')).toBe(
      'https://example.com/p?next=%2Fa%3Futm_source%3Dx',
    );
  });
});

describe('isHttpUrl', () => {
  it('accepts http and https only', () => {
    expect(isHttpUrl('https://example.com/a')).toBe(true);
    expect(isHttpUrl('http://localhost:5173/')).toBe(true);
    expect(isHttpUrl('file:///C:/x/index.html')).toBe(false);
    expect(isHttpUrl('about:blank')).toBe(false);
    expect(isHttpUrl('/relative')).toBe(false);
    expect(isHttpUrl('')).toBe(false);
  });
});

describe('siteRoot', () => {
  it('returns the origin with a trailing slash', () => {
    expect(siteRoot('https://hogehoge.com/service/cutting/?utm_source=x#price')).toBe('https://hogehoge.com/');
  });

  it('keeps www, other subdomains and non-default ports', () => {
    expect(siteRoot('https://www.example.com/a')).toBe('https://www.example.com/');
    expect(siteRoot('https://blog.example.com/a')).toBe('https://blog.example.com/');
    expect(siteRoot('http://localhost:5173/a/b')).toBe('http://localhost:5173/');
    expect(siteRoot('https://example.com:8443/a')).toBe('https://example.com:8443/');
  });

  it('is empty for non-http schemes, so that no local path is handed over', () => {
    expect(siteRoot('file:///C:/x/index.html')).toBe('');
    expect(siteRoot('file:///C:/x/index.html?utm_source=a#top')).toBe('');
    expect(siteRoot('about:blank')).toBe('');
  });

  it('is empty for unparsable input', () => {
    expect(siteRoot('nonsense')).toBe('');
  });
});

describe('fitEdit', () => {
  it('returns an edit that fits unchanged', () => {
    expect(fitEdit('abc', 'abXc', 3, 5)).toEqual({ value: 'abXc', caret: 3 });
  });

  it('undoes typing in the middle of a full text and puts the caret back', () => {
    expect(fitEdit('abcde', 'abXcde', 3, 5)).toEqual({ value: 'abcde', caret: 2 });
    expect(fitEdit('abcde', 'Xabcde', 1, 5)).toEqual({ value: 'abcde', caret: 0 });
    expect(fitEdit('abcde', 'abcdeX', 6, 5)).toEqual({ value: 'abcde', caret: 5 });
  });

  it('keeps the caret where it was among repeated characters', () => {
    expect(fitEdit('aaaaa', 'aaaaaa', 2, 5)).toEqual({ value: 'aaaaa', caret: 1 });
  });

  it('accepts as much of a paste as fits', () => {
    expect(fitEdit('', 'abcdefgh', 8, 5)).toEqual({ value: 'abcde', caret: 5 });
    expect(fitEdit('abde', 'abXYZde', 5, 5)).toEqual({ value: 'abXde', caret: 3 });
  });

  it('counts the replaced selection as free room', () => {
    // "bcd" was selected and replaced by "WXYZ".
    expect(fitEdit('abcde', 'aWXYZe', 5, 5)).toEqual({ value: 'aWXYe', caret: 4 });
  });

  it('never splits a surrogate pair', () => {
    expect(fitEdit('😀😀', '😀😁😀', 4, 2)).toEqual({ value: '😀😀', caret: 2 });
    expect(fitEdit('', '😀😀😀', 6, 2)).toEqual({ value: '😀😀', caret: 4 });
    // The first emoji was replaced by another one that shares its first half.
    expect(fitEdit('😀a', '😁ba', 3, 2)).toEqual({ value: '😁a', caret: 2 });
  });

  it('still works when the caret position is of no use', () => {
    expect(fitEdit('abcde', 'abXcde', 0, 5)).toEqual({ value: 'abcde', caret: 2 });
    expect(fitEdit('abc', '0123456789', 99, 5)).toEqual({ value: '01234', caret: 5 });
  });
});
