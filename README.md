# OBD Příspěvky

**OBD Příspěvky** is a WordPress plugin that loads and displays XML records from a custom XML source with fully customizable output formatting. The plugin supports display via a shortcode (`[obd_prispevky]`) and lets you define a single HTML template using placeholders (e.g. `{autor}`, `{nazev}`, `{rok}`, `{issn}`, `{zdroj}`, `{cislo}`, `{id}`).

## Features

- Loads XML records from a custom XML source.
- Customizable output using a single HTML template with placeholders.
- Shortcode support: `[obd_prispevky]`.
- Supports multi-level sorting by specifying sort fields and sort orders via shortcode parameters (e.g. `sort="rok,autor"` and `order="desc,asc"`).
- Record limiting via shortcode (e.g. `limit="5"`).

## Installation

1. Download the plugin zip from the release.
2. Install the plugin via the WordPress admin dashboard.
3. Activate the plugin via the WordPress admin dashboard.
4. Configure the plugin settings under **Settings > OBD Příspěvky**.

## Usage

- **Shortcode:**  
  Insert the shortcode into your posts or pages. Examples:
  - Basic usage (displays all records as they are in the XML):  
    `[obd_prispevky]`
  - With sorting and limiting (e.g. sort by year descending and then by author ascending, showing only 5 records):  
    `[obd_prispevky sort="rok,autor" order="desc,asc" limit="5"]`

- **Template Configuration:**  
  In the plugin settings (under **Settings > OBD Příspěvky**), you can paste your XML and define a single output template (pseudo code) using HTML and placeholders. For example:
  ```html
  <strong>{nazev}</strong> ({rok})<br>Autoři: {autor}<br>ISSN: {issn}, Zdroj: {zdroj}, Číslo: {cislo}, ID: {id}<br><br>

## License

This project is licensed under the [MIT License](LICENSE).