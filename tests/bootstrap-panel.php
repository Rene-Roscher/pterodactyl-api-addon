<?php

$panelPath = getenv('PTERODACTYL_PANEL_PATH') ?: realpath(__DIR__ . '/../panel');

if (!$panelPath) {
    throw new RuntimeException('Could not determine Pterodactyl Panel path. Set PTERODACTYL_PANEL_PATH.');
}

$bootstrap = $panelPath . '/bootstrap/tests.php';

if (!file_exists($bootstrap)) {
    throw new RuntimeException(sprintf('Panel test bootstrap not found at "%s".', $bootstrap));
}

require $bootstrap;
