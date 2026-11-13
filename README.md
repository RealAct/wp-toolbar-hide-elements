# WP Toolbar Hide Elements

A small WordPress snippet that hides items from the **Toolbar (Admin Bar)** by their visible name, e.g. "Theme Options", "Rank Math SEO" or "Smart Slider". It applies to every user and role, across the whole admin area and (optionally) the front-end toolbar.

## How it works

1. **Server-side removal (main method).** After every plugin has added its items, the snippet looks at each Toolbar node, turns its title into plain text, and removes it (plus all its sub-items) when it matches your list. Removed items are never sent to the browser, so there is no flicker. It runs twice: on `admin_bar_menu` at the latest priority, and again on `wp_before_admin_bar_render` for items added late.
2. **JavaScript fallback.** A rare plugin injects its item with JavaScript after the page loads. A small script hides those in the browser with the same rules, and keeps watching the Toolbar for new items.

Name matching ignores upper/lower case, accents, extra spaces, icons and counter badges, so:

- `Fluent Forms Pro` matches "Fluent Forms Pro **22**"
- `Anadir` or `Añadir` matches "Añadir"
- Write names in the language your site shows (e.g. `Fragmentos de código`, `Vaciar caché`).

## Installation

**Option A: Code Snippets plugin**
1. Snippets → Add New, type *PHP*.
2. Paste the contents of [`wp-toolbar-hide-elements.php`](wp-toolbar-hide-elements.php) **without the first `<?php` line**.
3. Edit the configuration (below), choose **Run snippet everywhere**, and activate.

**Option B: must-use plugin**
Copy `wp-toolbar-hide-elements.php` to `wp-content/mu-plugins/`. It loads automatically and can't be deactivated from the dashboard.

## Configuration

Everything is at the top of the file:

```php
function thbe_hidden_titles() {
    return array(
        'Theme Options',
        'Rank Math SEO',
        'Smart Slider',
    );
}

function thbe_hidden_ids() {
    return array(
        // 'wp-logo',
    );
}

define( 'THBE_ALSO_ON_FRONTEND', true );      // false = only inside wp-admin
define( 'THBE_HIDE_SCREEN_OPTIONS', false );  // "Opciones de pantalla" tab
define( 'THBE_HIDE_HELP_TAB', false );        // "Ayuda" tab
```

- **By name** (`thbe_hidden_titles`): the label exactly as you see it, minus any counter. Sub-menu items (inside a dropdown) can be listed too.
- **By ID** (`thbe_hidden_ids`): more robust if a plugin's label changes with language or contains only an icon (like the comments bubble or the ✨ icon). To find an ID, right-click the item → *Inspect*; the surrounding `<li id="wp-admin-bar-XXXX">` means the ID is `XXXX`. Common core IDs: `wp-logo`, `site-name`, `comments`, `new-content`, `updates`, `search`, `my-account`.
- Hiding a parent item also hides all its sub-items.

## Notes

- This hides items from the Toolbar only. The pages they link to are still reachable from the left admin menu or by URL; it isn't a permissions control.
- To hide the Toolbar for a specific user instead, use *Users → Profile → Show Toolbar when viewing site*.
