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

import { Edit, Monitor, Settings } from 'lucide-react';
import { type ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { useDisplayStatusBadge } from '../hooks/useDisplayStatusBadge';

import DisplayStatusBadge from './DisplayStatusBadge';

import DataTableRowActions from '@/components/ui/table/DataTableRowActions';
import type { Display } from '@/types/display';
import type { ActionItem } from '@/types/table';

interface DisplayCardProps {
  display: Display;
  /** Opens the newer Manage page (`/displays/displays/:displayId`). */
  onManagePage: (display: Display) => void;
  onEdit: (display: Display) => void;
  /** Same action list the Displays grid's row "⋮" menu is built from (getDisplayItemActions). */
  actions: ActionItem[];
  /** Set when rendered inside DataGrid's Grid view, which drives selection for bulk actions. */
  isSelected?: boolean;
  onToggleSelect?: (checked: boolean) => void;
}

// Card face follows display-selection.png: status pill overlaid on the
// screenshot (top-left), a "⋮" actions menu overlaid opposite it (top-right —
// functionally the same menu as the Displays grid's row kebab, built from the
// same getDisplayItemActions list), name + display group, a relative "Last
// seen" line, and a two-way Manage / Edit footer.
export default function DisplayCard({
  display,
  onManagePage,
  onEdit,
  actions,
  isSelected,
  onToggleSelect,
}: DisplayCardProps) {
  const { t } = useTranslation();

  const { bucket, colors, badgeLabel, lastSeenLabel, showThumbnail, onThumbnailError } =
    useDisplayStatusBadge(display);

  const subtitle = display.displayGroups?.[0]?.displayGroup || '';

  // Quick actions (e.g. the dropdown's own "Edit" entry) are already covered
  // by this card's dedicated Edit button, so only the non-quick menu items
  // are shown here — same split ActionsCell.tsx uses for table rows.
  const menuActions = actions.filter((action) => !action.isQuickAction) as ComponentProps<
    typeof DataTableRowActions
  >['actions'];

  return (
    <div
      className={twMerge(
        'flex w-full min-w-0 flex-col rounded-xl bg-slate-50 overflow-hidden pt-2.5 transition-colors',
        isSelected ? 'bg-xibo-blue-50 ring-2 ring-xibo-blue-500' : 'hover:bg-gray-100',
      )}
    >
      <div className="relative aspect-video w-full overflow-hidden rounded-t-lg bg-gradient-to-br from-gray-50 to-gray-100">
        <div className="absolute left-1.5 top-1.5 z-10 flex max-w-[85%] items-center gap-1.5">
          {onToggleSelect && (
            <input
              type="checkbox"
              checked={!!isSelected}
              onClick={(e) => e.stopPropagation()}
              onChange={(e) => onToggleSelect(e.target.checked)}
              aria-label={t('Select {{name}}', { name: display.display })}
              className={twMerge(
                'size-4 shrink-0 cursor-pointer rounded border-gray-300 text-xibo-blue-600 shadow-sm focus:ring-xibo-blue-500',
                // bg-white/90 is only for contrast against arbitrary thumbnails while
                // unchecked — applied unconditionally it paints over (and hides) the
                // forms plugin's checked-state checkmark.
                !isSelected && 'bg-white/90',
              )}
            />
          )}
          <DisplayStatusBadge
            bucket={bucket}
            colors={colors}
            label={badgeLabel}
            className="min-w-0 py-1 px-2 text-[11px] shadow-sm"
          />
        </div>

        {menuActions.length > 0 && (
          <div className="absolute right-1.5 top-1.5 z-10 rounded-lg bg-white/90 shadow-sm">
            <DataTableRowActions row={display} actions={menuActions} />
          </div>
        )}

        {showThumbnail ? (
          <img
            src={display.thumbnail}
            alt={t('Screenshot of {{name}}', { name: display.display })}
            className="h-full w-full object-cover"
            onError={onThumbnailError}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-300">
            <Monitor className="size-10" />
          </div>
        )}
      </div>

      <div className="flex flex-col gap-0.5 px-3 pt-2 pb-2">
        <h3 className="truncate text-sm font-semibold text-gray-800" title={display.display}>
          {display.display}
        </h3>
        {subtitle && (
          <p className="truncate text-xs text-gray-500" title={subtitle}>
            {subtitle}
          </p>
        )}
        <p className="text-xs text-gray-400">{t('Last seen {{time}}', { time: lastSeenLabel })}</p>
      </div>

      <div className="mt-auto grid grid-cols-2 border-t border-gray-200 text-xs font-semibold">
        <button
          type="button"
          onClick={() => onManagePage(display)}
          className="flex items-center justify-center gap-1.5 py-2 text-gray-800 border-r border-gray-200 hover:bg-gray-50 cursor-pointer focus:outline-2 focus:-outline-offset-2 focus:outline-xibo-blue-500"
        >
          <Settings className="size-3.5 shrink-0" />
          {t('Manage')}
        </button>
        <button
          type="button"
          onClick={() => onEdit(display)}
          className="flex items-center justify-center gap-1.5 py-2 text-xibo-blue-600 hover:bg-xibo-blue-50 cursor-pointer focus:outline-2 focus:-outline-offset-2 focus:outline-xibo-blue-500"
        >
          <Edit className="size-3.5 shrink-0" />
          {t('Edit')}
        </button>
      </div>
    </div>
  );
}
