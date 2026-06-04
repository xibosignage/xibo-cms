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

import { Info, RefreshCw } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';
import type { ReportSchedule } from '@/types/reportSchedule';

interface ResetReportScheduleModalProps {
  isOpen?: boolean;
  schedule: ReportSchedule | null;
  onClose: () => void;
  onConfirm: () => void;
  error?: string | null;
  isLoading?: boolean;
}

export default function ResetReportScheduleModal({
  isOpen = true,
  schedule,
  onClose,
  onConfirm,
  isLoading,
  error,
}: ResetReportScheduleModalProps) {
  const { t } = useTranslation();

  if (!schedule) {
    return null;
  }

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
          label: isLoading ? t('Resetting…') : t('Yes, Reset'),
          onClick: onConfirm,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-3">
        <div className="flex justify-center mb-4">
          <div className="bg-blue-100 w-15.5 h-15.5 text-blue-800 border-blue-50 border-[7px] rounded-full p-3">
            <RefreshCw size={26} />
          </div>
        </div>

        <h2 className="text-center text-lg font-semibold mb-2">{t('Reset to Previous Run?')}</h2>

        <p className="text-center text-gray-500">
          <Trans
            i18nKey='Are you sure you want to reset "<strong>{{name}}</strong>" to its previous run state?'
            values={{ name: schedule.name }}
            components={{ strong: <strong /> }}
          />
        </p>

        <span className="flex gap-px rounded-md justify-center bg-gray-50 p-1.5">
          <Info size={12} />
          <span className="text-xs px-1 font-medium leading-3.5">
            {t('This action cannot be undone.')}
          </span>
        </span>
      </div>
    </Modal>
  );
}
