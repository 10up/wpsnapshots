<?php

namespace WPProjects;

use \Symfony\Component\Console\Application;

require_once __DIR__ . '/utils.php';

$app = new Application( 'WPProjects - A tool for syncing WordPress projects.' '0.1' );

/**
 * Register commands
 */
$app->add( new Command\Connect() );
$app->add( new Command\CreateRepository() );
$app->add( new Command\Push() );
$app->add( new Command\Pull() );
$app->add( new Command\Search() );
$app->add( new Command\Delete() );

$app->run();
