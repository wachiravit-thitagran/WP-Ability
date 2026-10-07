# Native Plugin Package Ability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `wordpress/plugin-install-package` to WordPress Abilities Bridge so MCP can install or overwrite custom plugin ZIP packages through WordPress Core `Plugin_Upgrader`, with a required real-WordPress E2E CI flow.

**Architecture:** Keep the existing `Abilities` class as the registration surface, but isolate package installation behavior in a focused helper class so permission, URL validation, and upgrader orchestration are independently testable. The E2E job boots a real WordPress + MariaDB environment, activates the bridge, serves fixture ZIPs locally, invokes the ability, verifies install/overwrite/activation, then deactivates and deletes the fixture plugin.

**Tech Stack:** PHP 7.4+, WordPress 6.9+, WordPress Abilities API, `Plugin_Upgrader`, PHPUnit, WP-CLI, GitHub Actions, MariaDB, PHP built-in HTTP server.

**Spec:** `docs/superpowers/specs/2026-10-07-native-plugin-package-ability-design.md`

## Global Constraints

- Keep `wordpress/plugin-install` WordPress.org-only.
- Keep `wordpress/plugin-update` semantics unchanged.
- New ability name is exactly `wordpress/plugin-install-package`.
- Accepted package source is HTTPS URL only.
- No arbitrary local filesystem path input.
- No credential/token persistence.
- Use `Plugin_Upgrader::install()`; do not call `unzip_file()`, `copy_dir()`, or delete plugin directories directly.
- `overwrite=true` maps to `overwrite_package => true`.
- `activate=true` requires `activate_plugins` and verifies active state after activation.
- `overwrite=true` requires `update_plugins`.
- Every request requires `install_plugins`.
- E2E must exercise real WordPress upgrader code without mocking `Plugin_Upgrader`.
- E2E is a required CI gate.

## Review Focus

- Redirecting HTTPS package URLs to non-HTTPS/private destinations: request should fail safely rather than bypass source policy; add coverage to Task 2.
- Existing plugin plus `overwrite=false`: WordPress should refuse replacement and the ability should return a structured error; add coverage to Task 3.
- `overwrite=true` without `update_plugins`: permission callback must reject before execution; add coverage to Task 1.
- Package installs successfully but `plugin_info()` returns empty: return `WP_Error` and never claim success; add coverage to Task 3.
- Activation request succeeds nominally but plugin is not active: return activation verification error; add coverage to Task 3.

---

### Task 1: Register the package ability and capability model

**Files:**
- Modify: `includes/class-abilities.php`
- Test: `tests/phpunit/AbilitiesTest.php`

**Interfaces:**
- Consumes: existing `Abilities::register_abilities()`, `Abilities::meta()`.
- Produces: `Abilities::can_install_plugin_package(array $input): bool`, registered ability `wordpress/plugin-install-package` with input keys `package_url`, `overwrite`, `activate`.

- [ ] **Step 1: Write failing registration and permission tests**

Add tests:
- `test_registers_plugin_install_package_ability()`
- `test_plugin_install_package_requires_install_plugins()`
- `test_plugin_install_package_overwrite_requires_update_plugins()`
- `test_plugin_install_package_activation_requires_activate_plugins()`

Assertions must verify:
- `wp_get_ability('wordpress/plugin-install-package')` is non-null.
- Subscriber cannot execute.
- Administrator-like user lacking `update_plugins` is denied when `overwrite=true`.
- User lacking `activate_plugins` is denied when `activate=true`.
- A user with all required capabilities is allowed.

- [ ] **Step 2: Run tests and verify RED**

Run:
`vendor/bin/phpunit tests/phpunit/AbilitiesTest.php --filter PluginInstallPackage`

Expected: FAIL because the ability and permission callback do not exist.

- [ ] **Step 3: Register `wordpress/plugin-install-package` and add `can_install_plugin_package(array $input)`**

