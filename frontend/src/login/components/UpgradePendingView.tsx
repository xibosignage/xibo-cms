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

import { t } from '../i18n';

import { LoginCard } from './LoginCard';

const config = window.__LOGIN_CONFIG__;

export function UpgradePendingView() {
  return (
    <div
      style={{
        minHeight: '100vh',
        paddingTop: 40,
        paddingBottom: 40,
        backgroundColor: '#f7f7f7',
        fontFamily: 'system-ui, -apple-system, sans-serif',
        fontSize: 14,
      }}
    >
      <LoginCard logoUrl={config.logoUrl} supportUrl={config.supportUrl ?? ''}>
        <p style={{ textAlign: 'center', margin: 0 }}>{t('upgradeMessage')}</p>
      </LoginCard>
    </div>
  );
}
