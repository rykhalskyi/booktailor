<script setup lang="ts">
import type { AiProfile } from '../../types'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { getProviderConfig } from '../../constants/aiProviders'

const props = defineProps<{
	profile: AiProfile
	disabled?: boolean
}>()

const emit = defineEmits<{
	select: [profile: AiProfile]
	edit: [profile: AiProfile]
}>()

const provider = computed(() => getProviderConfig(props.profile.provider))
const model = computed(() => props.profile.model || provider.value.defaultModel)
</script>

<template>
	<div
		:class="[$style.card, { [$style.cardActive]: props.profile.isActive }]"
		@click="emit('select', props.profile)">
		<div :class="$style.radio" @click.stop>
			<NcCheckboxRadioSwitch
				:model-value="props.profile.isActive"
				type="radio"
				name="active-ai-profile"
				:disabled="props.disabled"
				aria-label="Use this AI profile"
				@update:model-value="emit('select', props.profile)" />
		</div>

		<div :class="$style.main">
			<div :class="$style.header">
				<h3 :class="$style.title">
					{{ props.profile.name }}
				</h3>
				<span :class="$style.badge">{{ provider.label }}</span>
				<span v-if="props.profile.isActive" :class="$style.active">Active</span>
			</div>
			<div :class="$style.details">
				<span>Model: {{ model }}</span>
				<span>Key: <code>{{ props.profile.apiKeyMasked }}</code></span>
			</div>
		</div>

		<div :class="$style.actions" @click.stop>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="props.disabled"
				aria-label="Edit AI profile"
				@click="emit('edit', props.profile)">
				Edit
			</NcButton>
		</div>
	</div>
</template>

<style module>
.card {
	display: flex;
	align-items: center;
	padding: 16px;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	gap: 16px;
	cursor: pointer;
}

.card:hover {
	border-color: var(--color-primary-element);
	background-color: var(--color-background-hover);
}

.cardActive {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light, rgba(0, 130, 201, 0.05));
}

.radio {
	display: flex;
	align-items: center;
}

.main {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.title {
	margin: 0;
	font-size: var(--font-size-large, 16px);
	font-weight: 600;
}

.badge,
.active {
	padding: 2px 8px;
	border-radius: 12px;
	font-size: var(--font-size-small, 12px);
	background-color: var(--color-background-darker);
	color: var(--color-text-maxcontrast);
}

.active {
	background-color: var(--color-primary-element);
	color: var(--color-primary-text, #fff);
	font-weight: 600;
}

.details {
	display: flex;
	gap: 16px;
	flex-wrap: wrap;
	font-size: var(--font-size-small, 13px);
	color: var(--color-text-maxcontrast);
}

.actions {
	display: flex;
	align-items: center;
}
</style>
