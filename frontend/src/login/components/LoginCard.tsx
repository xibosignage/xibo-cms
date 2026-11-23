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

interface Props {
  logoUrl: string;
  supportUrl: string;
  children: React.ReactNode;
}

export function LoginCard({ logoUrl, supportUrl, children }: Props) {
  return (
    <div className="login-card">
      <p className="login-card-logo">
        <a href={supportUrl}>
          <img
            src={logoUrl}
            alt="Logo"
            onError={(e) => {
              const img = e.currentTarget;
              if (!img.src.endsWith('.png')) {
                img.src = img.src.replace(/\.[^.]+$/, '.png');
              } else {
                // .png fallback also failed (e.g. a white-label install with no dark logo
                // asset) - collapse the whole fixed-height wrapper, not just the broken image,
                // so it doesn't leave an empty gap above the card content.
                img.closest<HTMLElement>('.login-card-logo')?.style.setProperty('display', 'none');
              }
            }}
          />
        </a>
      </p>
      {children}
    </div>
  );
}
