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
import { twMerge } from 'tailwind-merge';

type InputFilterProps = {
  label: string;
  name: string;
  placeholder?: string;
  type: 'text' | 'number';
  value: string | number;
  onChange: (name: string, value: string | number | null) => void;
  className?: string;
};

export default function InputFilter({
  label,
  name,
  placeholder,
  type,
  value,
  onChange,
  className,
}: InputFilterProps) {
  const { t } = useTranslation();

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;

    if (type === 'number') {
      onChange(name, val === '' ? null : Number(val));
    } else {
      onChange(name, val);
    }
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0 relative',
        className,
      )}
    >
      <label className="text-sm font-semibold text-gray-500 leading-5">{t(label)}</label>
      <input
        type={type}
        value={value ?? ''}
        onChange={handleChange}
        placeholder={t(placeholder || label)}
        className="w-full h-11.25 bg-white rounded-lg border border-gray-200 px-4 text-left text-gray-700 focus:outline-none focus:ring-2 focus:ring-xibo-blue-500/20 focus:border-xibo-blue-500 transition-all"
      />
    </div>
  );
}
