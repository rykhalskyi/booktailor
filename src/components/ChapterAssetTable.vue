<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import type { Asset } from '../types'
import { formatUpdatedAt } from '../utils/dates'
import { assetEditorUrl } from '../utils/files'
import { tagChipStyle } from '../utils/tags'

defineOptions({ name: 'ChapterAssetTable' })

defineProps<{
	assets: Asset[]
	indent: number
	busy: boolean
}>()

const emit = defineEmits<{
	(event: 'open', asset: Asset): void
	(event: 'tags', asset: Asset): void
	(event: 'delete', asset: Asset): void
	(event: 'include', asset: Asset, includeInComposition: boolean): void
}>()

function fileUrl(asset: Asset): string | null {
	return assetEditorUrl(asset)
}

function titleFor(asset: Asset): string {
	return asset.name === '' ? asset.fileName : asset.name
}

function showFileName(asset: Asset): boolean {
	return asset.name !== '' && asset.name !== asset.fileName
}

function onIncludeChange(asset: Asset, event: Event): void {
	emit('include', asset, (event.target as HTMLInputElement).checked)
}
</script>

<template>
	<div class="chapter-asset-table-panel" :style="{ paddingInlineStart: `${indent}px` }">
		<table class="chapter-asset-table">
			<caption class="visually-hidden">
				{{ t('booktailor', 'Assets in this chapter') }}
			</caption>
			<thead>
				<tr>
					<th scope="col" class="cell-name">
						{{ t('booktailor', 'Name') }}
					</th>
					<th scope="col" class="cell-tags">
						{{ t('booktailor', 'Tags') }}
					</th>
					<th scope="col" class="cell-date">
						{{ t('booktailor', 'Updated') }}
					</th>
					<th scope="col" class="cell-include">
						{{ t('booktailor', 'Compose') }}
					</th>
					<th scope="col" class="cell-actions">
						<span class="visually-hidden">{{ t('booktailor', 'Actions') }}</span>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="asset in assets" :key="asset.id" class="asset-row">
					<td class="cell-name">
						<button
							v-if="fileUrl(asset)"
							type="button"
							class="asset-name"
							:title="asset.fileName"
							@click="emit('open', asset)">
							<span class="asset-title">{{ titleFor(asset) }}</span>
							<span v-if="showFileName(asset)" class="asset-filename">{{ asset.fileName }}</span>
						</button>
						<span v-else class="asset-name asset-name--missing" :title="asset.fileName">
							<span class="asset-title">{{ titleFor(asset) }}</span>
							<span v-if="showFileName(asset)" class="asset-filename">{{ asset.fileName }}</span>
						</span>
					</td>
					<td class="cell-tags">
						<span v-if="asset.tags.length === 0" class="muted">—</span>
						<span
							v-for="tag in asset.tags"
							:key="tag.id"
							class="asset-tag"
							:style="tagChipStyle(tag.color)">
							{{ tag.name }}
						</span>
					</td>
					<td class="cell-date" :title="asset.updatedAt">
						{{ formatUpdatedAt(asset.updatedAt) }}
					</td>
					<td class="cell-include">
						<input
							type="checkbox"
							:checked="asset.includeInComposition"
							:disabled="busy"
							:aria-label="t('booktailor', 'Include {name} in composition', { name: titleFor(asset) })"
							@change="onIncludeChange(asset, $event)">
					</td>
					<td class="cell-actions">
						<NcActions
							:aria-label="t('booktailor', 'Actions for {name}', { name: asset.fileName })"
							:force-menu="true">
							<NcActionButton v-if="fileUrl(asset)" @click="emit('open', asset)">
								{{ t('booktailor', 'Open') }}
							</NcActionButton>
							<NcActionButton @click="emit('tags', asset)">
								{{ t('booktailor', 'Edit tags') }}
							</NcActionButton>
							<NcActionButton @click="emit('delete', asset)">
								{{ t('booktailor', 'Delete') }}
							</NcActionButton>
						</NcActions>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style scoped>
.chapter-asset-table-panel {
	padding-block: 4px 8px;
	padding-inline-end: 8px;
}

.chapter-asset-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.85rem;
	table-layout: fixed;
}

.chapter-asset-table th,
.chapter-asset-table td {
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border-dark);
	text-align: start;
	vertical-align: top;
}

.chapter-asset-table th {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.chapter-asset-table tbody tr:hover {
	background: var(--color-background-hover);
}

.cell-name {
	width: 42%;
}

.cell-tags {
	width: 22%;
}

.cell-date {
	width: 14%;
	white-space: nowrap;
}

.cell-include {
	width: 10%;
}

.muted {
	color: var(--color-text-maxcontrast);
}

.cell-actions {
	width: 6%;
}

.asset-name {
	display: flex;
	flex-direction: column;
	gap: 1px;
	min-width: 0;
	padding: 0;
	border: none;
	background: transparent;
	color: var(--color-main-text);
	font-weight: 600;
	text-align: start;
	cursor: pointer;
}

.asset-name:hover .asset-title {
	text-decoration: underline;
}

.asset-name:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.asset-title,
.asset-filename {
	overflow-wrap: anywhere;
}

.asset-filename {
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
	font-weight: 400;
}

.asset-name--missing {
	cursor: default;
	color: var(--color-text-maxcontrast);
}

.asset-tag {
	display: inline-block;
	margin: 0 4px 2px 0;
	padding: 0 8px;
	border-radius: 12px;
	font-size: 0.75rem;
	line-height: 1.6;
	white-space: nowrap;
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: -1px;
	padding: 0;
	border: 0;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
}
</style>
