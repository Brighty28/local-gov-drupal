# LocalGov Localisation

A Drupal module providing postcode-based localisation services for UK local authorities. Designed to be shared across multiple councils — each council connects to their own Localisation API instance.

## What It Does

Enter a postcode, get personalised local information:

- **Address Lookup** — Find addresses by postcode or UPRN
- **Bin Collections** — Next collection dates by premise
- **Your Councillors** — Local councillors by ward/postcode
- **Planning Applications** — Nearby planning apps with status
- **Planning Constraints** — Listed buildings, TPOs, conservation areas, flood zones
- **Local Events** — Events near a postcode
- **Democracy / Elections** — Upcoming elections via Democracy Club
- **Combined Search** — All of the above in one postcode lookup

## Installation

```bash
# Enable the module
ddev drush en localgov_localisation -y
ddev drush cr
```

## Configuration

1. Go to **Admin > Configuration > Web services > LocalGov Localisation**
2. Enter your **API Base URL** (e.g. `https://localisationapi.azurewebsites.net`)
3. Enter your **API Key** if required
4. Set your **Authority Identifier** (e.g. `SCDC`, `CCC`)
5. Toggle which services are enabled
6. Adjust cache lifetime and search radii

## Usage

### "In My Area" Page

The module provides a page at `/in-my-area` where users can search by postcode. This page uses AJAX for instant results without page reload.

### Postcode Search Block

Place the "In My Area - Postcode Search" block in any region:

1. Go to **Admin > Structure > Block layout**
2. Click **Place block** in your chosen region
3. Search for **In My Area - Postcode Search**
4. Save

### API Endpoints

The module exposes JSON endpoints for headless/decoupled use:

| Endpoint | Description |
|----------|-------------|
| `GET /api/localisation/search/{postcode}` | Combined localisation data |
| `GET /api/localisation/addresses/{postcode}` | Address lookup |
| `GET /api/localisation/collections/{premiseId}` | Bin collections |
| `GET /api/localisation/councillors/{postcode}` | Councillors |
| `GET /api/localisation/planning/{postcode}` | Planning applications |

## Multi-Council Deployment

This module is designed to work across multiple councils during LGR. Each council:

1. Deploys their own Localisation API instance
2. Configures the module to point at their API
3. Sets their authority identifier
4. Toggles which services their API supports

The same Drupal module works for all councils — only the config changes.

## Requirements

- Drupal 10.3+ or 11
- A running Localisation API instance
- PHP 8.1+

## License

GPL-2.0-or-later
