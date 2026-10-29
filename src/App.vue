<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
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
import AiChatPanel from './components/AiChatPanel.vue'
import AssetList from './components/AssetList.vue'
import ChapterTree from './components/ChapterTree.vue'
import ComposeScreen from './components/ComposeScreen.vue'
import CompositionsList from './components/CompositionsList.vue'
import DialogHost from './components/DialogHost.vue'
import LibraryToolbar from './components/LibraryToolbar.vue'
import SettingsScreen from './components/SettingsScreen.vue'
import StructureManager from './components/StructureManager.vue'
import TagManager from './components/TagManager.vue'
import VersionHistoryDialog from './components/VersionHistoryDialog.vue'
import { useLibrary } from './stores/useLibrary'

const {
	books,
	composeOpen,
	compositionsOpen,
	dialog,
	error,
	initialize,
	loading,
	assetManagerOpen,
	closeAssetManager,
	openCompose,
	openCompositions,
	openDialog,
	openTagsManager,
	selectBook,
	selectedBook,
	selectedBookId,
	settingsOpen,
	structureManagerOpen,
	tagsManagerOpen,
	openSettings,
} = useLibrary()

const chatOpen = ref(false)

onMounted(() => {
	initialize()
})
</script>

<template>
	<NcContent app-name="booktailor">
		<NcAppNavigation :aria-label="t('booktailor', 'BookTailor books')">
			<NcAppNavigationNew
				:text="t('booktailor', 'New book')"
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
			<NcAppNavigationCaption :name="t('booktailor', 'Book tools')" />
			<NcAppNavigationItem
				:name="t('booktailor', 'Tags')"
				icon="icon-tag"
				:active="tagsManagerOpen"
				:disabled="selectedBook === null"
				@click="openTagsManager" />
			<NcAppNavigationItem
				:name="t('booktailor', 'Compose')"
				icon="icon-add"
				:active="composeOpen"
				:disabled="selectedBook === null"
				@click="openCompose" />
			<NcAppNavigationItem
				:name="t('booktailor', 'Compositions')"
				icon="icon-file"
				:active="compositionsOpen"
				:disabled="selectedBook === null"
				@click="openCompositions" />
			<NcAppNavigationCaption :name="t('booktailor', 'Application')" />
			<NcAppNavigationItem
				:name="t('booktailor', 'Settings')"
				icon="icon-settings"
				:active="settingsOpen"
				@click="openSettings" />
		</NcAppNavigation>

		<NcAppContent>
			<div class="app-content-safe-area">
				<SettingsScreen v-if="settingsOpen" />
				<StructureManager v-else-if="structureManagerOpen" />
				<TagManager v-else-if="tagsManagerOpen" />
				<ComposeScreen v-else-if="composeOpen" />
				<CompositionsList v-else-if="compositionsOpen" />
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
						<NcLoadingIcon :name="t('booktailor', 'Loading books')" :size="32" />
					</div>

					<NcEmptyContent
						v-else-if="books.length === 0"
						:name="t('booktailor', 'No books yet')"
						:description="t('booktailor', 'Create a book to start organizing chapters and Markdown assets.')">
						<template #action>
							<NcButton
								variant="primary"
								@click="openDialog({ type: 'book-create' })">
								{{ t('booktailor', 'Create book') }}
							</NcButton>
						</template>
					</NcEmptyContent>

					<template v-else-if="selectedBook">
						<LibraryToolbar
							:chat-open="chatOpen"
							@update-chat-open="chatOpen = $event" />
						<div class="library-workspace">
							<section class="library-pane" :aria-label="t('booktailor', 'Chapters')">
								<div class="pane-header">
									<h2 class="pane-title">
										{{ t('booktailor', 'Chapters') }}
									</h2>
									<div class="pane-actions">
										<NcButton
											variant="primary"
											:disabled="loading.mutation"
											@click="openDialog({ type: 'chapter-create', parentId: null })">
											{{ t('booktailor', 'Add chapter') }}
										</NcButton>
									</div>
								</div>
								<ChapterTree />
							</section>
							<AiChatPanel v-if="chatOpen" @close="chatOpen = false" />
						</div>
					</template>
				</div>
			</div>
		</NcAppContent>

		<DialogHost />
		<VersionHistoryDialog />
	</NcContent>
</template>

<style scoped>
.app-content-safe-area {
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	min-height: 0;
	padding-top: 32px;
}

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

.library-workspace {
	display: flex;
	flex: 1;
	min-height: 0;
	gap: 16px;
}

.pane-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
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

@media (max-width: 1100px) {
	.library-workspace {
		flex-direction: column;
	}

	.library-workspace :deep(aside) {
		width: 100%;
		min-height: 360px;
	}
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
