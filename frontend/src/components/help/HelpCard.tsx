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

import type { LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';

interface HelpCardProps {
  icon: LucideIcon;
  title: string;
  desc: string;
  to?: string;
  href?: string;
  target?: string;
  onClick?: () => void;
}

const CARD_CLASS =
  'flex items-center gap-3 w-full text-left px-3 py-2 transition-colors hover:bg-gray-50 cursor-pointer';

export default function HelpCard({
  icon: Icon,
  title,
  desc,
  to,
  href,
  target,
  onClick,
}: HelpCardProps) {
  const inner = (
    <>
      <span className="flex items-center justify-center size-9.75 shrink-0 text-gray-800">
        <Icon className="size-9.75 stroke-1" />
      </span>
      <span className="min-w-0">
        <span className="block text-sm font-semibold text-gray-800">{title}</span>
        <span className="block text-xs text-xibo-blue-600">{desc}</span>
      </span>
    </>
  );

  if (to) {
    return (
      <Link to={to} className={CARD_CLASS}>
        {inner}
      </Link>
    );
  }

  if (href) {
    return (
      <a className={CARD_CLASS} href={href} target={target} rel="noopener noreferrer">
        {inner}
      </a>
    );
  }

  return (
    <button type="button" className={CARD_CLASS} onClick={onClick}>
      {inner}
    </button>
  );
}
