<?php

declare(strict_types=1);

namespace OCA\BookTailor\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getBookId()
 * @method void setBookId(int $bookId)
 * @method string getSharedWith()
 * @method void setSharedWith(string $sharedWith)
 * @method string getPermission()
 * @method void setPermission(string $permission)
 * @method ?string getNextcloudShareId()
 * @method void setNextcloudShareId(?string $nextcloudShareId)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class BookShare extends Entity {
	public const PERMISSION_READ = 'read';
	public const PERMISSION_WRITE = 'write';

	protected ?int $bookId = null;
	protected ?string $sharedWith = null;
	protected ?string $permission = null;
	protected ?string $nextcloudShareId = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
