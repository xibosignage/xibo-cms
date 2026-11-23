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

import { X } from 'lucide-react';

import { useBranding } from '@/context/BrandingContext';

interface Props {
  isCollapsed: boolean;
  contentHidden: boolean;
  closeMobileDrawer?: () => void;
}

export function SidebarHeader({ isCollapsed, contentHidden, closeMobileDrawer }: Props) {
  const { logoUrl, faviconUrl, appName } = useBranding();

  const showLogo = !contentHidden;

  return (
    <div
      className={`flex items-center h-8 ${isCollapsed ? 'justify-center' : 'justify-between pl-3'}`}
    >
      {showLogo ? (
        <div className="flex items-end gap-3 animate-fade-in">
          <img src={logoUrl} alt={appName} className="w-15.75 h-8 object-contain object-left" />
        </div>
      ) : (
        <div className="flex justify-center">
          <img src={faviconUrl} alt={appName} className="w-11 h-8 object-contain" />
        </div>
      )}

      <button
        onClick={closeMobileDrawer}
        className="md:hidden flex w-9.5 h-9.5 items-center
          text-(--sidebar-fg-muted) justify-center rounded-lg hover:bg-(--sidebar-overlay)"
      >
        <X size={16} />
      </button>
    </div>
  );
}
