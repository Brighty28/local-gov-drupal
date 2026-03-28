# SCDC LocalGov Drupal

LocalGov Drupal site for South Cambridgeshire District Council (SCDC), built as part of the Local Government Reorganisation (LGR) migration from Umbraco CMS to Drupal.

## Overview

This project uses the [LocalGov Drupal](https://localgovdrupal.org/) distribution — an open-source Drupal platform designed specifically for UK local authorities. It provides content types, features, and patterns common to council websites out of the box.

The custom **SCDC theme** extends `localgov_theme` and uses the **7-1 SASS architecture** for maintainable, scalable styling.

## Project Structure

```
local-gov-drupal/
├── composer.json              # Drupal dependencies (core, LocalGov, contrib modules)
├── .gitignore
├── web/
│   ├── sites/default/
│   │   └── settings.php       # Drupal configuration
│   ├── modules/custom/        # Custom Drupal modules (empty - ready for development)
│   └── themes/custom/
│       └── scdc/              # SCDC custom theme
│           ├── scdc.info.yml          # Theme definition
│           ├── scdc.libraries.yml     # CSS/JS asset libraries
│           ├── scdc.breakpoints.yml   # Responsive breakpoints
│           ├── scdc.theme             # PHP preprocessing hooks
│           ├── package.json           # Node.js build tooling
│           ├── .stylelintrc.json      # SCSS linting config
│           ├── scss/                  # 7-1 SASS architecture (see below)
│           ├── css/                   # Compiled CSS output
│           ├── js/                    # JavaScript behaviours
│           ├── templates/             # Twig templates
│           ├── images/                # Theme images
│           └── fonts/                 # Custom web fonts
```

## 7-1 SASS Architecture

The theme styles live in `web/themes/custom/scdc/scss/` and follow the 7-1 pattern:

```
scss/
├── main.scss                  # Entry point — imports everything
├── abstracts/                 # No CSS output — helpers only
│   ├── _variables.scss        # Colours, typography, spacing, breakpoints, z-index
│   ├── _mixins.scss           # Responsive breakpoints, focus states, buttons, layout
│   ├── _functions.scss        # rem(), em(), spacing(), z() helpers
│   └── _placeholders.scss     # Extendable patterns (container, list-reset, card-base)
├── base/                      # Global base styles
│   ├── _reset.scss            # CSS reset / normalise
│   ├── _typography.scss       # Headings, paragraphs, blockquotes, code
│   └── _animations.scss       # Keyframes + prefers-reduced-motion support
├── components/                # Reusable UI components
│   ├── _buttons.scss          # Primary, secondary, accent variants
│   ├── _cards.scss            # Card component + card grid
│   ├── _forms.scss            # Inputs, selects, checkboxes, fieldsets
│   ├── _breadcrumbs.scss      # Breadcrumb trail
│   ├── _alerts.scss           # Status/warning/error messages
│   ├── _accordion.scss        # Collapsible accordion
│   ├── _search.scss           # Search form + results
│   ├── _tables.scss           # Responsive tables
│   └── _pagination.scss       # Pager component
├── layout/                    # Major structural layout
│   ├── _grid.scss             # 12-column grid system + container
│   ├── _header.scss           # Site header + branding
│   ├── _navigation.scss       # Primary nav (desktop + mobile toggle) + secondary nav
│   ├── _footer.scss           # 4-column footer
│   └── _sidebar.scss          # Sidebar layout + blocks
├── pages/                     # Page-specific styles
│   ├── _home.scss             # Homepage hero, popular services, news
│   ├── _services.scss         # Services listing page
│   └── _contact.scss          # Contact page
├── themes/                    # Theme variations
│   ├── _default.scss          # CSS custom properties for default theme
│   └── _high-contrast.scss    # WCAG AAA high-contrast mode
└── vendors/                   # Third-party CSS (empty — ready for imports)
    └── _index.scss
```

## Twig Templates

Custom templates in `web/themes/custom/scdc/templates/`:

| Template | Purpose |
|----------|---------|
| `layout/page.html.twig` | Full page layout with all regions |
| `layout/html.html.twig` | HTML wrapper with skip-to-content link |
| `navigation/breadcrumb.html.twig` | Accessible breadcrumb trail |
| `block/block--system-branding-block.html.twig` | Logo and site name |
| `content/node.html.twig` | Node display with bundle-specific classes |
| `misc/status-messages.html.twig` | Alert/status messages |

## JavaScript

Drupal behaviours in `web/themes/custom/scdc/js/`:

- **scdc.js** — Global theme behaviours (skip link focus fix)
- **navigation.js** — Mobile menu toggle with Escape key support
- **accordion.js** — Accessible accordion (aria-expanded/aria-controls)
- **search.js** — Search input enhancements

---

## Development Setup

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Colima/OrbStack on macOS)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/) v1.23+
- [Composer](https://getcomposer.org/) (DDEV includes this, but useful to have locally)
- [Node.js](https://nodejs.org/) v18+ and npm (for SASS compilation)
- Git

### 1. Clone the repository

```bash
git clone git@github.com:Brighty28/local-gov-drupal.git
cd local-gov-drupal
```

### 2. Initialise DDEV

```bash
ddev config --project-type=drupal --php-version=8.3 --docroot=web
ddev start
```

### 3. Install Drupal dependencies

```bash
ddev composer install
```

This will pull in Drupal core, LocalGov Drupal, and all contrib modules into `web/`.

### 4. Install Drupal

```bash
ddev drush site:install localgov --account-name=admin --account-pass=admin --site-name="South Cambridgeshire District Council" -y
```

### 5. Enable the SCDC theme

```bash
ddev drush theme:enable scdc
ddev drush config:set system.theme default scdc -y
ddev drush cache:rebuild
```

### 6. Build the theme assets

```bash
cd web/themes/custom/scdc
npm install
npm run build
```

For active development with live recompilation:

```bash
npm run watch
```

### 7. Access the site

```bash
ddev launch
```

- **Site URL:** https://local-gov-drupal.ddev.site
- **Admin login:** `admin` / `admin`

### Useful DDEV Commands

| Command | Description |
|---------|-------------|
| `ddev start` | Start the environment |
| `ddev stop` | Stop the environment |
| `ddev restart` | Restart after config changes |
| `ddev ssh` | SSH into the web container |
| `ddev drush cr` | Clear Drupal caches |
| `ddev drush uli` | Generate a one-time admin login link |
| `ddev drush cex` | Export configuration |
| `ddev drush cim` | Import configuration |
| `ddev composer require drupal/module_name` | Add a contrib module |
| `ddev describe` | Show URLs, database credentials, etc. |

### Theme Development Workflow

1. Edit SCSS files in `web/themes/custom/scdc/scss/`
2. Run `npm run watch` (from the theme directory) to auto-compile on save
3. Clear Drupal cache if Twig templates change: `ddev drush cr`
4. Customise council branding in `scss/abstracts/_variables.scss`
5. Add new components as partials in the appropriate 7-1 directory
6. Register new JS/CSS libraries in `scdc.libraries.yml`

### SCSS Linting

```bash
cd web/themes/custom/scdc
npm run lint:scss
```

---

## Accessibility

The SCDC theme is built with WCAG 2.1 AA compliance as a baseline:

- **Focus states** — GDS-compliant yellow (`#ffdd00`) focus ring on all interactive elements
- **Skip to content** — Skip link at the top of every page
- **Reduced motion** — Respects `prefers-reduced-motion` media query
- **High contrast** — Automatic adjustments via `prefers-contrast: high`
- **Semantic HTML** — ARIA landmarks, roles, and labels throughout templates
- **Keyboard navigation** — Full keyboard support including Escape to close menus

## Customisation

### Changing council branding

Edit `scss/abstracts/_variables.scss`:

```scss
// Primary palette — change these to your council colours
$color-primary:       #00594f;  // Main brand colour
$color-primary-light: #007a6c;
$color-primary-dark:  #003d36;
```

Then rebuild: `npm run build`

### Adding a new component

1. Create `scss/components/_my-component.scss`
2. Add `@use 'components/my-component';` in `scss/main.scss`
3. If it needs JS, create `js/my-component.js` and register in `scdc.libraries.yml`

### Adding a new page style

1. Create `scss/pages/_my-page.scss`
2. Add `@use 'pages/my-page';` in `scss/main.scss`

---

## Migration Context

This project is part of the **Umbraco → Drupal migration** for Local Government Reorganisation (LGR). Key considerations:

- **Content types** — Map Umbraco document types to Drupal content types (LocalGov Drupal provides many out of the box: services, guides, news, directories, etc.)
- **Custom modules** — Umbraco plugins/packages should be reimplemented as Drupal custom modules in `web/modules/custom/`
- **Templates** — Umbraco Razor views map to Drupal Twig templates
- **Media** — Umbraco media library maps to Drupal's Media module
- **Forms** — Umbraco Forms maps to Drupal Webform module

## License

This project is licensed under GPL-2.0-or-later, consistent with Drupal's licensing.
