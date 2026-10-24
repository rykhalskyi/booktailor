<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Structure;
use OCA\BookTailor\Service\SerializerService;
use OCA\BookTailor\Service\StructureService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class StructureController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly StructureService $structureService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/structures')]
	public function index(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			$structures = $this->structureService->listForBook($this->currentUserId(), $bookId);

			return new DataResponse(array_map(
				fn (Structure $structure): array => $this->serializer->structure($structure),
				$structures,
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/structures/{structureId}')]
	public function show(int $structureId): DataResponse {
		return $this->handle(function () use ($structureId): DataResponse {
			$structure = $this->structureService->getOwned($this->currentUserId(), $structureId);

			return new DataResponse($this->serializer->structure($structure));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/structures/{structureId}/entries')]
	public function entries(int $structureId): DataResponse {
		return $this->handle(function () use ($structureId): DataResponse {
			$entries = $this->structureService->getEntries($this->currentUserId(), $structureId);

			return new DataResponse(array_map(
				fn (array $entry): array => $this->serializer->structureEntry(
					$entry['chapterId'],
					$entry['included'],
					$entry['position'],
					$entry['effectiveParentId'],
				),
				$entries,
			));
		});
	}
}
