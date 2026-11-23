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

import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { Settings, Lightbulb, Info, LogOut } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import AboutModal from './AboutModal';
import MenuItem from './MenuItem';

import type { MenuTriggerProps } from '@/components/layout/menuTrigger';
import Badge from '@/components/ui/Badge';
import { PREFERENCES_ROUTE } from '@/config/appRoutes';
import { useUserContext } from '@/context/UserContext';
import { useClock } from '@/hooks/useClock';
import { useDismissOnIframeFocus } from '@/hooks/useDismissOnIframeFocus';
import { getUserDisplayName, getUserInitials } from '@/utils/user';

interface UserMenuModals {
  about: true;
}

type ActiveModal = keyof UserMenuModals | null;

export default function UserMenu({ placement = 'bottom-end', renderTrigger }: MenuTriggerProps) {
  const { t } = useTranslation();
  const { user, logout } = useUserContext();
  const navigate = useNavigate();

  const [isOpen, setIsOpen] = useState(false);
  const [activeModal, setActiveModal] = useState<ActiveModal>(null);
  const clock = useClock();

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement,
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  useDismissOnIframeFocus(isOpen, () => setIsOpen(false));

  const openModal = (modal: ActiveModal) => {
    setActiveModal(modal);
    setIsOpen(false);
  };

  const handleSignOut = () => {
    setIsOpen(false);
    logout();
  };

  const handleNavigateToPreferences = () => {
    setIsOpen(false);
    navigate(`/${PREFERENCES_ROUTE.path}`);
  };

  const handleNavigateToWelcome = () => {
    setIsOpen(false);
    navigate('/welcome');
  };

  const initials = getUserInitials(user);
  const displayName = getUserDisplayName(user);

  return (
    <>
      {renderTrigger ? (
        renderTrigger({
          ref: refs.setReference,
          referenceProps: getReferenceProps(),
          isOpen,
        })
      ) : (
        <button
          ref={refs.setReference}
          {...getReferenceProps()}
          type="button"
          className="flex h-6.5 w-6.5 items-center justify-center cursor-pointer rounded-full bg-xibo-blue-100 text-[12px] font-semibold text-xibo-blue-800 transition hover:ring-2 hover:ring-xibo-blue-600 focus:outline-none"
        >
          {initials}
        </button>
      )}

      {/* Dropdown Menu */}
      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-50 rounded-xl bg-white shadow-lg ring-1 ring-black/5 focus:outline-none overflow-hidden"
          >
            {/* User Info Header */}
            <div className="flex items-center bg-gray-100 py-2 px-5 relative group gap-2.5">
              <div className="flex h-9.5 w-9.5 items-center justify-center rounded-full bg-xibo-blue-100 font-semibold text-xibo-blue-800">
                {initials}
              </div>
              <div className="flex flex-col">
                <span className="text-lg font-semibold text-gray-900 leading-tight truncate w-32">
                  {displayName}
                </span>
                <span className="text-sm text-gray-500 truncate w-50">{user?.email}</span>
              </div>
              {clock && (
                <Badge
                  type="neutral"
                  variation="outline"
                  className="bg-transparent ml-auto shrink-0"
                >
                  {clock}
                </Badge>
              )}
            </div>

            {/* Menu Items */}
            <div className="p-2">
              <MenuItem
                icon={<Settings size={18} />}
                label={t('Preferences')}
                onClick={handleNavigateToPreferences}
              />

              <div className="p-2 py-1 mt-2 border-t border-gray-200"></div>

              <MenuItem
                icon={<Lightbulb size={18} />}
                label={t('Introduction to {{appName}}', {
                  appName: user?.branding?.appName ?? 'Xibo',
                })}
                onClick={handleNavigateToWelcome}
              />
              <MenuItem
                icon={<Info size={18} />}
                label={t('About')}
                onClick={() => openModal('about')}
              />

              {!user?.hideLogoutButton && (
                <>
                  <div className="p-2 py-1 mt-2 border-t border-gray-200"></div>
                  <div className="space-y-0.5">
                    <MenuItem
                      icon={<LogOut size={18} />}
                      label={t('Sign Out')}
                      onClick={handleSignOut}
                      variant="danger"
                    />
                  </div>
                </>
              )}
            </div>
          </div>
        )}
      </FloatingPortal>

      {/* Modals */}
      {activeModal === 'about' && <AboutModal onClose={() => setActiveModal(null)} />}
    </>
  );
}
