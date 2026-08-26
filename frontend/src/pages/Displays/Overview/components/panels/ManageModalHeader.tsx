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

import { X, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import type { Display } from '@/types/display';

interface ManageModalHeaderProps {
  display: Display;
  onClose: () => void;
  /** Overridable so the Manage page (`OverviewManagePage.tsx`) can swap the "X" for a back arrow. */
  closeIcon?: LucideIcon;
  /** Overridable accessible label to pair with closeIcon — defaults to "Close". */
  closeLabel?: string;
}

// The Manage modal's header from display-management.html's .modal-head — a
// dark band with a breadcrumb and title. Built with the app's own sidebar
// token (--sidebar-bg/--sidebar-fg, the same brand-derived "dark surface"
// already used for the left nav) rather than the mock's invented navy hex
// values, so it follows whatever blue shade a given Xibo instance is branded
// with. Flat, not gradient — gradating to --sidebar-bg-hover (a hover-feedback
// shade, tuned to read as a highlight over a small area) made the whole band
// look too bright across this much surface.
export default function ManageModalHeader({
  display,
  onClose,
  closeIcon: CloseIcon = X,
  closeLabel,
}: ManageModalHeaderProps) {
  const { t } = useTranslation();
  const group = display.displayGroups?.[0]?.displayGroup;

  return (
    <div className="shrink-0 bg-(--sidebar-bg) px-6 py-5">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          {group && (
            <p className="truncate text-xs text-(--sidebar-fg-muted)">
              {t('All Displays')} / {group}
            </p>
          )}
          <h2 className="truncate text-lg font-semibold text-(--sidebar-fg)">{display.display}</h2>
        </div>
        <button
          type="button"
          aria-label={closeLabel ?? t('Close')}
          onClick={onClose}
          className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-(--sidebar-fg) hover:bg-white/20 cursor-pointer transition-colors"
        >
          <CloseIcon className="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>
  );
}
