=== Elementor to Bricks Media Migrator ===
Contributors: abidkp
Tags: media, migration, elementor, bricks, image metadata, alt text
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://buymeacoffee.com/abidkp

Page-aware media metadata migration from Elementor to Bricks for WordPress.

== Description ==

Elementor to Bricks Media Migrator is designed for website rebuilds and redesigns where the source Elementor site and target Bricks site have different media attachment IDs.

Developed by Abid KP (https://abidkp.com).

The source/export side scans Elementor document data, widgets, galleries, featured images and inline content images and exports page-wise image metadata to a clean JSON file. You can choose exactly which post types (Pages, Posts, or Custom Post Types) to scan and export.

The target/import side matches source pages to Bricks target pages by URL path/slug, scans images used by the Bricks page, and lets you visually map each source image to its matching target image.

The migrator copies:

* Media Title
* Alt Text
* Caption
* Description

No image files are replaced, deleted, uploaded, or modified. Only WordPress attachment metadata is updated.
After copying, you can also edit and fine-tune the target metadata directly in place and click "Save Target Changes".

== Key Features ==

* **Post Type Filtering**: Choose which post types (Pages, Posts, CPTs) to scan on export, and filter imported pages dynamically.
* **Elementor & Bricks Scanner**: Detects media in Elementor widgets, galleries, slides, Bricks elements, background images, and nested templates.
* **Editable Metadata**: Review and edit Title, Alt, Caption, and Description directly in the migrator before or after copying.
* **Safe & Isolated**: No direct connection between sites. Operates solely via an exported JSON file.
* **High Performance**: In-memory URL caching and constant-time image lookups.

== Typical workflow ==

1. Install the plugin on the source Elementor website.
2. Open Tools > Media Migrator.
3. Select the post types you want to scan and click "Download Production Media JSON".
4. Install the plugin on the target Bricks website.
5. Upload the JSON under Tools > Media Migrator.
6. Filter by post type if needed, and select a source page.
7. The plugin matches the corresponding Bricks page and lists the images used on both sides.
8. Select the matching target image.
9. Click "Copy Source → Target", or edit any field and click "Save Target Changes".

== Security ==

Only users with `manage_options` capability can access the plugin. Uploads, downloads, and AJAX operations are verified using WordPress nonces. Uploaded JSON files are stored in a protected directory inaccessible from the web.
