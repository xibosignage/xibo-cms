/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { useState } from 'react';

import { fetchLoginMethods, submitLogin } from '../api';
import { t } from '../i18n';
import type { LoginResponse, SignInProvider } from '../types';
import { publicPath } from '../utils';

import { ErrorBanner } from './ErrorBanner';
import { SignInProviders } from './SignInProviders';
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
  onForgot: (identifier: string) => void;
  passwordReminderEnabled: boolean;
  authCASEnabled: boolean;
  priorRoute?: string;
  signInProviders?: SignInProvider[];
  // Set when a sign-in provider offers a sign-out link.
  signInProviderLogoutUrl?: string | null;
  initialError?: string;
}

function isSignInProvider(method: SignInProvider | { type: 'password' }): method is SignInProvider {
  return method.type !== 'password';
}

export function LoginForm({
  onSuccess,
  onTfa,
  onForgot,
  passwordReminderEnabled,
  authCASEnabled,
  priorRoute = '',
  signInProviders,
  signInProviderLogoutUrl,
  initialError = '',
}: Props) {
  const [step, setStep] = useState<'identifier' | 'password'>('identifier');
  const [identifier, setIdentifier] = useState('');
  const [userProviders, setUserProviders] = useState<SignInProvider[]>([]);
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState(initialError);
  const [loading, setLoading] = useState(false);
  const [rateLimited, setRateLimited] = useState(false);

  async function handleNext(e: React.SyntheticEvent) {
    e.preventDefault();
    if (rateLimited) return;
    if (identifier.trim() === '') {
      setError(t('identifierRequired'));
      return;
    }

    setError('');
    setLoading(true);

    try {
      const res = await fetchLoginMethods(identifier, priorRoute);
      if (res.status === 'rate_limited') {
        setRateLimited(true);
        setError(res.message ?? t('rateLimitError'));
      } else {
        setUserProviders((res.methods ?? []).filter(isSignInProvider));
        setPassword('');
        setShowPassword(false);
        setStep('password');
      }
    } catch {
      setError(t('unexpectedError'));
    } finally {
      setLoading(false);
    }
  }

  function handleChangeIdentifier() {
    setError('');
    setRateLimited(false);
    setStep('identifier');
  }

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    if (rateLimited) return;

    setError('');
    setLoading(true);

    try {
      const res: LoginResponse = await submitLogin(identifier, password);
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

  if (step === 'identifier') {
    return (
      <form onSubmit={handleNext} noValidate>
        <div className="login-field">
          <label className="login-label" htmlFor="username">
            {t('identifier')}
          </label>
          <input
            id="username"
            type="text"
            name="username"
            value={identifier}
            onChange={(e) => {
              setIdentifier(e.target.value);
              setError('');
              setRateLimited(false);
            }}
            placeholder={t('identifierPlaceholder')}
            autoComplete="username"
            autoFocus
            required
            className="login-input"
          />
        </div>

        {error && <ErrorBanner message={error} />}

        <SubmitButton label={t('nextButton')} loading={loading} disabled={rateLimited} />
        <SignInProviders providers={signInProviders} logoutUrl={signInProviderLogoutUrl} />
      </form>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <input
        type="text"
        name="username"
        value={identifier}
        autoComplete="username"
        readOnly
        tabIndex={-1}
        aria-hidden="true"
        className="login-hidden-field"
      />

      <div className="login-field">
        <span className="login-label">{t('identifier')}</span>
        <div className="login-identifier">
          <span className="login-identifier-value">{identifier.trim()}</span>
          <button
            type="button"
            onClick={handleChangeIdentifier}
            aria-label={t('changeIdentifierLabel')}
            className="login-link"
          >
            {t('changeIdentifier')}
          </button>
        </div>
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
            autoFocus
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
          <button type="button" onClick={() => onForgot(identifier)} className="login-link">
            {t('forgotPasswordLink')}
          </button>
        </p>
      )}

      <SubmitButton label={t('loginButton')} loading={loading} disabled={rateLimited} />
      <SignInProviders providers={[...userProviders, ...(signInProviders ?? [])]} />
    </form>
  );
}
