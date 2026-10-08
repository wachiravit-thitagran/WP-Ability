# Complete Plugin Management Abilities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose WordPress Admin plugin-management functionality for single-site and Multisite as thin WordPress Abilities wrappers, excluding Plugin File Editor.

**Architecture:** Consolidate plugin-domain abilities into `Plugin_Abilities` while preserving released ability names. Every operation must delegate to the WordPress Core API/class used by WordPress Admin; the bridge adds only schemas, permission checks, validation, and result normalization. Existing `Plugin_Package_Installer` remains a minimal `Plugin_Upgrader` adapter.

**Tech Stack:** PHP 7.4+, WordPress 6.9+, WordPress Abilities API, WordPress Core plugin APIs, PHPUnit, WP-CLI, GitHub Actions, MySQL/MariaDB.

**Spec:** `docs/superpowers/specs/2026-10-07-complete-plugin-management-abilities-design.md`

## Global Constraints

- Thin wrapper over WordPress Core only.
- Preserve existing public plugin ability names and compatible schemas.
- Support single-site and Multisite.
- Exclude Plugin File Editor.
- Do not implement custom deployment, ZIP extraction, filesystem, update, dependency, activation-state, auto-update scheduling, or network-state engines.
- Prefer Core `WP_Error` results where safe.
- Real single-site and Multisite E2E are required CI gates.

## Review Focus

- Multisite requests executed outside Multisite must fail explicitly, not silently degrade to site scope.
- Network activation/deactivation must preserve Core Network Admin/Super Admin authorization.
- Bulk operations must delegate to Core batch APIs where available rather than custom transactional loops.
- Auto-update enable/disable must use WordPress Core storage/behavior and preserve existing unrelated auto-update entries.
- Refactoring plugin methods out of existing classes must not register duplicate abilities or change existing public contracts.

---

### Task 1: Extract existing plugin abilities into Plugin_Abilities

**Files:**
- Create: `includes/class-plugin-abilities.php`
- Modify: `wp-ability.php`
- Modify: `includes/class-abilities.php`
- Modify: `includes/class-core-abilities.php`
- Test: `tests/phpunit/AbilitiesTest.php`

**Interfaces:**
- Produces: `WP_Ability\Plugin_Abilities` responsible for all plugin-domain ability registration/execution.
- Preserves: existing plugin-list/install/install-package/update/activate/deactivate/delete names and schemas.

- [ ] Write failing tests asserting all existing plugin abilities register exactly once and existing permission behavior remains unchanged.
- [ ] Run PHPUnit and verify RED from missing `Plugin_Abilities`/migration.
- [ ] Move plugin registration/callback code mechanically into `Plugin_Abilities`; load it from `wp-ability.php`; instantiate it at boot.
- [ ] Run targeted PHPUnit and full suite; verify GREEN.
- [ ] Commit: `refactor: isolate plugin abilities`.

### Task 2: Plugin inventory, get, and MU inventory

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-list`
  - `wordpress/plugin-get`
  - read-only MU plugin inventory via an explicit ability name selected during implementation consistent with repository naming.
- Core APIs: `get_plugins()`, `get_mu_plugins()`, `is_plugin_active()`, `is_plugin_active_for_network()`.

- [ ] Write failing tests for installed plugin state, version, site-active, network-active, inactive, and MU-plugin read-only reporting.
- [ ] Run tests and verify RED.
- [ ] Implement thin Core-backed wrappers only.
- [ ] Run targeted and full PHPUnit; verify GREEN.
- [ ] Commit: `feat: expose plugin inventory abilities`.

### Task 3: WordPress.org search and information

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-search`
  - `wordpress/plugin-get-information`
- Core API: `plugins_api()`.

- [ ] Write failing tests proving input normalization, permission/read semantics, Core delegation, and Core error propagation.
- [ ] Run and verify RED.
- [ ] Implement wrappers around `plugins_api()` without a custom search backend.
- [ ] Run targeted and full suite; verify GREEN.
- [ ] Commit: `feat: expose plugin discovery abilities`.

### Task 4: Update checks, single update, and bulk update

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-check-updates`
  - existing `wordpress/plugin-update`
  - `wordpress/plugin-update-many`
- Core APIs: `wp_update_plugins()`, update site transient, `Plugin_Upgrader::upgrade()`, `Plugin_Upgrader::bulk_upgrade()`.

- [ ] Write failing tests for capability checks, update metadata normalization, single-update delegation, bulk-update delegation, and Core errors.
- [ ] Run and verify RED.
- [ ] Implement direct Core wrappers; no custom updater loop when `bulk_upgrade()` applies.
- [ ] Run targeted and full suite; verify GREEN.
- [ ] Commit: `feat: complete plugin update abilities`.

### Task 5: Auto-update controls

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-enable-auto-update`
  - `wordpress/plugin-disable-auto-update`
