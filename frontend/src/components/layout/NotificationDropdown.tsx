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

import {
  autoUpdate,
  flip,
  FloatingPortal,
  offset,
  shift,
  useClick,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Bell } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import InfoBanner from '@/components/ui/InfoBanner';
import { useUserContext } from '@/context/UserContext';
import { useDismissOnIframeFocus } from '@/hooks/useDismissOnIframeFocus';
import ShowNotificationModal from '@/pages/Notification/components/ShowNotificationModal';
import {
  notificationQueryKeys,
  useNotificationInbox,
} from '@/pages/Notification/hooks/useNotificationData';
import { markAllNotificationsRead } from '@/services/notificationApi';
import type { Notification } from '@/types/notification';
import { hasFeature } from '@/utils/permissions';

const getDateBoundaries = () => {
  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000;
  const yesterdayStart = todayStart - 86400;
  return { todayStart, yesterdayStart };
};

const formatTime = (ts: number | string): string => {
  const d = new Date(Number(ts) * 1000);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

function NotificationItem({
  notification,
  onClick,
}: {
  notification: Notification;
  onClick: (n: Notification) => void;
}) {
  const isUnread = !notification.read;

  return (
    <button
      type="button"
      onClick={() => onClick(notification)}
      className={`w-full text-left px-3 flex items-start gap-3 mb-1 rounded-lg cursor-pointer transition-colors ${
        isUnread ? 'py-3 bg-blue-50 hover:bg-blue-100' : 'py-2 hover:bg-gray-100'
      }`}
    >
      <div className="flex-shrink-0 mt-1.5">
        {isUnread ? (
          <div className="w-2 h-2 rounded-full bg-xibo-blue-600" />
        ) : (
          <div className="w-2 h-2 rounded-full bg-gray-300" />
        )}
      </div>
      <div className="flex-1 min-w-0">
        <p
          className={`text-sm truncate ${isUnread ? 'font-semibold text-gray-900' : 'font-normal text-gray-700'}`}
        >
          {notification.subject}
        </p>
        <p className="text-xs text-gray-400 mt-0.5">{formatTime(notification.releaseDt)}</p>
      </div>
    </button>
  );
}

function SectionLabel({ label }: { label: string }) {
  return (
    <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 pt-2 pb-1">
      {label}
    </p>
  );
}

function SystemNotificationItem({ notification }: { notification: Notification }) {
  return (
    <div className="w-full px-3 py-2 flex items-start gap-2 mb-1 rounded-lg bg-amber-50 border border-amber-200">
      <div className="flex-shrink-0 mt-0.5">
        <AlertTriangle size={14} className="text-amber-600" />
      </div>
      <p className="text-xs text-amber-800 leading-snug mt-0.5">{notification.subject}</p>
    </div>
  );
}

export default function NotificationDropdown() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useUserContext();

  const [isOpen, setIsOpen] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [isMarkingRead, setIsMarkingRead] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: (open) => {
      setIsOpen(open);
      if (open) setSaveError(null);
    },
    placement: 'bottom-end',
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift({ padding: 8 })],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  useDismissOnIframeFocus(isOpen, () => setIsOpen(false));

  const { data, isFetching } = useNotificationInbox();

  if (!hasFeature(user, 'notification.centre')) return null;

  const notifications = data?.rows ?? [];
  const unreadCount = data?.unreadCount ?? 0;

  const systemNotifs = notifications.filter((n) => n.notificationId === null);
  const regularNotifs = notifications.filter((n) => n.notificationId !== null);

  const byReleaseDtDesc = (a: Notification, b: Notification) =>
    Number(b.releaseDt) - Number(a.releaseDt);

  const { todayStart, yesterdayStart } = getDateBoundaries();
  const todayNotifs = regularNotifs
    .filter((n) => Number(n.releaseDt) >= todayStart)
    .sort(byReleaseDtDesc);
  const yesterdayNotifs = regularNotifs
    .filter((n) => Number(n.releaseDt) >= yesterdayStart && Number(n.releaseDt) < todayStart)
    .sort(byReleaseDtDesc);
  const olderNotifs = regularNotifs
    .filter((n) => Number(n.releaseDt) < yesterdayStart)
    .sort(byReleaseDtDesc);

  const handleMarkAllAsRead = async () => {
    if (unreadCount === 0 || isMarkingRead) return;

    const previousData = queryClient.getQueriesData<{
      rows: Notification[];
      totalCount: number;
      unreadCount: number;
    }>({ queryKey: notificationQueryKeys.all });

    queryClient.setQueriesData<{ rows: Notification[]; totalCount: number; unreadCount: number }>(
      { queryKey: notificationQueryKeys.all },
      (old) => {
        if (!old?.rows) return old;
        const remainingUnread = old.rows.filter((n) => n.notificationId === null).length;
        return {
          ...old,
          rows: old.rows.map((n) => (n.notificationId !== null && !n.read ? { ...n, read: 1 } : n)),
          unreadCount: remainingUnread,
        };
      },
    );

    setIsMarkingRead(true);
    setSaveError(null);

    try {
      await markAllNotificationsRead();
      queryClient.invalidateQueries({ queryKey: notificationQueryKeys.all });
    } catch {
      previousData.forEach(([queryKey, data]) => queryClient.setQueryData(queryKey, data));
      setSaveError(t('Error updating the notifications. Please try again.'));
    } finally {
      setIsMarkingRead(false);
    }
  };

  const handleNotificationClick = (notification: Notification) => {
    setSelectedNotification(notification);
    setIsOpen(false);
    if (!notification.read) {
      queryClient.setQueriesData<{ rows: Notification[]; totalCount: number; unreadCount: number }>(
        { queryKey: notificationQueryKeys.all },
        (old) => {
          if (!old?.rows) return old;
          return {
            ...old,
            rows: old.rows.map((n) =>
              n.notificationId === notification.notificationId ? { ...n, read: 1 } : n,
            ),
            unreadCount: Math.max(0, (old.unreadCount ?? 0) - 1),
          };
        },
      );
    }
  };

  const handleOpenCentre = () => {
    setIsOpen(false);
    navigate('/notification');
  };

  return (
    <>
      <button
        ref={refs.setReference}
        type="button"
        className="cursor-pointer flex items-center justify-center relative h-9.5 w-9.5"
        {...getReferenceProps()}
        aria-label={t('Notifications')}
        title={t('Notifications')}
      >
        <Bell size={16} className="text-xibo-blue-600" />
        {unreadCount > 0 && (
          <div className="w-4.5 h-4.5 bg-xibo-blue-600 rounded-full text-[10px] text-white absolute top-0 right-0 flex items-center justify-center pointer-events-none">
            {unreadCount > 9 ? '9+' : unreadCount}
          </div>
        )}
      </button>

      {isOpen && (
        <FloatingPortal>
          <div
            ref={refs.setFloating}
            style={{ ...floatingStyles, zIndex: 9999 }}
            {...getFloatingProps()}
            className="rounded-xl bg-white shadow-lg ring-1 ring-black/5 focus:outline-none overflow-hidden w-80 flex flex-col"
          >
            {/* Header — matches UserMenu header style */}
            <div className="flex items-center justify-between bg-gray-100 py-2 px-4">
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-semibold text-gray-900">{t('Notifications')}</h3>
                {unreadCount > 0 && (
                  <span className="inline-flex items-center justify-center p-1 text-[10px] font-bold bg-xibo-blue-600 text-white rounded-full min-w-[18px] leading-none">
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </div>
              <button
                type="button"
                onClick={handleMarkAllAsRead}
                disabled={isMarkingRead || unreadCount === 0}
                className="text-xs text-xibo-blue-600 hover:text-xibo-blue-800 font-medium cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                {isMarkingRead ? t('Marking…') : t('Mark All as Read')}
              </button>
            </div>

            {/* Body — matches UserMenu p-2 content area */}
            <div className="max-h-80 overflow-y-auto p-2">
              {saveError && (
                <div role="alert">
                  <InfoBanner type="danger" className="w-full mb-2">
                    {saveError}
                  </InfoBanner>
                </div>
              )}
              {isFetching && notifications.length === 0 ? (
                <div className="flex items-center justify-center py-8">
                  <p className="text-sm text-gray-400">{t('Loading...')}</p>
                </div>
              ) : (
                <>
                  {systemNotifs.length > 0 && (
                    <div className="mb-1">
                      {systemNotifs.map((n, i) => (
                        <SystemNotificationItem key={`system-${i}`} notification={n} />
                      ))}
                    </div>
                  )}
                  {regularNotifs.length === 0 && systemNotifs.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 gap-2">
                      <Bell size={24} className="text-gray-300" />
                      <p className="text-sm text-gray-400">{t('No notifications')}</p>
                    </div>
                  ) : regularNotifs.length === 0 ? null : (
                    <>
                      {todayNotifs.length > 0 && (
                        <>
                          <SectionLabel label={t('Today')} />
                          {todayNotifs.map((n) => (
                            <NotificationItem
                              key={n.notificationId}
                              notification={n}
                              onClick={handleNotificationClick}
                            />
                          ))}
                        </>
                      )}
                      {yesterdayNotifs.length > 0 && (
                        <>
                          <hr className="border-gray-300 my-2" />
                          <SectionLabel label={t('Yesterday')} />
                          {yesterdayNotifs.map((n) => (
                            <NotificationItem
                              key={n.notificationId}
                              notification={n}
                              onClick={handleNotificationClick}
                            />
                          ))}
                        </>
                      )}
                      {olderNotifs.length > 0 && (
                        <>
                          <hr className="border-gray-300 my-2" />
                          <SectionLabel label={t('Older')} />
                          {olderNotifs.map((n) => (
                            <NotificationItem
                              key={n.notificationId}
                              notification={n}
                              onClick={handleNotificationClick}
                            />
                          ))}
                        </>
                      )}
                    </>
                  )}
                </>
              )}
            </div>

            {/* Footer — Notification Centre as a branded button */}
            {hasFeature(user, 'notification.centre') && (
              <div className="p-2 border-t border-gray-200">
                <button
                  type="button"
                  onClick={handleOpenCentre}
                  className="flex w-full items-center justify-center gap-2 rounded-lg cursor-pointer px-3 py-2 text-sm font-semibold text-white bg-xibo-blue-600 hover:bg-xibo-blue-700 transition"
                >
                  <Bell size={14} />
                  {t('Notification Centre')}
                </button>
              </div>
            )}
          </div>
        </FloatingPortal>
      )}

      {selectedNotification && (
        <ShowNotificationModal
          notification={selectedNotification}
          onClose={() => setSelectedNotification(null)}
        />
      )}
    </>
  );
}
