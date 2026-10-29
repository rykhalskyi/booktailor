<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

/**
 * Parses Markdown image URLs that point at a chapter's `images/` folder.
 *
 * Shared by reference tracking (ImageService) and composition rewriting
 * (MarkdownComposer) so both agree on what counts as a chapter image.
 */
class ImageReferenceParser {
	/**
	 * @return list<string>
	 */
	public function extractNames(string $content): array {
		$names = [];

		if (preg_match_all('/!\[[^\]]*\]\(\s*(<[^>]+>|[^)\s]+)/', $content, $matches) > 0) {
			foreach ($matches[1] as $url) {
				$parsed = $this->parseUrl($url);
				if ($parsed !== null) {
					$names[] = $parsed['name'];
				}
			}
		}

		if (preg_match_all('/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1/i', $content, $matches) > 0) {
			foreach ($matches[2] as $url) {
				$parsed = $this->parseUrl($url);
				if ($parsed !== null) {
					$names[] = $parsed['name'];
				}
			}
		}

		return array_values(array_unique($names));
	}

	/**
	 * @return array{name: string, wrapped: bool, suffix: string}|null
	 */
	public function parseUrl(string $url): ?array {
		$trimmed = trim($url);
		$wrapped = false;
		if (str_starts_with($trimmed, '<') && str_ends_with($trimmed, '>')) {
			$wrapped = true;
			$trimmed = substr($trimmed, 1, -1);
		}

		if ($trimmed === ''
			|| preg_match('#^[a-z][a-z0-9+.-]*:#i', $trimmed) === 1
			|| str_starts_with($trimmed, '//')
			|| str_starts_with($trimmed, '#')) {
			return null;
		}

		$suffix = '';
		if (preg_match('/^([^?#]*)([?#].*)$/', $trimmed, $parts) === 1) {
			$trimmed = $parts[1];
			$suffix = $parts[2];
		}

		if (preg_match('#^\.?/?images/([^/]+)$#i', $trimmed, $parts) !== 1) {
			return null;
		}

		$name = rawurldecode($parts[1]);
		if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\')) {
			return null;
		}

		return ['name' => $name, 'wrapped' => $wrapped, 'suffix' => $suffix];
	}
}
