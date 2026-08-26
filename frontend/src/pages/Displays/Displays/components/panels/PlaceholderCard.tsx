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

import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { PanelCard } from './PanelCard';

interface PlaceholderCardProps {
  icon: LucideIcon;
  title: string;
  /** Defaults to "Coming soon" — override for a more specific one-line note. */
  message?: string;
}

// Shared "coming soon" card for Manage page sections that aren't wired to
// real data/actions yet (Now Playing, Daily timeline, All commands) — reuses
// the same dashed-box/icon convention as the since-replaced Proof of Play
// placeholder without duplicating it, since that component's props were
// specific to Proof of Play (onViewReport/onViewTroubleshooting) and could not
// be reused here as-is.
export default function PlaceholderCard({ icon: Icon, title, message }: PlaceholderCardProps) {
  const { t } = useTranslation();

  return (
    <PanelCard title={title} icon={Icon}>
      <div className="flex flex-1 min-h-32 flex-col items-center justify-center gap-2 px-4 py-8 text-center">
        <Icon className="size-6 text-gray-300" aria-hidden="true" />
        <p className="text-sm text-gray-400">{message ?? t('Coming soon')}</p>
      </div>
    </PanelCard>
  );
}
