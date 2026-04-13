<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and follows the requirements of the [Nextcloud Appstore Metadata specification](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#changelog).

<!-- changelog-linker -->
<!-- changelog-linker -->
## [Unreleased]

## 1.0.2 - 2026-04-13

💝 **SUPPORT PROFILE FIELDS** — If this project helps your team, please support ongoing maintenance via GitHub Sponsors: https://github.com/sponsors/LibreSign

⭐ **STAR THE REPOSITORY** — Stars help the project gain visibility and justify continued investment: https://github.com/LibreCodeCoop/profile_fields

🏢 **ENTERPRISE SUPPORT** — Need custom development, support, or sponsored features? Contact us: contact@librecode.coop

### Fixed
- Fixed migration compatibility for `profile_fields_definitions.active` and ensured upgrade safety for existing installs [#73](https://github.com/LibreCodeCoop/profile_fields/pull/73)
- Fixed migration failure on strict database identifier limits by shortening an index name [#74](https://github.com/LibreCodeCoop/profile_fields/pull/74)

### Changed
- Updated project dependencies and CI tooling via Dependabot updates [#64](https://github.com/LibreCodeCoop/profile_fields/pull/64) [#66](https://github.com/LibreCodeCoop/profile_fields/pull/66) [#69](https://github.com/LibreCodeCoop/profile_fields/pull/69) [#70](https://github.com/LibreCodeCoop/profile_fields/pull/70) [#71](https://github.com/LibreCodeCoop/profile_fields/pull/71) [#72](https://github.com/LibreCodeCoop/profile_fields/pull/72)

## 1.0.1 - 2026-04-03

💝 **SUPPORT PROFILE FIELDS** — If this project helps your team, please support ongoing maintenance via GitHub Sponsors: https://github.com/sponsors/LibreSign

⭐ **STAR THE REPOSITORY** — Stars help the project gain visibility and justify continued investment: https://github.com/LibreCodeCoop/profile_fields

🏢 **ENTERPRISE SUPPORT** — Need custom development, support, or sponsored features? Contact us: contact@librecode.coop

### Fixed
- Fixed installation and reinstallation failures caused by the `profile_fields_definitions.active` boolean column being created as `NOT NULL`, and added an upgrade migration for existing instances [#61](https://github.com/LibreCodeCoop/profile_fields/pull/61)

## 1.0.0 - 2026-03-30

💝 **SUPPORT PROFILE FIELDS** — Built from scratch and released for free. If it saves your team time, please consider sponsoring: https://github.com/sponsors/LibreSign

🏢 **ENTERPRISE SUPPORT** — Need custom field types or a tailored implementation? Contact us: contact@librecode.coop

### Added
- Seven field types out of the box: text, select, multiselect, date, boolean, URL, and email [#5](https://github.com/LibreCodeCoop/profile_fields/pull/5) [#14](https://github.com/LibreCodeCoop/profile_fields/pull/14) [#27](https://github.com/LibreCodeCoop/profile_fields/pull/27) [#28](https://github.com/LibreCodeCoop/profile_fields/pull/28) [#29](https://github.com/LibreCodeCoop/profile_fields/pull/29) [#30](https://github.com/LibreCodeCoop/profile_fields/pull/30) [#31](https://github.com/LibreCodeCoop/profile_fields/pull/31)
- Per-field edit and visibility policies (admin-only, user-and-admin, visible-to-all, hidden)
- Admin field catalog and user management dialog for setting values per user [#8](https://github.com/LibreCodeCoop/profile_fields/pull/8) [#21](https://github.com/LibreCodeCoop/profile_fields/pull/21)
- Personal Settings page for self-service field updates, subject to field policies
- Nextcloud Flow integration: field-aware operator with type-specific conditions (contains, before/after, is true/false, is/is not) and five actions: log, notify admins, notify groups, Talk message, and webhook [#11](https://github.com/LibreCodeCoop/profile_fields/pull/11)
- Unified Search provider so admins can find users by any field value [#15](https://github.com/LibreCodeCoop/profile_fields/pull/15)
- OpenAPI-documented REST API with auto-generated TypeScript client
- `occ` commands for data export, import, clear, and developer reset [#5](https://github.com/LibreCodeCoop/profile_fields/pull/5)
- Transifex integration for community translations [#34](https://github.com/LibreCodeCoop/profile_fields/pull/34)

[Unreleased]: https://github.com/LibreCodeCoop/profile_fields/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/LibreCodeCoop/profile_fields/compare/v1.0.0...v1.0.1
