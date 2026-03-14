# Media Categories Handoff

This document is meant to let a new context window pick up work quickly on the `Media Categories` plugin without rereading the entire chat history.

## Repository / Location

- Local plugin path: `/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories`
- GitHub repo: `https://github.com/ericsatzman/media-categories`
- Current branch: `main`
- Current HEAD when this handoff was written: `d8b5d05f2cf6404bc3096b37a5f29487eced90f5`

## Product Goal

This plugin creates a WordPress attachment taxonomy called `media_category` and adds:

- Native taxonomy management under `Media > Media Categories`
- Settings under `Settings > Media Categories`
- Role-based control over who can manage categories
- Multi-category assignment for media items
- Category filtering in the media library
- A virtual-folder sidebar in the media library grid

The user wants the experience to feel similar to folder-based media plugins, but the underlying data model is taxonomy-based, not filesystem-based.

## User Decisions Already Made

- Media items can belong to multiple categories.
- Use the native taxonomy UI for the Media Categories management screen.
- Virtual folders should appear in the left panel of the Media Library.
- Clicking a parent folder should include descendant items.
- Uncategorized should appear as a built-in virtual folder.
- All users who can work with media should be able to assign categories.
- Category management permissions are controlled from `Settings > Media Categories`.
- The menu label under Media should be `Media Categories`.
- If a child category is checked in attachment details, ancestor categories should also be checked/saved.

## Current Architecture

### Bootstrap / Core

- `media-categories.php`
  - Main plugin header
  - `Author` is set to `Eric Satzman`
  - `Update URI` points to the GitHub repo to avoid WP.org plugin collision
- `includes/class-plugin.php`
  - Bootstraps all services

### Taxonomy / Permissions

- `includes/class-taxonomy.php`
  - Registers `media_category` on `attachment`
  - `show_in_rest => true`
  - `hierarchical => true`
  - uses `_update_generic_term_count`
- `includes/class-capabilities.php`
  - Syncs custom management caps to selected roles
- `includes/class-settings.php`
  - Settings page under `Settings > Media Categories`
  - Role checkboxes determine who can manage categories
- `includes/class-admin-menu.php`
  - Adds native taxonomy screen under `Media > Media Categories`

### Assignment / Filtering

- `includes/class-attachment-fields.php`
  - Attachment edit screen metabox
  - Media modal details pane category UI
  - Saves ancestor terms automatically
  - Renders hierarchical category tree in the modal UI
- `includes/class-media-filters.php`
  - List mode taxonomy dropdown
  - Grid mode taxonomy filtering via `ajax_query_attachments_args`
  - Parent filter includes descendants

### Virtual Folders

- `includes/class-folder-sidebar.php`
  - Renders the left sidebar on the media library screen
  - Shows `All Files`, `Uncategorized`, and taxonomy tree
  - Computes direct term counts via real attachment queries
  - Rolls child counts into parent totals
  - Supports AJAX create / rename / delete term actions

### Assets

- `includes/class-assets.php`
  - Loads admin CSS/JS on media screens and taxonomy screen
- `assets/js/media-grid.js`
  - Grid filtering
  - Sidebar click handling
  - Folder toolbar controls
  - Parent-category enforcement in modal checkbox UI
  - Sidebar collapse/expand behavior
  - Folder create dialog with parent dropdown
  - Sidebar count refresh after attachment compat saves
- `assets/js/taxonomy-admin.js`
  - On native taxonomy add form, resets parent dropdown to `None` after successful add
- `assets/css/admin.css`
  - Sidebar UI
  - Folder tree styles
  - Collapse/open animations
  - Toolbar layout overrides
  - Dialog styling

## Important Recent Fixes

### 1. Plugin identity / updates

- Added `Update URI` to stop WordPress from offering updates from the old WP.org plugin named `Media Categories`.

### 2. Attachment details category persistence

- The media modal originally only persisted the last checked category due to WordPress compat-form autosave behavior.
- Current solution:
  - hidden aggregate field in modal
  - JS keeps hidden field synced with all checked categories
  - save callback parses and saves the full selection

### 3. Parent category behavior

