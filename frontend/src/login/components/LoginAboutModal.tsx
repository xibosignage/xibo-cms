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

import { useEffect, useState } from 'react';

interface AboutConfig {
  version: string;
  revision: string;
  appName: string;
  productName: string;
  aboutText: string;
  sourceUrl: string;
}

interface LoginAboutModalProps {
  onClose: () => void;
}

export function LoginAboutModal({ onClose }: LoginAboutModalProps) {
  const [config, setConfig] = useState<AboutConfig | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetch('/about/config')
      .then((r) => r.json())
      .then((data: AboutConfig) => setConfig(data))
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [onClose]);

  const currentYear = new Date().getFullYear();
  const version = config?.version ?? '';
  const revision = config?.revision ?? '';
  const appName = config?.appName ?? 'Xibo';
  const sourceUrl = config?.sourceUrl ?? 'https://github.com/xibosignage/xibo-cms';
  const aboutText = config?.aboutText ?? '';

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
          {isLoading ? (
            <div className="login-about-spinner">
              <div className="login-about-spinner-inner" />
            </div>
          ) : aboutText ? (
            <div dangerouslySetInnerHTML={{ __html: aboutText }} />
          ) : (
            <>
              <section>
                <h2>
                  Xibo{' '}
                  <span style={{ fontSize: '1rem', color: '#6b7280' }}>
                    - Simple. Intuitive. Impactful. Secure.
                  </span>
                </h2>
                <p>
                  Xibo Digital Signage -{' '}
                  <a target="_blank" rel="noreferrer" href="https://xibosignage.com">
                    xibosignage.com
                  </a>
                  .<br />
                  Copyright &copy; 2006-{currentYear} Xibo Signage Ltd and the{' '}
                  <a
                    target="_blank"
                    rel="noreferrer"
                    href="https://github.com/orgs/xibosignage/people"
                  >
                    Xibo Developers
                  </a>
                  .
                </p>
                {version && (
                  <p style={{ fontWeight: 500 }}>
                    Version {version}
                    <br />
                    <span className="login-about-version-badge">SHA: {revision}</span>
                  </p>
                )}
              </section>

              <section>
                <h2>Licence</h2>
                <p>
                  Xibo is free software: you can redistribute it and/or modify it under the terms of
                  the GNU Affero General Public License as published by the Free Software
                  Foundation, either version 3 of the License, or any later version.
                </p>
                <p>
                  Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
                  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
                  PURPOSE. See the GNU Affero General Public License for more details.
                </p>
                <p>
                  You should have received a copy of the GNU Affero General Public License along
                  with Xibo. If not, see{' '}
                  <a
                    target="_blank"
                    rel="noreferrer"
                    href="http://opensource.org/licenses/AGPL-3.0"
                  >
                    opensource.org/licenses/AGPL-3.0
                  </a>
                  .
                </p>
                <p>
                  Further guidance is available <a href="https://xibosignage.com/eula">here</a>. If
                  in doubt, please seek professional legal advice.
                </p>
              </section>

              <section>
                <h2>Source Code</h2>
                <p>
                  As an end user of the Xibo software you are entitled to download and view the
                  source code. You may do so from{' '}
                  <a target="_blank" rel="noreferrer" href={sourceUrl}>
                    GitHub
                  </a>
                  . As a service provider you <strong>MUST</strong> provide your end users
                  notification of and access to the source code.
                </p>
              </section>

              <section>
                <h2>Third Party Licenses</h2>
                <p>
                  Xibo uses some third party libraries and tools - these are provided with the
                  software under licences compatible with the AGPLv3. All licences for third party
                  libraries are included with that library and attributed in the code.
                </p>
                <h3>Fonts</h3>
                <p>
                  Xibo includes fonts licensed under the{' '}
                  <a href="http://scripts.sil.org/OFL_web">OFL</a> and{' '}
                  <a href="http://creativecommons.org/publicdomain/zero/1.0/">CC-0</a>.
                </p>
                <ul>
                  <li>
                    <strong>Aileron Regular (CC-0)</strong>:{' '}
                    <a href="https://fontlibrary.org/en/font/aileron#Aileron-Regular">
                      https://fontlibrary.org/en/font/aileron#Aileron-Regular
                    </a>
                  </li>
                  <li>
                    <strong>Aileron Heavy (CC-0)</strong>:{' '}
                    <a href="https://fontlibrary.org/en/font/aileron#Aileron-Heavy">
                      https://fontlibrary.org/en/font/aileron#Aileron-Heavy
                    </a>
                  </li>
                  <li>
                    <strong>Dancing Script Regular (OFL)</strong>:{' '}
                    <a href="https://fontlibrary.org/en/font/dancing">
                      https://fontlibrary.org/en/font/dancing
                    </a>
                  </li>
                  <li>
                    <strong>Linear Regular (OFL)</strong>:{' '}
                    <a href="https://fontlibrary.org/en/font/linear-regular#Linear-Regular">
                      https://fontlibrary.org/en/font/linear-regular#Linear-Regular
                    </a>
                  </li>
                  <li>
                    <strong>Open Sans (OFL)</strong>:{' '}
                    <a href="https://fonts.google.com/specimen/Open+Sans">
                      https://fonts.google.com/specimen/Open+Sans
                    </a>
                  </li>
                  <li>
                    <strong>Poppins (OFL)</strong>:{' '}
                    <a href="https://fonts.google.com/specimen/Poppins">
                      https://fonts.google.com/specimen/Poppins
                    </a>
                  </li>
                  <li>
                    <strong>Railway Regular (OFL)</strong>:{' '}
                    <a href="https://fontlibrary.org/en/font/railway-sans">
                      https://fontlibrary.org/en/font/railway-sans
                    </a>
                  </li>
                </ul>
              </section>

              <section>
                <h2>Weather</h2>
                <p>
                  Weather data provided by <a href="https://openweathermap.org/">OpenWeather</a> is
                  provided under{' '}
                  <a href="http://creativecommons.org/licenses/by-sa/4.0/">CC-BY-SA 4.0</a> and{' '}
                  <a href="http://opendatacommons.org/licenses/odbl/">oDbl</a>.
                </p>
              </section>

              {appName === 'Xibo' && (
                <section>
                  <h2>Feedback</h2>
                  <p>
                    Feedback can be submitted via the help button and feedback forms contained
                    within the CMS. Feedback helps us improve our products and services. When you
                    submit feedback, we collect your name and email address, along with your
                    comments and any attachments. This information is used and stored in accordance
                    with our <a href="https://xibosignage.com/privacy">privacy policy</a> and{' '}
                    <a href="https://xibosignage.com/dpa">data processing agreement</a>.
                  </p>
                </section>
              )}

              <section>
                <h2>Commercial</h2>
                <p>
                  Xibo is supported and maintained by{' '}
                  <a target="_blank" rel="noreferrer" href="https://xibosignage.com">
                    Xibo Signage Ltd
                  </a>
                  .
                </p>
              </section>
            </>
          )}
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
