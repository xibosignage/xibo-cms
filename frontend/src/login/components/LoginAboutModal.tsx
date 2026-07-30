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

import { useEffect } from 'react';

import { t } from '../i18n';

import { AboutModalContent } from '@/components/about/AboutModalContent';

interface LoginAboutModalProps {
  onClose: () => void;
}

export function LoginAboutModal({ onClose }: LoginAboutModalProps) {
  useEffect(() => {
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [onClose]);

  return (
    <div className="login-about-overlay">
      <div
        className="login-about-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="login-about-title"
      >
        <div className="login-about-header">
          <span id="login-about-title">{t('aboutLabel')}</span>
        </div>

        <div className="login-about-body">
          <AboutModalContent />
        </div>

        <div className="login-about-footer-bar">
          <button type="button" className="login-btn-secondary" onClick={onClose}>
            {t('closeLabel')}
          </button>
        </div>
      </div>
    </div>
  );
}
