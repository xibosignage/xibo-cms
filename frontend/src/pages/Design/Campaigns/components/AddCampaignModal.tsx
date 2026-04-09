import { useEffect, useState, useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import SelectFolder from '@/components/ui/forms/SelectFolder';
import TagInput from '@/components/ui/forms/TagInput';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import { getCampaignSchema } from '@/schema/campaign';
import { createCampaign } from '@/services/campaignApi';
import type { Tag } from '@/types/tag';

interface AddCampaignModalProps {
  isOpen?: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

type CampaignDraft = {
  name: string;
  folderId: number | null;
  tags: Tag[];
  type: 'list' | 'campaign';
  cyclePlaybackEnabled: boolean;
  playCount: number | '';
  listPlayOrder: 'round' | 'block';
};

type CampaignFormErrors = Partial<Record<keyof CampaignDraft, string>>;

const DEFAULT_DRAFT: CampaignDraft = {
  name: '',
  folderId: null,
  tags: [],
  type: 'list',
  cyclePlaybackEnabled: false,
  playCount: '',
  listPlayOrder: 'round',
};

export default function AddCampaignModal({
  isOpen = true,
  onClose,
  onSuccess,
}: AddCampaignModalProps) {
  const { t } = useTranslation();
  const [isPending, startTransition] = useTransition();

  const [draft, setDraft] = useState<CampaignDraft>(DEFAULT_DRAFT);
  const [formErrors, setFormErrors] = useState<CampaignFormErrors>({});
  const [apiError, setApiError] = useState<string | undefined>();

  useEffect(() => {
    if (!isOpen) {
      setDraft(DEFAULT_DRAFT);
      setFormErrors({});
      setApiError(undefined);
    }
  }, [isOpen]);

  const handleSave = () => {
    startTransition(async () => {
      const schema = getCampaignSchema(t);
      const result = schema.safeParse(draft);

      if (!result.success) {
        setApiError(undefined);

        const fieldErrors = result.error.flatten().fieldErrors;
        const mappedErrors: CampaignFormErrors = {};

        Object.entries(fieldErrors).forEach(([key, value]) => {
          if (value?.[0]) {
            mappedErrors[key as keyof CampaignFormErrors] = value[0];
          }
        });

        setFormErrors(mappedErrors);
        return;
      }

      setFormErrors({});

      try {
        await createCampaign({
          name: draft.name,
          folderId: draft.folderId,
          tags: draft.tags,
          cyclePlaybackEnabled: draft.cyclePlaybackEnabled,
          playCount:
            draft.cyclePlaybackEnabled && draft.playCount !== ''
              ? Number(draft.playCount)
              : undefined,
          listPlayOrder: !draft.cyclePlaybackEnabled ? draft.listPlayOrder : undefined,
        });

        onSuccess();
        onClose();
      } catch (err: unknown) {
        console.error('Failed to create campaign:', err);

        const apiErr = err as { response?: { data?: { message?: string } } };

        if (apiErr.response?.data?.message) {
          setApiError(apiErr.response.data.message);
        } else if (err instanceof Error) {
          setApiError(err.message);
        } else {
          setApiError(t('An unexpected error occurred while creating the campaign.'));
        }
      }
    });
  };

  if (!isOpen) return null;

  return (
    <Modal
      title={t('Add Campaign')}
      isOpen={isOpen}
      onClose={onClose}
      isPending={isPending}
      error={apiError}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
          disabled: isPending,
        },
        {
          label: isPending ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isPending,
        },
      ]}
    >
      <div className="flex flex-col h-full overflow-y-hidden overflow-x-visible gap-3 px-4">
        <div className="flex flex-col gap-3 flex-1 min-h-0 p-4 overflow-y-auto">
          {/* Folder */}
          <div className="relative z-20">
            <SelectFolder
              selectedId={draft.folderId}
              onSelect={(folder) => {
                setDraft((prev) => ({
                  ...prev,
                  folderId: folder ? Number(folder.id) : null,
                }));
              }}
            />
          </div>

          {/* Type */}
          <SelectDropdown
            label={t('Type')}
            value={draft.type}
            options={[
              { label: 'Layout list', value: 'list' },
              { label: 'Ad Campaign', value: 'campaign' },
            ]}
            onSelect={(val) =>
              setDraft((prev) => ({
                ...prev,
                type: val as 'list' | 'campaign',
              }))
            }
          />

          {/* Name */}
          <TextInput
            name="name"
            label={t('Name')}
            value={draft.name}
            onChange={(val) => setDraft((prev) => ({ ...prev, name: val }))}
            error={formErrors.name}
          />

          {/* Tags */}
          <TagInput
            value={draft.tags}
            helpText={t('Tags separated by commas')}
            onChange={(tags) => setDraft((prev) => ({ ...prev, tags }))}
          />

          {/* Cycle Playback */}
          <Checkbox
            id="cyclePlayback"
            title={t('Enable cycle based playback')}
            className="items-center px-3 py-2.5"
            label={t(
              `When cycle based playback is enabled only 1 Layout from this Campaign will be played each time it is in a Schedule loop. The same Layout will be shown until the 'Play count' is achieved.`,
            )}
            checked={draft.cyclePlaybackEnabled}
            onChange={() =>
              setDraft((prev) => ({
                ...prev,
                cyclePlaybackEnabled: !prev.cyclePlaybackEnabled,
              }))
            }
          />

          {/* Conditional Fields */}
          {draft.cyclePlaybackEnabled ? (
            <TextInput
              name="playCount"
              label={t('Play count')}
              type="number"
              value={draft.playCount === '' ? '' : String(draft.playCount)}
              onChange={(val) =>
                setDraft((prev) => ({
                  ...prev,
                  playCount: val === '' ? '' : Number(val),
                }))
              }
              error={formErrors.playCount}
            />
          ) : (
            <SelectDropdown
              label={t('List play order')}
              value={draft.listPlayOrder}
              options={[
                { label: 'Round-robin', value: 'round' },
                { label: 'Block', value: 'block' },
              ]}
              onSelect={(val) =>
                setDraft((prev) => ({
                  ...prev,
                  listPlayOrder: val as 'round' | 'block',
                }))
              }
              error={formErrors.listPlayOrder}
            />
          )}
        </div>
      </div>
    </Modal>
  );
}