- Uses the same WordPress Core option/filter behavior used by WordPress Admin.

- [ ] Write failing tests that enable/disable one plugin without changing unrelated entries and enforce update permissions.
- [ ] Run and verify RED.
- [ ] Implement the thinnest wrapper around Core auto-update state.
- [ ] Run targeted and full suite; verify GREEN.
- [ ] Commit: `feat: expose plugin auto update controls`.

### Task 6: Bulk activate, deactivate, and delete

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-activate-many`
  - `wordpress/plugin-deactivate-many`
  - `wordpress/plugin-delete-many`
- Core APIs: Core activation/deactivation/delete batch behavior where available.

- [ ] Write failing tests for permissions, multiple plugin inputs, Core error preservation, and refusal of invalid/active deletion states according to Core.
- [ ] Run and verify RED.
- [ ] Implement thin wrappers using Core batch behavior; do not add transaction/rollback logic.
- [ ] Run targeted and full suite; verify GREEN.
- [ ] Commit: `feat: expose plugin bulk actions`.

### Task 7: Multisite network plugin abilities

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesMultisiteTest.php`

**Interfaces:**
- Produces:
  - `wordpress/plugin-network-activate`
  - `wordpress/plugin-network-deactivate`
  - explicit site/network state in inventory.
- Core APIs:
  - `is_multisite()`
  - `activate_plugin( $plugin, '', true )`
  - `deactivate_plugins( $plugins, false, true )`
  - `is_plugin_active_for_network()`.

- [ ] Write failing Multisite tests for non-Multisite rejection, network permissions, network activation, network deactivation, and state reporting.
- [ ] Run Multisite PHPUnit and verify RED.
- [ ] Implement Core-only network wrappers and explicit network checks.
- [ ] Run Multisite targeted tests and full suite; verify GREEN.
- [ ] Commit: `feat: support multisite plugin management`.

### Task 8: Plugin dependency/status metadata

**Files:**
- Modify: `includes/class-plugin-abilities.php`
- Test: `tests/phpunit/PluginAbilitiesTest.php`

**Interfaces:**
- Extends inventory/get outputs with WordPress Core dependency/update status where available.
- Uses WordPress Core dependency APIs/metadata only.

- [ ] Write failing tests for dependency metadata and Core dependency failure propagation.
- [ ] Run and verify RED.
- [ ] Add normalization around Core-provided dependency state only.
- [ ] Run targeted and full suite; verify GREEN.
- [ ] Commit: `feat: expose core plugin dependency status`.

### Task 9: Single-site E2E parity flow

**Files:**
- Modify: `tests/e2e/run.sh`
- Modify/Create E2E assertions/fixtures as needed.
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Required CI flow: discovery -> search/info -> install -> activate -> list/get -> update check -> update -> auto-update toggle -> deactivate -> delete.

- [ ] Extend E2E assertions first so current branch fails for missing abilities.
- [ ] Verify RED in GitHub Actions.
- [ ] Wire real WordPress calls to the new abilities without mocking Core.
- [ ] Verify the full single-site E2E job GREEN.
- [ ] Commit: `test: require complete plugin management e2e`.

### Task 10: Multisite E2E parity flow

**Files:**
- Create/Modify: `tests/e2e-multisite/*`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Required Multisite flow: install -> network activate -> verify network/site state -> network deactivate -> update -> delete -> verify absent.

- [ ] Add Multisite E2E job and assertions before any environment-specific workaround.
- [ ] Run CI and verify RED on missing/broken network behavior.
- [ ] Fix only environment or wrapper issues; do not bypass Core behavior.
- [ ] Verify Multisite E2E GREEN.
- [ ] Commit: `test: require multisite plugin management e2e`.

### Task 11: Documentation and final compatibility audit

**Files:**
- Modify: `README.md`
- Test: full PHPUnit + all CI jobs.

**Interfaces:**
- Documents all plugin abilities, site/network scope, permissions, Core API delegation, and Plugin File Editor exclusion.

- [ ] Update README ability table and security model.
- [ ] Verify no Plugin File Editor ability exists.
- [ ] Verify existing released plugin ability schemas remain compatible.
- [ ] Run full PHPUnit, coding standards, syntax checks, single-site E2E, and Multisite E2E.
- [ ] Commit: `docs: document complete plugin management abilities`.

