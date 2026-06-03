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

import { PauseCircle, PlayCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';
import type { ReportSchedule } from '@/types/reportSchedule';

interface ToggleActiveModalProps {
  isOpen?: boolean;
  schedule: ReportSchedule | null;
  onClose: () => void;
  onConfirm: () => void;
  error?: string | null;
  isLoading?: boolean;
}

export default function ToggleActiveModal({
  isOpen = true,
  schedule,
  onClose,
  onConfirm,
  isLoading,
  error,
}: ToggleActiveModalProps) {
  const { t } = useTranslation();

  if (!schedule) {
    return null;
  }

  const isPausing = schedule.isActive === 1;
  const Icon = isPausing ? PauseCircle : PlayCircle;
  const title = isPausing ? t('Pause Schedule?') : t('Resume Schedule?');
  const confirmLabel = isPausing
    ? isLoading
      ? t('Pausing…')
      : t('Yes, Pause')
    : isLoading
      ? t('Resuming…')
      : t('Yes, Resume');
  const message = isPausing
    ? t('Are you sure you want to pause ')
    : t('Are you sure you want to resume ');

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
          label: confirmLabel,
          onClick: onConfirm,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-4">
          <div className="bg-yellow-100 w-15.5 h-15.5 text-yellow-800 border-yellow-50 border-[7px] rounded-full p-3">
            <Icon size={26} />
          </div>
        </div>

        <h2 className="text-center text-lg font-semibold mb-2">{title}</h2>

        <p className="text-center text-gray-500">
          {message}"<strong>{schedule.name}</strong>"?
        </p>
      </div>
    </Modal>
  );
}
