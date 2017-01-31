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

class LocalGitSource implements GitSource
{
    protected $path;

    /**
     * LocalGitSource constructor.
     *
     * @param string $path
     */
    function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * @param Repository $repository
     * @return string
     */
    protected function getPath(Repository $repository)
    {
        return str_replace(
            [
                '{owner}',
                '{repo}',
                '{master-branch}',
                '{commit-branch}'
            ],
            [
                $repository->getOwnerName(),
                $repository->getRepositoryName(),
                $repository->getMasterBranch(),
                $repository->getCommitBranch(),
            ],
            $this->path
        );
    }

    /**
     * @param Repository $repository
     * @return string
     */
    public function getRepositoryUrl(Repository $repository)
    {
        return $this->getPath($repository);
    }

    /**
     * @param Repository $repository
     * @return string
     */
    public function getPagesUrl(Repository $repository)
    {
        return $this->getPath($repository);
    }
}
