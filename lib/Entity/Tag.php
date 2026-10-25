<?php

declare(strict_types=1);

namespace OCA\BookTailor\Entity;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getBookId()
 * @method void setBookId(int $bookId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getSlug()
 * @method void setSlug(string $slug)
 * @method string getColor()
 * @method void setColor(string $color)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class Tag extends Entity {
	protected ?int $bookId = null;
	protected ?string $name = null;
	protected ?string $slug = null;
	protected ?string $color = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
	}
}
