import type { Dict } from './en';

const ja: Dict = {
  buttonLabel: 'AIに質問',
  panelTitle: 'このサイトについてAIに聞く',
  panelSubtitle: '選んだAIサービスが別タブで開きます。回答はAIサービスが生成します。',
  inputLabel: '質問',
  placeholder: '質問を入力してください',
  presetsLabel: '質問の例',
  providersLabel: '使うAI',
  note: 'このサイトのURLだけを渡します。{provider} はURLをその場で開いて読みます。',
  'note.chatgpt': '質問は自動で送信されます。',
  'note.claude': '質問が入力された状態で開きます。送信はご自身で押してください（ログインが必要です）。',
  'note.googleai': 'Google 検索の AIモードで開きます。AIモードを使えない地域・アカウントでは、通常の検索結果になることがあります。',
  fallbackTitle: 'AIがこのサイトを読めない場合',
  fallbackSteps: '質問とURLをコピーし、AIの入力欄に貼り付けて送信してください。',
  copy: '質問とURLをコピー',
  copied: 'コピーしました',
  copyHint: '質問をコピーしました。{provider} の入力欄に貼り付けて送信してください。',
  send: '{provider} で質問する ↗',
  close: '閉じる',
  tooLong: '質問が長すぎます',
  queryTemplate: '{question}\n\nこの質問について、以下のURLを参照して回答してください。\n{site_url}',
};

export default ja;
