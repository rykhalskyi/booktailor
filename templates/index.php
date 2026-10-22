<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\BookTailor\AppInfo\Application::APP_ID, OCA\BookTailor\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\BookTailor\AppInfo\Application::APP_ID, OCA\BookTailor\AppInfo\Application::APP_ID . '-main');

?>

<div id="booktailor"></div>
