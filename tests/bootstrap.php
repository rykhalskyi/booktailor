<?php

declare(strict_types=1);

// Load only the apps these tests need. The dev server's other apps can have
// incomplete schemas (for example files_external) and are irrelevant here.
putenv('TEST_DONT_LOAD_APPS=1');

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

try {
	\OC_App::loadApp('files_versions');
} catch (\Throwable) {
	// Version tests are skipped when the app is unavailable.
}
\OC_App::loadApp(OCA\BookTailor\AppInfo\Application::APP_ID);
OC_Hook::clear();
