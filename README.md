# WordPress Abilities Bridge

WordPress Core functionality exposed as secure, permission-aware semantic operations through the native WordPress Abilities API.

WordPress Abilities Bridge provides semantic, permission-aware operations that can be consumed by WordPress integrations, automation systems, AI agents, and MCP adapters without exposing an unrestricted PHP, shell, or SQL execution interface.

## Requirements

- WordPress 6.9 or newer with the WordPress Abilities API
- PHP 7.4 or newer

The plugin remains safely inactive when the Abilities API is unavailable.

## Plugin abilities

Plugin management is intentionally a thin semantic wrapper over WordPress Core. The bridge does not replace WordPress' installer, upgrader, dependency system, activation state, auto-update system, or Multisite plugin state.

| Ability | Core behavior | Required capability |
| --- | --- | --- |
| `wordpress/plugin-list` | `get_plugins()` plus Core activation/update/dependency state | `activate_plugins` |
| `wordpress/plugin-get` | `get_plugins()` for one installed plugin | `activate_plugins` |
| `wordpress/plugin-mu-list` | `get_mu_plugins()`, read-only | `activate_plugins` |
| `wordpress/plugin-search` | `plugins_api( 'query_plugins' )` | `install_plugins` |
| `wordpress/plugin-get-information` | `plugins_api( 'plugin_information' )` | `install_plugins` |
| `wordpress/plugin-install` | WordPress.org metadata + `Plugin_Upgrader::install()` | `install_plugins`, plus `activate_plugins` when requested |
| `wordpress/plugin-install-package` | `Plugin_Upgrader::install()` for HTTPS ZIPs | `install_plugins`, plus `update_plugins` for overwrite and `activate_plugins` for activation |
| `wordpress/plugin-check-updates` | `wp_update_plugins()` + Core update transient | `update_plugins` |
| `wordpress/plugin-update` | same Core upgrader path used by WordPress Admin AJAX | `update_plugins` |
| `wordpress/plugin-update-many` | `Plugin_Upgrader::bulk_upgrade()` | `update_plugins` |
| `wordpress/plugin-enable-auto-update` | Core `auto_update_plugins` state | `update_plugins` |
| `wordpress/plugin-disable-auto-update` | Core `auto_update_plugins` state | `update_plugins` |
| `wordpress/plugin-activate` | `activate_plugin()` | `activate_plugins` |
| `wordpress/plugin-deactivate` | `deactivate_plugins()` | `activate_plugins` |
| `wordpress/plugin-activate-many` | Core bulk activation API | `activate_plugins` |
| `wordpress/plugin-deactivate-many` | `deactivate_plugins()` | `activate_plugins` |
| `wordpress/plugin-delete` | `delete_plugins()` | `delete_plugins` |
| `wordpress/plugin-delete-many` | `delete_plugins()` | `delete_plugins` |
| `wordpress/plugin-network-activate` | `activate_plugin( ..., true )` | Multisite + `manage_network_plugins` |
| `wordpress/plugin-network-deactivate` | `deactivate_plugins( ..., false, true )` | Multisite + `manage_network_plugins` |

Other administrative abilities for users, options, media, cron, cache, database maintenance, themes, content, and taxonomy remain available as documented by ability discovery.

## Theme abilities

Theme management follows the same Core-first rule as plugin management. The bridge delegates installation, overwrite, updates, activation, deletion, discovery, and auto-update state to WordPress Core rather than implementing a second theme-management engine.

| Ability | Core behavior | Required capability |
| --- | --- | --- |
| `wordpress/theme-list` | `wp_get_themes()` plus Core activation/update/auto-update state | `switch_themes` |
| `wordpress/theme-get` | `wp_get_theme()` for one installed theme | `switch_themes` |
| `wordpress/theme-search` | `themes_api( 'query_themes' )` | `install_themes` |
| `wordpress/theme-get-information` | `themes_api( 'theme_information' )` | `install_themes` |
| `wordpress/theme-install` | WordPress.org metadata + `Theme_Upgrader::install()` | `install_themes` |
| `wordpress/theme-install-package` | `Theme_Upgrader::install()` for HTTPS ZIPs | `install_themes`, plus `update_themes` for overwrite and `switch_themes` for activation |
| `wordpress/theme-check-updates` | `wp_update_themes()` + Core update transient | `update_themes` |
| `wordpress/theme-update` | Core `Theme_Upgrader` update path | `update_themes` |
| `wordpress/theme-update-many` | `Theme_Upgrader::bulk_upgrade()` | `update_themes` |
| `wordpress/theme-enable-auto-update` | Core `auto_update_themes` state | `update_themes` |
| `wordpress/theme-disable-auto-update` | Core `auto_update_themes` state | `update_themes` |
| `wordpress/theme-activate` | `switch_theme()` | `switch_themes` |
| `wordpress/theme-delete` | `delete_theme()` for inactive themes | `delete_themes` |

