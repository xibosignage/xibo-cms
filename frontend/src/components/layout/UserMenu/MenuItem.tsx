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

export default function MenuItem({
  icon,
  label,
  onClick,
  variant = 'default',
}: {
  icon: React.ReactNode;
  label: string;
  onClick: () => void;
  variant?: 'default' | 'danger';
}) {
  const isDanger = variant === 'danger';

  return (
    <button
      onClick={onClick}
      className={`flex w-full items-center gap-3 rounded-lg cursor-pointer px-3 py-2 text-sm font-medium transition focus:outline-none ${isDanger ? 'text-red-600 hover:bg-red-50 focus:bg-red-50' : 'text-gray-800 hover:bg-gray-100 focus:bg-gray-100'}`}
    >
      <span className={isDanger ? 'text-red-600' : 'text-gray-500'}>{icon}</span>
      {label}
    </button>
  );
}
