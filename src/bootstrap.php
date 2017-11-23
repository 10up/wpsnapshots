<?php

namespace WPSnapshots;

use \Symfony\Component\Console\Application;

require_once __DIR__ . '/utils.php';

$app = new Application( 'WP Snapshots - A project sharing tool for WordPress.', '1.0' );

/**
 * Attempt to set this as WP Snapahots can consume a lot of memory.
 */
ini_set( 'memory_limit', '-1' );

/**
 * Register commands
 */
$app->add( new Command\Configure() );
$app->add( new Command\CreateRepository() );
$app->add( new Command\Push() );
$app->add( new Command\Pull() );
$app->add( new Command\Search() );
$app->add( new Command\Delete() );
$app->add( new Command\CreateEnvironment() );

$app->run();
