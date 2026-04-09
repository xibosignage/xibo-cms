import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { Campaign } from '@/types/campaign';
import { incrementName } from '@/utils/stringUtils';

interface CopyCampaignModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onConfirm: (newName: string) => void;
  campaign: Campaign | null;
  isLoading?: boolean;
  existingNames: string[];
}

type FormErrors = {
  name?: string;
};

export default function CopyCampaignModal({
  isOpen = true,
  onClose,
  onConfirm,
  campaign,
  isLoading,
  existingNames,
}: CopyCampaignModalProps) {
  const { t } = useTranslation();

  const [newName, setNewName] = useState('');
  const [formErrors, setFormErrors] = useState<FormErrors>({});

  useEffect(() => {
    if (campaign && isOpen) {
      setNewName(incrementName(campaign.campaign)); // reuse your helper
    }

    setFormErrors({});
  }, [campaign, isOpen]);

  const handleSave = () => {
    const trimmed = newName.trim();

    if (!trimmed) {
      setFormErrors({ name: t('Name is required') });
      return;
    }

    if (trimmed.length > 100) {
      setFormErrors({ name: t('Name must be less than 100 characters') });
      return;
    }

    const nameExists = existingNames.some((name) => name.toLowerCase() === trimmed.toLowerCase());

    if (nameExists) {
      setFormErrors({
        name: t('A campaign with this name already exists'),
      });
      return;
    }

    setFormErrors({});
    onConfirm(trimmed);
  };

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      title={t('Copy Campaign')}
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
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isLoading,
        },
      ]}
    >
      <div className="px-8 pb-8">
        <TextInput
          name="newName"
          value={newName}
          label={t('New name')}
          helpText={t('The name for the copy (1 - 100 characters)')}
          error={formErrors.name}
          onChange={(val) => {
            setNewName(val);
            if (formErrors.name) {
              setFormErrors((prev) => ({ ...prev, name: undefined }));
            }
          }}
        />
      </div>
    </Modal>
  );
}
