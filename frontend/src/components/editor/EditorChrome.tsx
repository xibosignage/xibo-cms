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

import NotificationDropdown from '@/components/layout/NotificationDropdown';
import UserMenu from '@/components/layout/UserMenu/UserMenu';

export default function EditorChrome() {
  return (
    <div className="absolute top-0 right-2.5 z-20 flex h-12.5 items-center gap-1">
      <div className="flex h-9.5 w-9.5 items-center justify-center">
        <NotificationDropdown />
      </div>
      <div className="flex h-9.5 w-9.5 items-center justify-center">
        <UserMenu />
      </div>
    </div>
  );
}
