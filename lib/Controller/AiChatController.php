<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Service\Ai\AiChatService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class AiChatController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly AiChatService $chatService,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	/**
	 * @param list<int> $contextAssetIds
	 * @param list<array{role: string, content: string}> $history
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai/chat')]
	public function chat(
		int $bookId,
		string $message,
		?int $structureId = null,
		?int $chapterId = null,
		array $contextAssetIds = [],
		?string $promptKey = null,
		?int $promptId = null,
		array $history = [],
	): DataResponse {
		return $this->handle(function () use ($bookId, $message, $structureId, $chapterId, $contextAssetIds, $promptKey, $promptId, $history): DataResponse {
			return new DataResponse($this->chatService->chat(
				$this->currentUserId(),
				$bookId,
				$message,
				$structureId,
				$chapterId,
				$contextAssetIds,
				$promptKey,
				$promptId,
				$history,
			));
		});
	}
}
