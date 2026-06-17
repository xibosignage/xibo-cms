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

import { Check, ClipboardCopy } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

import TextInput from '@/components/ui/forms/TextInput';

export default function ConfigurationTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
}: SettingsTabProps) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  const handleCopy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard not available
    }
  };

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Identity & authentication')}>
        {isVisible('LIBRARY_LOCATION') && (
          <TextInput
            name="LIBRARY_LOCATION"
            label={t('Library Location')}
            helpText={t('The fully qualified path to the CMS library location.')}
            value={formValues.LIBRARY_LOCATION ?? ''}
            onChange={(v) => updateField('LIBRARY_LOCATION', v)}
            disabled={!isEditable('LIBRARY_LOCATION')}
          />
        )}
        {isVisible('SERVER_KEY') && (
          <TextInput
            name="SERVER_KEY"
            label={t('CMS Secret Key')}
            helpText={t(
              'Mike · uniform · lima · Bravo · kilo · X-Ray · Tango · tango | Enter this key into each player to authenticate it with this CMS.',
            )}
            value={formValues.SERVER_KEY ?? ''}
            onChange={(v) => updateField('SERVER_KEY', v)}
            disabled={!isEditable('SERVER_KEY')}
            suffix={
              <button
                type="button"
                onClick={() => handleCopy(formValues.SERVER_KEY ?? '')}
                title={t('Copy to clipboard')}
                className="p-2 h-full rounded text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
              >
                {copied ? (
                  <Check size={16} className="text-green-500" />
                ) : (
                  <ClipboardCopy size={16} />
                )}
              </button>
            }
          />
        )}
      </SettingsSection>
    </div>
  );
}
