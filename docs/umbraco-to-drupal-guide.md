# Umbraco to Drupal: A Developer's Guide

This guide maps Umbraco CMS concepts to their Drupal equivalents. If you're coming from Umbraco and learning Drupal for the first time, this is your reference.

---

## Quick Reference Table

| Umbraco | Drupal | Notes |
|---------|--------|-------|
| **Back Office** | **Admin** (`/admin`) | Drupal's admin UI |
| **Document Type** | **Content Type** | Admin > Structure > Content types |
| **Template** | **Twig Template** | `.html.twig` files in theme |
| **Partial View** | **Twig include/embed** | `{% include %}` or `{% embed %}` |
| **Macro** | **Block** | Reusable content chunks |
| **Data Type** | **Field Type** | Text, Image, Entity Reference, etc. |
| **Property** | **Field** | Fields are attached to Content Types |
| **Media** | **Media** | Admin > Content > Media |
| **Content Node** | **Node** | Every piece of content is a "node" |
| **Content Tree** | **Content overview** | Admin > Content (flat list, not tree) |
| **Composition** | **Paragraphs / Layout Builder** | Reusable field groups |
| **Nested Content** | **Paragraphs** | Flexible content components |
| **Grid Editor** | **Layout Builder / Layout Paragraphs** | Visual layout editing |
| **Package** | **Module** | Contrib or custom PHP modules |
| **NuGet** | **Composer** | `composer require drupal/module_name` |
| **Surface Controller** | **Controller / Form** | Custom PHP routes and forms |
| **Examine (Lucene)** | **Search API** | Search indexing and queries |
| **Umbraco Forms** | **Webform** module | Form builder |
| **Members** | **Users** | Built into Drupal core |
| **Member Groups** | **Roles** | Admin > People > Roles |
| **User Groups/Sections** | **Permissions** | Admin > People > Permissions |
| **Dictionary Items** | **Interface Translation** | Admin > Config > Translation |
| **Backoffice Sections** | **Admin Menu** | Toolbar + admin routes |
| **Content Apps** | **Local Tasks (Tabs)** | Tabs on node pages |
| **Dashboards** | **Admin Dashboard / Views** | Custom admin views |
| **Webhooks** | **Hook system / Events** | PHP hooks or Symfony events |
| **Deploy / Courier** | **Config Export/Import** | `drush cex` / `drush cim` |
| **uSync** | **Config Sync** | Built into Drupal core |
| **Property Editors** | **Field Widgets** | How fields appear in edit forms |
| **Value Converters** | **Field Formatters** | How fields render on the frontend |

---

## 1. Content Modelling

### Umbraco: Document Types → Drupal: Content Types

In Umbraco, you create **Document Types** with **Properties** (fields) grouped into **Tabs**. In Drupal, the equivalent is:

**Where:** Admin > Structure > Content types > Add content type

```
Umbraco Document Type          →  Drupal Content Type
├── Tab: Content               →  Field Group (via field_group module)
│   ├── Property: pageTitle    →  Field: field_page_title (Text)
│   ├── Property: bodyText     →  Field: body (Text with summary)
│   └── Property: heroImage    →  Field: field_hero_image (Media)
├── Tab: SEO                   →  Field Group: SEO
│   ├── Property: metaTitle    →  Metatag module handles this
│   └── Property: metaDesc     →  Metatag module handles this
└── Allowed child types        →  Entity Hierarchy / Menu structure
```

### Key Differences

| Concept | Umbraco | Drupal |
|---------|---------|--------|
| Content hierarchy | Tree structure (parent/child) | Flat nodes + menu system + Entity Hierarchy |
| Templates | 1:1 Document Type → Template | Template suggestions (multiple templates per type) |
| Allowed children | Set on Document Type | No built-in equivalent (use Entity Hierarchy module) |
| Compositions | Shared property groups across types | Shared fields (same field, multiple content types) |

### Creating a Content Type (step by step)

1. Go to **Admin > Structure > Content types**
2. Click **Add content type**
3. Enter name (e.g. "Service Page") and description
4. Go to **Manage fields** tab
5. Click **Add field** — choose field type, set label
6. Go to **Manage form display** — arrange field order and widgets (like Umbraco property editors)
7. Go to **Manage display** — control how fields render (like Umbraco value converters)

---

## 2. Templates

### Umbraco: Razor Views → Drupal: Twig Templates

