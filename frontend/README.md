# Xibo CMS v5 Frontend

Modern frontend for **Xibo CMS v5**, built with:

- [React 19](https://react.dev/) + [React Router](https://reactrouter.com/) – SPA experience
- [Vite](https://vite.dev/) – fast dev server & bundler
- [TypeScript](https://www.typescriptlang.org/) – static typing
- [Tailwind CSS v4](https://tailwindcss.com/) + [Preline UI](https://preline.co/) – utility-first styling and UI components
- [i18next](https://www.i18next.com/) – internationalisation
- [Axios](https://axios-http.com/) – API client
- ESLint + Prettier – linting & formatting (semicolons enforced)

---

## Project structure

```text
frontend/
├── index.html
├── package.json
├── vite.config.ts          # Vite config (aliases, proxy, build output)
├── tsconfig.json           # TS config (strict mode, paths)
├── public/                 # Static assets
│   ├── assets/             # Images, fonts, etc.
│   ├── locale/             # Translation JSON files
│   └── theme/              # Theme CSS files
└── src/
    ├── app/                # App shell, layouts, providers
    ├── components/         # Shared UI
    ├── pages/              # Route-level pages (Dashboard, Settings, etc.)
    ├── routes/             # Router setup
    ├── hooks/              # Custom hooks
    ├── lib/                # Utils (API, i18n, theme)
    ├── styles/             # Global CSS
    └── assets/             # Local project assets
```

---

## Getting started

### Install dependencies

```bash
npm install
```

### Development server

```bash
npm run dev
```

Runs on [http://localhost:5173](http://localhost:5173) with hot reload.

### Build for production

```bash
npm run build
```

Output goes to `../web/dist/react` (consumed by the CMS).

### Preview build

```bash
npm run preview
```

---

## Available scripts

- `dev` – start Vite dev server
- `build` – type-check + build production output
- `preview` – preview production build locally
- `typecheck` – run TypeScript (`tsc --noEmit`)
- `lint` – run ESLint
- `format` – run Prettier

---

## Internationalisation (i18n)

- Backend `.mo` files in the CMS root `/locale` must be converted into JSON for the frontend.
- Run the conversion script before building:

  ```bash
  npm run i18n:convert
  ```

---

## Theming

- Themes live under `public/theme/<name>/theme.css`.
- Loaded at runtime with a small `useTheme` hook (no rebuild required).
- Light/Dark supported out of the box.

---

## Conventions

- TypeScript strict mode (`strict: true`, `noUncheckedIndexedAccess: true`)
- Always use **semicolons**
- Use **path aliases**: `@/` → `src/`
- Components in PascalCase, hooks in `useSomething` format
- Styling via Tailwind utilities; avoid standalone CSS unless necessary

---

## Next steps / TODO

- Authentication (session-based for now; SSO/token later)
- Script for `.mo → JSON` translation conversion
- Theme loader for white-labelling
- Data fetching layer (consider [`@tanstack/react-query`](https://tanstack.com/query))
- CI/CD integration (lint, typecheck, build cache)

---

## License

[AGPL-3.0](https://github.com/xibosignage/xibo-cms/blob/develop/LICENSE) (same as CMS core)
