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

import type { Row } from '@tanstack/react-table';
import { Loader2 } from 'lucide-react';
import { twMerge } from 'tailwind-merge';

import DataTableRowActions from '../DataTableRowActions';
import type { DataTableRowAction } from '../DataTableRowActions';

interface ActionsProps<TData> {
  row: Row<TData>;
  actions: DataTableRowAction<TData>[];
}

export function ActionsCell<TData>({ row, actions }: ActionsProps<TData>) {
  // Quick actions
  const quickActions = actions.filter((a) => a.isQuickAction && !a.isSeparator);

  // Menu actions and separators
  const menuActions = actions.filter((a) => !a.isQuickAction);

  return (
    <div className="flex justify-end items-center gap-1 no-print">
      {/* Quick Actions */}
      {quickActions.map((action, index) => (
        <button
          key={index}
          disabled={action.disabled}
          onClick={(e) => {
            e.stopPropagation();
            if (action.disabled) return;
            if (action.onClick) action.onClick(row.original);
          }}
          className={twMerge(
            'cursor-pointer flex justify-center p-1 items-center text-sm font-medium rounded-lg border border-transparent focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none',
            action.variant === 'danger'
              ? 'text-red-600 hover:bg-red-50 focus:bg-red-100'
              : action.variant === 'primary'
                ? 'text-xibo-blue-600 hover:bg-xibo-blue-50 focus:bg-xibo-blue-100'
                : 'text-gray-600 hover:bg-gray-50 focus:bg-gray-100',
          )}
          aria-label={action.label}
          title={action.label}
        >
          {action.loading ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            action.icon && <action.icon className="w-4 h-4" />
          )}
        </button>
      ))}

      {/* Menu Actions */}
      {menuActions.length > 0 && (
        <DataTableRowActions
          row={row.original}
          actions={menuActions as DataTableRowAction<TData>[]}
        />
      )}
    </div>
  );
}
