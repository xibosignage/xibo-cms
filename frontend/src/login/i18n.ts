const strings: Record<string, string> = window.__LOGIN_CONFIG__?.i18n ?? {};

export function t(key: string): string {
  return strings[key] ?? key;
}
