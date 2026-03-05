import { Info, Trash2Icon } from 'lucide-react';
import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';

interface DeleteMediaModalProps {
  isOpen: boolean;
  onClose: () => void;
  onDelete: (options: { allLayouts: boolean; purgeList: boolean }) => void;
  itemCount: number;
  fileName?: string;
  error?: string | null;
  isLoading?: boolean;
}

export default function DeleteMediaModal({
  isOpen,
  onClose,
  onDelete,
  fileName,
  isLoading,
  itemCount,
  error,
}: DeleteMediaModalProps) {
  const { t } = useTranslation();
  const [checkedToDelete, setCheckedToDelete] = useState({
    allLayouts: false,
    purgeList: false,
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
            {itemCount === 1 ? t('Delete File?') : t('Delete Files?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {itemCount === 1 ? (
            <>
              {t('Are you sure you want to delete ')}"<strong>{fileName}</strong>"?
            </>
          ) : (
            <>
              {t('Are you sure you want to delete ')}
              <strong>{itemCount}</strong> {t('files')}?
            </>
          )}
        </p>

        <span className="center gap-px rounded-md bg-gray-50 p-1.5">
          <Info size={12} />
          <p className="text-[12px] font-medium px-1">
            {itemCount === 1
              ? t(
                  'This item will be removed from all published layouts and connected displays immediately.',
                )
              : t(
                  'These items will be removed from all published layouts and connected displays immediately.',
                )}
          </p>
        </span>
        {error && (
          <div className="mt-2 text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
        <div className="p-2.5 flex flex-col gap-5.5">
          <Checkbox
            id="allLayouts"
            className="items-center"
            title={t('All Layouts')}
            label={t(`Force delete from any existing layouts, assignments, etc.`)}
            checked={checkedToDelete.allLayouts}
            classNameLabel="text-xs"
            onChange={() =>
              setCheckedToDelete((prev) => ({ ...prev, allLayouts: !prev.allLayouts }))
            }
          />
          <Checkbox
            id="purgeList"
            className="items-center"
            title={t('Add to Purge list')}
            label={t(
              `When set, each Display that has this Media in its local storage, will be notified to remove it.`,
            )}
            checked={checkedToDelete.purgeList}
            classNameLabel="text-xs"
            onChange={() => setCheckedToDelete((prev) => ({ ...prev, purgeList: !prev.purgeList }))}
          />
        </div>
      </div>
    </Modal>
  );
}
