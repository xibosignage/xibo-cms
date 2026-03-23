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

import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';

interface CopyDatasetRssModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  isLoading?: boolean;
}

export default function CopyDatasetRssModal({
  isOpen,
  onClose,
  onConfirm,
  isLoading,
}: CopyDatasetRssModalProps) {
  const { t } = useTranslation();

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Rss Row')}
      onClose={onClose}
      size="sm"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Copying…') : t('Yes, Duplicate'),
          onClick: onConfirm,
          disabled: isLoading,
        },
      ]}
    >
      <div className="px-8 pb-8 space-y-4">
        <p className="text-gray-600">
          {t('Are you sure you want to create an exact duplicate of this Rss?')}
        </p>
      </div>
    </Modal>
  );
}
