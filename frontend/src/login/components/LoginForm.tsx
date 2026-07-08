import { useState } from 'react';

import { submitLogin } from '../api';
import { t } from '../i18n';
import type { LoginResponse } from '../types';
import { publicPath } from '../utils';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

function EyeIcon() {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

function EyeOffIcon() {
  return (
    <svg
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
      <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
      <line x1="2" x2="22" y1="2" y2="22" />
    </svg>
  );
}

interface Props {
  onSuccess: (priorRoute: string, passwordChangeRequired?: boolean) => void;
  onTfa: (priorRoute: string) => void;
  onForgot: () => void;
  passwordReminderEnabled: boolean;
  authCASEnabled: boolean;
  initialError?: string;
}

export function LoginForm({
  onSuccess,
  onTfa,
  onForgot,
  passwordReminderEnabled,
  authCASEnabled,
  initialError = '',
}: Props) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState(initialError);
  const [loading, setLoading] = useState(false);
  const [rateLimited, setRateLimited] = useState(false);

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    if (rateLimited) return;

    setError('');
    setLoading(true);

    try {
      const res: LoginResponse = await submitLogin(username, password);
      if (res.status === 'ok') {
        onSuccess(res.priorRoute ?? '', res.isPasswordChangeRequired);
      } else if (res.status === '2fa_required') {
        onTfa(res.priorRoute ?? '');
      } else if (res.status === 'rate_limited') {
        setRateLimited(true);
        setError(res.message ?? t('rateLimitError'));
      } else {
        setError(res.message ?? t('invalidCredentials'));
      }
    } catch {
      setError(t('unexpectedError'));
    } finally {
      setLoading(false);
    }
  }

  if (authCASEnabled) {
    return (
      <div className="login-cas">
        <p className="login-prompt">{t('casPrompt')}</p>
        <form action={`${publicPath}cas/login`} method="post">
          <SubmitButton label={t('casLoginButton')} />
        </form>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <div className="login-field">
        <label className="login-label" htmlFor="username">
          {t('username')}
        </label>
        <input
          id="username"
          type="text"
          name="username"
          value={username}
          onChange={(e) => {
            setUsername(e.target.value);
            setError('');
            setRateLimited(false);
          }}
          placeholder={t('usernamePlaceholder')}
          autoComplete="username"
          autoFocus
          required
          className="login-input"
        />
      </div>

      <div className="login-field">
        <label className="login-label" htmlFor="password">
          {t('password')}
        </label>
        <div className="login-input-group">
          <input
            id="password"
            type={showPassword ? 'text' : 'password'}
            name="password"
            value={password}
            onChange={(e) => {
              setPassword(e.target.value);
              setError('');
              setRateLimited(false);
            }}
            placeholder={t('passwordPlaceholder')}
            autoComplete="current-password"
            required
            className="login-input"
          />
          <button
            type="button"
            className="login-password-toggle"
            onClick={() => setShowPassword((v) => !v)}
            aria-label={showPassword ? t('hidePassword') : t('showPassword')}
          >
            {showPassword ? <EyeOffIcon /> : <EyeIcon />}
          </button>
        </div>
      </div>

      {error && <ErrorBanner message={error} />}

      {passwordReminderEnabled && (
        <p className="login-forgot">
          <button type="button" onClick={onForgot} className="login-link">
            {t('forgotPasswordLink')}
          </button>
        </p>
      )}

      <SubmitButton label={t('loginButton')} loading={loading} disabled={rateLimited} />
    </form>
  );
}
