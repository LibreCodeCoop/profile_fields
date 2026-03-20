<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcNoteCard v-if="isVisible" type="info" data-testid="profile-fields-admin-support-banner">
		<div class="profile-fields-admin-support-banner">
			<div class="profile-fields-admin-support-banner__copy">
				<p><strong>Help keep Profile Fields sustainable.</strong></p>
				<p>Profile Fields is open source under the AGPL license and maintained by the LibreCode team, creators of LibreSign.</p>
				<p>If your organization depends on it, please help us sustain its development and maintenance.</p>

				<div class="profile-fields-admin-support-banner__actions">
					<NcButton class="profile-fields-admin-support-banner__action" variant="primary" @click="openSponsorPage">
						Sponsor LibreSign
					</NcButton>

					<NcButton class="profile-fields-admin-support-banner__dismiss" variant="tertiary-no-background" @click="dismissBanner">
						Maybe later
					</NcButton>
				</div>

				<div class="profile-fields-admin-support-banner__links">
					<a href="https://github.com/LibreCodeCoop/profile_fields" target="_blank" rel="noopener noreferrer nofollow">
						Give Profile Fields a ⭐ on GitHub
					</a>
					<a href="mailto:contact@librecode.coop">Contact us for support or custom development</a>
				</div>
			</div>
		</div>
	</NcNoteCard>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NcButton, NcNoteCard } from '@nextcloud/vue'

const props = withDefaults(defineProps<{
	storageKey?: string,
	sponsorUrl?: string,
}>(), {
	storageKey: 'profile_fields_support_banner_dismissed',
	sponsorUrl: 'https://github.com/sponsors/LibreCodeCoop',
})

const isVisible = ref(true)

const dismissBanner = () => {
	isVisible.value = false
	try {
		window.localStorage.setItem(props.storageKey, '1')
	} catch {
		// Ignore storage errors and keep only in-memory dismissal.
	}
}

const openSponsorPage = () => {
	window.open(props.sponsorUrl, '_blank', 'noopener,noreferrer')
}

onMounted(() => {
	try {
		isVisible.value = window.localStorage.getItem(props.storageKey) !== '1'
	} catch {
		isVisible.value = true
	}
})
</script>

<style scoped lang="scss">
.profile-fields-admin-support-banner {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 12px;
	width: 100%;

	&__copy {
		display: grid;
		gap: 8px;
		max-width: 72ch;

		p {
			margin: 0;
			overflow-wrap: anywhere;
			line-height: 1.5;
		}

		a {
			overflow-wrap: anywhere;
		}
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-start;
		gap: 8px 10px;
		padding-top: 2px;
	}

	&__action,
	&__dismiss {
		flex: 0 0 auto;
		white-space: nowrap;
	}

	&__dismiss {
		:deep(.button-vue) {
			padding-inline: 4px;
			min-height: auto;
		}
	}

	&__links {
		margin: 0;
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 6px 12px;
		line-height: 1.5;

		a {
			font-weight: 600;
		}
	}
}

@media (max-width: 1024px) {
	.profile-fields-admin-support-banner {
		gap: 8px;

		:deep(.button-vue) {
			max-width: 100%;
		}

		&__links {
			gap: 4px;
		}
	}
}
</style>
