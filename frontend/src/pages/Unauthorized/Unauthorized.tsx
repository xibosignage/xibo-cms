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

import { ArrowLeft, Lock } from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';

import Button from '@/components/ui/Button';
import { DEFAULT_INTERNAL_ROUTE } from '@/config/appRoutes';

export default function Unauthorized() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();

  const handleBack = () => {
    if (window.history.length > 1) navigate(-1);
    else navigate(DEFAULT_INTERNAL_ROUTE);
  };

  return (
    <section className="flex flex-1 flex-col items-center justify-center gap-3 text-center">
      <div className="inline-flex justify-center items-center size-15.5 rounded-full bg-xibo-blue-100 text-xibo-blue-800 border-7 border-xibo-blue-50">
        <Lock className="shrink-0 size-6" />
      </div>

      <div>
        <h1 className="text-4xl font-bold text-gray-800">{t('Access Denied')}</h1>
        <p className="mt-3 text-gray-500">
          <Trans
            i18nKey="You don’t have permission to view <path>{{path}}</path>."
            values={{ path: pathname }}
            components={{ path: <span className="font-mono text-gray-800" /> }}
          />
        </p>
        <p className="text-gray-500">{t('Please contact your administrator for access.')}</p>
      </div>

      <div className="flex flex-wrap items-center justify-center gap-3">
        <Button variant="secondary" leftIcon={ArrowLeft} onClick={handleBack}>
          {t('Go back')}
        </Button>
        <Button variant="primary" onClick={() => navigate(DEFAULT_INTERNAL_ROUTE)}>
          {t('Go to Dashboard')}
        </Button>
      </div>
    </section>
  );
}
