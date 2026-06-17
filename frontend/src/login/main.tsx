import React from 'react';
import { createRoot } from 'react-dom/client';

import './styles.css';
import { LoginApp } from './LoginApp';

// Guard: if window.__LOGIN_CONFIG__ is absent the page was not served by PHP
// (e.g. Vite served login.html directly at /prototype/login). Redirect to the
// real login page so the PHP shell can stamp the CSRF token and config blob.
if (!window.__LOGIN_CONFIG__) {
  window.location.assign('/login');
} else {
  const root = document.getElementById('login-root');
  if (root) {
    createRoot(root).render(
      <React.StrictMode>
        <LoginApp />
      </React.StrictMode>,
    );
  }
}
