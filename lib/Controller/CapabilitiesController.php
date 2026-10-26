<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class CapabilitiesController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly IAppManager $appManager,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/capabilities')]
	public function index(): DataResponse {
		return $this->handle(function (): DataResponse {
			$user = $this->currentUser();

			return new DataResponse([
				'editor' => $this->appManager->isEnabledForUser('text', $user) ? 'text' : null,
				'versions' => $this->appManager->isEnabledForUser('files_versions', $user),
			]);
		});
	}
}
