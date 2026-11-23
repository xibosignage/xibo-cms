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

import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';

import ShowNotificationModal from './ShowNotificationModal';

import { useUserContext } from '@/context/UserContext';
import { fetchInterruptNotifications } from '@/services/notificationApi';
import type { Notification } from '@/types/notification';

export default function NotificationInterruptCheck() {
  const { user } = useUserContext();
  const { pathname } = useLocation();
  const [queue, setQueue] = useState<Notification[]>([]);
  const lastCheckedPath = useRef<string | null>(null);

  useEffect(() => {
    if (!user || lastCheckedPath.current === pathname) return;

    lastCheckedPath.current = pathname;
    fetchInterruptNotifications()
      .then((notifications) => {
        if (notifications.length === 0) return;
        setQueue((prev) => {
          const existingIds = new Set(prev.map((n) => n.notificationId));
          const newItems = notifications.filter((n) => !existingIds.has(n.notificationId));
          return newItems.length > 0 ? [...prev, ...newItems] : prev;
        });
      })
      .catch(() => {});
  }, [user, pathname]);

  const current = queue[0];

  const handleClose = () => {
    if (!current) return;
    setQueue((prev) => prev.slice(1));
  };

  if (!current) return null;

  return <ShowNotificationModal notification={current} onClose={handleClose} />;
}
