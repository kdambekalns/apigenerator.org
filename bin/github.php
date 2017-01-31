<?php

/**
 * ApiGenerator.org
 * Copyright (C) 2013 Tristan Lins
 * PHP version 5
 *
 * @copyright  bit3 UG 2013
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @package    apigenerator.org
 * @license    LGPL-3.0+
 * @filesource
 */

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($argv[1])) {
    echo 'No payload given as $arg[1]' . PHP_EOL;
    exit(1);
}

$payload = $argv[1];
$payload = json_decode($payload);

$repository = \ApiGeneratorOrg\Repository::createFromGithubPayload($payload);
$source = new \ApiGeneratorOrg\GithubSource();

$hook = new \ApiGeneratorOrg\Hook();
$hook->run($repository, $source);

while (count(ob_list_handlers())) {
    ob_end_flush();
}
