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

import { ArrowLeft, SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import Button from '@/components/ui/Button';
import { BrandingProvider } from '@/context/BrandingContext';

export default function NotFound() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const handleBack = () => {
    if (window.history.length > 1) navigate(-1);
    else navigate('/');
  };

  return (
    <BrandingProvider>
      <section className="flex min-h-screen flex-col items-center justify-center gap-3 text-center">
        <div className="inline-flex justify-center items-center size-15.5 rounded-full bg-xibo-blue-100 text-xibo-blue-800 border-7 border-xibo-blue-50">
          <SearchX className="shrink-0 size-6" />
        </div>

        <div>
          <h1 className="text-4xl font-bold text-gray-800">{t('Page not found')}</h1>
          <p className="mt-3 text-gray-500">
            {t('Sorry, the page you are looking for could not be found.')}
          </p>
        </div>

        <div className="flex flex-wrap items-center justify-center gap-3">
          <Button variant="secondary" leftIcon={ArrowLeft} onClick={handleBack}>
            {t('Go back')}
          </Button>
          <Button variant="primary" onClick={() => navigate('/')}>
            {t('Go to Dashboard')}
          </Button>
        </div>
      </section>
    </BrandingProvider>
  );
}
