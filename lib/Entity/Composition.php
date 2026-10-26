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
 * @method ?int getStructureId()
 * @method void setStructureId(?int $structureId)
 * @method string getStructureName()
 * @method void setStructureName(string $structureName)
 * @method ?string getOutputName()
 * @method void setOutputName(?string $outputName)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?int getOutputFileId()
 * @method void setOutputFileId(?int $outputFileId)
 * @method ?string getOutputPath()
 * @method void setOutputPath(?string $outputPath)
 * @method ?string getError()
 * @method void setError(?string $error)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getStartedAt()
 * @method void setStartedAt(?DateTime $startedAt)
 * @method ?DateTime getCompletedAt()
 * @method void setCompletedAt(?DateTime $completedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class Composition extends Entity {
	public const STATUS_QUEUED = 'queued';
	public const STATUS_RUNNING = 'running';
	public const STATUS_SUCCEEDED = 'succeeded';
	public const STATUS_FAILED = 'failed';

	protected ?int $bookId = null;
	protected ?int $structureId = null;
	protected ?string $structureName = null;
	protected ?string $outputName = null;
	protected ?string $status = self::STATUS_QUEUED;
	protected ?int $outputFileId = null;
	protected ?string $outputPath = null;
	protected ?string $error = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $startedAt = null;
	protected ?DateTime $completedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('bookId', Types::BIGINT);
		$this->addType('structureId', Types::BIGINT);
		$this->addType('outputFileId', Types::BIGINT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('startedAt', Types::DATETIME);
		$this->addType('completedAt', Types::DATETIME);
	}
}
