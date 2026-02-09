# UrlParameter – Matomo Plugin

A Matomo plugin that lists all URL query string parameters found in tracked page URLs and shows how often each parameter appears (pageviews).

## Features

- Shows all query parameters extracted from tracked page URLs
- Displays the number of pageviews each parameter appeared in
- Drill-down into individual parameter values with their respective hit counts
- Available via the Matomo UI (under **Actions > URL Parameters**) and the HTTP API
- Supports segmentation and all standard period types (day, week, month, year, range)

## Requirements

- Matomo >= 4.0.0

## Installation

1. Clone or download this repository into your Matomo `plugins/` directory **as `UrlParameter`**:

   ```bash
   cd /path/to/matomo/plugins
   git clone <repo-url> UrlParameter
   ```

2. Activate the plugin in Matomo under **Administration > Plugins**, or via CLI:

   ```bash
   ./console plugin:activate UrlParameter
   ```

3. The report will appear under **Actions > URL Parameters** after the next archiving run.

## API

### `UrlParameter.getUrlParameters`

Returns a DataTable of all URL query parameters with pageview counts.

| Parameter | Type   | Description                        |
|-----------|--------|------------------------------------|
| idSite    | int    | Site ID                            |
| period    | string | `day`, `week`, `month`, `year`, `range` |
| date      | string | Date or date range                 |
| segment   | string | (optional) Segment definition      |

Each row contains a subtable with individual parameter values. Use `expanded=1` to retrieve them in a single request.

**Example:**

```
?module=API&method=UrlParameter.getUrlParameters&idSite=1&period=day&date=today&format=JSON
```

## License

GPL v3 or later
