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
 * @method ?int getParentId()
 * @method void setParentId(?int $parentId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?int getFileId()
 * @method void setFileId(?int $fileId)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class Chapter extends Entity {
	protected ?int $bookId = null;
	protected ?int $parentId = null;
	protected ?string $name = null;
	protected ?int $fileId = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
		$this->addType('parentId', Types::BIGINT);
		$this->addType('fileId', Types::BIGINT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
