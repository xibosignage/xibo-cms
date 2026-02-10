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

import { ExternalLink, LockKeyhole, LogOut, RefreshCw, AlertTriangle } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalAction } from '../ui/modals/Modal';
import Modal from '../ui/modals/Modal';

import http from '@/lib/api';
import { authEvents } from '@/lib/auth-events';

export function SessionExpiredModal() {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [isChecking, setIsChecking] = useState(false);

  // Listen for the "session-expired" event
  useEffect(() => {
    const handleExpired = () => setIsOpen(true);
    authEvents.addEventListener('session-expired', handleExpired);
    return () => authEvents.removeEventListener('session-expired', handleExpired);
  }, []);

  const checkSession = useCallback(async () => {
    if (isChecking) {
      return;
    }

    // If modal is open, check if we can close it
    if (isOpen) {
      setIsChecking(true);
      try {
        await http.get('/user/me');
        setIsOpen(false);
      } catch {
        // Still expired, stay open
      } finally {
        setIsChecking(false);
      }
    } else if (document.visibilityState === 'visible') {
      // If modal is closed, check silently
      try {
        await http.get('/user/me');
      } catch {
        // Interceptor catches 401 and opens modal
      }
    }
  }, [isOpen, isChecking]);

  // Pro active check - focus and auto-resume
  useEffect(() => {
    // Check when tab becomes visible
    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        checkSession();
      }
    };

    // Check when window gets focus
    const handleFocus = () => {
      checkSession();
    };

    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('focus', handleFocus);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibility);
      window.removeEventListener('focus', handleFocus);
    };
  }, [checkSession]);

  const modalActions: ModalAction[] = [
    {
      label: t('Log Out'),
      onClick: () => {
        setIsOpen(false);
        window.location.href = '/login';
      },
      variant: 'secondary',
      leftIcon: LogOut,
    },
    {
      label: t('Log In'),
      onClick: () => {
        window.open('/login', '_blank');
      },
      disabled: isChecking,
      rightIcon: ExternalLink,
    },
  ];

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => {}}
      closeOnOverlay={false}
      actions={modalActions}
      size="sm"
    >
      <div className="flex flex-col gap-3 p-5 text-center">
        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-xibo-blue-100 text-xibo-blue-800 outline-[7px] outline-xibo-blue-50">
          <LockKeyhole size={28} />
        </div>

        <div className="text-lg font-semibold leading-7.5 text-xibo-blue-800">
          {t('Session Expired')}
        </div>

        <div className="text-gray-500 dark:text-neutral-400 text-sm leading-relaxed">
          <p>{t('You have been away for a while.')}</p>
          <p>{t('To prevent data loss, we have paused your session.')}</p>
        </div>

        {isChecking ? (
          <div className="flex items-center justify-center gap-2 text-xs font-medium text-xibo-blue-600 animate-pulse">
            <RefreshCw className="size-3 animate-spin" />
            <span>{t('Verifying connection...')}</span>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            <div className="flex justify-center gap-3 p-3 rounded-lg bg-amber-50 text-yellow-800">
              <AlertTriangle className="mt-0.5 shrink-0" size={16} />
              <p className="text-xs font-medium leading-relaxed">
                {t('Do not refresh this page or you will lose unsaved work.')}
              </p>
            </div>

            <button
              onClick={() => checkSession()}
              className="text-xs text-gray-500 underline decoration-dashed cursor-pointer hover:text-xibo-blue-600 transition-colors"
            >
              {t('I have already logged in')}
            </button>
          </div>
        )}
      </div>
    </Modal>
  );
}
