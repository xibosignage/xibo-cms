/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { useLoaderData, useSearchParams } from 'react-router-dom';

import { useBranding } from '@/context/BrandingContext';
import http from '@/lib/api';
import { ErrorBanner } from '@/login/components/ErrorBanner';
import { LoginCard } from '@/login/components/LoginCard';
import { SubmitButton } from '@/login/components/SubmitButton';
import { getSafeRedirectUrl } from '@/login/utils';
import type { User } from '@/types/user';

import '@/login/styles.css';

export default function ForceChangePassword() {
  const { t } = useTranslation();
  const { user } = useLoaderData() as { user: User };
  const { logoDarkUrl, supportUrl } = useBranding();
  const [searchParams] = useSearchParams();
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.SyntheticEvent) {
    e.preventDefault();
    setError('');

    if (newPassword !== confirmPassword) {
      setError(t('Passwords do not match.'));
      return;
    }

    setLoading(true);
    try {
      await http.put('/user/password/forceChange', {
        newPassword,
        retypeNewPassword: confirmPassword,
      });
      const priorRoute = searchParams.get('priorRoute') ?? undefined;
      window.location.assign(getSafeRedirectUrl(priorRoute));
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        t('An error occurred. Please try again.');
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-root">
      <LoginCard logoUrl={logoDarkUrl} supportUrl={supportUrl}>
        <div className="login-view-enter">
          <form onSubmit={handleSubmit} noValidate>
            <p className="login-prompt">
              {t('Your account requires a password change before you can continue.')}
              <br />
              <Trans
                i18nKey="Signed in as <strong>{{name}}</strong>"
                values={{ name: user.userName }}
                components={{ strong: <strong /> }}
              />
            </p>

            <div className="login-field">
              <label className="login-label" htmlFor="new-password">
                {t('New password')}
              </label>
              <input
                id="new-password"
                className="login-input"
                type="password"
                value={newPassword}
                onChange={(e) => {
                  setNewPassword(e.target.value);
                  setError('');
                }}
                placeholder={t('Enter new password')}
                autoFocus
                required
                autoComplete="new-password"
              />
            </div>

            <div className="login-field">
              <label className="login-label" htmlFor="confirm-password">
                {t('Confirm new password')}
              </label>
              <input
                id="confirm-password"
                className="login-input"
                type="password"
                value={confirmPassword}
                onChange={(e) => {
                  setConfirmPassword(e.target.value);
                  setError('');
                }}
                placeholder={t('Re-enter new password')}
                required
                autoComplete="new-password"
              />
            </div>

            {error && <ErrorBanner message={error} />}

            <SubmitButton label={t('Save password')} loading={loading} />
          </form>
        </div>
      </LoginCard>
    </div>
  );
}