| Umbraco (Razor/C#) | Drupal (Twig) |
|---------------------|---------------|
| `@Model.Value("title")` | `{{ node.label }}` or `{{ content.field_title }}` |
| `@Model.Value<IPublishedContent>("image")` | `{{ content.field_image }}` |
| `@Umbraco.RenderMacro("myMacro")` | `{{ drupal_block('block_id') }}` |
| `@Html.Partial("_header")` | `{% include '@scdc/layout/includes/header.html.twig' %}` |
| `@RenderBody()` | `{{ page.content }}` |
| `@RenderSection("scripts")` | Libraries system (`*.libraries.yml`) |
| `@inherits UmbracoViewPage` | `{% extends 'base-template.html.twig' %}` |

### Template File Location

```
Umbraco:  /Views/ServicePage.cshtml
Drupal:   /web/themes/custom/scdc/templates/node/node--localgov-services-page.html.twig
```

### Template Naming Convention (Drupal "Suggestions")

Drupal auto-discovers templates by naming convention. This is like Umbraco's 1:1 mapping but more flexible:

```
node.html.twig                          ← Fallback for all nodes
node--localgov-services-page.html.twig  ← Specific content type
node--42.html.twig                      ← Specific node by ID
node--localgov-services-page--full.html.twig  ← Type + view mode
```

**View modes** are like Umbraco's different rendering contexts:
- `full` = full page view
- `teaser` = listing/card view
- `search_result` = search results

### Where Templates Live

```
web/themes/custom/scdc/templates/
├── layout/          ← page.html.twig, html.html.twig
├── node/            ← Node templates (per content type)
├── block/           ← Block templates
├── field/           ← Field templates
├── views/           ← Views (query results) templates
├── paragraph/       ← Paragraph type templates
├── navigation/      ← Menus, breadcrumbs
├── form/            ← Form templates
└── misc/            ← Messages, pagers, etc.
```

### Common Twig Syntax

```twig
{# Variable output (like @Model.Value) #}
{{ content.field_name }}

{# Conditionals (like @if in Razor) #}
{% if content.field_hero_image is not empty %}
  <div class="hero">{{ content.field_hero_image }}</div>
{% endif %}

{# Loops (like @foreach in Razor) #}
{% for item in items %}
  <li>{{ item.content }}</li>
{% endfor %}

{# Raw field value (like Model.Value<string>) #}
{{ node.field_name.value }}

{# Include partial (like @Html.Partial) #}
{% include '@scdc/layout/includes/header.html.twig' %}

{# Extend a layout (like @inherits + @RenderBody) #}
{% extends 'page.html.twig' %}
{% block content %}
  ...
{% endblock %}
```

---

## 3. The Admin Interface

### Where Things Live

| Task | Umbraco | Drupal |
|------|---------|--------|
| Create/edit content | Content section (tree) | Admin > Content |
| Create content types | Settings > Document Types | Admin > Structure > Content types |
| Manage media | Media section | Admin > Content > Media |
| Upload files | Media section | Admin > Content > Media > Add media |
| Manage menus | None (tree = navigation) | Admin > Structure > Menus |
| Configure blocks/widgets | None (macros/partials) | Admin > Structure > Block layout |
| Manage users | Members section | Admin > People |
| Set permissions | Users section | Admin > People > Permissions |
| Manage forms | Umbraco Forms | Admin > Structure > Webforms |
| Site settings | Settings section | Admin > Configuration |
| Install packages | Packages section | `composer require` via CLI |
| View logs | Log Viewer | Admin > Reports > Recent log messages |
| Rebuild cache | Published cache | `ddev drush cr` or Admin > Config > Performance |

### Drupal Admin Toolbar

The admin toolbar at the top of every page is your primary navigation:
- **Content** — create/edit nodes and media
- **Structure** — content types, block layout, menus, views, taxonomies
- **Appearance** — theme settings
- **Extend** — enable/disable modules
- **Configuration** — site settings, performance, file system
- **People** — users, roles, permissions
- **Reports** — status, logs, updates

---

## 4. Blocks vs Macros

### Umbraco Macros → Drupal Blocks

In Umbraco, you use **Macros** for reusable components (e.g. a contact widget, related links panel). In Drupal, the equivalent is **Blocks**.

**Where:** Admin > Structure > Block layout

Types of blocks:
- **Content blocks** — custom HTML blocks (like Umbraco's Rich Text macro)
- **Views blocks** — dynamic content queries (like Umbraco's content picker with filtering)
- **System blocks** — breadcrumbs, menus, search form, user login
- **Custom blocks** — built via custom module code

### Placing Blocks in Regions

Drupal pages have **regions** (header, footer, sidebar, content, etc.). You place blocks into regions:

1. Go to **Admin > Structure > Block layout**
2. You'll see all regions listed (Header, Content, Sidebar, Footer, etc.)
3. Click **Place block** next to a region
4. Choose or create a block
5. Configure visibility (show on specific pages, content types, user roles)

This is fundamentally different from Umbraco where you embed macros in templates. In Drupal, blocks are configured in the UI and rendered in template regions.

---

## 5. Views (No Umbraco Equivalent — This Is a Game Changer)

**Views** is Drupal's visual query builder. There's no direct Umbraco equivalent — it replaces custom C# controllers and Examine queries.

**Where:** Admin > Structure > Views

With Views you can:
- List content by type, date, taxonomy, etc.
- Create news listings, event calendars, directory pages
- Output as pages, blocks, or REST endpoints
- Add filters, sorts, paging, and exposed filters (user-facing search)

### Example: "Latest News" listing

1. Admin > Structure > Views > Add view
2. Name: "Latest News"
3. Show: Content, of type: News article, sorted by: Newest first
4. Create a block display (to place in a region) or page display (with its own URL)
5. Add fields: Title, Image, Published date
6. Set pager: 3 items

This replaces what would be a custom controller + Examine query + partial view in Umbraco.

---

## 6. Taxonomy (Tags/Categories)

### Umbraco: Tags → Drupal: Taxonomy

In Umbraco, you might use Tags or create custom data types for categories. In Drupal, **Taxonomy** is a first-class feature.

**Where:** Admin > Structure > Taxonomy

1. Create a **Vocabulary** (e.g. "Service Categories", "News Topics")
2. Add **Terms** to it (e.g. "Housing", "Planning", "Council Tax")
3. Add an **Entity Reference** field to your content type, pointing to the vocabulary
4. Content editors pick terms when creating content
5. Use Views to create listing pages filtered by taxonomy

---

## 7. Configuration Management

### Umbraco: uSync → Drupal: Config Sync

This is a major Drupal strength. All site configuration (content types, fields, views, block placement, etc.) is exportable as YAML files.

```bash
# Export all config to files (like uSync export)
ddev drush cex

# Import config from files (like uSync import)
ddev drush cim
```

Config files are stored in `config/sync/` (or wherever configured). They get committed to git, so your content types, views, and settings are all version-controlled.

**This replaces:** uSync, Deploy/Courier, and manual re-creation of settings across environments.

---

## 8. Modules vs Packages

### Umbraco: NuGet Packages → Drupal: Composer + Modules

| Action | Umbraco | Drupal |
|--------|---------|--------|
| Install a package | `dotnet add package Umbraco.Forms` | `ddev composer require drupal/webform` |
| Enable it | Automatic on install | `ddev drush en webform -y` |
| Find packages | NuGet / Our Umbraco | [drupal.org/project/project_module](https://www.drupal.org/project/project_module) |
| Create a custom package | Class library + NuGet | Custom module in `web/modules/custom/` |

### Key Contrib Modules (Umbraco Package Equivalents)

| Umbraco Package | Drupal Module | Install |
|----------------|--------------|---------|
| Umbraco Forms | **Webform** | `composer require drupal/webform` |
| Examine | **Search API** | Already installed (LocalGov) |
| uSync | **Config Sync** | Built into core |
| Nested Content | **Paragraphs** | Already installed (LocalGov) |
| Our.ModelsBuilder | Not needed | Drupal uses Entity API |
| SEO Checker | **Metatag + Pathauto** | Already installed (LocalGov) |
| Umbraco Deploy | **Config Sync + Drush** | Built into core |

---

## 9. Custom Module Development

### Umbraco: Controllers + Services → Drupal: Modules

In Umbraco, you write C# controllers, services, and composers. In Drupal, you write PHP modules.

### Module Structure

```
web/modules/custom/my_module/
├── my_module.info.yml          ← Module definition (like package.json)
├── my_module.module            ← Hook implementations (like Composers)
├── my_module.routing.yml       ← URL routes (like controllers)
├── my_module.services.yml      ← Dependency injection services
├── my_module.permissions.yml   ← Custom permissions
├── src/
│   ├── Controller/             ← Route controllers (like SurfaceControllers)
│   ├── Form/                   ← Admin forms (like backoffice dashboards)
│   ├── Plugin/
│   │   ├── Block/              ← Custom blocks (like macros)
│   │   └── Field/              ← Custom field types
│   └── Service/                ← Business logic services
├── config/
│   └── install/                ← Default config installed with module
└── templates/                  ← Twig templates for this module
```

### Umbraco Concepts → Drupal Equivalents

```csharp
// Umbraco: Surface Controller
public class SearchController : SurfaceController
{
    public IActionResult Search(string query) { ... }
}
```

```php
// Drupal: Controller (in src/Controller/SearchController.php)
namespace Drupal\my_module\Controller;

use Drupal\Core\Controller\ControllerBase;

class SearchController extends ControllerBase {
  public function search(string $query) {
    return ['#markup' => 'Search results for: ' . $query];
  }
}
```

```yaml
# Drupal: Route (in my_module.routing.yml)
my_module.search:
  path: '/search'
  defaults:
    _controller: '\Drupal\my_module\Controller\SearchController::search'
    _title: 'Search'
  requirements:
    _permission: 'access content'
```

---

## 10. Essential Drush Commands

**Drush** is Drupal's CLI tool — like Umbraco's built-in CLI but far more powerful.

```bash
# Cache (like rebuilding Umbraco's published cache)
ddev drush cr                    # Clear all caches

# Content
ddev drush content:list          # List content

# Modules (like NuGet)
ddev drush en module_name -y     # Enable a module
ddev drush pm:uninstall module   # Remove a module
ddev drush pm:list               # List all modules

# Config (like uSync)
ddev drush cex                   # Export config to files
ddev drush cim                   # Import config from files

# Users
ddev drush uli                   # Generate one-time admin login link
ddev drush user:create bob       # Create a user

# Database
ddev drush sql:dump > backup.sql # Backup database
ddev drush updatedb              # Run database updates

# Theme
ddev drush theme:enable scdc     # Enable a theme
ddev drush cr                    # Clear cache after template changes
```

---

## 11. Quick Wins: Common Tasks

### "I want to create a new page type"
Admin > Structure > Content types > Add content type > Add fields > Save

### "I want to add a field to an existing type"
Admin > Structure > Content types > [Type] > Manage fields > Add field

### "I want to create a listing page"
Admin > Structure > Views > Add view > Choose content type > Configure fields/filters

### "I want to add something to the sidebar"
Admin > Structure > Block layout > Sidebar region > Place block

### "I want to create a menu"
Admin > Structure > Menus > Add menu > Add links

### "I want to change how a page looks"
Edit the Twig template in your theme's `templates/` directory, then `ddev drush cr`

### "I want to add CSS/JS"
Add to your theme's `scss/` directory, rebuild with `npm run build`, clear cache

### "I want to install a contributed module"
```bash
ddev composer require drupal/module_name
ddev drush en module_name -y
```

---

## 12. Debugging

| Umbraco | Drupal |
|---------|--------|
| `Umbraco.Cms.Web.Common.Profiler` | **Webprofiler** module or Xdebug |
| Log Viewer | Admin > Reports > Recent log messages |
| `ILogger<T>` | `\Drupal::logger('my_module')->error('message')` |
| ModelsBuilder debug | Twig debug: add `{{ dump() }}` |
| `?umbDebug=true` | Enable Twig debug in `services.yml` |

### Enable Twig Debugging

Edit `web/sites/default/services.yml` (or create `development.services.yml`):

```yaml
parameters:
  twig.config:
    debug: true
    auto_reload: true
    cache: false
```

Then `ddev drush cr`. You'll see HTML comments showing which template is used and suggestions for overrides.

---

## Summary: Mental Model Shift

1. **Tree → Flat + Menus**: Drupal content is flat. Use menus and Entity Hierarchy for structure.
2. **Everything in code → UI + export**: Build content types, views, and blocks in the UI, then export config with `drush cex`.
3. **Razor → Twig**: Similar logic, different syntax. Twig is simpler.
4. **NuGet → Composer**: Same idea, different tool.
5. **Surface Controllers → Routing + Controllers**: Drupal uses Symfony routing.
6. **Published cache → Cache API**: Drupal has a sophisticated cache system. When in doubt, `drush cr`.
7. **No query builder → Views**: Views replaces most custom C# queries you'd write in Umbraco.
