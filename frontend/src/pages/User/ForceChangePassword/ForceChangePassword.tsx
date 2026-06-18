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

import { useState } from 'react';
import { useLoaderData, useSearchParams } from 'react-router-dom';

import http from '@/lib/api';
import type { User } from '@/types/user';

export default function ForceChangePassword() {
  const { user } = useLoaderData() as { user: User };
  const [searchParams] = useSearchParams();
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');

    if (newPassword !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    setLoading(true);
    try {
      await http.put('/user/password/forceChange', {
        newPassword,
        retypeNewPassword: confirmPassword,
      });
      const priorRoute = searchParams.get('priorRoute');
      window.location.assign(priorRoute || '/');
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'An error occurred. Please try again.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  const inputStyle: React.CSSProperties = {
    display: 'block',
    width: '100%',
    padding: '7px 9px',
    fontSize: 14,
    lineHeight: 1.5,
    color: '#495057',
    backgroundColor: '#fff',
    border: '1px solid #ced4da',
    borderRadius: 4,
    boxSizing: 'border-box',
    marginBottom: 12,
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        paddingTop: 40,
        paddingBottom: 40,
        backgroundColor: '#f7f7f7',
        fontFamily: 'system-ui, -apple-system, sans-serif',
        fontSize: 14,
      }}
    >
      <div
        style={{
          maxWidth: 330,
          margin: '0 auto',
          borderRadius: 5,
          overflow: 'hidden',
          border: '1px solid #e5e5e5',
          boxShadow: '0 2px 8px rgba(0,0,0,.15)',
        }}
      >
        <div
          style={{
            backgroundColor: 'var(--brand-primary, #3f7fff)',
            padding: '16px 29px',
          }}
        >
          <h1 style={{ margin: 0, fontSize: 16, fontWeight: 600, color: '#fff' }}>
            Change your password
          </h1>
        </div>

        <div style={{ padding: '19px 29px 29px', backgroundColor: '#fff' }}>
          <p style={{ marginTop: 0, marginBottom: 12, color: '#6c757d' }}>
            Your account requires a password change before you can continue.
          </p>
          <p style={{ marginTop: 0, marginBottom: 12, color: '#495057' }}>
            Signed in as <strong>{user.userName}</strong>
          </p>

          <form onSubmit={handleSubmit} noValidate>
            <input
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              placeholder="New password"
              autoFocus
              required
              autoComplete="new-password"
              style={inputStyle}
            />
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Confirm new password"
              required
              autoComplete="new-password"
              style={inputStyle}
            />

            {error && (
              <div
                role="alert"
                style={{
                  marginBottom: 12,
                  padding: '7px 10px',
                  backgroundColor: '#dc3545',
                  color: '#fff',
                  borderRadius: 4,
                  fontSize: 13,
                }}
              >
                {error}
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              style={{
                backgroundColor: 'var(--brand-primary, #3f7fff)',
                border: '1px solid var(--brand-primary, #3f7fff)',
                color: '#fff',
                cursor: loading ? 'not-allowed' : 'pointer',
                display: 'block',
                width: '100%',
                padding: 8,
                borderRadius: 4,
                fontSize: 14,
                fontWeight: 500,
                opacity: loading ? 0.6 : 1,
              }}
            >
              {loading ? 'Saving…' : 'Save password'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
