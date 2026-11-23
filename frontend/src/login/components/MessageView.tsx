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

import type { ReactNode } from 'react';

import { t } from '../i18n';

import { LoginCard } from './LoginCard';

const config = window.__LOGIN_CONFIG__;

interface Props {
  icon: ReactNode;
  titleKey: string;
  messageKey: string;
  spinning?: boolean;
}

export function MessageView({ icon, titleKey, messageKey, spinning = false }: Props) {
  return (
    <div className="login-root">
      <LoginCard logoUrl={config.logoDarkUrl} supportUrl={config.supportUrl ?? ''}>
        <div className="login-message">
          <span
            className={
              spinning ? 'login-message-icon login-message-icon--spin' : 'login-message-icon'
            }
          >
            {icon}
          </span>
          <h1 className="login-message-title">{t(titleKey)}</h1>
          <p className="login-message-message">{t(messageKey)}</p>
        </div>
      </LoginCard>
    </div>
  );
}