Theme package installation accepts only validated HTTPS package URLs. The bridge does not expose the Theme File Editor or implement custom ZIP extraction, copy, rollback, or deployment logic.



## Ability contracts

Every `wordpress/*` ability publishes both `input_schema` and `output_schema`. Object properties are annotated with client-facing titles and descriptions so REST, MCP, AI, and other schema-driven consumers can inspect the operation before execution.

The bridge keeps output schemas forward-compatible with WordPress Core by documenting stable semantic fields while allowing additional Core-provided fields.

## Optional GitHub self-update

The bridge can surface its GitHub Releases through the normal WordPress plugin update transient. This integration is deliberately **disabled by default** because some managed hosts block outbound access to GitHub and a forced remote check would add latency or failures to normal WordPress update checks.

Enable it only on hosts that can reach `api.github.com`:

```php
define( 'WP_ABILITY_GITHUB_UPDATES', true );
```

It can also be controlled dynamically:

```php
add_filter( 'wp_ability_github_updates_enabled', '__return_true' );
```

When GitHub is unreachable, invalid, or times out, the updater fails open and leaves the existing WordPress update transient unchanged.

## Package integrity

Custom plugin and theme package abilities accept an optional `expected_sha256` value:

```json
{
  "package_url": "https://example.com/package.zip",
  "overwrite": true,
  "activate": true,
  "expected_sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
}
```

When supplied, the bridge hooks WordPress Core's upgrader download step, calculates the downloaded ZIP SHA-256, and refuses installation when the digest does not match. Package extraction, overwrite, and activation remain delegated to Core.

## WordPress Core compatibility

The bridge preserves its existing `wordpress/*` names for compatibility while publishing known official Core equivalents in ability metadata. Current mappings include:

- `wordpress/user-list` → `core/read-users`
- `wordpress/post-list` and `wordpress/post-get` → `core/read-content`
- `wordpress/option-get` → `core/read-settings`

The registrar never replaces an ability name that is already registered. This lets future WordPress Core abilities take ownership without a duplicate registration conflict.

## Media abilities

Media management uses WordPress attachment APIs and capability checks:

- `wordpress/media-list`
- `wordpress/media-get`
- `wordpress/media-upload` — validated HTTPS source URL through `download_url()` and `media_handle_sideload()`
- `wordpress/media-update` — attachment title, caption, description, and alternative text
- `wordpress/media-delete`

## Comment abilities

Comment management uses `WP_Comment_Query` and WordPress Core comment APIs:

- `wordpress/comment-list`
- `wordpress/comment-get`
- `wordpress/comment-create`
- `wordpress/comment-update`
- `wordpress/comment-delete`
- `wordpress/comment-approve`
- `wordpress/comment-spam`
- `wordpress/comment-trash`

Creation is constrained to posts editable by the current user; moderation actions require the normal Core moderation capability.

## Audit integration

The bridge listens to the native `wp_before_execute_ability` and `wp_after_execute_ability` lifecycle hooks and emits a normalized `wp_ability_audit_event` action for external observability systems.

The event intentionally excludes raw input and output values. It exposes only metadata such as phase, ability name, current user ID, input field names, result type, error code, and timestamp. The bridge does **not** create its own audit database or log store.

The event context can be extended with the `wp_ability_audit_event_context` filter.

## Internal domain ownership

Production registration is split into focused classes for users, content, taxonomy, media, options, cron/transients, maintenance, comments, plugins, and themes. The former broad classes remain available as compatibility layers but are no longer booted as the production owners of those registrations.

## Custom plugin packages

Use `wordpress/plugin-install-package` when a plugin is distributed as a ZIP package rather than through WordPress.org.

Example input:

```json
{
  "package_url": "https://example.com/my-plugin.zip",
  "overwrite": true,
  "activate": true,
  "expected_sha256": "optional-64-character-sha256"
}
```

Rules:

