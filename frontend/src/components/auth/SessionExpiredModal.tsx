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

import { ExternalLink, Lock, RefreshCw, AlertCircle } from 'lucide-react';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '../ui/Button';
import type { ModalAction } from '../ui/modals/Modal';
import Modal from '../ui/modals/Modal';

import http from '@/lib/api';
import { authEvents } from '@/lib/auth-events';

export function SessionExpiredModal() {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const [isChecking, setIsChecking] = useState(false);

  const checkLock = useRef(false);

  // Listen for the "session-expired" event
  useEffect(() => {
    const handleExpired = () => setIsOpen(true);
    authEvents.addEventListener('session-expired', handleExpired);
    return () => authEvents.removeEventListener('session-expired', handleExpired);
  }, []);

  const checkSession = useCallback(async () => {
    if (checkLock.current) {
      return;
    }
    checkLock.current = true;

    try {
      // If modal is open, check if we can close it
      if (isOpen) {
        setIsChecking(true);
        try {
          await http.head('/user/me');
          setIsOpen(false);
        } catch {
          // Still expired, stay open
        } finally {
          setIsChecking(false);
        }
      } else if (document.visibilityState === 'visible') {
        // If modal is closed, check silently
        try {
          await http.head('/user/me');
        } catch {
          // Interceptor catches 401 and opens modal
        }
      }
    } finally {
      checkLock.current = false;
    }
  }, [isOpen]);

  // Pro active check - focus and auto-resume
  useEffect(() => {
    const handleTrigger = () => {
      checkSession();
    };

    document.addEventListener('visibilitychange', handleTrigger);
    window.addEventListener('focus', handleTrigger);

    return () => {
      document.removeEventListener('visibilitychange', handleTrigger);
      window.removeEventListener('focus', handleTrigger);
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
        <div className="mx-auto flex size-13.75 items-center justify-center rounded-full bg-xibo-blue-100 text-xibo-blue-800 outline-[7px] outline-xibo-blue-50">
          <Lock size={26} />
        </div>

        <div className="text-lg font-semibold leading-7.5 text-xibo-blue-800">
          {t('Session Expired')}
        </div>

        <div className="text-gray-500 dark:text-neutral-400 leading-6">
          {t('We’ve paused your session to keep your work safe while you were away.')}
        </div>

        <div className="w-fit mx-auto flex justify-center items-center gap-1 p-1.5 rounded-lg bg-yellow-100 text-yellow-800">
          <AlertCircle size={16} />
          <p className="text-sm font-medium leading-4.5">
            {t('Keep this window open! Login to pick up where you left off.')}
          </p>
        </div>

        {isChecking ? (
          <div className="flex items-center justify-center p-2 text-sm font-medium text-xibo-blue-600 animate-pulse">
            <RefreshCw className="size-3 animate-spin" />
            <span className="ml-1 p-1">{t('Verifying connection...')}</span>
          </div>
        ) : (
          <div className="flex flex-col">
            <Button variant="link" onClick={() => checkSession()} className="underline">
              {t('I have already logged in')}
            </Button>
          </div>
        )}
      </div>
    </Modal>
  );
}
