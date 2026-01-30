import { AlertTriangle, Trash2Icon } from 'lucide-react';
import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import Modal from '@/components/ui/modals/Modal';

interface DeleteMediaModalProps {
  isOpen: boolean;
  onClose: () => void;
  onDelete: () => void;
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
}: DeleteMediaModalProps) {
  const { t } = useTranslation();
  const [checkedToDelete, setCheckedToDelete] = useState({
    allLayouts: true,
    purgeList: false,
  });
  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      actions={[
        {
          label: t('No, Keep it'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Deleting…') : t('Yes, Delete'),
          onClick: onDelete,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-[62px] h-[62px] text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Trash2Icon size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {t('Delete File?')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {t(`Are you sure you want to delete `)} <strong>{fileName}?</strong>
        </p>
        <span className="center gap-0.5">
          <AlertTriangle size={12} />
          <p className="text-[12px] font-medium">
            {t(
              'This item will be removed from all published layouts and connected displays immediately.',
            )}
          </p>
        </span>
        <div className="p-2.5 flex flex-col gap-[22px]">
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
