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
 * @method string getName()
 * @method void setName(string $name)
 * @method string getProvider()
 * @method void setProvider(string $provider)
 * @method string getApiKey()
 * @method void setApiKey(string $apiKey)
 * @method ?string getModel()
 * @method void setModel(?string $model)
 * @method int getConnectTimeout()
 * @method void setConnectTimeout(int $connectTimeout)
 * @method int getReadTimeout()
 * @method void setReadTimeout(int $readTimeout)
 * @method int getMaxTokens()
 * @method void setMaxTokens(int $maxTokens)
 * @method bool getIsActive()
 * @method void setIsActive(bool $isActive)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class LlmProfile extends Entity {
	protected ?string $userId = null;
	protected ?string $name = null;
	protected ?string $provider = null;
	protected ?string $apiKey = null;
	protected ?string $model = null;
	protected ?int $connectTimeout = 30;
	protected ?int $readTimeout = 60;
	protected ?int $maxTokens = 2048;
	protected ?bool $isActive = false;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('connectTimeout', Types::INTEGER);
		$this->addType('readTimeout', Types::INTEGER);
		$this->addType('maxTokens', Types::INTEGER);
		$this->addType('isActive', Types::BOOLEAN);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
