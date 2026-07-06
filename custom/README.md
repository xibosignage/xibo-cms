# Custom Folder


This folder is provided as a reasonable place to copy/develop custom modules. It is autoloaded by Composer under the `Xibo\Custom` PSR-4 namespace. It is not intended for use for open-ended third party customisations.

### Custom modules (`custom/modules/`)

Place module XML definitions in `custom/modules/`.  The module factory discovers them automatically.
The corresponding PHP class should live in `custom/` under the `Xibo\Custom` namespace.

### Middleware

Custom middleware classes (e.g. SAML) are loaded by reference from `$middleware` in`web/settings-custom.php`. The class itself can live anywhere that Composer can autoload it, including `custom/` via the `Xibo\Custom` namespace.

### Task classes

Background tasks are registered directly in the database via a Phinx migration that references the fully-qualified class name. The class itself can live in `custom/`.

## Not supported in v4.5

- **Custom connectors** (`.connector`) : JSON file discovery from `custom/` is removed. All connectors are registered in the database as system connectors.
- **Custom reports** (`.report`) : JSON file discovery from `custom/` is removed. Only built-in reports in `reports/` are loaded.
- **Custom Twig views** (`custom/`) : is no longer in the Twig filesystem loader path. Module templates should live in `modules/` or be served by a React component.
- **Custom display profile types** : the runtime middleware registration mechanism (`CustomDisplayProfileMiddlewareTrait`) is removed. Only the built-in profile types (Android, ChromeOS, Hisense, Linux, webOS, Tizen and Windows) are supported.
