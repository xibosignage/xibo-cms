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

import React from 'react';
import { createRoot } from 'react-dom/client';

import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/maven-pro/700.css';
import './styles.css';
import { LoginApp } from './LoginApp';
import { NotFoundView } from './components/NotFoundView';
import { SuspendedView } from './components/SuspendedView';
import { UpgradePendingView } from './components/UpgradePendingView';
import type { LoginConfig } from './types';
import { publicPath } from './utils';

function resolveComponent(config: LoginConfig) {
  if (config.upgradeInProgress) return UpgradePendingView;
  if (config.instanceSuspended) return SuspendedView;
  if (config.notFound) return NotFoundView;
  return LoginApp;
}

// Guard: if window.__LOGIN_CONFIG__ is absent the page was not served by PHP
// (e.g. Vite served login.html directly). Redirect to the real login page so
// the PHP shell can stamp the CSRF token and config blob.
if (!window.__LOGIN_CONFIG__) {
  window.location.assign(`${publicPath}login`);
} else {
  const root = document.getElementById('login-root');
  if (root) {
    const Component = resolveComponent(window.__LOGIN_CONFIG__);
    createRoot(root).render(
      <React.StrictMode>
        <Component />
      </React.StrictMode>,
    );
  }
}
