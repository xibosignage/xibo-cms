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

import { useQueryClient } from '@tanstack/react-query';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';
import { withPublicPath } from '@/config/publicPath';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { notificationQueryKeys } from '@/pages/Notification/hooks/useNotificationData';
import { markAllNotificationsRead } from '@/services/notificationApi';
import type { Notification } from '@/types/notification';

interface ShowNotificationModalProps {
  isOpen?: boolean;
  notification: Notification;
  onClose: () => void;
}

export default function ShowNotificationModal({
  isOpen = true,
  notification,
  onClose,
}: ShowNotificationModalProps) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { formatDateTime } = useDateFormatter();
  const [iframeHeight, setIframeHeight] = useState(200);
  const iframeRef = useRef<HTMLIFrameElement>(null);

  const formatDate = (value: number | string | null | undefined): string => {
    if (value === null || value === undefined || value === '') return '';
    const ts = Number(value);
    if (isNaN(ts) || ts === 0) return String(value);
    return formatDateTime(new Date(ts * 1000));
  };

  useEffect(() => {
    markAllNotificationsRead(notification.notificationId ?? undefined)
      .then(() => queryClient.invalidateQueries({ queryKey: notificationQueryKeys.all }))
      .catch(() => {});
  }, [notification.notificationId, queryClient]);

  useEffect(() => {
    const handler = (e: MessageEvent) => {
      if (
        e.data?.type === 'xibo-notification-resize' &&
        typeof e.data.height === 'number' &&
        e.source === iframeRef.current?.contentWindow
      ) {
        setIframeHeight(Math.max(150, e.data.height + 20));
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, []);

  const releaseDt = formatDate(notification.releaseDt);
  const readDt = notification.readDt ? formatDate(notification.readDt) : null;
  const hasAttachment = notification.originalFileName && notification.originalFileName !== '';
  const bodyWithResize = `<script>window.addEventListener('load',function(){parent.postMessage({type:'xibo-notification-resize',height:document.documentElement.scrollHeight},'*');});</script>${notification.body ?? ''}`;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="lg"
      scrollable
      actions={[
        {
          label: t('Close'),
          onClick: onClose,
          variant: 'primary',
        },
      ]}
    >
      <div className="flex flex-col items-center gap-4 p-8">
        {/* Bell icon header */}
        <div className="flex items-center justify-center w-12 h-12 rounded-full bg-xibo-blue-100">
          <Bell size={24} className="text-xibo-blue-600" />
        </div>

        <p className="text-lg font-bold text-gray-600 text-center">{notification.subject}</p>

        <p className="text-xs text-gray-400 text-center">
          {readDt
            ? t('Published {{releaseDt}}, you read this {{readDt}}', { releaseDt, readDt })
            : t('Published {{releaseDt}}', { releaseDt })}
        </p>

        <iframe
          ref={iframeRef}
          sandbox="allow-scripts"
          frameBorder={0}
          id={`notification_${notification.notificationId}`}
          srcDoc={bodyWithResize}
          title={notification.subject}
          className="w-full border-none rounded"
          style={{ minHeight: '150px', height: `${iframeHeight}px` }}
        />

        {hasAttachment && (
          <a
            href={withPublicPath(`json/notification/export/${notification.notificationId}`)}
            className="text-xibo-blue-600 hover:text-xibo-blue-800 underline text-sm text-left"
          >
            {notification.originalFileName}
          </a>
        )}
      </div>
    </Modal>
  );
}
