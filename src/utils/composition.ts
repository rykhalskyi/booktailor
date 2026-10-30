import type { Asset, CompositionRule, Tag } from '../types'

export interface AssetPick {
	assetId: number
	rulePriority: number
	tag: Tag | null
}

function ruleMatches(asset: Asset, rule: CompositionRule): boolean {
	return asset.tags.some((tag) => tag.id === rule.tagId)
}

function tagForRule(asset: Asset, rule: CompositionRule): Tag | null {
	return asset.tags.find((tag) => tag.id === rule.tagId) ?? null
}

export function pickAssetsForChapter(
	assets: Asset[],
	rules: CompositionRule[],
): Map<number, AssetPick> {
	const picks = new Map<number, AssetPick>()
	if (rules.length === 0) {
		return picks
	}

	const ordered = [...rules].sort((left, right) => left.priority - right.priority)
	const normal = ordered.filter((rule) => !rule.fallback)
	const fallback = ordered.filter((rule) => rule.fallback)

	const applyRule = (rule: CompositionRule): boolean => {
		const matches = assets.filter((asset) => ruleMatches(asset, rule))
		if (matches.length === 0) {
			return false
		}

		for (const asset of matches) {
			picks.set(asset.id, {
				assetId: asset.id,
				rulePriority: rule.priority,
				tag: tagForRule(asset, rule),
			})
		}
		return true
	}

	for (const rule of normal) {
		if (applyRule(rule)) {
			return picks
		}
	}

	for (const rule of fallback) {
		if (applyRule(rule)) {
			return picks
		}
	}

	return picks
}
