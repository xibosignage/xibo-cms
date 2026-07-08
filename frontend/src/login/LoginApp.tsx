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

import { ForgotForm } from './components/ForgotForm';
import { ForgotSentView } from './components/ForgotSentView';
import { LoginAboutModal } from './components/LoginAboutModal';
import { LoginCard } from './components/LoginCard';
import { LoginForm } from './components/LoginForm';
import { TwoFactorForm } from './components/TwoFactorForm';
import { t } from './i18n';
import type { LoginView } from './types';
import { getSafeRedirectUrl, publicPath } from './utils';

const config = window.__LOGIN_CONFIG__;

// Module-level so publicPath is evaluated once after DOM is ready.
const FORCE_CHANGE_PASSWORD_PATH = `${publicPath}user/force-change-password`;

// Module-level so the React Compiler doesn't flag it as a mutation inside the component.
function navigateTo(url: string) {
  window.location.assign(url);
}

export function LoginApp() {
  const [view, setView] = useState<LoginView>('login');
  const [tfaMode, setTfaMode] = useState<'code' | 'recovery'>('code');
  const [priorRoute, setPriorRoute] = useState(config.priorRoute);
  const [showAbout, setShowAbout] = useState(false);

  function handleLoginSuccess(route: string, passwordChangeRequired?: boolean) {
    const destination = route || priorRoute;
    if (passwordChangeRequired) {
      const target = destination
        ? `${FORCE_CHANGE_PASSWORD_PATH}?priorRoute=${encodeURIComponent(destination)}`
        : FORCE_CHANGE_PASSWORD_PATH;
      navigateTo(target);
    } else {
      navigateTo(getSafeRedirectUrl(destination));
    }
  }

  function handleTfaSuccess(route: string, passwordChangeRequired?: boolean) {
    const destination = route || priorRoute;
    if (passwordChangeRequired) {
      const target = destination
        ? `${FORCE_CHANGE_PASSWORD_PATH}?priorRoute=${encodeURIComponent(destination)}`
        : FORCE_CHANGE_PASSWORD_PATH;
      navigateTo(target);
    } else {
      navigateTo(getSafeRedirectUrl(destination));
    }
  }

  function handleTfaRequired(route: string) {
    if (route) setPriorRoute(route);
    setView('tfa');
  }

  function renderView() {
    switch (view) {
      case 'login':
        return (
          <LoginForm
            key="login"
            onSuccess={handleLoginSuccess}
            onTfa={handleTfaRequired}
            onForgot={() => setView('forgot')}
            passwordReminderEnabled={config.passwordReminderEnabled}
            authCASEnabled={config.authCASEnabled}
            initialError={config.loginError}
          />
        );

      case 'tfa':
      case 'recovery':
        return (
          <TwoFactorForm
            key={view}
            mode={tfaMode}
            onSuccess={handleTfaSuccess}
            onSwitchMode={(mode) => {
              setTfaMode(mode);
              setView(mode === 'code' ? 'tfa' : 'recovery');
            }}
            onBack={() => setView('login')}
          />
        );

      case 'forgot':
        return (
          <ForgotForm
            key="forgot"
            onSent={() => setView('forgotSent')}
            onBack={() => setView('login')}
          />
        );

      case 'forgotSent':
        return <ForgotSentView key="forgotSent" onBack={() => setView('login')} />;
    }
  }

  return (
    <div className="login-root">
      <LoginCard logoUrl={config.logoDarkUrl} supportUrl={config.supportUrl}>
        <div className="login-view-enter">{renderView()}</div>
      </LoginCard>

      {showAbout && <LoginAboutModal onClose={() => setShowAbout(false)} />}

      <p className="login-footer">
        <span className="login-footer-badge">{config.version}</span>
        {!config.removeLicenceFromLogin && config.sourceUrl && (
          <>
            <span className="login-footer-sep">|</span>
            <a href={config.sourceUrl}>{t('sourceLabel')}</a>
          </>
        )}
        {!config.removeLicenceFromLogin && (
          <>
            <span className="login-footer-sep">|</span>
            <button type="button" onClick={() => setShowAbout(true)}>
              {t('aboutLabel')}
            </button>
          </>
        )}
      </p>
    </div>
  );
}
