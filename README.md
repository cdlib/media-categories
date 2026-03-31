# Media Categories

Current version: `1.0.3`

Media Categories is a WordPress plugin that adds a hierarchical `media_category`
taxonomy to attachments and presents those terms as folder-like organization
tools inside the Media Library.

The plugin is taxonomy-based rather than filesystem-based. Media items can
belong to multiple categories, and parent categories can be used like virtual
folders that include descendant items.

## Open Source

This project is open source under the MIT License. See [LICENSE](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/LICENSE) for the full text.

## Getting Started

1. Clone the repository.
2. Place the plugin in your WordPress installation under `wp-content/plugins/media-categories`.
3. Activate `Media Categories` from the WordPress admin.
4. Optional: install Composer dev dependencies for linting and tests.

## Features

- Native taxonomy management under `Media > Media Categories`
- Settings page under `Settings > Media Categories`
- Role-based control over who can manage media categories
- Multi-category assignment for attachments
- Automatic ancestor selection when a child category is assigned
- Category filtering in Media Library list view
- Virtual folder sidebar in Media Library grid view
- Built-in `All Files` and `Uncategorized` virtual folders
- Create, rename, and delete folder actions from the grid sidebar

## Plugin Structure

- [media-categories.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/media-categories.php): plugin bootstrap and metadata
- [includes/class-plugin.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-plugin.php): service registration
- [includes/class-taxonomy.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-taxonomy.php): taxonomy registration
- [includes/class-settings.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-settings.php): admin settings page
- [includes/class-attachment-fields.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-attachment-fields.php): attachment assignment UI
- [includes/class-media-filters.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-media-filters.php): list and grid filtering
- [includes/class-folder-sidebar.php](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/includes/class-folder-sidebar.php): virtual folder sidebar and AJAX actions
- [assets/js/media-grid.js](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/assets/js/media-grid.js): grid-mode interactions
- [assets/css/admin.css](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/assets/css/admin.css): admin-side styling

## Local Development

Plugin path:
`/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories`

WordPress install path:
`/Users/local-esatzman/Desktop/Sites/media-categories/app/public`

### Useful Commands

```bash
php -l media-categories.php
php -l includes/class-folder-sidebar.php
php -l includes/class-attachment-fields.php
git status
```

If Composer dependencies are installed, the repository also includes:

```bash
composer lint
composer test
```

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/CONTRIBUTING.md) before opening a pull request.

## Security

If you discover a security issue, please follow the reporting guidance in [SECURITY.md](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/SECURITY.md) instead of opening a public issue.

## Code of Conduct

Participation in this project is governed by [CODE_OF_CONDUCT.md](/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories/CODE_OF_CONDUCT.md).

## Current Behavior Notes

- Parent category filters include descendant attachments.
- Sidebar counts are based on the same visible query logic as the grid filter.
- The grid sidebar defaults to open and persists collapse state for the current
  browser session only.
- The top toolbar category dropdown is hidden while the sidebar is open and
  shown when the sidebar is collapsed.

## Status

The plugin is in a refinement and stabilization phase. Core media-category
assignment, filtering, and virtual-folder behavior are implemented, with most
remaining work centered around browser validation and UI polish.

## License

MIT