Schema:
- `package_url`: string, format `uri`, required.
- `overwrite`: boolean, default `false`.
- `activate`: boolean, default `false`.
- `additionalProperties: false`.

Metadata:
- readonly false.
- destructive false.
- idempotent false.
- openWorldHint true.

Capability logic:
- require `install_plugins`.
- additionally require `update_plugins` when overwrite requested.
- additionally require `activate_plugins` when activation requested.

- [ ] **Step 4: Run tests and verify GREEN**

Run:
`vendor/bin/phpunit tests/phpunit/AbilitiesTest.php --filter PluginInstallPackage`

Expected: PASS.

- [ ] **Step 5: Commit**

`git add includes/class-abilities.php tests/phpunit/AbilitiesTest.php && git commit -m "feat: register plugin package install ability"`

---

### Task 2: Add HTTPS package source validation

**Files:**
- Create: `includes/class-plugin-package-installer.php`
- Modify: `wp-ability.php`
- Test: `tests/phpunit/PluginPackageInstallerTest.php`

**Interfaces:**
- Produces: `WP_Ability\Plugin_Package_Installer::validate_package_url(string $package_url): string|\WP_Error`.
- Later tasks consume: `Plugin_Package_Installer::install(array $input): array|\WP_Error`.

- [ ] **Step 1: Write failing URL validation tests**

Add tests:
- `test_rejects_empty_package_url()`
- `test_rejects_http_package_url()`
- `test_rejects_local_file_path()`
- `test_accepts_valid_https_package_url()`
- `test_rejects_https_url_that_wordpress_marks_unsafe()`

Assertions:
- invalid cases return `WP_Error`.
- valid case returns normalized URL string.
- validation uses WordPress safe URL validation rather than regex-only acceptance.

- [ ] **Step 2: Run tests and verify RED**

Run:
`vendor/bin/phpunit tests/phpunit/PluginPackageInstallerTest.php`

Expected: FAIL because class does not exist.

- [ ] **Step 3: Implement `Plugin_Package_Installer::validate_package_url(string $package_url)`**

Rules:
- trim input.
- require `https` scheme.
- use `wp_http_validate_url()`.
- return `wp_ability_invalid_plugin_package_url` error for rejected input.
- do not accept local paths or non-HTTP schemes.

- [ ] **Step 4: Load installer class from `wp-ability.php`**

Add `require_once __DIR__ . '/includes/class-plugin-package-installer.php';` before boot.

- [ ] **Step 5: Run tests and verify GREEN**

Run:
`vendor/bin/phpunit tests/phpunit/PluginPackageInstallerTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

`git add includes/class-plugin-package-installer.php wp-ability.php tests/phpunit/PluginPackageInstallerTest.php && git commit -m "feat: validate plugin package sources"`

---

### Task 3: Implement native Plugin_Upgrader install/overwrite behavior

**Files:**
- Modify: `includes/class-plugin-package-installer.php`
- Test: `tests/phpunit/PluginPackageInstallerTest.php`

**Interfaces:**
- Consumes: `validate_package_url(string): string|\WP_Error`.
- Produces: `Plugin_Package_Installer::install(array $input): array|\WP_Error`.
- Success result keys: `plugin_file`, `installed`, `overwritten`, `activated`, optional `version`.

- [ ] **Step 1: Write failing upgrader orchestration tests**

Add tests covering:
- successful install with `overwrite=false`.
- successful overwrite with `overwrite=true`.
- existing plugin + `overwrite=false` returns structured error.
- `Plugin_Upgrader::install()` returning `WP_Error`.
- `Plugin_Upgrader::install()` returning `false`.
- empty `plugin_info()` returns `wp_ability_plugin_file_unknown`.
- `activate=true` calls activation and verifies active state.
- activation API returns `WP_Error`.
- activation nominally succeeds but `is_plugin_active()` remains false -> verification error.

Use the project test harness to substitute a fake upgrader factory only in PHPUnit; production implementation must instantiate WordPress `Automatic_Upgrader_Skin` and `Plugin_Upgrader`.

- [ ] **Step 2: Run tests and verify RED**

Run:
`vendor/bin/phpunit tests/phpunit/PluginPackageInstallerTest.php`

Expected: FAIL because install orchestration is missing.

- [ ] **Step 3: Implement `Plugin_Package_Installer::install(array $input)`**

Required behavior:
- validate URL first.
- load `class-wp-upgrader.php` and plugin APIs.
- instantiate `Automatic_Upgrader_Skin`.
- instantiate `Plugin_Upgrader`.
- call `install($package_url, ['overwrite_package' => $overwrite, 'clear_update_cache' => true])`.
- map false/error results to structured `WP_Error`.
- call `plugin_info()`.
- refresh plugin cache.
- inspect version via `get_plugins()`.
- activate and verify when requested.
- return only semantic result fields; no filesystem paths.

- [ ] **Step 4: Run tests and verify GREEN**

Run:
`vendor/bin/phpunit tests/phpunit/PluginPackageInstallerTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

