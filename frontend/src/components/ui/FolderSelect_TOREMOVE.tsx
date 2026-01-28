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

interface FolderItem {
  id: number;
  name: string;
}

interface FolderSelectProps {
  value: number;
  folders: FolderItem[];
  onChange: (value: number) => void;
}

export function FolderSelect({ value, folders, onChange }: FolderSelectProps) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-1">
      <label
        htmlFor="hs-input-with-add-on-url"
        className="block text-sm text-gray-500 font-semibold"
      >
        {t('Select folder location')}
      </label>
      <div className="flex">
        <div className="px-3 inline-flex items-center min-w-fit rounded-s-md border border-e-0 border-gray-200 ">
          <span className="text-sm text-gray-500">{t('My Files')}</span>
        </div>

        <select
          value={value}
          onChange={(e) => onChange(Number(e.target.value))}
          className="block w-full p-3 text-sm border-gray-200 rounded-e-lg focus:ring-gray-600 focus:border-gray-600 "
        >
          {folders.map((f) => (
            <option key={f.id} value={f.id}>
              {f.name}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
}
