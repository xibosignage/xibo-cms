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
import { Settings, AppWindow, Lightbulb, Info, LogOut, PenLine } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import AboutModal from './AboutModal';
import ApplicationsModal from './ApplicationsModal';
import PreferencesModal from './PreferencesModal';
import ProfileEditModal from './ProfileEditModal';

import { useUserContext } from '@/context/UserContext';

// Expand the modal types
type ActiveModal = 'preferences' | 'applications' | 'profile' | 'about' | null;

function getInitials(user: { firstName?: string; lastName?: string; userName?: string } | null) {
  if (!user) return '??';
  if (user.firstName && user.lastName) {
    return `${user.firstName[0]}${user.lastName[0]}`.toUpperCase();
  }
  return (user.userName?.substring(0, 2) || 'U').toUpperCase();
}

export default function UserMenu() {
  const { t } = useTranslation();
  const { user, logout } = useUserContext();

  const [isOpen, setIsOpen] = useState(false);
  const [activeModal, setActiveModal] = useState<ActiveModal>(null);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-end',
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const openModal = (modal: ActiveModal) => {
    setActiveModal(modal);
    setIsOpen(false);
  };

  const handleSignOut = () => {
    setIsOpen(false);
    logout();
  };

  const handleNavigateToWelcome = () => {
    setIsOpen(false);
    window.location.href = '/welcome';
  };

  const initials = getInitials(user);
  const displayName =
    [user?.firstName, user?.lastName].filter(Boolean).join(' ') || user?.userName || 'User';

  return (
    <>
      {/* Avatar */}
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        type="button"
        className="flex h-6.5 w-6.5 items-center justify-center cursor-pointer rounded-full bg-blue-100 text-[12px] font-semibold text-xibo-blue-800 transition hover:ring-2 hover:ring-xibo-blue-600 focus:outline-none"
      >
        {initials}
      </button>

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
            <div className="flex items-center justify-between bg-gray-100 py-2 px-5 relative group gap-2.5">
              <div className="flex h-9.5 w-9.5 items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-800">
                {initials}
              </div>
              <div className="flex flex-col">
                <span className="text-lg font-semibold text-gray-900 leading-tight truncate w-32">
                  {displayName}
                </span>
                <span className="text-sm text-gray-500 truncate w-50">{user?.email}</span>
              </div>
              <button
                onClick={() => openModal('profile')}
                className="rounded-md p-1 h-6 w-6 text-gray-500 cursor-pointer transition hover:bg-gray-200 hover:text-gray-700"
                title={t('Edit Profile')}
              >
                <PenLine size={16} />
              </button>
            </div>

            {/* Menu Items */}
            <div className="p-2">
              <MenuItem
                icon={<Settings size={18} />}
                label={t('Preferences')}
                onClick={() => openModal('preferences')}
              />
              <MenuItem
                icon={<AppWindow size={18} />}
                label={t('My Applications')}
                onClick={() => openModal('applications')}
              />

              <div className="p-2 py-1 mt-2 border-t border-gray-200"></div>

              <MenuItem
                icon={<Lightbulb size={18} />}
                label={t('Introduction to Xibo')}
                onClick={handleNavigateToWelcome}
              />
              <MenuItem
                icon={<Info size={18} />}
                label={t('About')}
                onClick={() => openModal('about')}
              />

              <div className="p-2 py-1 mt-2 border-t border-gray-200"></div>

              <div className="space-y-0.5">
                <MenuItem
                  icon={<LogOut size={18} />}
                  label={t('Sign Out')}
                  onClick={handleSignOut}
                />
              </div>
            </div>
          </div>
        )}
      </FloatingPortal>

      {/* Modals */}
      {activeModal === 'preferences' && <PreferencesModal onClose={() => setActiveModal(null)} />}

      {activeModal === 'profile' && <ProfileEditModal onClose={() => setActiveModal(null)} />}

      {activeModal === 'applications' && <ApplicationsModal onClose={() => setActiveModal(null)} />}

      {activeModal === 'about' && <AboutModal onClose={() => setActiveModal(null)} />}
    </>
  );
}

function MenuItem({
  icon,
  label,
  onClick,
}: {
  icon: React.ReactNode;
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className="flex w-full items-center gap-3 rounded-lg cursor-pointer px-3 py-2 text-sm font-medium text-gray-800 transition hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
    >
      <span className="text-gray-500">{icon}</span>
      {label}
    </button>
  );
}
