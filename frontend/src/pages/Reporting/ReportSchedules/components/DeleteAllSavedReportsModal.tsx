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

import { Trash2Icon } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface DeleteAllSavedReportsModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: () => void;
  scheduleName?: string;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeleteAllSavedReportsModal({
  isOpen = true,
  onClose,
  onConfirm,
  scheduleName,
  isLoading,
  error,
}: DeleteAllSavedReportsModalProps) {
  const { t } = useTranslation();

  return (
    <Modal
      variant="confirmation"
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      error={error ?? undefined}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Deleting…') : t('Yes, Delete All'),
          onClick: () => onConfirm(),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-4">
          <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
            <Trash2Icon size={26} />
          </div>
        </div>

        <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
          {t('Delete All Saved Reports?')}
        </h2>

        <p className="text-center text-gray-500">
          {scheduleName ? (
            <Trans
              i18nKey='Are you sure you want to delete all saved reports for "<strong>{{name}}</strong>"? This cannot be undone.'
              values={{ name: scheduleName }}
              components={{ strong: <strong /> }}
            />
          ) : (
            t('Are you sure you want to delete all saved reports? This cannot be undone.')
          )}
        </p>
      </div>
    </Modal>
  );
}
