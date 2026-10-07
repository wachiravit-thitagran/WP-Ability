# WordPress Abilities Bridge

WordPress Core functionality exposed as secure, permission-aware semantic operations through the native WordPress Abilities API.

WordPress Abilities Bridge provides semantic, permission-aware operations that can be consumed by WordPress integrations, automation systems, AI agents, and MCP adapters without exposing an unrestricted PHP, shell, or SQL execution interface.

## Requirements

- WordPress 6.9 or newer with the WordPress Abilities API
- PHP 7.4 or newer

The plugin remains safely inactive when the Abilities API is unavailable.

## Abilities

| Ability | Purpose | Required capability |
| --- | --- | --- |
| `wordpress/plugin-install` | Install a plugin from WordPress.org, optionally activate it | `install_plugins`, plus `activate_plugins` when activation is requested |
| `wordpress/plugin-update` | Update one installed plugin | `update_plugins` |
| `wordpress/user-create` | Create a WordPress user and assign an editable role | `create_users`, plus `promote_users` for non-subscriber roles |
| `wordpress/option-update` | Update a JSON-compatible WordPress option | `manage_options` |
| `wordpress/media-delete` | Delete or permanently delete an attachment | `delete_post` for the attachment |
| `wordpress/cron-run` | Run one existing scheduled WordPress event | `manage_options` |
| `wordpress/cache-flush` | Flush the active WordPress object cache | `manage_options` |
| `wordpress/database-optimize` | Optimize WordPress-managed database tables | `manage_options` |

## Security model

WP Ability is designed around semantic administrative operations rather than generic remote execution.

- Every ability has a server-side WordPress capability check.
- Plugin installation only accepts WordPress.org plugin slugs; arbitrary package URLs are not accepted.
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

- plugins: inventory, install, activate, deactivate, update, delete
- themes: inventory, activate, delete
- users: list, get, create, update, delete
- posts, pages, and custom post types: list, get, create, update, delete
- taxonomy terms: list, create, update, delete
- media: list, get, delete
- options: get, update, delete with protected-option safeguards
- cron: list, schedule, run, delete
- transients: get, set, delete
- object cache, rewrite rules, database optimization, and update checks

The project intentionally does not expose arbitrary PHP evaluation, raw SQL execution, shell commands, or unrestricted filesystem/network operations.

## Planned expansion

The repository is intended to become a broader WordPress administration ability layer. Useful next areas include:

- plugin activation, deactivation, uninstall, and inventory
- theme management
- post, page, taxonomy, and custom post type management
- media upload and metadata management
- user update, role management, and deletion
- option discovery and dedicated site settings
- cron discovery and event management
- Site Health and diagnostics
- cache and transient inspection
- update management and maintenance workflows
- backup-aware and rollback-aware composite operations
- audit logging and execution history

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
