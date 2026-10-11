// Settings of the website that are not page content.

export const SITE_NAME = 'askai-button';
export const REPO_URL = 'https://github.com/elephancube/askai-button';

// Where the site is served. Both can be replaced through the environment, e.g. for a custom domain:
//   SITE_URL=https://example.org/ SITE_BASE=/ npm run build:site
export const DEFAULT_SITE_URL = 'https://elephancube.github.io/askai-button/';

// The first language is served at the top of the site, the others under /<code>/.
export const LANGS = ['ja', 'en'];

// Language that search engines offer to visitors whose language is neither of the above.
export const FALLBACK_LANG = 'en';

// Page slugs in navigation order. '' is the top page.
export const PAGES = ['', 'install', 'reference', 'wordpress', 'how-it-works'];

export const STRINGS = {
  ja: {
    langName: '日本語',
    ogLocale: 'ja_JP',
    tagline: 'Webサイトに置ける「AIに質問」ボタン',
    skip: '本文へ移動',
    mainNav: 'サイト内のページ',
    langNav: '言語',
    toc: 'このページの内容',
    table: '{title}の表',
    footerNav: 'フッター',
    markdown: 'このページのMarkdown版',
    github: 'GitHub',
    noTracking: 'このサイトは、アクセス解析・広告・外部フォントを使っていません。読み込むスクリプトは、右下のボタン（askai-button）だけです。',
    trademark: 'ChatGPT、Claude、Google は各社の商標です。本プロジェクトは各社と提携・公認の関係にありません。',
    licence: 'ライブラリは MIT License、WordPress プラグインは GPL-2.0-or-later です。',
    mockLabel: '図：ボタンを押すと開く質問パネル。質問の入力欄、質問の例、AIサービスの選択、送信ボタンが並んでいます。',
    mockCaption: '図：ボタンを押すと開くパネル（実物は画面の右下にあります）',
    mockQuestion: 'WordPressでも使えますか？',
    // Questions this site can answer. Shown in the panel of the button on every page.
    presets: ['導入方法を教えてください', 'WordPressでも使えますか？', 'AIには何が送られますか？', '費用はかかりますか？'],
  },
  en: {
    langName: 'English',
    ogLocale: 'en_US',
    tagline: 'An "Ask AI" button for any website',
    skip: 'Skip to content',
    mainNav: 'Pages of this site',
    langNav: 'Language',
    toc: 'On this page',
    table: 'Table: {title}',
    footerNav: 'Footer',
    markdown: 'Markdown version of this page',
    github: 'GitHub',
    noTracking: 'This site uses no analytics, no ads and no external fonts. The only script it loads is the button in the corner (askai-button).',
    trademark: 'ChatGPT, Claude and Google are trademarks of their respective owners. This project is not affiliated with or endorsed by them.',
    licence: 'The library is released under the MIT License, the WordPress plugin under GPL-2.0-or-later.',
    mockLabel: 'Illustration: the panel that opens when the button is pressed, with a question field, example questions, a choice of AI services and a send button.',
    mockCaption: 'Illustration: the panel behind the button (the real one is in the corner of this page)',
    mockQuestion: 'Does it work with WordPress?',
    presets: ['How do I install it?', 'Does it work with WordPress?', 'What is sent to the AI?', 'Does it cost anything?'],
  },
};

// The page shown for addresses that do not exist. One file for both languages.
export const NOT_FOUND = {
  ja: { title: 'ページが見つかりません', text: 'お探しのページは、移動または削除された可能性があります。', home: 'トップページへ' },
  en: { title: 'Page not found', text: 'The page you are looking for may have been moved or removed.', home: 'Go to the top page' },
};