- If a child category is selected:
  - ancestors are auto-added on save
  - ancestors render as checked on reopen
  - modal UI also checks ancestors client-side immediately

### 4. Virtual folder counting

- Sidebar counts do not rely on native taxonomy count alone.
- Direct counts come from attachment queries.
- Parent counts sum child counts recursively.

### 5. Parent folder filtering

- Parent virtual folders include descendant media items.

### 6. Sidebar refresh after attachment edits

- `assets/js/media-grid.js` listens for the WordPress `attachment:compat:ready` event.
- On save completion, it fetches the current page and replaces the sidebar markup so counts update without a full page reload.

## Current UI Behavior

### Media Library, left sidebar open

- Shows:
  - `New Folder`
  - `Rename`
  - `Delete`
  - `Sort`
  - folder search box
  - `All Files`
  - `Uncategorized`
  - hierarchical categories with divider after `Uncategorized`
- When sidebar is open, the top `All media categories` toolbar dropdown is intentionally hidden to reduce toolbar crowding.

### Media Library, left sidebar closed

- Sidebar collapses into a narrow rail with a toggle button.
- Grid should return to WordPress default thumbnail sizing.
- `All media categories` dropdown should show in the top toolbar when sidebar is closed.

## Likely Remaining UX / Layout Work

The plugin is usable, but the layout is still the area most likely to need follow-up polish.

### Most likely open issue

The user repeatedly reported toolbar/search spacing issues. The latest approach was:

- hide the top category dropdown when the sidebar is open
- keep it visible when the sidebar is closed
- tighten widths/spacings in `admin.css`
- set the media toolbar container `right: 16px` to force visible right margin

This may still need visual validation in the browser. If the right margin still does not appear, inspect the live DOM/CSS on:

- `.attachments-browser .media-toolbar`
- `.media-frame-toolbar .media-toolbar`
- `.media-toolbar-primary.search-form`
- `.media-toolbar-primary.search-form input[type="search"]`

The issue is almost certainly caused by a core media toolbar positioning rule overriding our spacing expectations.

### Other polish areas that may still need work

- Sidebar collapse/open animation can likely be refined further.
- Folder sort/search interactions are functional but basic.
- Folder create dialog uses a simple custom modal, not WordPress component APIs.
- No inline notices/toasts yet; most failures use `window.alert`.

## Files Most Worth Reading First

If resuming work, start here:

1. `includes/class-folder-sidebar.php`
2. `assets/js/media-grid.js`
3. `assets/css/admin.css`
4. `includes/class-assets.php`
5. `includes/class-attachment-fields.php`
6. `includes/class-media-filters.php`

## Known Constraints / Environment Notes

- The WordPress install is at:
  - `/Users/local-esatzman/Desktop/Sites/media-categories/app/public`
- The plugin repo itself lives inside:
  - `/Users/local-esatzman/Desktop/Sites/media-categories/app/public/wp-content/plugins/media-categories`
- Network access for GitHub push worked.
- `php -l` was used repeatedly and passed for changed PHP files.
- Full runtime verification via CLI was limited because the local WordPress database connection was not reliably available from CLI in earlier attempts.
- `phpcs` and `phpunit` were not available in the environment when checked.

## Git History Summary

- `22e2253` `Add initial Media Categories plugin`
- `e01563a` `Fix category syncing and virtual folder behavior`
- `202a586` `Add media library folder controls`
- `d8b5d05` `Polish media library folder UI`

## Suggested Next Steps

If continuing work, this is the best order:

1. Open the Media Library in grid mode and validate:
   - sidebar open state
   - sidebar closed state
   - top toolbar one-line fit
   - visible right margin on search box
2. If toolbar spacing is still wrong, inspect the live media toolbar DOM/CSS and simplify the override strategy rather than layering more rules.
3. Validate create/rename/delete folder flows after the newer dialog/search/sort changes.
4. Verify that folder counts update after:
   - modal attachment save
   - native attachment edit screen save
5. If needed, add lightweight admin notices instead of alert dialogs.

## Current Intent

The plugin is already fairly far along. The next context window should treat this as a refinement / stabilization phase, not a greenfield build.
