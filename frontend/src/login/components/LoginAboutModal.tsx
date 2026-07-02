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
    <div className="login-about-overlay" onClick={onClose}>
      <div className="login-about-card" onClick={(e) => e.stopPropagation()}>
        <div className="login-about-header">
          <span>About</span>
          <button className="login-about-close" onClick={onClose} aria-label="Close">
            &#x2715;
          </button>
        </div>

        <div className="login-about-body">
          <AboutModalContent />
        </div>

        <div className="login-about-footer-bar">
          <button className="btn-link btn-link-muted" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
