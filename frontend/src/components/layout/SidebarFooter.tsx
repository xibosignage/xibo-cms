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

import { Bell, ChevronRight, CircleHelp, type LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import NotificationDropdown from './NotificationDropdown';
import UserMenu from './UserMenu/UserMenu';
import type { MenuTriggerRenderProps } from './menuTrigger';

import HelpMenu from '@/components/help/HelpMenu';
import { useUserContext } from '@/context/UserContext';
import { getUserDisplayName, getUserInitials, getUserTypeLabel } from '@/utils/user';

const MENU_PLACEMENT = 'right-end';

interface SidebarFooterProps {
  isCollapsed: boolean;
  contentHidden: boolean;
}

interface FooterButtonProps extends MenuTriggerRenderProps {
  icon: LucideIcon;
  label: string;
  count?: number;
  isCollapsed: boolean;
  contentHidden: boolean;
}

function CountBadge({ count, className = '' }: { count: number; className?: string }) {
  return (
    <span
      className={`flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-red-500 px-1 text-xs leading-none font-semibold text-white pointer-events-none ${className}`}
    >
      {count > 9 ? '9+' : count}
    </span>
  );
}

function FooterButton({
  ref,
  referenceProps,
  isOpen,
  icon: Icon,
  label,
  count = 0,
  isCollapsed,
  contentHidden,
}: FooterButtonProps) {
  return (
    <button
      ref={ref}
      {...referenceProps}
      type="button"
      aria-label={label}
      aria-expanded={isOpen}
      title={isCollapsed ? label : undefined}
      className={`relative flex items-center py-2 px-3 rounded-sm cursor-pointer font-medium text-sm hover:bg-(--sidebar-overlay) hover:text-(--sidebar-fg) dark:text-neutral-400 dark:hover:text-neutral-500 focus:outline-hidden ${isOpen ? 'bg-(--sidebar-overlay) text-(--sidebar-fg)' : 'text-(--sidebar-fg-muted)'} ${isCollapsed ? 'w-fit justify-center' : 'w-full'}`}
    >
      <Icon width={20} height={20} className={isCollapsed ? '' : 'mr-2'} />
      {!contentHidden && (
        <>
          <span className="flex-1 text-left animate-fade-in">{label}</span>
          {count > 0 && <CountBadge count={count} className="mr-1" />}
          <ChevronRight size={16} />
        </>
      )}
      {contentHidden && count > 0 && (
        <CountBadge count={count} className="absolute top-0 right-0" />
      )}
    </button>
  );
}

export default function SidebarFooter({ isCollapsed, contentHidden }: SidebarFooterProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  const displayName = getUserDisplayName(user);

  return (
    <div
      className={`flex-none flex flex-col gap-y-2 mt-2 pt-3 border-t border-(--sidebar-overlay-strong) ${isCollapsed ? 'items-center' : 'px-5'}`}
    >
      <NotificationDropdown
        placement={MENU_PLACEMENT}
        renderTrigger={(props) => (
          <FooterButton
            {...props}
            icon={Bell}
            label={t('Notifications')}
            count={props.unreadCount}
            isCollapsed={isCollapsed}
            contentHidden={contentHidden}
          />
        )}
      />
      <HelpMenu
        placement={MENU_PLACEMENT}
        renderTrigger={(props) => (
          <FooterButton
            {...props}
            icon={CircleHelp}
            label={t('Help & Support')}
            isCollapsed={isCollapsed}
            contentHidden={contentHidden}
          />
        )}
      />
      <UserMenu
        placement={MENU_PLACEMENT}
        renderTrigger={({ ref, referenceProps, isOpen }) => (
          <button
            ref={ref}
            {...referenceProps}
            type="button"
            aria-label={t('User menu for {{name}}', { name: displayName })}
            aria-expanded={isOpen}
            title={isCollapsed ? displayName : undefined}
            className={`flex items-center gap-2.5 p-2 rounded-lg border border-(--sidebar-overlay-strong) cursor-pointer text-left hover:bg-(--sidebar-overlay) focus:outline-hidden ${isOpen ? 'bg-(--sidebar-overlay)' : ''} ${isCollapsed ? 'w-fit' : 'w-full'}`}
          >
            <span className="flex size-9.5 shrink-0 items-center justify-center rounded-full bg-xibo-blue-100 text-sm font-semibold text-xibo-blue-800">
              {getUserInitials(user)}
            </span>
            {!contentHidden && (
              <>
                <span className="flex min-w-0 flex-1 flex-col animate-fade-in">
                  <span className="truncate text-sm font-semibold text-(--sidebar-fg) dark:text-black">
                    {displayName}
                  </span>
                  {user && (
                    <span className="truncate text-xs text-(--sidebar-fg-muted) dark:text-neutral-600">
                      {getUserTypeLabel(t, user.userTypeId)}
                    </span>
                  )}
                </span>
                <ChevronRight size={16} className="shrink-0 text-(--sidebar-fg)" />
              </>
            )}
          </button>
        )}
      />
    </div>
  );
}
