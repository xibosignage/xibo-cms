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

import Modal from '@/components/ui/modals/Modal';

interface AboutModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function AboutModal({ isOpen, onClose }: AboutModalProps) {
  const { t } = useTranslation();
  const currentYear = new Date().getFullYear();

  // TODO: Replace with current values
  const appConfig = {
    version: '5.0.0',
    revision: 'a1b2c3d',
    sourceUrl: 'https://github.com/xibosignage/xibo-cms',
    appName: 'Xibo',
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('About')}
      size="lg"
      scrollable={true}
      actions={[{ label: t('Close'), variant: 'secondary', onClick: onClose }]}
    >
      <div className="p-6 text-gray-700 space-y-6">
        {/* Header Section */}
        <section>
          <h2 className="text-xl font-semibold text-gray-900 mb-2">
            Xibo{' '}
            <span className="text-base font-semibold text-gray-500">
              - Simple. Intuitive. Impactful. Secure.
            </span>
          </h2>
          <p className="">
            Xibo Digital Signage -{' '}
            <a
              target="_blank"
              rel="noreferrer"
              href="https://xibosignage.com"
              className="text-blue-600 hover:underline"
            >
              xibosignage.com
            </a>
            .<br />
            Copyright &copy; 2006-{currentYear} Xibo Signage Ltd and the{' '}
            <a
              target="_blank"
              rel="noreferrer"
              href="https://github.com/orgs/xibosignage/people"
              className="text-blue-600 hover:underline"
            >
              Xibo Developers
            </a>
            .
          </p>
          <p className="mt-2 font-medium">
            Version {appConfig.version} <br />
            <span className="inline-block mt-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded-md">
              SHA: {appConfig.revision}
            </span>
          </p>
        </section>

        {/* Licence Section */}
        <section>
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Licence</h2>
          <div className="space-y-2 ">
            <p>
              Xibo is free software: you can redistribute it and/or modify it under the terms of the
              GNU Affero General Public License as published by the Free Software Foundation, either
              version 3 of the License, or any later version.
            </p>
            <p>
              Xibo is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
              without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
              PURPOSE. See the GNU Affero General Public License for more details.
            </p>
            <p>
              You should have received a copy of the GNU Affero General Public License along with
              Xibo. If not, see{' '}
              <a
                target="_blank"
                rel="noreferrer"
                href="http://opensource.org/licenses/AGPL-3.0"
                className="text-blue-600 hover:underline"
              >
                opensource.org/licenses/AGPL-3.0
              </a>
              .
            </p>
            <p>
              Further guidance is available{' '}
              <a href="https://xibosignage.com/eula" className="text-blue-600 hover:underline">
                here
              </a>
              . If in doubt, please seek professional legal advice.
            </p>
          </div>
        </section>

        {/* Source Code Section */}
        <section>
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Source Code</h2>
          <p className="">
            As an end user of the Xibo software you are entitled to download and view the source
            code. You may do so from{' '}
            <a
              target="_blank"
              rel="noreferrer"
              href={appConfig.sourceUrl}
              className="text-blue-600 hover:underline"
            >
              GitHub
            </a>
            . As a service provider you <strong>MUST</strong> provide your end users notification of
            and access to the source code.
          </p>
        </section>

        {/* Third Party Licenses */}
        <section>
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Third Party Licenses</h2>
          <p className="mb-2">
            Xibo uses some third party libraries and tools - these are provided with the software
            under licences compatible with the AGPLv3. All licences for third party libraries are
            included with that library and attributed in the code.
          </p>

