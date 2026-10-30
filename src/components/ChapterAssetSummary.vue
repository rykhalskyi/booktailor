<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheckCircle, mdiChevronDown, mdiChevronRight, mdiFileDocument, mdiTagMultiple } from '../icons'
import type { Tag } from '../types'
import { normalizeTagColor } from '../utils/tags'

defineOptions({ name: 'ChapterAssetSummary' })

const props = defineProps<{
	assetCount: number
	tags: Tag[]
	pickedCount: number
	showPicks: boolean
	expanded: boolean
}>()

const emit = defineEmits<{
	(event: 'toggle'): void
}>()

const visibleTags = computed<Tag[]>(() => props.tags.slice(0, 3))
const tagNames = computed<string>(() => props.tags.map((tag) => tag.name).join(', '))
const ariaLabel = computed<string>(() => t(
	'booktailor',
	'{count} assets. Show asset table.',
	{ count: props.assetCount },
))
</script>

<template>
	<button
		type="button"
		class="chapter-asset-summary"
		:aria-label="ariaLabel"
		:aria-expanded="expanded"
		@click.stop="emit('toggle')">
		<span class="summary-metric">
			<NcIconSvgWrapper :path="mdiFileDocument" :size="16" />
			{{ assetCount }}
		</span>
		<span v-if="tags.length > 0" class="summary-metric" :title="tagNames">
			<NcIconSvgWrapper :path="mdiTagMultiple" :size="16" />
			{{ tags.length }}
			<span class="summary-tag-dots">
				<span
					v-for="tag in visibleTags"
					:key="tag.id"
					class="summary-tag-dot"
					:style="{ backgroundColor: normalizeTagColor(tag.color) }" />
			</span>
		</span>
		<span v-if="showPicks" class="summary-metric summary-metric--picked">
			<NcIconSvgWrapper :path="mdiCheckCircle" :size="16" />
			{{ pickedCount }}
		</span>
		<NcIconSvgWrapper
			class="summary-chevron"
			:path="expanded ? mdiChevronDown : mdiChevronRight"
			:size="16" />
	</button>
</template>

<style scoped>
.chapter-asset-summary {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;
	padding: 2px 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: 12px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 0.75rem;
	line-height: 1.6;
	cursor: pointer;
}

.chapter-asset-summary:hover {
	background: var(--color-primary-element-light);
}

.chapter-asset-summary:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.summary-metric {
	display: inline-flex;
	align-items: center;
	gap: 3px;
}

.summary-metric--picked {
	color: var(--color-success);
}

.summary-tag-dots {
	display: inline-flex;
	align-items: center;
	gap: 2px;
}

.summary-tag-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	box-shadow: 0 0 0 1px var(--color-main-background);
}

.summary-chevron {
	opacity: 0.7;
}
</style>
