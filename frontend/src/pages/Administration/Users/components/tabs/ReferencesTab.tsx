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
