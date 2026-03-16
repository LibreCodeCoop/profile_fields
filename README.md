<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Profile fields

Turn Nextcloud accounts into a structured directory for real operational work.

Profile fields lets teams add organization-specific profile data that does not fit in the default Nextcloud profile, with clear governance over who can edit each field and who can see it.

- Model support regions, customer segments, escalation aliases, incident roles and other business-specific profile data.
- Combine self-service updates with admin-managed fields for sensitive operational context.
- Surface the same custom data in personal settings, admin catalog management and user administration workflows.

This makes the app useful for internal directories, support operations, partner programs and other corporate deployments that need richer account metadata without leaving Nextcloud.

## API documentation

The public API contract for this app is published as [openapi-full.json](https://github.com/LibreCodeCoop/profile_fields/blob/main/openapi-full.json).

## Data backup and import

Run the app commands from the Nextcloud stack root, not from the host PHP environment.

Export the current Profile Fields catalog and stored values:

```bash
docker compose exec -u www-data -w /var/www/html nextcloud \
	php occ profile_fields:data:export \
	--output=/tmp/profile_fields-export.json
```

Validate an import payload without writing anything:

```bash
docker compose exec -u www-data -w /var/www/html nextcloud \
	php occ profile_fields:data:import \
	--input=/tmp/profile_fields-export.json \
	--dry-run
```

Apply the non-destructive `upsert` import:

```bash
docker compose exec -u www-data -w /var/www/html nextcloud \
	php occ profile_fields:data:import \
	--input=/tmp/profile_fields-export.json
```

Notes:

- The import contract is versioned with `schema_version` and reconciles definitions by `field_key` and values by `field_key + user_uid`.
- The first delivery is non-destructive: missing items in the payload do not delete existing definitions or values.
- Validation is all-or-nothing. If the payload contains an incompatible definition or a missing destination user, no database write is performed.
- For a restore in the same environment, clear app data explicitly before reimporting:

```bash
docker compose exec -u www-data -w /var/www/html nextcloud \
	php occ profile_fields:data:clear \
	--definitions \
	--force
```

## Screenshots

![Admin catalog](img/screenshots/admin-catalog.png)
