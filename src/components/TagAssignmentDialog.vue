<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useLibrary } from '../stores/useLibrary'
import { tagChipStyle } from '../utils/tags'

const { assignAssetTags, closeDialog, dialog, error, loading, tags } = useLibrary()

const selected = ref<number[]>([])

const asset = computed(() => dialog.value?.type === 'asset-tags' ? dialog.value.asset : null)

watch(asset, (current) => {
	selected.value = current ? current.tags.map((tag) => tag.id) : []
}, { immediate: true })

function toggleTag(tagId: number): void {
	selected.value = selected.value.includes(tagId)
		? selected.value.filter((id) => id !== tagId)
		: [...selected.value, tagId]
}

async function save(): Promise<void> {
	const current = asset.value
	if (current === null) {
		return
	}

	if (await assignAssetTags(current, selected.value)) {
		closeDialog()
	}
}
</script>

<template>
	<NcDialog
		:open="asset !== null"
		:name="asset ? `Tags — ${asset.fileName}` : 'Tags'"
		size="small"
		@closing="closeDialog">
		<div class="tag-assignment">
			<NcNoteCard v-if="error" type="error" :text="error" />
			<p v-if="tags.length === 0" class="tag-assignment-empty">
				No tags exist in this book yet. Create them in the Tags screen first.
			</p>
			<ul v-else class="tag-choice-list">
				<li v-for="tag in tags" :key="tag.id">
					<label class="tag-choice">
						<input
							type="checkbox"
							:checked="selected.includes(tag.id)"
							@change="toggleTag(tag.id)">
						<span class="tag-choice-name" :style="tagChipStyle(tag.color)">{{ tag.name }}</span>
					</label>
				</li>
			</ul>
		</div>
		<template #actions>
			<NcButton variant="tertiary" :disabled="loading.mutation" @click="closeDialog">
				Cancel
			</NcButton>
			<NcButton variant="primary" :disabled="loading.mutation" @click="save">
				Save tags
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped>
.tag-assignment {
	display: flex;
	flex-direction: column;
	gap: 10px;
	min-width: 240px;
}

.tag-assignment-empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.tag-choice-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.tag-choice {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 2px;
	cursor: pointer;
}

.tag-choice-name {
	padding: 2px 10px;
	border-radius: 12px;
	font-weight: 500;
	font-size: 0.9rem;
}
</style>
