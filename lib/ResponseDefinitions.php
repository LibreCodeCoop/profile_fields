<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields;

/**
 * @psalm-type ProfileFieldsType = 'text'|'number'|'boolean'|'date'|'select'|'multiselect'
 * @psalm-type ProfileFieldsVisibility = 'private'|'users'|'public'
 * @psalm-type ProfileFieldsEditPolicy = 'admins'|'users'
 * @psalm-type ProfileFieldsExposurePolicy = 'hidden'|'private'|'users'|'public'
 * @psalm-type ProfileFieldsDefinitionInput = array{
 *     field_key?: string,
 *     label?: string,
 *     type?: string,
 *     edit_policy?: ProfileFieldsEditPolicy,
 *     exposure_policy?: ProfileFieldsExposurePolicy,
 *     sort_order?: int,
 *     active?: bool,
 *     options?: list<string>,
 * }
 * @psalm-type ProfileFieldsDefinition = array{
 *     id: int,
 *     field_key: non-empty-string,
 *     label: non-empty-string,
 *     type: ProfileFieldsType,
 *     edit_policy: ProfileFieldsEditPolicy,
 *     exposure_policy: ProfileFieldsExposurePolicy,
 *     sort_order: int,
 *     active: bool,
 *     options: list<string>|null,
 *     created_at: string,
 *     updated_at: string,
 * }
 * @psalm-type ProfileFieldsValuePayload = array{
 *     value: mixed,
 * }
 * @psalm-type ProfileFieldsValueRecord = array{
 *     id: int,
 *     field_definition_id: int,
 *     user_uid: string,
 *     value: ProfileFieldsValuePayload,
 *     current_visibility: ProfileFieldsVisibility,
 *     updated_by_uid: string,
 *     updated_at: string,
 * }
 * @psalm-type ProfileFieldsEditableField = array{
 *     definition: ProfileFieldsDefinition,
 *     value: ProfileFieldsValueRecord|null,
 *     can_edit: bool,
 * }
 * @psalm-type ProfileFieldsLookupField = array{
 *     definition: ProfileFieldsDefinition,
 *     value: ProfileFieldsValueRecord,
 * }
 * @psalm-type ProfileFieldsLookupResult = array{
 *     user_uid: string,
 *     lookup_field_key: string,
 *     fields: array<string, ProfileFieldsLookupField>,
 * }
 * @psalm-type ProfileFieldsSearchItem = array{
 *     user_uid: string,
 *     display_name: string,
 *     fields: array<string, ProfileFieldsLookupField>,
 * }
 * @psalm-type ProfileFieldsSearchResult = array{
 *     items: list<ProfileFieldsSearchItem>,
 *     pagination: array{limit: int, offset: int, total: int},
 * }
 */
final class ResponseDefinitions {
	private function __construct() {
	}
}
