<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\ApiException;
use OCA\BookTailor\Exception\UnauthorizedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCSController;
use OCP\Files\NotFoundException as FileNotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

abstract class AbstractApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	protected function currentUser(): ?IUser {
		return $this->userSession->getUser();
	}

	protected function currentUserId(): string {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			throw new UnauthorizedException();
		}

		return $userId;
	}

	/**
	 * @param callable(): DataResponse $fn
	 */
	protected function handle(callable $fn): DataResponse {
		try {
			return $fn();
		} catch (ApiException $e) {
			/** @psalm-suppress ArgumentTypeCoercion */
			return new DataResponse(['message' => $e->getMessage()], $e->getStatusCode());
		} catch (FileNotFoundException $e) {
			return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (NotPermittedException $e) {
			return new DataResponse(['message' => 'Not permitted'], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor API request failed', ['exception' => $e]);

			return new DataResponse(['message' => 'Internal server error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param callable(): Response $fn
	 */
	protected function handleResponse(callable $fn): Response {
		try {
			return $fn();
		} catch (ApiException $e) {
			/** @psalm-suppress ArgumentTypeCoercion */
			return new DataResponse(['message' => $e->getMessage()], $e->getStatusCode());
		} catch (FileNotFoundException $e) {
			return new DataResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (NotPermittedException $e) {
			return new DataResponse(['message' => 'Not permitted'], Http::STATUS_FORBIDDEN);
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor API request failed', ['exception' => $e]);

			return new DataResponse(['message' => 'Internal server error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