`git add includes/class-plugin-package-installer.php tests/phpunit/PluginPackageInstallerTest.php && git commit -m "feat: install plugin packages with Plugin_Upgrader"`

---

### Task 4: Connect the ability execute callback to the installer

**Files:**
- Modify: `includes/class-abilities.php`
- Test: `tests/phpunit/AbilitiesTest.php`

**Interfaces:**
- Consumes: `Plugin_Package_Installer::install(array $input)`.
- Produces: `Abilities::plugin_install_package(array $input): array|\WP_Error`.

- [ ] **Step 1: Write failing execution callback test**

Add `test_plugin_install_package_delegates_to_installer()`.

Assert:
- callback returns installer result.
- invalid URL propagates installer `WP_Error`.
- result contains no filesystem path key.

- [ ] **Step 2: Run test and verify RED**

Run:
`vendor/bin/phpunit tests/phpunit/AbilitiesTest.php --filter plugin_install_package`

Expected: FAIL because execute callback is missing.

- [ ] **Step 3: Implement callback**

Add `plugin_install_package(array $input)` that instantiates `Plugin_Package_Installer` and returns `install($input)`.

Keep business logic out of `Abilities`.

- [ ] **Step 4: Run tests and verify GREEN**

Run:
`vendor/bin/phpunit tests/phpunit/AbilitiesTest.php --filter plugin_install_package`

Expected: PASS.

- [ ] **Step 5: Commit**

`git add includes/class-abilities.php tests/phpunit/AbilitiesTest.php && git commit -m "feat: execute plugin package ability"`

---

### Task 5: Add real WordPress E2E fixture packages and runner

**Files:**
- Create: `tests/e2e/fixture-v1/fixture-plugin.php`
- Create: `tests/e2e/fixture-v2/fixture-plugin.php`
- Create: `tests/e2e/run.sh`
- Create: `tests/e2e/assert-flow.php`

**Interfaces:**
- Fixture plugin slug: `wp-ability-e2e-fixture`.
- v1 header version: `1.0.0`.
- v2 header version: `2.0.0`.
- Runner exits non-zero on any assertion failure.

- [ ] **Step 1: Create E2E assertions before wiring CI**

`assert-flow.php` must expose commands that verify:
- bridge active.
- ability discoverable.
- fixture installed.
- exact version.
- active/inactive state.
- fixture deleted.

Do not mock WordPress upgrader classes.

- [ ] **Step 2: Create fixture plugins**

Both fixture ZIP roots must use the same directory slug `wp-ability-e2e-fixture/`.
Only version and a harmless constant/function differ between v1 and v2.

- [ ] **Step 3: Create `tests/e2e/run.sh`**

Flow:
1. Build v1/v2 ZIPs.
2. Start local package HTTP server reachable from WordPress.
3. Activate bridge.
4. Invoke `wordpress/plugin-install-package` for v1 with `activate=true`.
5. Assert installed, version 1.0.0, active.
6. Invoke same ability for v2 with `overwrite=true`, `activate=true`.
7. Assert version 2.0.0, active.
8. Invoke existing deactivate ability.
9. Assert inactive.
10. Invoke existing delete ability.
11. Assert fixture absent.

