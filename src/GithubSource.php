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

class GithubSource implements GitSource
{
    /**
     * @param Repository $repository
     * @return string
     */
    public function getRepositoryUrl(Repository $repository)
    {
        return 'git@github.com:' . $repository->getRepository() . '.git';
    }

    /**
     * @param Repository $repository
     * @return string
     */
    public function getPagesUrl(Repository $repository)
    {
        return sprintf(
            'http://%s.github.io/%s/',
            $repository->getOwnerName(),
            $repository->getRepositoryName()
        );
    }
}
