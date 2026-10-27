<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\LlmProfile;
use OCA\BookTailor\Exception\ApiException;
use OCA\BookTailor\Service\Ai\AiProviderException;
use OCA\BookTailor\Service\Ai\AiProviderRegistry;
use OCA\BookTailor\Service\Ai\AiSettingsService;
use OCA\BookTailor\Service\LlmProfileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class AiSettingsController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly LlmProfileService $profileService,
		private readonly AiSettingsService $settingsService,
		private readonly AiProviderRegistry $providerRegistry,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/ai/settings')]
	public function index(): DataResponse {
		return $this->handle(function (): DataResponse {
			$userId = $this->currentUserId();

			return new DataResponse([
				'profiles' => array_map(
					fn (LlmProfile $profile): array => $this->profileService->serialize($profile),
					$this->profileService->list($userId),
				),
				'contentLogging' => $this->settingsService->contentLogging($userId),
				'langfuse' => $this->settingsService->langfuseStatus(),
				'providers' => $this->providerRegistry->list(),
			]);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/ai/settings')]
	public function updateSettings(?bool $contentLogging = null): DataResponse {
		return $this->handle(function () use ($contentLogging): DataResponse {
			$userId = $this->currentUserId();
			if ($contentLogging !== null) {
				$this->settingsService->setContentLogging($userId, $contentLogging);
			}

			return new DataResponse([
				'contentLogging' => $this->settingsService->contentLogging($userId),
				'langfuse' => $this->settingsService->langfuseStatus(),
			]);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai/profiles')]
	public function create(
		string $name,
		string $provider,
		string $apiKey,
		?string $model = null,
		int $connectTimeoutSeconds = 30,
		int $readTimeoutSeconds = 60,
		int $maxTokens = 2048,
		bool $isActive = false,
	): DataResponse {
		return $this->handle(function () use ($name, $provider, $apiKey, $model, $connectTimeoutSeconds, $readTimeoutSeconds, $maxTokens, $isActive): DataResponse {
			$profile = $this->profileService->create(
				$this->currentUserId(),
				$name,
				$provider,
				$apiKey,
				$model,
				$connectTimeoutSeconds,
				$readTimeoutSeconds,
				$maxTokens,
				$isActive,
			);

			return new DataResponse($this->profileService->serialize($profile), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/ai/profiles/{profileId}')]
	public function update(
		int $profileId,
		?string $name = null,
		?string $provider = null,
		?string $apiKey = null,
		?string $model = null,
		?int $connectTimeoutSeconds = null,
		?int $readTimeoutSeconds = null,
		?int $maxTokens = null,
		?bool $isActive = null,
	): DataResponse {
		return $this->handle(function () use ($profileId, $name, $provider, $apiKey, $model, $connectTimeoutSeconds, $readTimeoutSeconds, $maxTokens, $isActive): DataResponse {
			$profile = $this->profileService->update(
				$this->currentUserId(),
				$profileId,
				$name,
				$provider,
				$apiKey,
				$model,
				$connectTimeoutSeconds,
				$readTimeoutSeconds,
				$maxTokens,
				$isActive,
			);

			return new DataResponse($this->profileService->serialize($profile));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/ai/profiles/{profileId}')]
	public function destroy(int $profileId): DataResponse {
		return $this->handle(function () use ($profileId): DataResponse {
			$this->profileService->delete($this->currentUserId(), $profileId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai/profiles/{profileId}/activate')]
	public function activate(int $profileId, bool $active = true): DataResponse {
		return $this->handle(function () use ($profileId, $active): DataResponse {
			$userId = $this->currentUserId();
			$this->profileService->activate($userId, $profileId, $active);

			return new DataResponse(array_map(
				fn (LlmProfile $profile): array => $this->profileService->serialize($profile),
				$this->profileService->list($userId),
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai/profiles/{profileId}/test')]
	public function test(int $profileId): DataResponse {
		return $this->handle(function () use ($profileId): DataResponse {
			$userId = $this->currentUserId();
			$profile = $this->profileService->getOwned($userId, $profileId);
			$provider = $this->providerRegistry->provider($profile->getProvider());
			$model = $profile->getModel();
			if ($model === null || trim($model) === '') {
				$model = \OCA\BookTailor\Service\Ai\AiProviderCatalog::defaultModel($profile->getProvider());
			}

			try {
				$completion = $provider->complete(
					$this->profileService->decrypt($profile),
					$model,
					[['role' => 'user', 'content' => 'Reply with the single word OK.']],
					[
						'provider' => $profile->getProvider(),
						'maxTokens' => 16,
						'connectTimeout' => $profile->getConnectTimeout(),
						'readTimeout' => $profile->getReadTimeout(),
						'json' => false,
					],
				);
			} catch (AiProviderException $e) {
				throw new ApiException($e->getMessage(), 502);
			}

			return new DataResponse([
				'ok' => true,
				'model' => $completion->model,
				'message' => trim($completion->content),
			]);
		});
	}
}