- [ ] **Step 4: Run E2E locally in CI-compatible environment and verify RED if ability is unavailable**

Run:
`bash tests/e2e/run.sh`

Expected before CI infrastructure is wired: non-zero with a clear environment/WordPress setup prerequisite message, not silent success.

- [ ] **Step 5: Commit**

`git add tests/e2e && git commit -m "test: add plugin package end-to-end flow"`

---

### Task 6: Add required real-WordPress E2E GitHub Actions job

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: `tests/e2e/run.sh`.
- Produces: required `e2e` job covering install -> overwrite -> deactivate -> delete.

- [ ] **Step 1: Add E2E job**

Job requirements:
- MariaDB service.
- PHP compatible with production floor/current supported runtime.
- WordPress test site installed with WP-CLI.
- repository plugin mounted/copied into `wp-content/plugins/wordpress-abilities-bridge`.
- local HTTP fixture server.
- `bash tests/e2e/run.sh`.

- [ ] **Step 2: Run workflow and verify first E2E result**

Expected: workflow reaches real WordPress ability execution. Any failure must name the failing flow step.

- [ ] **Step 3: Fix only E2E environment wiring issues**

Do not weaken assertions or replace WordPress Core paths with mocks.

- [ ] **Step 4: Verify full workflow GREEN**

Required:
- existing PHP/PHPUnit job passes.
- E2E job passes.
- fixture v1 install verified.
- fixture v2 overwrite verified.
- activation verified.
- deactivation verified.
- deletion verified.

- [ ] **Step 5: Commit**

`git add .github/workflows/ci.yml tests/e2e && git commit -m "ci: require real WordPress plugin package e2e"`

---

### Task 7: Update documentation and migration guidance

**Files:**
- Modify: `README.md`

**Interfaces:**
- Documents new ability contract and migration away from `WP-plugin-deploy`.

- [ ] **Step 1: Add documentation assertions/checklist**

Verify README will contain:
- `wordpress/plugin-install-package`.
- HTTPS-only source rule.
- overwrite/update capability requirement.
- activation capability requirement.
- example request.
- statement that native `Plugin_Upgrader` owns installation.
- migration note that `WP-plugin-deploy` is no longer required for normal custom ZIP install/overwrite once deployed.

- [ ] **Step 2: Update README**

Keep existing plugin-install/update descriptions unchanged.

- [ ] **Step 3: Run full test suite**

Run project PHPUnit command from `composer.json`, then run all CI-local checks available.

Expected: all unit/integration tests pass.

- [ ] **Step 4: Commit**

`git add README.md && git commit -m "docs: document plugin package ability"`

---

### Task 8: Production connector verification

**Files:**
- No source changes unless a verified compatibility bug is found.

**Interfaces:**
- Production MCP should discover `wordpress/plugin-install-package`.

- [ ] **Step 1: Build/install latest bridge ZIP on the target WordPress site**

Use the repository's existing release/build process.

- [ ] **Step 2: Discover abilities through MCP**

Verify `wordpress/plugin-install-package` appears with the expected schema and annotations.

- [ ] **Step 3: Execute a controlled package install/update test**

Use one owned test plugin package or a known internal plugin package.
Verify:
- ability call reaches WordPress.
- install/overwrite succeeds.
- version and activation state are correct.

- [ ] **Step 4: Verify existing abilities still work**

At minimum:
- `wordpress/plugin-install`
- `wordpress/plugin-update`
- `wordpress/plugin-list`
- `wordpress/plugin-activate`
- `wordpress/plugin-deactivate`
- `wordpress/plugin-delete`

- [ ] **Step 5: Record final validation result**

Document commit SHA, CI run, WordPress version, PHP version, installed bridge version, and tested package/version pair before release completion.
