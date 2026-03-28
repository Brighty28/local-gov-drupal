# LocalGov Localisation

A Drupal module providing postcode-based localisation services for UK local authorities. Designed to be shared across multiple councils — each council connects to their own Localisation API instance.

## What It Does

Enter a postcode, get personalised local information:

- **Address Lookup** — Find addresses by postcode or UPRN (via Alloy or OS Data Hub)
- **Geocoding** — Get lat/lng for a postcode
- **Bin Collections** — Next collection dates by premise ID
- **Councillors** — Local councillors by postcode or ward (via ModernGov)
- **Council Meetings** — Upcoming meetings filtered by authority (via ModernGov)
- **Planning Applications** — Nearby planning apps by postcode or UPRN
- **Planning Constraints** — Listed buildings, TPOs, conservation areas, flood zones
- **Local Events** — Events near a postcode by radius
- **Democracy / Elections** — Upcoming elections via Democracy Club (by postcode or UPRN)
- **Combined Localisation** — All of the above in one postcode lookup

## API Endpoint Mapping

The module maps to the following Localisation API endpoints (OpenAPI 3.0.4 spec):

| Drupal method | API endpoint | Parameters |
|--------------|-------------|------------|
| `addressPostcodeSearch()` | `GET /api/v1/Addresses/PostcodeSearch/{postcode}` | `?source=Alloy\|OSData` |
| `addressUprnSearch()` | `GET /api/v1/Addresses/UprnSearch/{uprn}` | `?source=Alloy\|OSData` |
| `geocode()` | `GET /api/v1/Addresses/Geocode/{postcode}` | |
| `collections()` | `GET /api/v1/Collections/Search/{premiseId}` | `?numberOfCollections=999&date=&includeBinEvents=false` |
| `democracyByPostcode()` | `GET /api/v1/DemocracyClubs/Postcode/{postcode}` | |
| `democracyByUprn()` | `GET /api/v1/DemocracyClubs/UPRN/{uprn}` | |
| `events()` | `GET /api/v1/Events/{postcode}` | `?radius=5` |
| `localise()` | `GET /api/v1/Localisations/{postcode}` | `?premiseId=&RadiusEvents=5&RadiusPlanning=2` |
| `councillorsByPostcode()` | `GET /api/v1/ModernGov/GetCouncillors/{postcode}` | |
| `councillorsByWard()` | `GET /api/v1/ModernGov/GetWardCouncillors/{ward}` | |
| `meetings()` | `GET /api/v1/ModernGov/GetEvents/{numberOfMonths}` | `?numberOfEvents=0&authoritySource=SCDC\|CCC\|All&excludeEventTitles=` |
| `planningByPostcode()` | `GET /api/v1/Planning/ApplicationByPostcode/{postcode}` | `?radius=5` |
| `planningByUprn()` | `GET /api/v1/Planning/ApplicationByUPRN/{uprn}` | `?radius=5` |
| `planningConstraints()` | `GET /api/v1/PlanningConstraints/{postcode}` | `?premiseId=&radiusPlanning=1&radiusListedBuilding=5&radiusConservationAreas=5&radiusTreePreservationOrder=5&radiusFloodZone=5&area=&reference=` |
| `listedBuildings()` | `GET /api/v1/PlanningConstraints/Get-Listed-Buildings/{postcode}` | `?radius=5&area=&reference=` |
| `treePreservation()` | `GET /api/v1/PlanningConstraints/Get-Tree-Preservation/{postcode}` | `?premiseId=&radius=5&area=&reference=` |
| `conservationAreas()` | `GET /api/v1/PlanningConstraints/Get-Conservation-Area/{postcode}` | `?premiseId=&radius=5&area=&reference=` |
| `floodZones()` | `GET /api/v1/PlanningConstraints/Get-Flood-Zone/{postcode}` | `?premiseId=&radius=5&area=&reference=` |

### API Enums

**AddressSource:** `Alloy` | `OSData`
**AuthoritySource:** `SCDC` | `CCC` | `All`

## Installation

```bash
ddev drush en localgov_localisation -y
ddev drush cr
```

## Configuration

Go to **Admin > Configuration > Web services > LocalGov Localisation**:

- **API Base URL** — e.g. `https://localisationapi.azurewebsites.net`
- **API Key** — if your instance requires authentication
- **Authority Source** — `SCDC`, `CCC`, or `All` (for ModernGov queries)
- **Address Source** — `Alloy` or `OSData` (for address lookup provider)
- **Cache Lifetime** — how long to cache responses (default: 1 hour)
- **Radii** — default search radii for events and planning
- **Enabled Services** — toggle individual API services on/off

## Usage

### "In My Area" Page

Visit `/in-my-area` — users enter a postcode and get AJAX results.

### Postcode Search Block

Place the **"In My Area - Postcode Search"** block in any region via Admin > Structure > Block layout.

### Drupal JSON Endpoints

For headless/decoupled use:

| Drupal endpoint | Proxies to |
|----------------|-----------|
| `GET /api/localisation/search/{postcode}` | `/api/v1/Localisations/{postcode}` |
| `GET /api/localisation/addresses/{postcode}` | `/api/v1/Addresses/PostcodeSearch/{postcode}` |
| `GET /api/localisation/collections/{premiseId}` | `/api/v1/Collections/Search/{premiseId}` |
| `GET /api/localisation/councillors/{postcode}` | `/api/v1/ModernGov/GetCouncillors/{postcode}` |
| `GET /api/localisation/planning/{postcode}` | `/api/v1/Planning/ApplicationByPostcode/{postcode}` |

### Using the API Client in Custom Code

```php
// Get the service.
$client = \Drupal::service('localgov_localisation.api_client');

// Combined lookup.
$data = $client->localise('CB2 1TN');

// Individual endpoints.
$addresses = $client->addressPostcodeSearch('CB2 1TN');
$councillors = $client->councillorsByPostcode('CB2 1TN');
$planning = $client->planningByPostcode('CB2 1TN', 3.0);
$constraints = $client->planningConstraints('CB2 1TN');
$meetings = $client->meetings(6, 10, 'SCDC');
$collections = $client->collections('PREMISE-ID-HERE');
```

## Multi-Council Deployment

This module is designed for sharing across councils during LGR:

1. Each council deploys their own Localisation API instance
2. Install this module via Composer
3. Configure the API URL and authority source
4. Toggle which services their API supports
5. Same module, different config

## Requirements

- Drupal 10.3+ or 11
- A running Localisation API instance (OpenAPI 3.0.4 compatible)
- PHP 8.1+

## License

GPL-2.0-or-later
