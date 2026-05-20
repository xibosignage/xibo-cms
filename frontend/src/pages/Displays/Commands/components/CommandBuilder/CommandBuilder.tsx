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

import FreeTextFields from './FreeTextFields';
import HttpFields from './HttpFields';
import IntentFields from './IntentFields';
import PhilipsAndroidFields from './PhilipsAndroidFields';
import Rs232Fields from './Rs232Fields';
import type { CommandType, ParsedCommand } from './commandStringUtils';
import {
  buildCommandString,
  getDefaultParsedCommand,
  parseCommandString,
} from './commandStringUtils';

import Checkbox from '@/components/ui/forms/Checkbox';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';

const getCommandTypeOptions = (t: ReturnType<typeof useTranslation>['t']) => [
  { value: 'freetext', label: t('Free Text') },
  { value: 'tpv_led', label: t('Philips Android') },
  { value: 'rs232', label: t('RS232') },
  { value: 'intent', label: t('Android Intent') },
  { value: 'http', label: t('HTTP') },
];

interface CommandBuilderProps {
  value: string;
  onChange: (value: string) => void;
  error?: string;
}

export default function CommandBuilder({ value, onChange, error }: CommandBuilderProps) {
  const { t } = useTranslation();
  const [parsed, setParsed] = useState<ParsedCommand>(() => parseCommandString(value));
  const [showPreview, setShowPreview] = useState(false);

  // Sync parsed state when external value changes (e.g. on edit mode load)
  useEffect(() => {
    setParsed(parseCommandString(value));
  }, [value]);

  const emitChange = (updated: ParsedCommand) => {
    setParsed(updated);
    onChange(buildCommandString(updated));
  };

  const handleTypeChange = (newType: string) => {
    const type = (newType || 'freetext') as CommandType;
    const newParsed = getDefaultParsedCommand(type);
    emitChange(newParsed);
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-gray-200 space-y-2.5 bg-slate-50 p-4">
        <div className="flex gap-x-5 justify-between items-end">
          <SelectDropdown
            label={t('Command Type')}
            value={parsed.type}
            options={getCommandTypeOptions(t)}
            onSelect={handleTypeChange}
            className="flex-1"
          />
          <Checkbox
            id="show-preview"
            title={t('Preview')}
            checked={showPreview}
            onChange={() => setShowPreview((prev) => !prev)}
            classNameInput="mr-2"
            className="mb-3"
          />
        </div>
        {parsed.type === 'freetext' && (
          <FreeTextFields
            value={parsed.freetext || ''}
            onChange={(val) => emitChange({ ...parsed, freetext: val })}
          />
        )}

        {parsed.type === 'tpv_led' && (
          <PhilipsAndroidFields
            value={parsed.tpvLedColor || 'off'}
            onChange={(val) => emitChange({ ...parsed, tpvLedColor: val })}
          />
        )}

        {parsed.type === 'rs232' && parsed.rs232 && (
          <Rs232Fields
            config={parsed.rs232.cs}
            command={parsed.rs232.command}
            onConfigChange={(cs) =>
              emitChange({
                ...parsed,
                rs232: { ...parsed.rs232!, cs, command: parsed.rs232!.command },
              })
            }
            onCommandChange={(cmd) =>
              emitChange({ ...parsed, rs232: { ...parsed.rs232!, command: cmd } })
            }
          />
        )}

        {parsed.type === 'intent' && parsed.intent && (
          <IntentFields
            intentType={parsed.intent.type}
            name={parsed.intent.name}
            extras={parsed.intent.extras}
            onTypeChange={(type) => emitChange({ ...parsed, intent: { ...parsed.intent!, type } })}
            onNameChange={(name) => emitChange({ ...parsed, intent: { ...parsed.intent!, name } })}
            onExtrasChange={(extras) =>
              emitChange({ ...parsed, intent: { ...parsed.intent!, extras } })
            }
          />
        )}

        {parsed.type === 'http' && parsed.http && (
          <HttpFields
            url={parsed.http.url}
            contenttype={parsed.http.contenttype}
            requestOptions={parsed.http.requestOptions}
            onUrlChange={(url) => emitChange({ ...parsed, http: { ...parsed.http!, url } })}
            onContentTypeChange={(contenttype) =>
              emitChange({ ...parsed, http: { ...parsed.http!, contenttype } })
            }
            onRequestOptionsChange={(requestOptions) =>
              emitChange({ ...parsed, http: { ...parsed.http!, requestOptions } })
            }
          />
        )}
        {showPreview && (
          <div className="rounded-lg bg-gray-800 p-3">
            <code className="text-sm text-xibo-blue-400 break-all">
              {buildCommandString(parsed) || '...'}
            </code>
          </div>
        )}
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  );
}