- `package_url` must be a valid HTTPS URL.
- Every request requires `install_plugins`.
- `overwrite: true` additionally requires `update_plugins`.
- `activate: true` additionally requires `activate_plugins`.
- Installation and overwrite are delegated to WordPress Core `Plugin_Upgrader`; the bridge does not manually unzip, delete, or copy live plugin directories.
- The ability does not persist repository credentials or tokens.

For normal MCP-driven custom ZIP installation and overwrite, the separate `WP-plugin-deploy` plugin is no longer required once this bridge version is installed and the ability is discoverable.

## Security model

WP Ability is designed around semantic administrative operations rather than generic remote execution.

- Every ability has a server-side WordPress capability check.
- WordPress.org installation uses plugin slugs; custom package installation is separately constrained to validated HTTPS ZIP URLs through `wordpress/plugin-install-package`.
- Plugin updates only operate on plugins already registered by WordPress.
- User passwords are never returned.
- Direct option updates block options that have dedicated, security-sensitive management paths such as active plugins, cron storage, and active theme selection.
- Media deletion requires permission on the specific attachment.
- Cron execution can only run an event that is already scheduled.
- Database optimization only accepts table names from WordPress' own managed table list.
- No arbitrary SQL, PHP evaluation, shell command, filesystem write, or remote URL execution ability is provided.

The protected option list can be extended with the `wp_ability_protected_options` filter.

## AI and automation safety

WordPress abilities may be called by AI agents, automated workflows, remote clients, or conventional application code.

Descriptions and schemas are interface metadata, not authorization. All security decisions remain server-side.

Consumers should inspect ability annotations before execution, especially for operations marked destructive or non-idempotent. Human confirmation is recommended before destructive actions such as permanent media deletion, option changes, scheduled task execution, or other future write operations.

## Design principles

1. Use WordPress core APIs whenever an operation has a supported API.
2. Preserve WordPress capability and ownership checks.
3. Prefer narrow semantic abilities over generic administration backdoors.
4. Declare readonly, destructive, idempotent, and open-world behavior accurately.
5. Avoid returning secrets or credentials.
6. Keep ability descriptions neutral so they remain useful outside any particular transport such as MCP.
7. Make potentially dangerous extension points explicit and reviewable.

## Coverage

The bridge currently registers abilities across these WordPress Core domains:

- plugins: inventory/details, WordPress.org discovery, install/package install, site and network activation, bulk actions, update checks, single/bulk updates, auto-update controls, dependency/update state, delete, and read-only MU inventory
- themes: inventory/details, WordPress.org discovery, install/package install, optional SHA-256 verification, update checks, single/bulk updates, auto-update controls, activate, and delete
- users: list, get, create, update, delete
- posts, pages, and custom post types: list, get, create, update, delete
- taxonomy terms: list, create, update, delete
- media: list, get, HTTPS upload, metadata update, delete
- options: get, update, delete with protected-option safeguards
- cron: list, schedule, run, delete
- transients: get, set, delete
- comments: list/get/create/update/delete plus approve/spam/trash moderation
- object cache, rewrite rules, database optimization, and update checks

The project intentionally does not expose Plugin File Editor, arbitrary PHP evaluation, raw SQL execution, shell commands, or unrestricted filesystem/network operations.

## Plugin management design boundary

Plugin management follows WordPress Admin/Core behavior rather than introducing a second management engine. Plugin File Editor is deliberately not exposed. Must-Use plugins are inventory-only because normal WordPress Admin does not provide lifecycle management for them.

Single-site and Multisite plugin/theme-management E2E flows are required CI gates. CI also enforces WordPress Coding Standards, PHPUnit contracts, Composer advisory audit, WordPress-aware PHPStan analysis, and an installable release-ZIP smoke test.

## Releases

GitHub Actions builds an installable WordPress plugin ZIP whenever a version tag matching `v*` is pushed.

Example:

```bash
git tag v0.1.0
git push origin v0.1.0
```

The tag version must match the `Version:` value in `wp-ability.php`.

Each release contains:

- `wp-ability-<version>.zip` — ready to upload from **Plugins → Add Plugin → Upload Plugin**
- `wp-ability-<version>.zip.sha256` — checksum for verifying the package

The ZIP contains a top-level `wp-ability/` directory and excludes development-only files such as tests, GitHub workflows, Composer development dependencies, and Git metadata.

The release workflow can also be started manually from **Actions → Build and Release Plugin** for an existing version tag.

## License

GPL-2.0-or-later.
