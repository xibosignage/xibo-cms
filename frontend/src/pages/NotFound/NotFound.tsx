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
import { Link, useLocation, useNavigate } from 'react-router-dom';

export default function NotFound() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const navigate = useNavigate();

  const handleBack = () => {
    if (window.history.length > 1) navigate(-1);
    else navigate('/');
  };

  return (
    <section className="flex min-h-[60vh] flex-col items-center justify-center gap-6 text-center">
      <div>
        <p className="text-sm uppercase tracking-widest text-gray-500">{t('Error 404')}</p>
        <h1 className="mt-1 text-3xl font-semibold">{t('Page not found')}</h1>
        <p className="mt-2 text-gray-600">
          {t('We couldn’t find ')}
          <span className="font-mono text-gray-800">{pathname}</span>.
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-center gap-3">
        <button
          onClick={handleBack}
          className="rounded-md cursor-pointer hover:text-sky-900 border px-4 py-2"
        >
          {t('Go back')}
        </button>
        <Link to="/" className="rounded-md bg-gray-900 hover:bg-gray-700 px-4 py-2 text-white">
          {t('Go home')}
        </Link>
      </div>
    </section>
  );
}
