<?php
/**
 * Bootstrap WP Snapshots
 *
 * @package  wpsnapshots
 */

namespace WPSnapshots;

use \Symfony\Component\Console\Application;

define( 'WPSNAPSHOTS_VERSION', '2.0' );

require_once __DIR__ . '/utils.php';

$app = new Application( 'WP Snapshots - A project sharing tool for WordPress.', WPSNAPSHOTS_VERSION );

/**
 * Attempt to set this as WP Snapahots can consume a lot of memory.
 */
ini_set( 'memory_limit', '-1' );

/**
 * Register commands
 */
$app->add( new Command\Configure() );
$app->add( new Command\Create() );
$app->add( new Command\CreateRepository() );
$app->add( new Command\Push() );
$app->add( new Command\Pull() );
$app->add( new Command\Search() );
$app->add( new Command\Delete() );
$app->add( new Command\Download() );

$app->run();
