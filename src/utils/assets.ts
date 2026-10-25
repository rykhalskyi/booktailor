import type { Asset } from '../types'

export function filterAssetsByTag(assets: Asset[], tagId: number | null): Asset[] {
	if (tagId === null) {
		return assets
	}

	return assets.filter((asset) => asset.tags.some((tag) => tag.id === tagId))
}
