<script setup lang="ts">
import { onMounted } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AssetList from './components/AssetList.vue'
import ChapterTree from './components/ChapterTree.vue'
import DialogHost from './components/DialogHost.vue'
import LibraryToolbar from './components/LibraryToolbar.vue'
import StructureManager from './components/StructureManager.vue'
import TagManager from './components/TagManager.vue'
import VersionHistoryDialog from './components/VersionHistoryDialog.vue'
import { useLibrary } from './stores/useLibrary'

const {
	books,
	dialog,
	error,
	initialize,
	loading,
	assetManagerOpen,
	closeAssetManager,
	openDialog,
	openTagsManager,
	selectBook,
	selectedBook,
	selectedBookId,
	structureManagerOpen,
	tagsManagerOpen,
} = useLibrary()

onMounted(() => {
	initialize()
})
</script>

<template>
	<NcContent app-name="booktailor">
		<NcAppNavigation aria-label="BookTailor books">
			<NcAppNavigationNew
				text="New book"
				:disabled="loading.mutation"
				@click="openDialog({ type: 'book-create' })" />
			<NcAppNavigationList>
				<NcAppNavigationItem
					v-for="book in books"
					:key="book.id"
					:name="book.name"
					:title="book.description ?? book.name"
					:active="book.id === selectedBookId"
					icon="icon-folder"
					@click="selectBook(book)" />
			</NcAppNavigationList>
			<NcAppNavigationCaption name="Book tools" />
			<NcAppNavigationItem
				name="Tags"
				icon="icon-tag"
				:active="tagsManagerOpen"
				:disabled="selectedBook === null"
				@click="openTagsManager" />
		</NcAppNavigation>

		<NcAppContent>
			<StructureManager v-if="structureManagerOpen" />
			<TagManager v-else-if="tagsManagerOpen" />
			<div v-else-if="assetManagerOpen" class="asset-manager">
				<AssetList show-back @back="closeAssetManager" />
			</div>
			<div v-else class="library-app">
				<NcNoteCard
					v-if="error && !dialog"
					type="error"
					:text="error"
					class="library-error" />

				<div v-if="loading.books" class="library-loading">
					<NcLoadingIcon name="Loading books" :size="32" />
				</div>

				<NcEmptyContent
					v-else-if="books.length === 0"
					name="No books yet"
					description="Create a book to start organizing chapters and Markdown assets.">
					<template #action>
						<NcButton
							variant="primary"
							@click="openDialog({ type: 'book-create' })">
							Create book
						</NcButton>
					</template>
				</NcEmptyContent>

				<template v-else-if="selectedBook">
					<LibraryToolbar />
					<section class="library-pane" aria-label="Chapters">
						<div class="pane-header">
							<h2 class="pane-title">
								Chapters
							</h2>
							<NcButton
								variant="primary"
								:disabled="loading.mutation"
								@click="openDialog({ type: 'chapter-create', parentId: null })">
								Add chapter
							</NcButton>
						</div>
						<ChapterTree />
					</section>
				</template>
			</div>
		</NcAppContent>

		<DialogHost />
		<VersionHistoryDialog />
	</NcContent>
</template>

<style scoped>
.library-app {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
	min-height: 0;
}

.library-error {
	flex-shrink: 0;
}

.library-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	flex: 1;
	min-height: 240px;
}

.library-pane {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	overflow: hidden;
}

.pane-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
}

.pane-title {
	margin: 0;
	font-size: 1rem;
}

.library-pane :deep(.chapter-tree) {
	flex: 1;
}

.asset-manager {
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	padding: 16px;
}

.asset-manager :deep(.asset-list) {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	overflow: hidden;
}

@media (max-width: 900px) {
	.library-app {
		padding: 12px;
	}

	.asset-manager {
		padding: 12px;
	}
}
</style>
