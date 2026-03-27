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

interface DeleteDatasetModalProps {
  isOpen: boolean;
  onClose: () => void;
  onDelete: (options: { deleteData: boolean }) => void;
  itemCount: number;
  datasetName?: string;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeleteDatasetModal({
  isOpen,
  onClose,
  onDelete,
  datasetName,
  isLoading,
  itemCount,
  error,
}: DeleteDatasetModalProps) {
  const { t } = useTranslation();
  const [checkedToDelete, setCheckedToDelete] = useState({
    deleteData: false,
  });

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
          label: isLoading ? t('Deleting…') : t('Yes, Delete'),
          onClick: () => onDelete(checkedToDelete),
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2Icon size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {itemCount === 1 ? t('Delete Dataset?') : t('Delete Datasets?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {itemCount === 1 ? (
            <>
              {t('Are you sure you want to delete ')}"<strong>{datasetName}</strong>?"
            </>
          ) : (
            <>
              {t('Are you sure you want to delete ')}
              <strong>{itemCount}</strong> {t('datasets')}?
            </>
          )}
        </p>

        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}

        <div className="p-2.5 flex flex-col gap-5.5">
          <Checkbox
            id="deleteData"
            className="items-center"
            title={t('Delete any associated data?')}
            label={t(
              `Please tick the box if you would like to delete all the Data contained in this DataSet.`,
            )}
            checked={checkedToDelete.deleteData}
            classNameLabel="text-xs"
            onChange={() =>
              setCheckedToDelete((prev) => ({ ...prev, deleteData: !prev.deleteData }))
            }
          />
        </div>
      </div>
    </Modal>
  );
}
