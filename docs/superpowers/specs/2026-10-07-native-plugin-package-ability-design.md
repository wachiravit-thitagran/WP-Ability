# Native Plugin Package Ability Design

Date: 2026-10-07

## Objective

Move custom plugin package installation and overwrite behavior into `WordPress-Abilities-Bridge` by exposing a narrow WordPress Ability that delegates installation to WordPress Core `Plugin_Upgrader`.

This replaces the need for a separate deployment engine in `WP-plugin-deploy` for the primary workflow of installing or updating custom plugins from ZIP packages through MCP.

## Context

`WordPress-Abilities-Bridge` already exposes:

- `wordpress/plugin-install` for WordPress.org plugins by slug.
- `wordpress/plugin-update` for installed plugins that participate in the normal WordPress update system.

The missing capability is installation or overwrite from a caller-supplied plugin ZIP package URL, equivalent to the WordPress Admin "Upload Plugin" workflow.

The new ability must preserve WordPress Core filesystem and package handling rather than reimplementing extraction, replacement, or filesystem management.

## Selected Approach

Add one new ability:

`wordpress/plugin-install-package`

Keep existing abilities unchanged:

- `wordpress/plugin-install` remains WordPress.org-only.
- `wordpress/plugin-update` remains the normal installed-plugin update path.

Do not broaden either existing interface.

## Ability Contract

### Name

`wordpress/plugin-install-package`

### Purpose

Install a WordPress plugin from a ZIP package URL, optionally overwrite an existing plugin package, and optionally activate the resulting plugin.

### Input

- `package_url` — required HTTPS URL to a plugin ZIP package.
- `overwrite` — optional boolean, default `false`.
- `activate` — optional boolean, default `false`.

No arbitrary local filesystem path is accepted.

No credentials, tokens, or secrets are persisted by the ability.

### Output

On success return:

- `plugin_file`
- `installed: true`
- `overwritten`
- `activated`
- `version` when available

Do not return filesystem paths, temporary paths, credentials, or package contents.

## WordPress Core Flow

The implementation must use WordPress Core APIs only:

1. Load plugin installation/upgrader dependencies.
2. Create an `Automatic_Upgrader_Skin`.
3. Create a `Plugin_Upgrader`.
4. Call `Plugin_Upgrader::install()` with:
   - the package URL
   - `overwrite_package => true` only when `overwrite=true`
   - `clear_update_cache => true`
5. Resolve the installed plugin file with `Plugin_Upgrader::plugin_info()`.
6. If `activate=true`, call `activate_plugin()`.
7. Verify activation with `is_plugin_active()`.
8. Refresh plugin cache before returning.

The bridge must not:

- call `unzip_file()` directly;
- call `copy_dir()` directly;
- delete plugin directories directly;
- implement its own filesystem credential flow;
- shell out;
- use Git CLI;
- use SSH.

## Permission Model

The ability permission callback must enforce:

- `install_plugins` for every request.
- `update_plugins` when `overwrite=true`.
- `activate_plugins` when `activate=true`.

The permission callback is authoritative and server-side.

The execution callback must also validate critical assumptions and return structured `WP_Error` values rather than relying solely on schema validation.

## URL and Package Rules

Version 1 accepts HTTPS package URLs only.

The ability delegates plugin package structure validation and installation to `Plugin_Upgrader`.

The bridge must reject:

- empty URLs;
- malformed URLs;
- non-HTTPS URLs;
- local file paths;
- unsupported schemes.

The bridge does not fetch or persist authentication credentials for private package URLs in the first release.

## Existing Ability Compatibility

No behavior changes are made to:

- `wordpress/plugin-install`
- `wordpress/plugin-update`
- plugin activate/deactivate/delete abilities
- unrelated WordPress administration abilities

This avoids breaking existing MCP clients.

## MCP Usage

Expected MCP flow:

1. MCP adapter discovers `wordpress/plugin-install-package`.
2. Caller invokes the ability with a ZIP package URL.
3. WordPress capability checks run.
4. `Plugin_Upgrader` performs the install or overwrite.
5. The ability returns a structured result.
6. Existing abilities can inspect, activate, deactivate, or delete the plugin afterward.

## Relationship to WP-plugin-deploy

After this ability and its E2E tests are proven:

- `WP-plugin-deploy` is no longer required for normal custom plugin install/overwrite through MCP.
- No new deployment functionality should be added to `WP-plugin-deploy`.
- The repository may be archived later after operational migration is complete.

This design does not require deleting or archiving `WP-plugin-deploy` as part of the initial implementation.

## Testing Strategy

### Unit / Integration Tests

Add tests covering:

- ability registration;
- input schema;
- install permission;
- overwrite permission;
- activation permission;
- HTTPS-only validation;
- successful install;
- successful overwrite;
- activation success;
- activation failure;
- `Plugin_Upgrader` errors;
- inability to resolve plugin file.

Existing tests must remain green.

### End-to-End Test

Add a required CI E2E job using a real WordPress instance and database.

The E2E flow must:

1. Start WordPress and MariaDB.
2. Install and activate `WordPress-Abilities-Bridge`.
3. Serve a fixture plugin v1 ZIP from a local HTTP test server accessible to WordPress.
4. Execute `wordpress/plugin-install-package`.
5. Verify:
   - plugin installed;
   - expected plugin file exists in WordPress plugin inventory;
   - plugin version is v1;
   - plugin can be active when requested.
6. Serve fixture plugin v2 with the same plugin slug.
7. Execute the same ability with `overwrite=true`.
8. Verify plugin version is v2.
9. Use existing deactivate/delete abilities.
10. Verify the plugin is no longer active and is deleted.
11. Fail the workflow on any failed assertion.

The E2E test must use the real WordPress upgrader code path. It must not replace `Plugin_Upgrader` with a mock.

## CI Gate

A change is not releasable unless all of the following pass:

- PHP syntax checks;
- existing PHPUnit tests;
- new package ability tests;
- real WordPress E2E install/overwrite/delete flow.

The E2E job is required, not informational.

## Error Handling

Return structured WordPress errors for cases including:

- invalid package URL;
- permission denied;
- upgrader unavailable;
- package download failure;
- invalid plugin package;
- install failure;
- overwrite failure;
- plugin file resolution failure;
- activation failure.

Do not expose raw credentials or server filesystem paths in errors.

## Security Properties

- Narrow semantic ability, not generic PHP execution.
- Server-side capability checks.
- HTTPS-only package source.
- No arbitrary target directory input.
- No raw filesystem API exposed to MCP.
- No shell, SQL, PHP eval, or unrestricted network execution.
- WordPress Core owns package extraction and installation behavior.

## Out of Scope

The first release does not add:

- theme package upload;
- WordPress Core update;
- private repository token storage;
- arbitrary local ZIP paths;
- deployment history;
- deployment backup history;
- custom rollback engine;
- Git operations;
- filesystem browsing.

## Success Criteria

The design is complete when:

1. MCP can discover `wordpress/plugin-install-package`.
2. A custom plugin ZIP can be installed through the ability.
3. The same plugin can be overwritten by a newer ZIP using `overwrite=true`.
4. Activation works through the same request.
5. Existing plugin administration abilities continue to work.
6. A full real-WordPress E2E test proves the flow in CI.
7. The implementation contains no custom live-plugin directory replacement logic.
