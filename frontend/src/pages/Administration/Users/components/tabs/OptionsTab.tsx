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

import type { OptionsTabProps } from '../../config/addEditUserTypes';
import SwitchRow from '../SwitchRow';

import InfoBanner from '@/components/ui/InfoBanner';

export default function OptionsTab({ draft, setDraft, isEdit, isSuperAdmin }: OptionsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold text-gray-800">{t('Additional Settings')}</span>
        <p className="text-xs text-gray-500">
          {t("Configure additional settings that control the user's experience and security.")}
        </p>
      </div>
      <SwitchRow
        title={t('Hide Navigation')}
        description={t('Hide the navigation for this user')}
        checked={draft.hideNavigation === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, hideNavigation: val ? 1 : 0 }))}
      />
      <SwitchRow
        title={t('Hide User Guide')}
        description={t('Hide the new user guide for this user')}
        checked={draft.newUserWizard === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, newUserWizard: val ? 1 : 0 }))}
      />
      <SwitchRow
        title={t('Force Password Change')}
        description={t('Require this user to change their password on next login')}
        checked={draft.isPasswordChangeRequired === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isPasswordChangeRequired: val ? 1 : 0 }))}
      />
      {isEdit && isSuperAdmin && (
        <SwitchRow
          title={t('Disable Two Factor Authentication')}
          description={t("Reset this user's two factor authentication")}
          checked={draft.disableTwoFactor === 1}
          onChange={(val) => setDraft((prev) => ({ ...prev, disableTwoFactor: val ? 1 : 0 }))}
        />
      )}
      <InfoBanner type="info" hideInfoIcon={true} className="text-[12px] font-medium">
        {t(
          'We recommend enabling "Force Password Change" for new users to ensure they set a password only they know, especially if you generated a temporary password for them.',
        )}
      </InfoBanner>
    </div>
  );
}
