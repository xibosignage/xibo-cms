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
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { notify } from '@/components/ui/Notification';
import Modal from '@/components/ui/modals/Modal';
import { tidyLibrary } from '@/services/dashboardApi';

interface TidyLibraryModalProps {
  onClose: () => void;
  onSuccess: () => void;
  unusedCount: number;
}

export default function TidyLibraryModal({
  onClose,
  onSuccess,
  unusedCount,
}: TidyLibraryModalProps) {
  const { t } = useTranslation();
  const [isPending, setIsPending] = useState(false);

  const handleConfirm = async () => {
    setIsPending(true);
    try {
      const result = await tidyLibrary();
      notify.success(
        t('Library Tidy Complete. {{count}} file(s) deleted.', { count: result.countDeleted }),
      );
      onSuccess();
    } catch {
      notify.error(t('Failed to tidy the library.'));
    } finally {
      setIsPending(false);
    }
  };

  return (
    <Modal
      onClose={onClose}
      isPending={isPending}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isPending ? t('Tidying...') : t('Yes, Tidy'),
          onClick: handleConfirm,
          disabled: isPending || unusedCount === 0,
        },
      ]}
      size="sm"
    >
      <div className="flex flex-col items-center gap-3 p-5">
        <div className="flex h-15.5 w-15.5 items-center justify-center rounded-full border-[7px] border-red-50 bg-red-100 text-red-800">
          <Trash2Icon size={26} />
        </div>
        <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
          {t('Tidy Library?')}
        </h2>
        <p className="text-center text-gray-500">
          {t('Tidying your Library will delete any media that is not currently in use.')}
        </p>
        {unusedCount === 0 ? (
          <div className="w-full rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-center text-sm text-yellow-700">
            {t('You do not have any library files that are not in use.')}
          </div>
        ) : (
          <p className="text-center text-gray-500">
            {t('There are ')} <strong>{unusedCount}</strong> {t('file(s) that can be removed.')}
          </p>
        )}
      </div>
    </Modal>
  );
}
