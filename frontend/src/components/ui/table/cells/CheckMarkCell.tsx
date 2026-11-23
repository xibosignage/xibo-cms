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

import type { LucideIcon } from 'lucide-react';
import { Check, X } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

const BASE_STYLE = 'inline-flex justify-center items-center size-6 rounded-lg';

export function CheckMarkCell({ active = true, title }: { active?: boolean; title?: string }) {
  const Icon = (active ? Check : X) as LucideIcon;

  return (
    <div className="flex items-center justify-center">
      <span
        title={title}
        className={twMerge(
          BASE_STYLE,
          active ? 'text-teal-800 bg-teal-100' : 'text-gray-500 bg-gray-50',
        )}
      >
        <Icon className="size-4"></Icon>
      </span>
    </div>
  );
}
