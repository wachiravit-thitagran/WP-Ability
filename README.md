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

## Custom plugin packages

Use `wordpress/plugin-install-package` when a plugin is distributed as a ZIP package rather than through WordPress.org.

Example input:

```json
{
  "package_url": "https://example.com/my-plugin.zip",
  "overwrite": true,
  "activate": true
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
- themes: inventory, activate, delete
- users: list, get, create, update, delete
- posts, pages, and custom post types: list, get, create, update, delete
- taxonomy terms: list, create, update, delete
- media: list, get, delete
- options: get, update, delete with protected-option safeguards
- cron: list, schedule, run, delete
- transients: get, set, delete
- object cache, rewrite rules, database optimization, and update checks

The project intentionally does not expose Plugin File Editor, arbitrary PHP evaluation, raw SQL execution, shell commands, or unrestricted filesystem/network operations.

## Plugin management design boundary

Plugin management follows WordPress Admin/Core behavior rather than introducing a second management engine. Plugin File Editor is deliberately not exposed. Must-Use plugins are inventory-only because normal WordPress Admin does not provide lifecycle management for them.

Single-site and Multisite plugin-management E2E flows are required CI gates.

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
