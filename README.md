# Elementor to Bricks Media Migrator

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-ffdd00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/abidkp)

A lightweight, secure, and production-ready WordPress plugin for migrating image metadata (Title, Alt Text, Caption, and Description) from Elementor pages to Bricks Builder pages during website redesigns and rebuilds.

Developed by **Abid KP** ([abidkp.com](https://abidkp.com)). Support this project: [![Buy Me a Coffee](https://img.shields.io/badge/☕%20Buy%20me%20a%20coffee-ffdd00?style=flat-square)](https://buymeacoffee.com/abidkp)

---

## 🎯 The Problem

When redesigning a WordPress website from Elementor to Bricks, images are often re-uploaded or newly imported with different attachment IDs. Re-entering Title, Alt text, captions, and descriptions across hundreds of images and pages is tedious, time-consuming, and prone to SEO loss.

Automatic image matchers frequently misassign cropped, renamed, or replaced images. 

**Elementor to Bricks Media Migrator solves this** by scanning the exact images used on each Elementor page and pairing them side-by-side with the images detected on the corresponding Bricks page for verified, 1-click manual metadata migration.

---

## ✨ Features

- **Selective Post Type Scanning**: Choose whether to scan and export standard Pages, Posts, or any Custom Post Type (CPT).
- **Elementor Deep Scanner**: Detects featured images, content images, Elementor widgets, galleries, carousels, and repeaters.
- **Bricks Deep Scanner**: Accurately detects Bricks image elements, background images, icon attachments, sliders, and nested section templates.
- **Safe & Non-Destructive**: Copies **metadata only** (Title, Alt, Caption, Description). Never deletes, replaces, or alters actual media files.
- **In-Place Metadata Editor**: Review and adjust Title, Alt, Caption, or Description directly in the migrator before or after copying, with instant save.
- **Progress Tracking & Visual Badges**: Visual indicators (`✓ Synced` vs `Pending`) and live progress bars show migration completion in real time.
- **Candidate Matching Hints**: Visually highlights target images with matching filenames to help you spot matches effortlessly without sacrificing manual verification.
- **Instant Search**: Real-time page title and slug search filter to navigate hundreds of pages smoothly.
- **Zero Remote Connection**: Production and staging communicate exclusively via a secure JSON file export—no direct database or API connection required.
- **High Performance & Security**:
  - $O(1)$ constant-time image lookup index.
  - In-memory URL-to-attachment caching to eliminate redundant database queries.
  - Upload validation with signature verification and protected `.htaccess` file storage.
  - Strict `manage_options` capability guards and WordPress nonces.

---

## 🚀 Workflow

```
[Production Site (Elementor)]                [Staging Site (Bricks)]
         │                                              │
         ▼                                              ▼
Select Post Types & Export JSON ────► Download ──► Upload JSON
                                                        │
                                                        ▼
                                              Select Source Page
                                                        │
                                                        ▼
                                              Auto-detect Bricks Page
                                                        │
                                                        ▼
                                              Side-by-side Image Review
                                                        │
                                                        ▼
                                              Copy / Edit Metadata ✓
```

### 1. On Production (Source Site)
1. Install and activate the plugin.
2. Go to **Tools > Media Migrator**.
3. Select which post types you want to scan (Pages, Posts, CPTs).
4. Click **Download Production Media JSON**.

### 2. On Staging (Target Site)
1. Install and activate the plugin on the new Bricks site.
2. Go to **Tools > Media Migrator** > **Import & Map**.
3. Upload the exported JSON file.
4. Filter by post type or search for a page title.
5. The migrator automatically detects the matching Bricks page and lists images side-by-side.
6. Click the matching target thumbnail for each image.
7. Click **Copy Source → Target**, or tweak the metadata in place and click **Save Target Changes**.

---

## 🔒 Security

- Restricted exclusively to users with `manage_options` capabilities.
- All forms and AJAX operations are secured using WordPress nonces.
- Uploaded export files are stored in a secured directory protected by `.htaccess` (`Deny from all`) and index silence guards.
- All text strings are properly sanitized and slashed using `wp_slash()`.

---

## 📄 License

GPL-2.0-or-later. Free to use, modify, and distribute.

## 👨‍💻 Author & Support

Developed with ❤️ by **Abid KP**  
- **Website**: [https://abidkp.com](https://abidkp.com)  
- **GitHub**: [@abidkakkur11](https://github.com/abidkakkur11)

If this tool saved you hours of manual metadata copying during your Elementor → Bricks migration, please consider supporting future updates and maintenance!

<a href="https://buymeacoffee.com/abidkp" target="_blank" rel="noopener noreferrer">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" height="46">
</a>
