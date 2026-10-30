<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Service\Ai\AiProposalService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class AiActionController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly AiProposalService $proposalService,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai-actions/{actionId}/confirm')]
	public function confirm(int $actionId): DataResponse {
		return $this->handle(function () use ($actionId): DataResponse {
			return new DataResponse($this->proposalService->confirm($this->currentUserId(), $actionId));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/ai-actions/{actionId}/reject')]
	public function reject(int $actionId): DataResponse {
		return $this->handle(function () use ($actionId): DataResponse {
			return new DataResponse($this->proposalService->reject($this->currentUserId(), $actionId));
		});
	}
}
