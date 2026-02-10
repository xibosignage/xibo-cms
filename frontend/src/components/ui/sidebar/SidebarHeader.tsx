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

import { ChevronLeftSquare, ChevronRightSquare, X } from 'lucide-react';

import favIcon from '@/assets/xibo-logo-icon.svg';
import logo from '@/assets/xibo-logo.svg';

interface Props {
  isCollapsed: boolean;
  toggleSidebar: () => void;
  closeMobileDrawer?: () => void;
}

export function SidebarHeader({ isCollapsed, toggleSidebar, closeMobileDrawer }: Props) {
  return (
    <>
      {isCollapsed && (
        <div className="flex justify-center">
          <img src={favIcon} alt="Xibo Logo" className="w-11 h-10" />
        </div>
      )}

      <div
        className={`flex items-center ${
          isCollapsed ? 'justify-center absolute top-5 -right-3' : 'justify-between'
        }`}
      >
        {!isCollapsed && (
          <div className="flex gap-2 items-end">
            <img src={logo} alt="Xibo Logo" className="w-[77.287px] h-auto" />
            <span className="border text-[10px] border-xibo-white/20 px-1 py-0.5 rounded-full text-white">
              V5.0
            </span>
          </div>
        )}

        <button
          onClick={toggleSidebar}
          className={`md:flex hidden items-center justify-center rounded-lg
            bg-xibo-blue-800 text-xibo-white z-10 transition-colors
            hover:bg-white/10 cursor-pointer
            ${isCollapsed ? 'w-6 h-6 hover:bg-xibo-blue-800' : 'w-9.5 h-9.5'}
          `}
        >
          {isCollapsed ? <ChevronRightSquare size={16} /> : <ChevronLeftSquare size={16} />}
        </button>
        <button
          onClick={closeMobileDrawer}
          className="md:hidden flex w-9.5 h-9.5 items-center
            text-xibo-blue-100 justify-center rounded-lg hover:bg-white/10"
        >
          <X size={16} />
        </button>
      </div>
    </>
  );
}
