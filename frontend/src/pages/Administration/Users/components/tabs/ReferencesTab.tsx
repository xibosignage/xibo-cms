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

import { useTranslation } from 'react-i18next';

import type { ReferencesTabProps } from '../../config/addEditUserTypes';

import TextInput from '@/components/ui/forms/TextInput';

export default function ReferencesTab({ draft, setDraft }: ReferencesTabProps) {
  const { t } = useTranslation();

  return (
    <>
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold text-gray-800">{t('Add Reference Fields')}</span>
        <p className="text-xs text-gray-500">
          {t(
            'These optional fields can be used to store additional custom information about the user, such as employee ID, department code, or other organization-specific data.',
          )}
        </p>
      </div>
      <div className="flex flex-col gap-y-3">
        <TextInput
          name="phone"
          label={t('Phone Number')}
          placeholder={t('Enter phone number')}
          value={draft.phone}
          onChange={(val) => setDraft((prev) => ({ ...prev, phone: val }))}
          optional
        />

        <TextInput
          name="ref1"
          label={t('Reference 1')}
          placeholder={t('Enter reference')}
          value={draft.ref1}
          onChange={(val) => setDraft((prev) => ({ ...prev, ref1: val }))}
          optional
        />

        <TextInput
          name="ref2"
          label={t('Reference 2')}
          placeholder={t('Enter reference')}
          value={draft.ref2}
          onChange={(val) => setDraft((prev) => ({ ...prev, ref2: val }))}
          optional
        />

        <TextInput
          name="ref3"
          label={t('Reference 3')}
          placeholder={t('Enter reference')}
          value={draft.ref3}
          onChange={(val) => setDraft((prev) => ({ ...prev, ref3: val }))}
          optional
        />

        <TextInput
          name="ref4"
          label={t('Reference 4')}
          placeholder={t('Enter reference')}
          value={draft.ref4}
          onChange={(val) => setDraft((prev) => ({ ...prev, ref4: val }))}
          optional
        />

        <TextInput
          name="ref5"
          label={t('Reference 5')}
          placeholder={t('Enter reference')}
          value={draft.ref5}
          onChange={(val) => setDraft((prev) => ({ ...prev, ref5: val }))}
          optional
        />
      </div>
    </>
  );
}
