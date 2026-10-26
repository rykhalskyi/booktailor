<script setup lang="ts">
import type { AiProfile, AiProfilePayload, AiProvider } from '../../types'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { AI_PROVIDERS } from '../../constants/aiProviders'
import { api, ApiError } from '../../api/client'

const props = defineProps<{
	open: boolean
	entity?: AiProfile
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [profile: AiProfile]
	updated: [profile: AiProfile]
	deleted: [id: number]
}>()

const name = ref('')
const provider = ref(AI_PROVIDERS[0])
const apiKey = ref('')
const model = ref('')
const connectTimeout = ref('30')
const readTimeout = ref('60')
const maxTokens = ref('2048')
const showAdvanced = ref(false)
const submitting = ref(false)
const deleting = ref(false)
const error = ref<string | null>(null)

const isEditing = computed(() => props.entity !== undefined)
const canSubmit = computed(() => !submitting.value
	&& !deleting.value
	&& name.value.trim() !== ''
	&& (isEditing.value || apiKey.value.trim() !== ''))

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		error.value = null
		submitting.value = false
		deleting.value = false
		showAdvanced.value = false
		const entity = props.entity
		if (entity) {
			name.value = entity.name
			provider.value = AI_PROVIDERS.find((candidate) => candidate.id === entity.provider) ?? AI_PROVIDERS[0]
			apiKey.value = ''
			model.value = entity.model ?? ''
			connectTimeout.value = String(entity.connectTimeoutSeconds ?? 30)
			readTimeout.value = String(entity.readTimeoutSeconds ?? 60)
			maxTokens.value = String(entity.maxTokens ?? 2048)
		} else {
			name.value = ''
			provider.value = AI_PROVIDERS[0]
			apiKey.value = ''
			model.value = ''
			connectTimeout.value = '30'
			readTimeout.value = '60'
			maxTokens.value = '2048'
		}
	},
)

function errorMessage(errorValue: unknown, fallback: string): string {
	return errorValue instanceof ApiError ? errorValue.message : fallback
}

async function submit(): Promise<void> {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const payload: AiProfilePayload = {
			name: name.value.trim(),
			provider: provider.value.id as AiProvider,
			model: model.value.trim() || provider.value.defaultModel,
			connectTimeoutSeconds: Number.parseInt(connectTimeout.value, 10) || 30,
			readTimeoutSeconds: Number.parseInt(readTimeout.value, 10) || 60,
			maxTokens: Number.parseInt(maxTokens.value, 10) || 2048,
		}
		if (apiKey.value.trim() !== '') {
			payload.apiKey = apiKey.value.trim()
		}

		if (props.entity === undefined) {
			emit('created', await api.ai.profiles.create(payload))
		} else {
			emit('updated', await api.ai.profiles.update(props.entity.id, payload))
		}
		emit('update:open', false)
	} catch (errorValue) {
		error.value = errorMessage(errorValue, 'Could not save the AI profile.')
	} finally {
		submitting.value = false
	}
}

async function remove(): Promise<void> {
	if (props.entity === undefined) {
		return
	}
	deleting.value = true
	error.value = null
	try {
		await api.ai.profiles.remove(props.entity.id)
		emit('deleted', props.entity.id)
		emit('update:open', false)
	} catch (errorValue) {
		error.value = errorMessage(errorValue, 'Could not delete the AI profile.')
	} finally {
		deleting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? 'Edit AI profile' : 'Add AI profile'"
		:open="props.open"
		size="normal"
		is-form
		@submit="submit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcNoteCard v-if="error" type="error" :text="error" />

			<NcTextField
				v-model="name"
				label="Profile name"
				placeholder="DeepSeek"
				:disabled="submitting || deleting" />

			<div :class="$style.field">
				<NcSelect
					v-model="provider"
					:options="AI_PROVIDERS"
					label="label"
					input-label="Provider"
					:clearable="false"
					:disabled="submitting || deleting" />
				<p :class="$style.hint">
					DeepSeek and SiliconFlow are supported through OpenAI-compatible endpoints.
				</p>
			</div>

			<NcTextField
				v-model="apiKey"
				type="password"
				label="API key"
				:placeholder="isEditing ? props.entity?.apiKeyMasked : 'Paste your API key'"
				:disabled="submitting || deleting"
				:helper-text="isEditing ? 'Leave empty to keep the stored key.' : 'Encrypted before saving and never returned.'" />

			<NcTextField
				v-model="model"
				label="Model"
				:placeholder="provider.defaultModel"
				:disabled="submitting || deleting"
				:helper-text="`Leave empty to use ${provider.defaultModel}.`" />

			<NcButton type="button" variant="tertiary" @click="showAdvanced = !showAdvanced">
				{{ showAdvanced ? 'Hide advanced options' : 'Show advanced options' }}
			</NcButton>

			<div v-if="showAdvanced" :class="$style.advanced">
				<NcTextField
					v-model="connectTimeout"
					type="number"
					label="Connect timeout (seconds)"
					:disabled="submitting || deleting" />
				<NcTextField
					v-model="readTimeout"
					type="number"
					label="Read timeout (seconds)"
					:disabled="submitting || deleting" />
				<NcTextField
					v-model="maxTokens"
					type="number"
					label="Max tokens"
					:disabled="submitting || deleting" />
			</div>
		</div>

		<template #actions>
			<NcButton
				v-if="isEditing"
				type="button"
				variant="error"
				:disabled="submitting || deleting"
				@click="remove">
				Delete
			</NcButton>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting || deleting"
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
	gap: 16px;
}

.field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.advanced {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 12px;
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);
}
</style>
