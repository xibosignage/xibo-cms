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

import { UploadCloud } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { PublishValue } from '../forms/PublishDateSelect';
import PublishDateSelect from '../forms/PublishDateSelect';

import Modal from '@/components/ui/modals/Modal';

interface PublishModalProps {
  isOpen?: boolean;
  onClose: () => void;
  fileName?: string;
  error?: string | null;
  isLoading?: boolean;
  titleText: string;
  onPublish: (id: number, value: PublishValue) => void;
  layoutId?: number;
}

export default function PublishModal({
  isOpen = true,
  onClose,
  fileName,
  isLoading,
  error,
  titleText,
  onPublish,
  layoutId,
}: PublishModalProps) {
  const { t } = useTranslation();

  const [publishValue, setPublishValue] = useState<PublishValue>({
    type: 'now',
  });

  const handlePublish = () => {
    if (!layoutId) return;

    if (publishValue.type === 'scheduled' && !publishValue.date) {
      return;
    }

    onPublish(layoutId, publishValue);
  };

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
          label: isLoading ? t('Publishing…') : t('Publish'),
          onClick: handlePublish,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center">
          <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
            <UploadCloud size={26} />
          </div>
        </div>

        <h2 className="text-center text-lg font-semibold text-gray-800">{titleText}</h2>

        <p className="text-center text-gray-500">
          {t('Are you sure you want to publish ')}"<strong>{fileName}</strong>?"
          {t(' If it is already in use the update will automatically get pushed.')}
        </p>

        <PublishDateSelect value={publishValue} onSelect={setPublishValue} />

        {error && (
          <div className="text-center">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
