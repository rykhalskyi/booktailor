<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\LlmProfileMapper;
use OCA\BookTailor\Entity\LlmProfile;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\Ai\AiProviderCatalog;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class LlmProfileService {
	private const MASK = '••••••••';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly LlmProfileMapper $profileMapper,
		private readonly ICrypto $crypto,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return list<LlmProfile>
	 */
	public function list(string $userId): array {
		return $this->profileMapper->findAllByUser($userId);
	}

	public function create(
		string $userId,
		string $name,
		string $provider,
		string $apiKey,
		?string $model = null,
		int $connectTimeout = 30,
		int $readTimeout = 60,
		int $maxTokens = 2048,
		bool $isActive = false,
	): LlmProfile {
		$name = $this->validateName($name);
		$provider = $this->validateProvider($provider);
		$apiKey = trim($apiKey);
		if ($apiKey === '') {
			throw new ValidationException('API key is required');
		}
		$model = $this->normalizeModel($model, $provider);

		$now = new DateTime();
		$profile = new LlmProfile();
		$profile->setUserId($userId);
		$profile->setName($name);
		$profile->setProvider($provider);
		$profile->setApiKey($this->encrypt($apiKey));
		$profile->setModel($model);
		$profile->setConnectTimeout($this->timeout($connectTimeout, 'Connect timeout'));
		$profile->setReadTimeout($this->timeout($readTimeout, 'Read timeout'));
		$profile->setMaxTokens($this->maxTokens($maxTokens));
		$profile->setIsActive(false);
		$profile->setCreatedAt($now);

		$created = $this->profileMapper->insert($profile);
		$isFirst = count($this->profileMapper->findAllByUser($userId)) === 1;
		if ($isActive || $isFirst) {
			$this->profileMapper->setActive($created->getId(), $userId, true);
			$created->setIsActive(true);
		}

		return $created;
	}

	public function update(
		string $userId,
		int $profileId,
		?string $name = null,
		?string $provider = null,
		?string $apiKey = null,
		?string $model = null,
		?int $connectTimeout = null,
		?int $readTimeout = null,
		?int $maxTokens = null,
		?bool $isActive = null,
	): LlmProfile {
		$profile = $this->getOwned($userId, $profileId);

		if ($name !== null) {
			$profile->setName($this->validateName($name));
		}

		if ($provider !== null) {
			$provider = $this->validateProvider($provider);
			$profile->setProvider($provider);
		}

		$trimmedKey = $apiKey !== null ? trim($apiKey) : null;
		if ($trimmedKey !== null && $trimmedKey !== '' && !str_contains($trimmedKey, '•')) {
			$profile->setApiKey($this->encrypt($trimmedKey));
		}

		$effectiveProvider = $profile->getProvider();
		if ($model !== null) {
			$profile->setModel($this->normalizeModel($model, $effectiveProvider));
		} elseif ($profile->getModel() === null || trim((string)$profile->getModel()) === '') {
			$profile->setModel(AiProviderCatalog::defaultModel($effectiveProvider));
		}

		if ($connectTimeout !== null) {
			$profile->setConnectTimeout($this->timeout($connectTimeout, 'Connect timeout'));
		}
		if ($readTimeout !== null) {
			$profile->setReadTimeout($this->timeout($readTimeout, 'Read timeout'));
		}
		if ($maxTokens !== null) {
			$profile->setMaxTokens($this->maxTokens($maxTokens));
		}

		$profile->setUpdatedAt(new DateTime());
		$updated = $this->profileMapper->update($profile);

		if ($isActive !== null) {
			$this->profileMapper->setActive($updated->getId(), $userId, $isActive);
			$updated->setIsActive($isActive);
		}

		return $updated;
	}

	public function activate(string $userId, int $profileId, bool $active = true): void {
		$this->getOwned($userId, $profileId);
		$this->profileMapper->setActive($profileId, $userId, $active);
	}

	public function delete(string $userId, int $profileId): void {
		$profile = $this->getOwned($userId, $profileId);
		$this->profileMapper->delete($profile);
	}

	public function getOwned(string $userId, int $profileId): LlmProfile {
		$profile = $this->profileMapper->findByIdAndUser($profileId, $userId);
		if ($profile === null) {
			throw new NotFoundException('AI profile not found');
		}

		return $profile;
	}

	public function activeFor(string $userId): ?LlmProfile {
		return $this->profileMapper->findActiveByUser($userId);
	}

	/**
	 * Decrypt a key only at the point of use. Never serialize the result.
	 *
	 * @return array{profile: LlmProfile, apiKey: string}
	 */
	public function activeCredentials(string $userId): array {
		$profile = $this->activeFor($userId);
		if ($profile === null) {
			throw new ValidationException('Configure an AI profile in Settings before using the assistant');
		}

		return [
			'profile' => $profile,
			'apiKey' => $this->decrypt($profile),
		];
	}

	public function decrypt(LlmProfile $profile): string {
		$encrypted = $profile->getApiKey();
		if ($encrypted === '') {
			throw new ValidationException('The selected AI profile has no API key');
		}

		try {
			return $this->crypto->decrypt($encrypted);
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor could not decrypt an AI profile key', [
				'profileId' => $profile->getId(),
				'exceptionClass' => $e::class,
			]);
			throw new ValidationException('The selected AI profile key could not be read. Enter it again in Settings.');
		}
	}

	/**
	 * @return array{id: int, name: string, provider: string, apiKeyMasked: string, model: ?string, connectTimeoutSeconds: int, readTimeoutSeconds: int, maxTokens: int, isActive: bool, createdAt: string, updatedAt: ?string}
	 */
	public function serialize(LlmProfile $profile): array {
		return [
			'id' => $profile->getId(),
			'name' => $profile->getName(),
			'provider' => $profile->getProvider(),
			'apiKeyMasked' => $this->mask($profile),
			'model' => $profile->getModel(),
			'connectTimeoutSeconds' => $profile->getConnectTimeout(),
			'readTimeoutSeconds' => $profile->getReadTimeout(),
			'maxTokens' => $profile->getMaxTokens(),
			'isActive' => $profile->getIsActive(),
			'createdAt' => $profile->getCreatedAt()->format(\DateTimeInterface::ATOM),
			'updatedAt' => $profile->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
		];
	}

	private function mask(LlmProfile $profile): string {
		try {
			$key = $this->decrypt($profile);
		} catch (ValidationException) {
			return self::MASK;
		}

		if (strlen($key) <= 4) {
			return self::MASK;
		}

		return self::MASK . substr($key, -4);
	}

	private function encrypt(string $apiKey): string {
		try {
			return $this->crypto->encrypt($apiKey);
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor could not encrypt an AI profile key', ['exceptionClass' => $e::class]);
			throw new ValidationException('The API key could not be stored securely');
		}
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Profile name is required');
		}
		if (mb_strlen($name) > 128) {
			throw new ValidationException('Profile name is too long');
		}

		return $name;
	}

	private function validateProvider(string $provider): string {
		$provider = trim($provider);
		if (!AiProviderCatalog::supports($provider)) {
			throw new ValidationException('Unsupported AI provider');
		}

		return $provider;
	}

	private function normalizeModel(?string $model, string $provider): string {
		$model = $model === null ? '' : trim($model);
		if ($model === '') {
			$model = AiProviderCatalog::defaultModel($provider);
		}
		if ($model === '') {
			throw new ValidationException('A model is required');
		}
		if (mb_strlen($model) > 128) {
			throw new ValidationException('Model name is too long');
		}

		return $model;
	}

	private function timeout(int $value, string $label): int {
		if ($value < 1 || $value > 300) {
			throw new ValidationException($label . ' must be between 1 and 300 seconds');
		}

		return $value;
	}

	private function maxTokens(int $value): int {
		if ($value < 1 || $value > 1000000) {
			throw new ValidationException('Max tokens must be between 1 and 1000000');
		}

		return $value;
	}
}
