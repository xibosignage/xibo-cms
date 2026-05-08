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

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';

interface DeleteSpotModalProps {
  onClose: () => void;
  onConfirm: (deleteMedia: boolean) => void;
  widgetName?: string;
  isRemoveAll?: boolean;
  itemCount?: number;
  isLoading?: boolean;
  hasLibraryMedia?: boolean;
  isDynamic?: boolean;
}

export default function DeleteSpotModal({
  onClose,
  onConfirm,
  widgetName,
  isRemoveAll = false,
  itemCount = 1,
  isLoading = false,
  hasLibraryMedia = false,
  isDynamic = false,
}: DeleteSpotModalProps) {
  const { t } = useTranslation();
  const [deleteMedia, setDeleteMedia] = useState(false);

  return (
    <Modal
      onClose={onClose}
      isPending={isLoading}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Removing...') : t('Yes, Remove'),
          onClick: () => onConfirm(deleteMedia),
          disabled: isLoading || isDynamic,
        },
      ]}
      size="sm"
    >
      <div className="flex flex-col items-center gap-3 p-5">
        <div className="flex h-15.5 w-15.5 items-center justify-center rounded-full border-[7px] border-red-50 bg-red-100 text-red-800">
          <Trash2Icon size={26} />
        </div>
        <h2 className="text-lg font-semibold text-red-800">
          {isRemoveAll ? t('Remove All Widgets?') : t('Remove Widget?')}
        </h2>
        <p className="text-center text-gray-500">
          {isRemoveAll ? (
            <>
              {t('Are you sure you want to remove all')} <strong>{itemCount}</strong>{' '}
              {t('widgets from this playlist?')}
            </>
          ) : (
            <>
              {t('Are you sure you want to remove ')}&quot;<strong>{widgetName}</strong>&quot;
              {t(' from this playlist?')}
            </>
          )}
        </p>

        {hasLibraryMedia && (
          <div className="w-full pt-2">
            <Checkbox
              id="deleteMedia"
              title={t('Also delete from the Library?')}
              label={t(
                'This widget is linked to Media in the Library. Check this option to also delete that Media.',
              )}
              checked={deleteMedia}
              onChange={() => setDeleteMedia((prev) => !prev)}
            />
          </div>
        )}

        {isDynamic && (
          <div className="w-full rounded-lg border border-red-200 bg-red-50 p-3 text-center text-sm text-red-600">
            {t('This Playlist is dynamically managed so cannot accept manual assignments.')}
          </div>
        )}
      </div>
    </Modal>
  );
}
