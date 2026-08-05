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

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import BandwidthInput from '@/components/ui/forms/BandwidthInput';
import Modal from '@/components/ui/modals/Modal';
import { getBandwidthLimitSchema } from '@/schema/display';

interface SetBandwidthModalProps {
  displayCount: number;
  onClose: () => void;
  onConfirm: (bandwidthLimitKb: number) => void;
  isActionPending: boolean;
  actionError: string | null;
}

export default function SetBandwidthModal({
  displayCount,
  onClose,
  onConfirm,
  isActionPending,
  actionError,
}: SetBandwidthModalProps) {
  const { t } = useTranslation();
  const [bandwidthKb, setBandwidthKb] = useState<number | null>(null);
  const [fieldError, setFieldError] = useState<string | undefined>();

  const handleChange = (kb: number | null) => {
    setBandwidthKb(kb);
    setFieldError(undefined);
  };

  const handleSave = () => {
    const result = getBandwidthLimitSchema(t).safeParse(bandwidthKb ?? 0);
    if (!result.success) {
      setFieldError(result.error.issues[0]?.message);
      return;
    }
    setFieldError(undefined);
    onConfirm(result.data);
  };

  return (
    <Modal
      title={t('Bandwidth Limit')}
      isOpen
      isPending={isActionPending}
      onClose={onClose}
      error={actionError ?? undefined}
      size="md"
      actions={[
        { label: t('Cancel'), onClick: onClose, variant: 'secondary', disabled: isActionPending },
        {
          label: isActionPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isActionPending || bandwidthKb === null,
        },
      ]}
    >
      <div className="flex flex-col p-5 gap-4">
        <p className="text-sm text-gray-500">
          {t('Change Bandwidth Limit to all the selected displays.', { count: displayCount })}
        </p>
        <BandwidthInput
          valueKb={bandwidthKb}
          onChange={handleChange}
          label={t('Bandwidth limit')}
          helpText={t('The bandwidth limit that should be applied. Enter 0 for no limit.')}
          error={fieldError}
        />
      </div>
    </Modal>
  );
}
