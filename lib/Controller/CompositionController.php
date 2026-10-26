<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Composition;
use OCA\BookTailor\Service\CompositionService;
use OCA\BookTailor\Service\SerializerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class CompositionController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly CompositionService $compositionService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/compositions')]
	public function index(int $bookId, ?string $status = null, ?int $limit = null, int $offset = 0): DataResponse {
		return $this->handle(function () use ($bookId, $status, $limit, $offset): DataResponse {
			$compositions = $this->compositionService->listForBook(
				$this->currentUserId(),
				$bookId,
				$status,
				$limit,
				$offset,
			);

			return new DataResponse($this->serializeMany($compositions));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books/{bookId}/compositions')]
	public function create(int $bookId, int $structureId, array $rules = [], ?string $name = null): DataResponse {
		return $this->handle(function () use ($bookId, $structureId, $rules, $name): DataResponse {
			$created = $this->compositionService->create(
				$this->currentUserId(),
				$bookId,
				$structureId,
				$rules,
				$name,
			);

			return new DataResponse(
				$this->serializer->composition($created['composition'], $created['rules']),
				Http::STATUS_ACCEPTED,
			);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/compositions/{compositionId}')]
	public function show(int $compositionId): DataResponse {
		return $this->handle(function () use ($compositionId): DataResponse {
			$composition = $this->compositionService->getOwned($this->currentUserId(), $compositionId);

			return new DataResponse($this->serializeOne($composition));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/compositions/{compositionId}')]
	public function destroy(int $compositionId): DataResponse {
		return $this->handle(function () use ($compositionId): DataResponse {
			$this->compositionService->delete($this->currentUserId(), $compositionId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/compositions/{compositionId}/retry')]
	public function retry(int $compositionId): DataResponse {
		return $this->handle(function () use ($compositionId): DataResponse {
			$composition = $this->compositionService->retry($this->currentUserId(), $compositionId);

			return new DataResponse($this->serializeOne($composition), Http::STATUS_ACCEPTED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/compositions/{compositionId}/download')]
	public function download(int $compositionId): Response {
		return $this->handleResponse(function () use ($compositionId): Response {
			$file = $this->compositionService->download($this->currentUserId(), $compositionId);

			return new FileDisplayResponse($file, Http::STATUS_OK, [
				'Content-Type' => 'text/markdown; charset=utf-8',
			]);
		});
	}

	/**
	 * @param list<Composition> $compositions
	 * @return list<array>
	 */
	private function serializeMany(array $compositions): array {
		$ids = array_map(static fn (Composition $composition): int => $composition->getId(), $compositions);
		$rules = $this->compositionService->rulesForMany($ids);

		return array_map(
			fn (Composition $composition): array => $this->serializer->composition(
				$composition,
				$rules[$composition->getId()] ?? [],
				$this->compositionService->isStale($composition),
			),
			$compositions,
		);
	}

	private function serializeOne(Composition $composition): array {
		return $this->serializer->composition(
			$composition,
			$this->compositionService->rulesFor($composition->getId()),
			$this->compositionService->isStale($composition),
		);
	}
}
