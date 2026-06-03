<div align="center">

# 🤖 WordPress MCP

[![Version](https://img.shields.io/badge/version-1.0.0-blue)](https://github.com/hermes98761234/wordpress-mcp)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](https://opensource.org/licenses/MIT)
[![MCP](https://img.shields.io/badge/MCP-compatible-orange)](https://modelcontextprotocol.io/)

**Manage your entire WordPress site through AI assistants using the Model Context Protocol**

</div>

---

## ✨ Features

- **Posts & Custom Post Types** — list, get, create, update, delete posts; enumerate post types
- **Pages** — full CRUD for pages
- **Users & Roles** — list, get, create, update, delete users
- **Media Library** — list, get, upload, update, delete media items
- **Site Settings** — read and update WordPress options
- **Plugins Management** — list, activate, deactivate, update plugins
- **Themes & Customizer** — list, activate, delete themes
- **Comments Moderation** — list, get, create, update, delete, approve, spam comments
- **Taxonomies & Terms** — list taxonomies; list, get, create, update, delete terms
- **Navigation Menus** — list, get, create, update, delete menus; add menu items
- **WooCommerce** *(optional)* — products (list/get/update), orders (list/get/update status), customers (list)

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 8.0+    |
| WooCommerce | optional (enables WooCommerce tool) |

## ⚡ Installation

1. Download the [latest release](https://github.com/hermes98761234/wordpress-mcp/archive/refs/heads/main.zip) or clone this repo
2. Upload the `wordpress-mcp` folder to your `/wp-content/plugins/` directory (or upload the zip via **Plugins → Add New → Upload Plugin**)
3. Activate the plugin through the **Plugins** screen in WordPress
4. Navigate to **Settings → MCP Settings** to view your API key and endpoint URL

Your API key is auto-generated on activation. You can regenerate it anytime from the settings page.

## 🔗 Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/mcp/v1/execute` | `POST` | Execute a tool action |
| `/wp-json/mcp/v1/tools` | `GET` | List all available tools and their actions |

## 🔑 Authentication

All requests to the `/execute` endpoint require a Bearer token in the `Authorization` header:

```
Authorization: Bearer YOUR_API_KEY
```

Find your API key at **Settings → MCP Settings** in your WordPress admin.

**Example request:**

```bash
curl -X POST https://yoursite.com/wp-json/mcp/v1/execute \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"tool":"posts","action":"list_posts","params":{"per_page":5}}'
```

**Success response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Hello World",
      "status": "publish",
      "date": "2025-01-01 00:00:00",
      "link": "https://yoursite.com/hello-world/",
      "excerpt": "Welcome to WordPress."
    }
  ]
}
```

**Error response:**

```json
{
  "success": false,
  "error": "Invalid API key.",
  "code": "wmcp_invalid_key"
}
```

## 🛠️ Available Tools

### `posts` — Posts & Custom Post Types

| Action | Description |
|--------|-------------|
| `list_posts` | List posts with optional filtering by type, status, search, pagination |
| `get_post` | Get a single post by ID (includes content, meta, terms) |
| `create_post` | Create a new post with title, content, status, type, categories, tags, meta |
| `update_post` | Update an existing post |
| `delete_post` | Delete a post (optionally force-delete, bypassing trash) |
| `list_post_types` | List all registered public post types |

### `pages` — Pages

| Action | Description |
|--------|-------------|
| `list_pages` | List pages with filtering and pagination |
| `get_page` | Get a single page by ID |
| `create_page` | Create a new page |
| `update_page` | Update an existing page |
| `delete_page` | Delete a page |

### `users` — Users & Roles

| Action | Description |
|--------|-------------|
| `list_users` | List users with search and pagination |
| `get_user` | Get a single user by ID |
| `create_user` | Create a new user |
| `update_user` | Update an existing user |
| `delete_user` | Delete a user |

### `media` — Media Library

| Action | Description |
|--------|-------------|
| `list_media` | List media items |
| `get_media` | Get a single media item by ID |
| `upload_media` | Upload a media file |
| `update_media` | Update media metadata |
| `delete_media` | Delete a media item |

### `settings` — Site Settings

| Action | Description |
|--------|-------------|
| `get_settings` | Get WordPress option values |
| `update_settings` | Update WordPress option values |

### `plugins` — Plugins Management

| Action | Description |
|--------|-------------|
| `list_plugins` | List all installed plugins with status |
| `activate_plugin` | Activate a plugin |
| `deactivate_plugin` | Deactivate a plugin |
| `update_plugin` | Update a plugin to the latest version |

### `themes` — Themes & Customizer

| Action | Description |
|--------|-------------|
| `list_themes` | List all installed themes |
| `activate_theme` | Activate a theme |
| `delete_theme` | Delete a theme |

### `comments` — Comments Moderation

| Action | Description |
|--------|-------------|
| `list_comments` | List comments with filtering |
| `get_comment` | Get a single comment by ID |
| `create_comment` | Create a new comment |
| `update_comment` | Update an existing comment |
| `delete_comment` | Delete a comment |
| `approve_comment` | Approve a comment |
| `spam_comment` | Mark a comment as spam |

### `taxonomies` — Taxonomies & Terms

| Action | Description |
|--------|-------------|
| `list_taxonomies` | List all registered taxonomies |
| `list_terms` | List terms in a taxonomy |
| `get_term` | Get a single term by ID |
| `create_term` | Create a new term |
| `update_term` | Update an existing term |
| `delete_term` | Delete a term |

### `menus` — Navigation Menus

| Action | Description |
|--------|-------------|
| `list_menus` | List all navigation menus |
| `get_menu` | Get a single menu by ID |
| `create_menu` | Create a new menu |
| `update_menu` | Update an existing menu |
| `delete_menu` | Delete a menu |
| `add_menu_item` | Add an item to a menu |

### `woocommerce` — WooCommerce *(only when WooCommerce is active)*

| Action | Description |
|--------|-------------|
| `list_products` | List products with filtering by status, category, search |
| `get_product` | Get a single product with full details (price, SKU, stock, categories) |
| `update_product` | Update product price, stock, status, description |
| `list_orders` | List orders with filtering by status, customer |
| `get_order` | Get a single order with line items and addresses |
| `update_order_status` | Update the status of an order |
| `list_customers` | List WooCommerce customers with search |

## 🤖 Using with Claude

To use this plugin with Claude Desktop, add the following to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://yoursite.com/wp-json/mcp/v1/execute",
      "headers": {
        "Authorization": "Bearer YOUR_API_KEY"
      }
    }
  }
}
```

> **Note:** The MCP spec uses stdio transport by default. To connect Claude Desktop to this HTTP-based MCP server, you'll need an [MCP proxy/bridge](https://github.com/modelcontextprotocol) that translates between stdio and HTTP/SSE transport. Community bridges like [mcp-remote](https://github.com/geelen/mcp-remote) or a simple SSE-to-stdio adapter can handle this.

## 📁 Project Structure

```
wordpress-mcp/
├── wordpress-mcp.php                  # Main plugin file, REST routes, admin UI
├── uninstall.php                      # Cleanup on plugin uninstall
├── LICENSE                            # MIT license
├── README.md                          # This file
└── includes/
    ├── class-auth.php                 # Bearer token authentication
    ├── class-mcp-server.php           # MCP server dispatcher & tool registry
    └── tools/
        ├── class-tool-posts.php       # Posts & custom post types
        ├── class-tool-pages.php       # Pages
        ├── class-tool-users.php       # Users & roles
        ├── class-tool-media.php       # Media library
        ├── class-tool-settings.php    # Site settings
        ├── class-tool-plugins-manager.php  # Plugin management
        ├── class-tool-themes-manager.php   # Theme management
        ├── class-tool-comments-tool.php    # Comments moderation
        ├── class-tool-taxonomies.php       # Taxonomies & terms
        ├── class-tool-menus.php            # Navigation menus
        └── class-tool-woocommerce.php      # WooCommerce (conditional)
```

## 🔒 Security

- **API key storage** — Keys are stored in the `wp_options` table as a 64-character random string generated by `wp_generate_password()`. Keys are compared using `hash_equals()` to prevent timing attacks.
- **HTTPS recommended** — Always serve your site over HTTPS to protect the Bearer token in transit. Unencrypted HTTP exposes your API key to network interception.
- **WordPress capability checks** — All tool actions respect WordPress capabilities and roles. The settings page requires `manage_options`.
- **Input sanitization** — All parameters are sanitized using WordPress core functions (`sanitize_text_field`, `absint`, `wp_kses_post`, etc.) before use.

## 📄 License

This project is licensed under the [MIT License](LICENSE).
