<script setup lang="ts">
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = withDefaults(defineProps<{
	title: string
	label: string
	initialValue?: string
	confirmLabel?: string
	busy?: boolean
	error?: string | null
}>(), {
	initialValue: '',
	confirmLabel: 'Save',
	busy: false,
	error: null,
})

const emit = defineEmits<{
	(event: 'confirm', value: string): void
	(event: 'close'): void
}>()

const value = ref(props.initialValue)

watch(() => props.initialValue, (initialValue) => {
	value.value = initialValue
})

function close(): void {
	emit('close')
}

function submit(): void {
	const trimmed = value.value.trim()
	if (trimmed === '' || props.busy) {
		return
	}

	emit('confirm', trimmed)
}
</script>

<template>
	<NcDialog
		:open="true"
		:name="title"
		size="small"
		@closing="close">
		<NcNoteCard v-if="error" type="error" :text="error" />
		<form class="dialog-form" @submit.prevent="submit">
			<NcTextField
				v-model="value"
				:label="label"
				autofocus />
		</form>
		<template #actions>
			<NcButton variant="tertiary" :disabled="busy" @click="close">
				Cancel
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="busy || value.trim() === ''"
				@click="submit">
				{{ confirmLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.dialog-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-width: 260px;
}
</style>