          <h3 className="text-md font-semibold text-gray-800 mt-4 mb-2">Fonts</h3>
          <p className="mb-2">
            Xibo includes fonts licensed under the{' '}
            <a href="http://scripts.sil.org/OFL_web" className="text-blue-600 hover:underline">
              OFL
            </a>{' '}
            and{' '}
            <a
              href="http://creativecommons.org/publicdomain/zero/1.0/"
              className="text-blue-600 hover:underline"
            >
              CC-0
            </a>
            .
          </p>
          <ul className="list-disc pl-5  space-y-1">
            <li>
              <strong>Aileron Regular (CC-0)</strong>:{' '}
              <a
                href="https://fontlibrary.org/en/font/aileron#Aileron-Regular"
                className="text-blue-600 hover:underline"
              >
                https://fontlibrary.org/en/font/aileron#Aileron-Regular
              </a>
            </li>
            <li>
              <strong>Aileron Heavy (CC-0)</strong>:{' '}
              <a
                href="https://fontlibrary.org/en/font/aileron#Aileron-Heavy"
                className="text-blue-600 hover:underline"
              >
                https://fontlibrary.org/en/font/aileron#Aileron-Heavy
              </a>
            </li>
            <li>
              <strong>Dancing Script Regular (OFL)</strong>:{' '}
              <a
                href="https://fontlibrary.org/en/font/dancing"
                className="text-blue-600 hover:underline"
              >
                https://fontlibrary.org/en/font/dancing
              </a>
            </li>
            <li>
              <strong>Linear Regular (OFL)</strong>:{' '}
              <a
                href="https://fontlibrary.org/en/font/linear-regular#Linear-Regular"
                className="text-blue-600 hover:underline"
              >
                https://fontlibrary.org/en/font/linear-regular#Linear-Regular
              </a>
            </li>
            <li>
              <strong>Open Sans (OFL)</strong>:{' '}
              <a
                href="https://fonts.google.com/specimen/Open+Sans"
                className="text-blue-600 hover:underline"
              >
                https://fonts.google.com/specimen/Open+Sans
              </a>
            </li>
            <li>
              <strong>Poppins (OFL)</strong>:{' '}
              <a
                href="https://fonts.google.com/specimen/Poppins"
                className="text-blue-600 hover:underline"
              >
                https://fonts.google.com/specimen/Poppins
              </a>
            </li>
            <li>
              <strong>Railway Regular (OFL)</strong>:{' '}
              <a
                href="https://fontlibrary.org/en/font/railway-sans"
                className="text-blue-600 hover:underline"
              >
                https://fontlibrary.org/en/font/railway-sans
              </a>
            </li>
          </ul>
        </section>

        {/* Weather */}
        <section>
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Weather</h2>
          <p className="">
            Weather data provided by{' '}
            <a href="https://openweathermap.org/" className="text-blue-600 hover:underline">
              OpenWeather
            </a>{' '}
            is provided under{' '}
            <a
              href="http://creativecommons.org/licenses/by-sa/4.0/"
              className="text-blue-600 hover:underline"
            >
              CC-BY-SA 4.0
            </a>{' '}
            and{' '}
            <a
              href="http://opendatacommons.org/licenses/odbl/"
              className="text-blue-600 hover:underline"
            >
              oDbl
            </a>
            .
          </p>
        </section>

        {/* Feedback (Conditional) */}
        {appConfig.appName === 'Xibo' && (
          <section>
            <h2 className="text-lg font-semibold text-gray-900 mb-2">Feedback</h2>
            <p className="">
              Feedback can be submitted via the help button and feedback forms contained within the
              CMS. Feedback helps us improve our products and services. When you submit feedback, we
              collect your name and email address, along with your comments and any attachments.
              This information is used and stored in accordance with our{' '}
              <a href="https://xibosignage.com/privacy" className="text-blue-600 hover:underline">
                privacy policy
              </a>{' '}
              and{' '}
              <a href="https://xibosignage.com/dpa" className="text-blue-600 hover:underline">
                data processing agreement
              </a>
              .
            </p>
          </section>
        )}

        {/* Commercial */}
        <section>
          <h2 className="text-lg font-semibold text-gray-900 mb-2">Commercial</h2>
          <p className="">
            Xibo is supported and maintained by{' '}
            <a
              target="_blank"
              rel="noreferrer"
              href="https://xibosignage.com"
              className="text-blue-600 hover:underline"
            >
              Xibo Signage Ltd
            </a>
            .
          </p>
        </section>
      </div>
    </Modal>
  );
}
