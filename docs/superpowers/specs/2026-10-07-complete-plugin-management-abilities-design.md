# Complete Plugin Management Abilities Design

## Goal
Expose WordPress Admin plugin-management capabilities through WordPress Abilities for single-site and Multisite, excluding Plugin File Editor.

## Core-first rule
This project must remain a thin wrapper over WordPress Core. For each ability, call the same WordPress Core API/class/function used by WordPress Admin. Add only registration, schemas, permission checks, validation, and safe result normalization. Do not recreate Core plugin-management behavior.

## Abilities
Preserve existing plugin-list, plugin-install, plugin-install-package, plugin-update, plugin-activate, plugin-deactivate, and plugin-delete. Add plugin-get, plugin-search, plugin-get-information, plugin-check-updates, plugin-update-many, plugin-enable-auto-update, plugin-disable-auto-update, plugin-activate-many, plugin-deactivate-many, plugin-delete-many, plugin-network-activate, plugin-network-deactivate, and read-only MU-plugin inventory.

## Core mapping
Use get_plugins(), get_mu_plugins(), is_plugin_active(), is_plugin_active_for_network(), plugins_api(), Plugin_Upgrader::install(), Plugin_Upgrader::upgrade(), Plugin_Upgrader::bulk_upgrade(), activate_plugin(), deactivate_plugins(), delete_plugins(), wp_update_plugins(), and the same Core auto-update/dependency mechanisms used by WordPress Admin.

## Multisite
Support Multisite from the first release. Preserve Network Admin/Super Admin restrictions, use Core network activation/deactivation behavior, and never maintain network plugin state separately from WordPress Core.

## Structure
Create includes/class-plugin-abilities.php and mechanically move plugin-related registrations/callbacks from current classes while preserving public ability names. Keep Plugin_Package_Installer as a minimal adapter around Plugin_Upgrader only.

## Testing
Require unit/integration delegation tests plus real single-site and Multisite E2E flows covering discovery, install, activation, update, auto-update, network activation/deactivation, deletion, and state verification.

## Success criteria
The bridge covers WordPress Admin plugin-management behavior except Plugin File Editor, supports single-site and Multisite, keeps existing ability compatibility, and remains a thin wrapper around WordPress Core.
