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

import { createElement } from 'react';
import type { ReactNode } from 'react';

const t = (key: string, opts?: Record<string, unknown>) => {
  if (!opts) {
    return key;
  }
  return key.replace(/\{\{(\w+)\}\}/g, (_, k) => String(opts[k] ?? ''));
};

const i18n = { changeLanguage: () => Promise.resolve() };

export const useTranslation = () => ({ t, i18n });

export const Trans = ({
  i18nKey = '',
  values = {},
  children,
}: {
  i18nKey?: string;
  values?: Record<string, unknown>;
  components?: Record<string, unknown>;
  children?: ReactNode;
}) => {
  if (children !== undefined) {
    return children;
  }
  let s = String(i18nKey ?? '');
  for (const [k, v] of Object.entries(values)) {
    s = s.replace(new RegExp(`{{${k}}}`, 'g'), String(v ?? ''));
  }
  return s.split(/(<\w+>.*?<\/\w+>)/g).map((p, i) => {
    const m = p.match(/^<(\w+)>(.*?)<\/\1>$/);
    if (!m) {
      return p || null;
    }
    return createElement(m[1], { key: i }, m[2]);
  });
};
