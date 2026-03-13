<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use OCA\ProfileFields\AppInfo\Application;
use OCP\Util;

Util::addStyle(Application::APP_ID, 'profile_fields-settings-admin');
Util::addScript(Application::APP_ID, 'profile_fields-settings-admin');
?>

<div id="profile-fields-admin-settings"></div>
