<?php

declare(strict_types=1);

namespace OCA\BookTailor\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getChapterId()
 * @method void setChapterId(int $chapterId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method ?int getFileId()
 * @method void setFileId(?int $fileId)
 * @method bool getIncludeInComposition()
 * @method void setIncludeInComposition(bool $includeInComposition)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class Asset extends Entity {
	protected ?int $chapterId = null;
	protected ?string $name = null;
	protected ?string $fileName = null;
	protected ?int $fileId = null;
	protected ?bool $includeInComposition = false;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('chapterId', Types::BIGINT);
		$this->addType('fileId', Types::BIGINT);
		$this->addType('includeInComposition', Types::BOOLEAN);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
