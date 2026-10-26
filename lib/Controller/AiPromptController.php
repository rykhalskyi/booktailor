<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\AiPrompt;
use OCA\BookTailor\Service\Ai\AiPromptService;
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
class AiPromptController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly AiPromptService $promptService,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/prompts')]
	public function index(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			return new DataResponse(array_map(
				fn (AiPrompt $prompt): array => $this->promptService->serialize($prompt),
				$this->promptService->listForBook($this->currentUserId(), $bookId),
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books/{bookId}/prompts')]
	public function create(int $bookId, string $name, string $text): DataResponse {
		return $this->handle(function () use ($bookId, $name, $text): DataResponse {
			$prompt = $this->promptService->create($this->currentUserId(), $bookId, $name, $text);

			return new DataResponse($this->promptService->serialize($prompt), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/prompts/{promptId}')]
	public function update(int $promptId, ?string $name = null, ?string $text = null): DataResponse {
		return $this->handle(function () use ($promptId, $name, $text): DataResponse {
			$prompt = $this->promptService->update($this->currentUserId(), $promptId, $name, $text);

			return new DataResponse($this->promptService->serialize($prompt));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/prompts/{promptId}')]
	public function destroy(int $promptId): DataResponse {
		return $this->handle(function () use ($promptId): DataResponse {
			$this->promptService->delete($this->currentUserId(), $promptId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}
}
