<?php

declare(strict_types=1);

use OCA\ProfileFields\AppInfo\Application;
use OCP\Util;

Util::addStyle(Application::APP_ID, 'profile_fields-settings-personal');
Util::addScript(Application::APP_ID, 'profile_fields-settings-personal');
?>

<div id="profile-fields-personal-info-shell" class="personal-settings-setting-box profile-fields-personal-info-box">
	<div id="profile-fields-personal-info-settings"></div>
</div>
