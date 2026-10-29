<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCP\App\IAppManager;
use OCP\ServerVersion;
use OCP\Util;

class SettingsService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly StorageService $storage,
		private readonly IAppManager $appManager,
		private readonly ServerVersion $serverVersion,
	) {
	}

	/**
	 * @return array{
	 *     storage: array{root: string},
	 *     limits: array{uploadBytes: int, imageUploadBytes: int, imageTypes: list<string>},
	 *     defaults: array{editor: string, compositionFormat: string, aiTransport: string},
	 *     app: array{version: string, nextcloudVersion: string}
	 * }
	 */
	public function get(): array {
		$uploadLimit = (int)Util::uploadLimit();
		$uploadBytes = $uploadLimit > 0 ? $uploadLimit : ImageService::MAX_IMAGE_BYTES;

		return [
			'storage' => [
				'root' => $this->storage->getBooksRootPath(),
			],
			'limits' => [
				'uploadBytes' => $uploadBytes,
				'imageUploadBytes' => min(ImageService::MAX_IMAGE_BYTES, $uploadBytes),
				'imageTypes' => array_keys(ImageService::MIME_EXTENSIONS),
			],
			'defaults' => [
				'editor' => 'text',
				'compositionFormat' => 'markdown',
				'aiTransport' => 'json',
			],
			'app' => [
				'version' => $this->appManager->getAppVersion('booktailor'),
				'nextcloudVersion' => $this->serverVersion->getVersionString(),
			],
		];
	}
}
