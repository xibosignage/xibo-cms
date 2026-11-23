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

import type { NotificationsTabProps } from '../../config/addEditUserTypes';
import SwitchRow from '../SwitchRow';

export default function NotificationsTab({ draft, setDraft }: NotificationsTabProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-1">
        <span className="text-sm font-semibold text-gray-800">{t('Notification Preferences')}</span>
        <p className="text-xs text-gray-500">
          {t('Configure which notifications this user should receive.')}
        </p>
      </div>

      <SwitchRow
        title={t('System Notifications')}
        description={t('Receive system notifications')}
        checked={draft.isSystemNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isSystemNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Display Notifications')}
        description={t('Receive display notifications')}
        checked={draft.isDisplayNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isDisplayNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('DataSet Notifications')}
        description={t('Receive dataset notifications')}
        checked={draft.isDataSetNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isDataSetNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Layout Notifications')}
        description={t('Receive layout notifications')}
        checked={draft.isLayoutNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isLayoutNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Library Notifications')}
        description={t('Receive library notifications')}
        checked={draft.isLibraryNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isLibraryNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Report Notifications')}
        description={t('Receive report notifications')}
        checked={draft.isReportNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isReportNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Schedule Notifications')}
        description={t('Receive schedule notifications')}
        checked={draft.isScheduleNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isScheduleNotification: val ? 1 : 0 }))}
      />

      <SwitchRow
        title={t('Custom Notifications')}
        description={t('Receive custom notifications')}
        checked={draft.isCustomNotification === 1}
        onChange={(val) => setDraft((prev) => ({ ...prev, isCustomNotification: val ? 1 : 0 }))}
      />
    </div>
  );
}
