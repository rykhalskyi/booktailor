<?php

declare(strict_types=1);

namespace OCA\BookTailor\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method ?int getBookId()
 * @method void setBookId(?int $bookId)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string getPayload()
 * @method void setPayload(string $payload)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getDecidedAt()
 * @method void setDecidedAt(?DateTime $decidedAt)
 * @method ?string getResult()
 * @method void setResult(?string $result)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class AiAction extends Entity {
	public const STATUS_PROPOSED = 'proposed';
	public const STATUS_APPLIED = 'applied';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_FAILED = 'failed';

	protected ?string $userId = null;
	protected ?int $bookId = null;
	protected ?string $action = null;
	protected ?string $payload = null;
	protected ?string $status = self::STATUS_PROPOSED;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $decidedAt = null;
	protected ?string $result = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('decidedAt', Types::DATETIME);
	}
}
