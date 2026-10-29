<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { Chapter } from '../types'
import { moveTargetsFor, type MoveTarget } from '../utils/movement'

const props = withDefaults(defineProps<{
	chapter: Chapter
	chapters: Chapter[]
	busy?: boolean
	error?: string | null
}>(), {
	busy: false,
	error: null,
})

const emit = defineEmits<{
	(event: 'confirm', parentId: number | null): void
	(event: 'close'): void
}>()

const targets = computed<MoveTarget[]>(() => moveTargetsFor(props.chapter, props.chapters))
const selected = ref<MoveTarget | null>(null)

watch(targets, (value) => {
	if (selected.value !== null && !value.some((target) => target.id === selected.value?.id)) {
		selected.value = null
	}
})

function onSelect(target: MoveTarget | null): void {
	selected.value = target
}

function close(): void {
	emit('close')
}

function confirm(): void {
	if (selected.value !== null && !props.busy) {
		emit('confirm', selected.value.id)
	}
}
</script>

<template>
	<NcDialog
		:open="true"
		:name="t('booktailor', 'Move chapter')"
		size="small"
		@closing="close">
		<NcNoteCard v-if="error" type="error" :text="error" />
		<p class="move-message">
			{{ t('booktailor', 'Move “{name}” to:', { name: chapter.name }) }}
		</p>
		<NcSelect
			v-if="targets.length > 0"
			:model-value="selected"
			:options="targets"
			label="label"
			:input-label="t('booktailor', 'New parent')"
			:aria-label="t('booktailor', 'New parent')"
			:placeholder="t('booktailor', 'Select a new parent')"
			:clearable="false"
			:searchable="false"
			@update:model-value="onSelect" />
		<NcNoteCard
			v-else
			type="info"
			:text="t('booktailor', 'This chapter has no allowed movement targets.')" />
		<template #actions>
			<NcButton variant="tertiary" :disabled="busy" @click="close">
				{{ t('booktailor', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="busy || selected === null"
				@click="confirm">
				{{ t('booktailor', 'Move chapter') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.move-message {
	margin: 0 0 12px;
	max-width: 420px;
}
</style>
