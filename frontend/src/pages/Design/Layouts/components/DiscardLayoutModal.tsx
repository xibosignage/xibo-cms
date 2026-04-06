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

import { AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface DiscardLayoutModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: () => void;
  layoutName?: string;
  isLoading?: boolean;
  error?: string | null;
}

export default function DiscardLayoutModal({
  isOpen,
  onClose,
  onConfirm,
  layoutName,
  isLoading,
  error,
}: DiscardLayoutModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      isOpen={isOpen}
      isPending={isLoading}
      onClose={isLoading ? () => {} : onClose}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Discarding…') : t('Discard'),
          onClick: onConfirm,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3 text-center">
        <div className="flex justify-center mb-2">
          <div className="bg-red-100 w-15.5 h-15.5 text-red-600 border-red-50 border-[7px] rounded-full p-3">
            <AlertTriangle size={26} />
          </div>
        </div>

        <h2 className="text-lg font-semibold text-gray-800">{t('Discard Changes?')}</h2>

        <p className="text-gray-500">
          {t('Are you sure you want to discard changes for ')}
          <strong>{layoutName}</strong>?
        </p>

        <p className="text-sm text-gray-400">
          {t('This will restore the original layout and cannot be undone.')}
        </p>

        {error && <p className="text-sm font-medium text-red-600">{error}</p>}
      </div>
    </Modal>
  );
}
