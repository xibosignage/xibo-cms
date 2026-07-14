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

import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { useAuthorizeRequest } from './hooks/useAuthorizeRequest';

import './authorize.css';

import { withPublicPath } from '@/config/publicPath';
import { useBranding } from '@/context/BrandingContext';
import { ErrorBanner } from '@/login/components/ErrorBanner';
import { LoginCard } from '@/login/components/LoginCard';
import { isSafeHttpUrl } from '@/utils/url';

import '@/login/styles.css';

// The XSRF-TOKEN cookie is set (non-HttpOnly, by design) by CsrfGuard so the SPA can read it.
// axios attaches it automatically for its own requests, but the Approve/Deny action below is a
// plain HTML form submit (not axios) so the browser can follow the resulting cross-origin
// redirect natively — that means the token has to be read and placed in a hidden field by hand.
function getCsrfTokenFromCookie(): string {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
  return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export default function AuthorizeApplication() {
  const { t } = useTranslation();
  const { logoDarkUrl, supportUrl } = useBranding();
  const { data, isLoading, isError } = useAuthorizeRequest();

  const alreadyAuthorized = data?.alreadyAuthorized === true;
  const redirectUrl = alreadyAuthorized ? data.redirectUrl : null;

  useEffect(() => {
    if (redirectUrl) {
      window.location.href = redirectUrl;
    }
  }, [redirectUrl]);

  if (isLoading || alreadyAuthorized) {
    return (
      <div className="login-root">
        <LoginCard logoUrl={logoDarkUrl} supportUrl={supportUrl}>
          <p className="login-prompt">{alreadyAuthorized ? t('Redirecting…') : t('Loading…')}</p>
        </LoginCard>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="login-root">
        <LoginCard logoUrl={logoDarkUrl} supportUrl={supportUrl}>
          <ErrorBanner
            message={t(
              'This authorization request could not be loaded. It may have expired — please try again from the application.',
            )}
          />
        </LoginCard>
      </div>
    );
  }

  const { application, scopes } = data;

  // Terms/privacy URLs are set by the application owner, not the user viewing this screen —
  // only render them as links when they're genuinely http/https, never javascript:/data:/etc.
  const termsUrl = isSafeHttpUrl(application.termsUrl) ? application.termsUrl : null;
  const privacyUrl = isSafeHttpUrl(application.privacyUrl) ? application.privacyUrl : null;

  return (
    <div className="login-root">
      <LoginCard logoUrl={logoDarkUrl} supportUrl={supportUrl}>
        <div className="authorize-view">
          {application.coverImage && (
            <div
              className="authorize-cover"
              style={{ backgroundImage: `url(${application.coverImage})` }}
            />
          )}

          {application.logo && (
            <div className="authorize-logo">
              <img src={application.logo} alt={application.name} />
            </div>
          )}

          <h1 className="authorize-title">
            {application.companyName ? `${application.companyName} - ` : ''}
            {application.name}
          </h1>

          <p className="login-prompt">{t('would like access to the following scopes')}:</p>

          <ul className="authorize-scopes">
            {scopes.map((scope) => (
              <li key={scope.id} dangerouslySetInnerHTML={{ __html: scope.description }} />
            ))}
          </ul>

          {application.description && (
            <p className="authorize-description">{application.description}</p>
          )}

          {(termsUrl || privacyUrl) && (
            <p className="authorize-links">
              {termsUrl && (
                <a href={termsUrl} target="_blank" rel="noreferrer">
                  {t('Terms')}
                </a>
              )}
              {privacyUrl && (
                <a href={privacyUrl} target="_blank" rel="noreferrer">
                  {t('Privacy Policy')}
                </a>
              )}
            </p>
          )}

          {/*
            Plain native form submit, deliberately not a React onSubmit/axios call: the backend's
            response to this POST is always a 3xx redirect to the third-party app's redirect_uri.
            A real browser navigation follows that transparently; fetch/axios cannot safely follow
            a cross-origin redirect and hand control back to the browser.
          */}
          <form
            method="post"
            action={withPublicPath('json/application/authorize')}
            className="authorize-actions"
          >
            <input type="hidden" name="csrfToken" value={getCsrfTokenFromCookie()} />
            <button
              type="submit"
              name="authorization"
              value="Deny"
              className="authorize-button-deny"
            >
              {t('Deny')}
            </button>
            <button type="submit" name="authorization" value="Approve" className="login-button">
              {t('Approve')}
            </button>
          </form>
        </div>
      </LoginCard>
    </div>
  );
}
