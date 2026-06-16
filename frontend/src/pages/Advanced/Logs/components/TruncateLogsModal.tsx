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

import { isAxiosError } from 'axios';
import { Scissors } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Modal from '@/components/ui/modals/Modal';
import { truncateLogs } from '@/services/logApi';

interface TruncateLogsModalProps {
  onClose: () => void;
  onSuccess: () => void;
}

export default function TruncateLogsModal({ onClose, onSuccess }: TruncateLogsModalProps) {
  const { t } = useTranslation();
  const [isTruncating, setIsTruncating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleTruncate = async () => {
    if (isTruncating) return;

    setIsTruncating(true);
    setError(null);

    try {
      await truncateLogs();
      onSuccess();
      onClose();
    } catch (err) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to truncate logs. Please try again.');
      setError(message);
    } finally {
      setIsTruncating(false);
    }
  };

  return (
    <Modal
      isOpen
      onClose={onClose}
      size="sm"
      variant="confirmation"
      scrollable={false}
      isPending={isTruncating}
      error={error ?? undefined}
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary' },
        {
          label: isTruncating ? t('Truncating...') : t('Truncate'),
          onClick: handleTruncate,
          variant: 'primary',
          disabled: isTruncating,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-3">
        <div>
          <div className="flex justify-center mb-4">
            <div className="bg-red-100 w-15.5 h-15.5 text-red-800 border-red-50 border-[7px] rounded-full p-3">
              <Scissors size={26} />
            </div>
          </div>
          <h2 className="text-center text-lg font-semibold mb-2 text-red-800">
            {t('Truncate Log')}
          </h2>
        </div>
        <p className="text-center text-gray-500">
          {t('Are you sure you want to truncate all logs? This action cannot be undone.')}
        </p>
      </div>
    </Modal>
  );
}
