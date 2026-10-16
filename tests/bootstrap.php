<?php
declare(strict_types=1);
// Match production: Doctrine DATE/DATETIME hydration uses PHP's default timezone.
date_default_timezone_set('Europe/Berlin');
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
