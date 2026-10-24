<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useLibrary } from '../stores/useLibrary'
import ChapterNode from './ChapterNode.vue'

const { chapters, loading, openDialog } = useLibrary()
</script>

<template>
	<div class="chapter-tree">
		<div v-if="loading.tree" class="chapter-tree-state">
			<NcLoadingIcon name="Loading chapters" />
		</div>
		<NcEmptyContent
			v-else-if="chapters.length === 0"
			name="No chapters"
			description="Add a root chapter to start building the book.">
			<template #action>
				<NcButton
					variant="primary"
					@click="openDialog({ type: 'chapter-create', parentId: null })">
					Add chapter
				</NcButton>
			</template>
		</NcEmptyContent>
		<ul v-else class="chapter-tree-list">
			<ChapterNode
				v-for="chapter in chapters"
				:key="chapter.id"
				:chapter="chapter"
				:depth="0" />
		</ul>
	</div>
</template>

<style scoped>
.chapter-tree {
	height: 100%;
	padding: 8px;
	overflow: auto;
}

.chapter-tree-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.chapter-tree-state {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 120px;
}
</style>
