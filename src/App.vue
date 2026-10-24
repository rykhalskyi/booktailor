<script setup lang="ts">
import { onMounted } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
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
import { useLibrary } from './stores/useLibrary'

const {
	books,
	dialog,
	error,
	initialize,
	loading,
	openDialog,
	selectBook,
	selectedBook,
	selectedBookId,
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
		</NcAppNavigation>

		<NcAppContent>
			<div class="library-app">
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
					<div class="library-layout">
						<section class="library-pane library-pane--tree" aria-label="Chapters">
							<h2 class="pane-title">
								Chapters
							</h2>
							<ChapterTree />
						</section>
						<section class="library-pane library-pane--assets" aria-label="Assets">
							<AssetList />
						</section>
					</div>
				</template>
			</div>
		</NcAppContent>

		<DialogHost />
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

.library-layout {
	display: grid;
	grid-template-columns: minmax(240px, 340px) minmax(0, 1fr);
	gap: 16px;
	flex: 1;
	min-height: 0;
}

.library-pane {
	display: flex;
	flex-direction: column;
	min-height: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	overflow: hidden;
}

.pane-title {
	margin: 0;
	padding: 14px 16px;
	border-bottom: 1px solid var(--color-border);
	font-size: 1rem;
}

.library-pane--tree :deep(.chapter-tree) {
	flex: 1;
}

.library-pane--assets :deep(.asset-list) {
	flex: 1;
}

@media (max-width: 900px) {
	.library-app {
		padding: 12px;
	}

	.library-layout {
		grid-template-columns: minmax(0, 1fr);
		grid-template-rows: minmax(180px, 40vh) minmax(240px, 1fr);
	}
}
</style>
