<?php

declare(strict_types=1);

namespace OCA\BookTailor\Entity;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getCompositionId()
 * @method void setCompositionId(int $compositionId)
 * @method int getTagId()
 * @method void setTagId(int $tagId)
 * @method int getPriority()
 * @method void setPriority(int $priority)
 * @method bool getFallback()
 * @method void setFallback(bool $fallback)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class CompositionRule extends Entity {
	protected ?int $compositionId = null;
	protected ?int $tagId = null;
	protected ?int $priority = 0;
	protected ?bool $fallback = false;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('compositionId', Types::BIGINT);
		$this->addType('tagId', Types::BIGINT);
		$this->addType('priority', Types::INTEGER);
		$this->addType('fallback', Types::BOOLEAN);
	}
}
