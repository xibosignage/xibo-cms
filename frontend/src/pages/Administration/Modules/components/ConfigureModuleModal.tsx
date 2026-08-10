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

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import Modal from '@/components/ui/modals/Modal';
import type { UpdateModuleSettingsRequest } from '@/services/moduleApi';
import type { Module, ModuleSetting } from '@/types/module';

interface ConfigureModuleModalProps {
  isOpen?: boolean;
  module: Module;
  onClose: () => void;
  onSave: (id: string, settings: UpdateModuleSettingsRequest) => void;
  error?: string | null;
  isLoading?: boolean;
}

type DraftSettings = Record<string, string | number>;

function buildInitialDraft(module: Module): DraftSettings {
  const draft: DraftSettings = {};
  (module.settings ?? []).forEach((setting) => {
    draft[setting.id] = setting.value ?? '';
  });
  return draft;
}

function DynamicSettingField({
  setting,
  value,
  onChange,
}: {
  setting: ModuleSetting;
  value: string | number | undefined;
  onChange: (id: string, val: string | number) => void;
}) {
  if (setting.type === 'checkbox') {
    return (
      <div className="flex flex-col gap-1 mb-4">
        <div className="flex items-center gap-2">
          <Checkbox
            id={`setting-${setting.id}`}
            label={setting.title}
            checked={value === 1 || value === '1'}
            onChange={(e) => onChange(setting.id, e.target.checked ? 1 : 0)}
          />
        </div>
        {setting.helpText && <p className="text-xs text-gray-500 ml-6">{setting.helpText}</p>}
      </div>
    );
  }

  if ((setting.type === 'dropdown' || setting.type === 'select') && setting.options?.length) {
    const selectOptions: SelectOption[] = setting.options.map((opt) => ({
      value: opt.name,
      label: opt.title,
    }));

    return (
      <div className="mb-4">
        <SelectDropdown
          label={setting.title}
          value={String(value ?? '')}
          options={selectOptions}
          onSelect={(val) => onChange(setting.id, val)}
          helpText={setting.helpText}
        />
      </div>
    );
  }

  return (
    <div className="mb-4">
      <TextInput
        name={setting.id}
        label={setting.title}
        value={String(value ?? '')}
        type={setting.type === 'number' ? 'number' : 'text'}
        helpText={setting.helpText}
        onChange={(val) => onChange(setting.id, setting.type === 'number' ? Number(val) : val)}
      />
    </div>
  );
}

export default function ConfigureModuleModal({
  isOpen = true,
  module,
  onClose,
  onSave,
  error,
  isLoading,
}: ConfigureModuleModalProps) {
  const { t } = useTranslation();

  const [previewEnabled, setPreviewEnabled] = useState(module.previewEnabled === 1);
  const [enabled, setEnabled] = useState(module.enabled === 1);
  const [defaultDuration, setDefaultDuration] = useState(String(module.defaultDuration ?? 10));
  const [dynamicSettings, setDynamicSettings] = useState<DraftSettings>(buildInitialDraft(module));

  const handleDynamicChange = (id: string, val: string | number) => {
    setDynamicSettings((prev) => ({ ...prev, [id]: val }));
  };

  const handleSave = () => {
    const settings: UpdateModuleSettingsRequest = {
      enabled: enabled ? 1 : 0,
      previewEnabled: previewEnabled ? 1 : 0,
      defaultDuration: Number(defaultDuration) || 0,
      ...dynamicSettings,
    };
    onSave(module.moduleId, settings);
  };

  const previewDisabled = module.allowPreview === 0;

  return (
    <Modal
      isOpen={isOpen}
      isPending={isLoading}
      onClose={onClose}
      title={t('Edit Module')}
      actions={[
        {
          label: t('Cancel'),
          onClick: onClose,
          variant: 'secondary',
        },
        {
          label: isLoading ? t('Saving…') : t('Save'),
          onClick: handleSave,
          disabled: isLoading,
        },
      ]}
      size="md"
    >
      <div className="flex flex-col p-5 gap-4">
        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <Checkbox
              id="previewEnabled"
              label={t('Preview Enabled?')}
              checked={previewEnabled}
              disabled={previewDisabled}
              onChange={(e) => setPreviewEnabled(e.target.checked)}
            />
          </div>
          <p className="text-xs text-gray-500 ml-6">
            {t('When Preview is Enabled users will be able to see a preview in the layout editor.')}
          </p>
        </div>

        <div className="flex flex-col gap-1">
          <div className="flex items-center gap-2">
            <Checkbox
              id="enabled"
              label={t('Enabled?')}
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
            />
          </div>
          <p className="text-xs text-gray-500 ml-6">
            {t('When Enabled users will be able to add media using this module.')}
          </p>
        </div>

        <TextInput
          name="defaultDuration"
          label={t('Default Duration')}
          value={defaultDuration}
          type="number"
          helpText={t(
            'The default duration for Widgets of this Module when the user has elected to not set a specific duration.',
          )}
          onChange={setDefaultDuration}
        />

        {(module.settings ?? []).map((setting) => (
          <DynamicSettingField
            key={setting.id}
            setting={setting}
            value={dynamicSettings[setting.id]}
            onChange={handleDynamicChange}
          />
        ))}

        {error && (
          <div className="mt-2">
            <p className="text-sm font-medium text-red-600">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  );
}
