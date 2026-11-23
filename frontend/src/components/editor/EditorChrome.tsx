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

import HelpIconMenu from '@/components/help/HelpIconMenu';
import NotificationDropdown from '@/components/layout/NotificationDropdown';
import UserMenu from '@/components/layout/UserMenu/UserMenu';

/** Where the editor page left room for the help button, relative to its iframe. */
export interface HelpSlot {
  top: number;
  left: number;
}

interface EditorChromeProps {
  helpSlot?: HelpSlot | null;
}

export default function EditorChrome({ helpSlot }: EditorChromeProps) {
  if (helpSlot) {
    return (
      <div
        className="absolute z-20 flex h-9.5 w-9.5 items-center justify-center"
        style={{ top: helpSlot.top, left: helpSlot.left }}
      >
        <HelpIconMenu />
      </div>
    );
  }

  return (
    <div className="absolute top-0 right-2.5 z-20 flex h-12.5 items-center gap-1">
      <div className="flex h-9.5 w-9.5 items-center justify-center">
        <HelpIconMenu />
      </div>
      <div className="flex h-9.5 w-9.5 items-center justify-center">
        <NotificationDropdown />
      </div>
      <div className="flex h-9.5 w-9.5 items-center justify-center">
        <UserMenu />
      </div>
    </div>
  );
}
