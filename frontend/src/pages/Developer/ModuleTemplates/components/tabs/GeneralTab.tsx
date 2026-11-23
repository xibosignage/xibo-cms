/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { AlertTriangle } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';

import { getShowInOptions } from '../../ModuleTemplatesConfig';

import type { SelectOption } from '@/components/ui/forms/SelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';
import type { DataType, ModuleTemplateEditFormValues } from '@/types/moduleTemplates';

interface GeneralTabProps {
  formValues: ModuleTemplateEditFormValues;
  dataTypes: DataType[];
  updateField: <K extends keyof ModuleTemplateEditFormValues>(
    key: K,
    value: ModuleTemplateEditFormValues[K],
  ) => void;
  isInvalidateWidget: boolean;
  onIsInvalidateWidgetChange: (v: boolean) => void;
}

export default function GeneralTab({
  formValues,
  dataTypes,
  updateField,
  isInvalidateWidget,
  onIsInvalidateWidgetChange,
}: GeneralTabProps) {
  const { t } = useTranslation();

  const dataTypeOptions: SelectOption[] = dataTypes.map((dt) => ({
    label: dt.name,
    value: dt.id,
  }));

  return (
    <div className="flex flex-col gap-6">
      {/* Options */}
      <div className="bg-white rounded-lg p-5 flex flex-col gap-4">
        <p className="font-bold text-lg">{t('Template Identity')}</p>
        <div className="flex items-start gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-800">
          <AlertTriangle size={16} className="shrink-0 mt-0.5" />
          <span>
            <Trans
              i18nKey="Changing the <strong>ID</strong> or <strong>DataType</strong> will break any existing Widgets which use this template."
              components={{ strong: <strong /> }}
            />
          </span>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="flex flex-col gap-4">
            <TextInput
              name="templateId"
              label={t('ID')}
              helpText={t('A unique ID for the module template')}
              value={formValues.templateId}
              onChange={(v) => updateField('templateId', v)}
            />
            <SelectDropdown
              label={t('Data Type')}
              value={formValues.dataType}
              helpText={t('Which data type does this template need?')}
              options={dataTypeOptions}
              translateLabels={false}
              placeholder={t('Select data type...')}
              onSelect={(v: string) => updateField('dataType', v)}
            />
          </div>
          <div className="flex flex-col gap-4">
            <TextInput
              name="title"
              label={t('Title')}
              helpText={t('A title for the module template')}
              value={formValues.title}
              onChange={(v) => updateField('title', v)}
            />
            <SelectDropdown
              label={t('Show In')}
              helpText={t('Which Editor should this template be available in?')}
              value={formValues.showIn}
              options={getShowInOptions(t)}
              onSelect={(v: string) => updateField('showIn', v)}
            />
          </div>
        </div>
      </div>

      {/* Status and Invalidate */}
      <div className="bg-white rounded-lg p-5 flex flex-col gap-4">
        <p className="font-bold text-lg">{t('Status')}</p>
        <SwitchRow
          title={t('Enable')}
          description={t('Is this template enabled?')}
          checked={formValues.enabled}
          onChange={(v) => updateField('enabled', v)}
        />
        <SwitchRow
          title={t('Invalidate any widgets using this template')}
          checked={isInvalidateWidget}
          onChange={onIsInvalidateWidgetChange}
        />
      </div>
    </div>
  );
}
