<script setup lang="ts">
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { DEFAULT_TAG_COLOR, TAG_COLORS } from '../utils/tags'

const props = withDefaults(defineProps<{
	title: string
	initialName?: string
	initialColor?: string
	confirmLabel?: string
	busy?: boolean
	error?: string | null
}>(), {
	initialName: '',
	initialColor: DEFAULT_TAG_COLOR,
	confirmLabel: 'Save tag',
	busy: false,
	error: null,
})

const emit = defineEmits<{
	(event: 'confirm', value: { name: string, color: string }): void
	(event: 'close'): void
}>()

const name = ref(props.initialName)
const color = ref(props.initialColor)

watch(() => props.initialName, (value) => {
	name.value = value
})

watch(() => props.initialColor, (value) => {
	color.value = value
})

function close(): void {
	emit('close')
}

function submit(): void {
	const trimmed = name.value.trim()
	if (trimmed === '' || props.busy) {
		return
	}

	emit('confirm', { name: trimmed, color: color.value })
}
</script>

<template>
	<NcDialog
		:open="true"
		:name="title"
		size="small"
		@closing="close">
		<NcNoteCard v-if="error" type="error" :text="error" />
		<form class="tag-edit-form" @submit.prevent="submit">
			<NcTextField
				v-model="name"
				label="Tag name"
				autofocus />
			<fieldset class="tag-colors">
				<legend class="tag-colors-label">
					Color
				</legend>
				<div class="tag-color-options">
					<button
						v-for="option in TAG_COLORS"
						:key="option.value"
						type="button"
						class="tag-color-option"
						:class="{ 'tag-color-option--selected': option.value === color }"
						:style="{ backgroundColor: option.value }"
						:title="option.name"
						:aria-label="`${option.name} tag color`"
						:aria-pressed="option.value === color"
						@click="color = option.value" />
				</div>
			</fieldset>
		</form>
		<template #actions>
			<NcButton variant="tertiary" :disabled="busy" @click="close">
				Cancel
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="busy || name.trim() === ''"
				@click="submit">
				{{ confirmLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.tag-edit-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-width: 280px;
}

.tag-colors {
	margin: 0;
	padding: 0;
	border: none;
}

.tag-colors-label {
	margin-block-end: 6px;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.tag-color-options {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.tag-color-option {
	width: 28px;
	height: 28px;
	padding: 0;
	border: 2px solid transparent;
	border-radius: 50%;
	box-shadow: 0 0 0 1px var(--color-border-dark) inset;
	cursor: pointer;
}

.tag-color-option:hover {
	transform: scale(1.08);
}

.tag-color-option--selected {
	border-color: var(--color-main-text);
	box-shadow: 0 0 0 2px var(--color-main-background) inset;
}

.tag-color-option:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}
</style>
