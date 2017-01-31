<?php
namespace ApiGeneratorOrg;

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

interface GitSource
{
    /**
     * @param Repository $repository
     * @return string
     */
    public function getRepositoryUrl(Repository $repository);

    /**
     * @param Repository $repository
     * @return string
     */
    public function getPagesUrl(Repository $repository);
}
