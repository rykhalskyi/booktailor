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
 * @method string getName()
 * @method void setName(string $name)
 * @method bool getIsDefault()
 * @method void setIsDefault(bool $isDefault)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class Structure extends Entity {
	protected ?int $bookId = null;
	protected ?string $name = null;
	protected ?bool $isDefault = false;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
		$this->addType('isDefault', Types::BOOLEAN);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
