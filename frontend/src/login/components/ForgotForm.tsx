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

import { submitForgotPassword } from '../api';
import { t } from '../i18n';

import { ErrorBanner } from './ErrorBanner';
import { SubmitButton } from './SubmitButton';

interface Props {
  initialIdentifier?: string;
  onSent: () => void;
  onBack: () => void;
}

export function ForgotForm({ initialIdentifier = '', onSent, onBack }: Props) {
  const [username, setUsername] = useState(initialIdentifier.trim());
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      // PHP always returns 200 with the same message regardless of whether the user
      // exists, is rate-limited, or the email was sent — preserving constant-time behaviour.
      await submitForgotPassword(username);
      onSent();
    } catch {
      setError(t('unexpectedError'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate>
      <p className="login-prompt">{t('forgotPrompt')}</p>

      <div className="login-field">
        <label className="login-label" htmlFor="forgot-username">
          {t('identifier')}
        </label>
        <input
          id="forgot-username"
          type="text"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          placeholder={t('identifierPlaceholder')}
          autoComplete="username"
          autoFocus
          required
          className="login-input"
        />
      </div>

      {error && <ErrorBanner message={error} />}

      <SubmitButton label={t('forgotSendButton')} loading={loading} />

      <p className="login-alt">
        <button type="button" onClick={onBack} className="login-link login-link-muted">
          {t('loginInstead')}
        </button>
      </p>
    </form>
  );
}
