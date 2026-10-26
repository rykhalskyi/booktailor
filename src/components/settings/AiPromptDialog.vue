<script setup lang="ts">
import type { AiPrompt } from '../../types'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { api, ApiError } from '../../api/client'

const props = defineProps<{
	open: boolean
	bookId: number
	entity?: AiPrompt
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [prompt: AiPrompt]
	updated: [prompt: AiPrompt]
	deleted: [id: number]
}>()

const name = ref('')
const text = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const isEditing = computed(() => props.entity !== undefined)
const canSubmit = computed(() => !busy.value && name.value.trim() !== '' && text.value.trim() !== '')

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		error.value = null
		busy.value = false
		name.value = props.entity?.name ?? ''
		text.value = props.entity?.text ?? ''
	},
)

function messageFor(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError ? errorValue.message : fallback
}

async function submit(): Promise<void> {
	if (!canSubmit.value) {
		return
	}
	busy.value = true
	error.value = null
	try {
		if (props.entity === undefined) {
			emit('created', await api.ai.prompts.create(props.bookId, name.value.trim(), text.value.trim()))
		} else {
			emit('updated', await api.ai.prompts.update(props.entity.id, name.value.trim(), text.value.trim()))
		}
		emit('update:open', false)
	} catch (errorValue) {
		error.value = messageFor(errorValue, 'Could not save the prompt.')
	} finally {
		busy.value = false
	}
}

async function remove(): Promise<void> {
	if (props.entity === undefined) {
		return
	}
	busy.value = true
	error.value = null
	try {
		await api.ai.prompts.remove(props.entity.id)
		emit('deleted', props.entity.id)
		emit('update:open', false)
	} catch (errorValue) {
		error.value = messageFor(errorValue, 'Could not delete the prompt.')
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? 'Edit saved prompt' : 'Save a prompt'"
		:open="props.open"
		size="normal"
		is-form
		@submit="submit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcNoteCard v-if="error" type="error" :text="error" />
			<NcTextField v-model="name" label="Prompt name" :disabled="busy" />
			<textarea
				v-model="text"
				:class="$style.textarea"
				rows="7"
				placeholder="Write the instructions for the assistant…"
				:disabled="busy" />
		</div>
		<template #actions>
			<NcButton
				v-if="isEditing"
				type="button"
				variant="error"
				:disabled="busy"
				@click="remove">
				Delete
			</NcButton>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="busy"
				@click="emit('update:open', false)">
				Cancel
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				{{ isEditing ? 'Save' : 'Add' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.textarea {
	box-sizing: border-box;
	width: 100%;
	resize: vertical;
	min-height: 120px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.textarea:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}
</style>
