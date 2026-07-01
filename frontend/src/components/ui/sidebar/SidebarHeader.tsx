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
    <div className={`flex items-center h-10 ${isCollapsed ? 'justify-center' : 'justify-between'}`}>
      {showLogo ? (
        <div className="flex items-center animate-fade-in">
          <img
            src={logoUrl}
            alt={appName}
            className="w-[77.287px] h-10 object-contain object-left"
          />
        </div>
      ) : (
        <div className="flex justify-center">
          <img src={faviconUrl} alt={appName} className="w-11 h-10 object-contain" />
        </div>
      )}

      <button
        onClick={closeMobileDrawer}
        className="md:hidden flex w-9.5 h-9.5 items-center
          text-xibo-blue-100 justify-center rounded-lg hover:bg-white/10"
      >
        <X size={16} />
      </button>
    </div>
  );
}
