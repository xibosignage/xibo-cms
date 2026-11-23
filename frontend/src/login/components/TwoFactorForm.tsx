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

import { useRef, useState } from 'react';

import { submitRecoveryCode, submitTwoFactor } from '../api';
import { t } from '../i18n';
import type { LoginResponse } from '../types';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

interface Props {
  mode: 'code' | 'recovery';
  onSuccess: (priorRoute: string, passwordChangeRequired?: boolean) => void;
  onSwitchMode: (mode: 'code' | 'recovery') => void;
  onBack: () => void;
}

export function TwoFactorForm({ mode, onSuccess, onSwitchMode, onBack }: Props) {
  const [value, setValue] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [rateLimited, setRateLimited] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    if (rateLimited) return;

    setError('');
    setLoading(true);

    try {
      const res: LoginResponse =
        mode === 'code' ? await submitTwoFactor(value) : await submitRecoveryCode(value);

      if (res.status === 'ok') {
        onSuccess(res.priorRoute ?? '', res.isPasswordChangeRequired);
      } else if (res.status === 'rate_limited') {
        setRateLimited(true);
        setError(res.message ?? t('rateLimitError'));
      } else {
        setError(res.message ?? t('tfaInvalidCode'));
        setValue('');
        inputRef.current?.focus();
      }
    } catch {
      setError(t('unexpectedError'));
    } finally {
      setLoading(false);
    }
  }

  const isCode = mode === 'code';

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="login-prompt">{isCode ? t('tfaPrompt') : t('tfaRecoveryPrompt')}</p>

      <div className="login-field">
        <label className="login-label" htmlFor="tfa-code">
          {isCode ? t('tfaCode') : t('tfaRecoveryCode')}
        </label>
        <input
          id="tfa-code"
          ref={inputRef}
          type="text"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={isCode ? t('tfaCodePlaceholder') : t('tfaRecoveryCodePlaceholder')}
          autoFocus
          autoComplete="one-time-code"
          required
          className="login-input"
        />
      </div>

      {error && <ErrorBanner message={error} />}

      <SubmitButton label={t('tfaVerifyButton')} loading={loading} disabled={rateLimited} />

      <p className="login-alt">
        <button
          type="button"
          onClick={() => {
            setValue('');
            setError('');
            onSwitchMode(isCode ? 'recovery' : 'code');
          }}
          className="login-link"
        >
          {isCode ? t('tfaSwitchToRecovery') : t('tfaSwitchToCode')}
        </button>
      </p>

      <p className="login-alt">
        <button type="button" onClick={onBack} className="login-link login-link-muted">
          {t('backToLogin')}
        </button>
      </p>
    </form>
  );
}
