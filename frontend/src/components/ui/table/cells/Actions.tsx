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

import type { Row } from '@tanstack/react-table';

import RowActions from '../RowActions';
import type { RowAction } from '../RowActions';

interface ActionsProps<TData> {
  row: Row<TData>;
  actions: RowAction<TData>[];
}

export function Actions<TData>({ row, actions }: ActionsProps<TData>) {
  // Quick actions
  const quickActions = actions.filter((a) => a.isQuickAction && !a.isSeparator);

  // Menu actions and separators
  const menuActions = actions.filter((a) => !a.isQuickAction);

  return (
    <div className="flex justify-end items-center gap-1">
      {/* Quick Actions */}
      {quickActions.map((action, index) => (
        <button
          key={index}
          onClick={(e) => {
            e.stopPropagation();
            if (action.onClick) action.onClick(row.original);
          }}
          className={`p-1.5 ${
            action.variant === 'danger'
              ? 'text-gray-800 hover:bg-red-50'
              : 'text-gray-800  hover:bg-blue-50 dark:text-neutral-400'
          }`}
          title={action.label}
        >
          {action.icon}
        </button>
      ))}

      {/* Menu Actions */}
      {menuActions.length > 0 && (
        <RowActions row={row.original} actions={menuActions as RowAction<TData>[]} />
      )}
    </div>
  );
}
