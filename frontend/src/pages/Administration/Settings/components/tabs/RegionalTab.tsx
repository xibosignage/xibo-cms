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

import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  useHover,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { Info } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { DATE_FORMAT_ROWS, type SettingsTabProps } from '../../SettingsConfig';
import SettingsSection from '../SettingsSection';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import SwitchRow from '@/pages/Administration/Users/components/SwitchRow';

function DateFormatInfoPopover() {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'right-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(8), flip(), shift({ padding: 8 })],
  });

  const hover = useHover(context, { delay: { open: 0, close: 300 } });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover, dismiss]);

  return (
    <>
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        type="button"
        className="inline-flex items-center text-gray-400 hover:text-blue-500 transition-colors"
        aria-label={t('Date format reference')}
      >
        <Info size={14} />
      </button>
      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white shadow-xl rounded-lg border border-gray-100 max-w-md max-h-96 overflow-y-auto"
          >
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-gray-50">
                <tr>
                  <th className="px-3 py-2 text-left font-semibold text-gray-600">{t('Format')}</th>
                  <th className="px-3 py-2 text-left font-semibold text-gray-600">
                    {t('Description')}
                  </th>
                  <th className="px-3 py-2 text-left font-semibold text-gray-600">
                    {t('Example')}
                  </th>
                </tr>
              </thead>
              <tbody>
                {DATE_FORMAT_ROWS.map((row, i) =>
                  'category' in row ? (
                    <tr key={i} className="bg-gray-50">
                      <td colSpan={3} className="px-3 py-1.5 font-semibold text-gray-700">
                        {t(row.category)}
                      </td>
                    </tr>
                  ) : (
                    <tr key={i} className="border-t border-gray-50">
                      <td className="px-3 py-1.5 font-mono font-semibold text-blue-600">
                        {row.char}
                      </td>
                      <td className="px-3 py-1.5 text-gray-600">{t(row.desc)}</td>
                      <td className="px-3 py-1.5 text-gray-500">{t(row.example)}</td>
                    </tr>
                  ),
                )}
              </tbody>
            </table>
          </div>
        )}
      </FloatingPortal>
    </>
  );
}

export default function RegionalTab({
  formValues,
  updateField,
  isVisible,
  isEditable,
  options,
}: SettingsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <SettingsSection title={t('Regional')}>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('DEFAULT_LANGUAGE') && (
            <SelectDropdown
              label={t('Default Language')}
              helpText={t('Applied globally; users can override in their profile.')}
              value={formValues.DEFAULT_LANGUAGE ?? ''}
              options={options.languages.map((l) => ({
                value: l.id,
                label: l.value,
              }))}
              onSelect={(v) => updateField('DEFAULT_LANGUAGE', v)}
              searchable
              className="w-full"
            />
          )}
          {isVisible('defaultTimezone') && (
            <SelectDropdown
              label={t('Timezone')}
              helpText={t('Set the default timezone for the application.')}
              value={formValues.defaultTimezone ?? ''}
              options={options.timeZones.map((tz) => ({
                value: tz.id,
                label: tz.value,
              }))}
              onSelect={(v) => updateField('defaultTimezone', v)}
              searchable
              className="w-full"
            />
          )}
        </div>
        <div className="flex items-start justify-between space-x-4">
          {isVisible('DATE_FORMAT') && (
            <div className="flex-1">
              <TextInput
                name="DATE_FORMAT"
                label={t('Date Format')}
                labelExtra={<DateFormatInfoPopover />}
                helpText={t('The Date Format to use when displaying dates in the CMS.')}
                value={formValues.DATE_FORMAT ?? ''}
                onChange={(v) => updateField('DATE_FORMAT', v)}
                disabled={!isEditable('DATE_FORMAT')}
                className="flex-1"
              />
            </div>
          )}
          {isVisible('CALENDAR_TYPE') && (
            <SelectDropdown
              label={t('Calendar Type')}
              value={formValues.CALENDAR_TYPE ?? ''}
              options={[
                { value: 'Gregorian', label: t('Gregorian') },
                { value: 'Jalali', label: t('Jalali') },
              ]}
              onSelect={(v) => updateField('CALENDAR_TYPE', v)}
              className="flex-1"
            />
          )}
        </div>
        {isVisible('DETECT_LANGUAGE') && (
          <SwitchRow
            title={t('Auto-detect browser language')}
            description={t(
              "Use each visitor's browser locale instead of the default language above.",
            )}
            checked={formValues.DETECT_LANGUAGE === '1'}
            onChange={(v) => updateField('DETECT_LANGUAGE', v ? '1' : '0')}
            disabled={!isEditable('DETECT_LANGUAGE')}
          />
        )}
      </SettingsSection>
    </div>
  );
}
