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

import { Info, LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface LogoutSessionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onLogout: () => void;
  userName?: string;
  itemCount?: number;
  error?: string | null;
  isLoading?: boolean;
}

export default function LogoutSessionModal({
  isOpen,
  onClose,
  onLogout,
  userName,
  itemCount,
  isLoading,
  error,
}: LogoutSessionModalProps) {
  const { t } = useTranslation();
  return (
    <Modal
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Logging out session…') : t('Yes, Logout'),
          onClick: () => onLogout(),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <LogOut size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {itemCount === 1 ? t('Logout from this session?') : t('Logout from the sessions?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {itemCount === 1 ? (
            <>
              {t('Are you sure you want to logout ')}"<strong>{userName}</strong>?"
            </>
          ) : (
            <>
              {t('Are you sure you want to logout ')}
              <strong>{itemCount}</strong> {t('sessions')}?
            </>
          )}
        </p>

        <span className="flex gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <span className="text-[12px] px-1 font-medium">
            {t('This user will be logged out from this session')}
          </span>
        </span>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
