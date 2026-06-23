export type LoginView = 'login' | 'tfa' | 'recovery' | 'forgot' | 'forgotSent';

export interface LoginConfig {
  upgradeInProgress?: boolean;
  priorRoute: string;
  loginError?: string;
  logoUrl: string;
  passwordReminderEnabled: boolean;
  authCASEnabled: boolean;
  version: string;
  appName: string;
  supportUrl: string;
  sourceUrl: string;
  removeLicenceFromLogin: boolean;
  i18n: Record<string, string>;
}

export interface LoginResponse {
  status: 'ok' | '2fa_required' | 'error' | 'rate_limited';
  message?: string;
  priorRoute?: string;
  isPasswordChangeRequired?: boolean;
}

export interface ForgotResponse {
  status: 'ok';
  message: string;
}

declare global {
  interface Window {
    __LOGIN_CONFIG__: LoginConfig;
  }
}
