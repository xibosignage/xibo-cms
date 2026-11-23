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

import ProfileFormFields from './components/ProfileFormFields';
import { useProfileForm } from './hooks/useProfileForm';

import Button from '@/components/ui/Button';
import InfoBanner from '@/components/ui/InfoBanner';
import { notify } from '@/components/ui/Notification';
import PreferencesLayout from '@/pages/Preferences/PreferencesLayout';

const FORM_ID = 'preferences-profile-form';

export default function Profile() {
  const { t } = useTranslation();
  const profileForm = useProfileForm({ onSaved: () => notify.success(t('Profile saved')) });
  const { state, isPending } = profileForm;

  return (
    <PreferencesLayout>
      <div className="flex flex-1 flex-col gap-5 p-5">
        <h2 className="text-xl font-semibold text-gray-800">{t('Profile')}</h2>

        {state.apiError && <InfoBanner type="danger">{state.apiError}</InfoBanner>}

        <div className="flex flex-col gap-6 p-5 bg-white rounded-lg border border-gray-200">
          <ProfileFormFields formId={FORM_ID} profileForm={profileForm} />

          <div className="flex justify-end">
            <Button type="submit" form={FORM_ID} variant="primary" disabled={isPending}>
              {isPending ? t('Saving...') : t('Save')}
            </Button>
          </div>
        </div>
      </div>
    </PreferencesLayout>
  );
}
