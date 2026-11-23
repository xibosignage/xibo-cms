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

type Props = {
  title?: string;
  actions?: ReactNode;
  children: ReactNode;
};

export default function PageWrapper({ title, actions, children }: Props) {
  return (
    <section className="flex-1 flex flex-col min-h-0">
      {(title || actions) && (
        <div className="flex items-center justify-between shrink-0 mb-4">
          {title && <h1 className="text-xl font-semibold">{title}</h1>}
          {actions}
        </div>
      )}
      <div className="text-gray-900 flex-1 flex flex-col min-h-0">{children}</div>
    </section>
  );
}
