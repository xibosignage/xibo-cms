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

import { SearchX } from 'lucide-react';

import { t } from '../i18n';
import { publicPath } from '../utils';

export function NotFoundView() {
  return (
    <div className="login-root">
      <div className="not-found-content">
        <span className="not-found-icon">
          <SearchX size={24} aria-hidden="true" />
        </span>
        <h1 className="not-found-title">{t('notFoundTitle')}</h1>
        <p className="not-found-message">{t('notFoundMessage')}</p>
        <a className="login-button not-found-button" href={publicPath}>
          {t('notFoundAction')}
        </a>
      </div>
    </div>
  );
}
