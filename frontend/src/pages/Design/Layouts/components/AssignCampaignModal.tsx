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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import Modal from '@/components/ui/modals/Modal';
import type { Campaign } from '@/services/campaignApi';
import { fetchCampaigns } from '@/services/campaignApi';

interface AssignCampaignModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (campaignId: number) => void;
  isLoading?: boolean;
}

export default function AssignCampaignModal({
  isOpen = true,
  onClose,
  onConfirm,
  isLoading,
}: AssignCampaignModalProps) {
  const { t } = useTranslation();
  const [selectedCampaign, setSelectedCampaign] = useState<string>('');
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [error, setError] = useState<string | undefined>();

  const [isFetching, setIsFetching] = useState(false);

  useEffect(() => {
    if (!isOpen) return;

    setSelectedCampaign('');
    setError(undefined);
    setCampaigns([]);
    setIsFetching(true);

    fetchCampaigns()
      .then((res) => setCampaigns(res.rows))
      .catch((err) => {
        console.error(err);
        setError(t('Failed to load campaigns'));
      })
      .finally(() => setIsFetching(false));
  }, [isOpen, t]);

  const campaignOptions: SelectOption[] = campaigns.map((c) => ({
    label: c.campaign,
    value: String(c.campaignId),
  }));

  const handleSave = () => {
    if (!selectedCampaign) {
      setError(t('Please select a campaign'));
      return;
    }

    setError(undefined);
    onConfirm(Number(selectedCampaign));
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      isOpen={isOpen}
      title={t('Assign Layout to Campaign')}
      onClose={onClose}
      size="md"
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isLoading,
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isLoading,
        },
      ]}
    >
      <div className="px-8 pb-8 space-y-4">
        <SelectDropdown
          label="Campaign"
          placeholder={
            isFetching
              ? t('Loading campaigns...')
              : campaignOptions.length
                ? t('Select campaign')
                : t('No campaigns available')
          }
          value={selectedCampaign}
          options={campaignOptions}
          onSelect={(value) => {
            setSelectedCampaign(value);
            setError(undefined);
          }}
          searchable={!isFetching}
          error={error}
          isLoading={isFetching}
        />
      </div>
    </Modal>
  );
}
