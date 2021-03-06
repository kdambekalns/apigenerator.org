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

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractGenerator
{
    /**
     * @var HandlerInterface
     */
    protected $handler;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var array
     */
    protected $defaultSettings;

    /**
     * @var array
     */
    protected $settings;

    /**
     * AbstractGenerator constructor.
     */
    function __construct()
    {
        $this->handler = new RotatingFileHandler(dirname(__DIR__) . '/log/hook.log', 7);
        $this->logger = new Logger('*/*', [$this->handler]);

        $this->fs = new Filesystem();
    }

    /**
     * @param Repository $repository
     * @param GitSource $source
     * @return void
     */
    public function run(Repository $repository, GitSource $source)
    {
        $this->logger = $this->logger->withName($repository->getRepository());

        try {
            $docsRepository = null;

            $this->initSourcePath($repository, $source);
            $this->checkoutSource($repository, $source);
            $this->buildDefaultSettings($repository, $source);
            $this->buildSettings($repository, $docsRepository, $source);

            $this->checkBranch($repository, $docsRepository, $source);

            $this->initDocsPath($repository, $docsRepository, $source);
            $this->prepareDocs($repository, $docsRepository, $source);
            $this->generateDocs($repository, $docsRepository, $source);
            $this->pushDocs($repository, $docsRepository, $source);
        } catch (\Exception $exception) {
            $this->logger->addCritical($exception->getMessage() . "\n" . $exception->getTraceAsString());
        }
    }

    /**
     * @param Repository $repository
     * @param Repository $docsRepository
     * @param GitSource $source
     * @return mixed
     */
    abstract protected function generateDocs(Repository $repository, Repository $docsRepository, GitSource $source);

    /**
     * @param Repository $repository
     * @param GitSource $source
     * @return void
     */
    protected function initSourcePath(Repository $repository, GitSource $source)
    {
        $this->logger->debug(sprintf('Init sources directory %s', $repository->getSourcesPath()));

        if (!$this->fs->exists($repository->getSourcesPath())) {
            $this->fs->mkdir($repository->getSourcesPath());
        }
    }

    /**
     * @param Repository $repository
     * @param GitSource $source
     * @return void
     */
    protected function checkoutSource(Repository $repository, GitSource $source)
    {
        $url = $source->getRepositoryUrl($repository);

        if ($this->fs->exists($repository->getSourcesPath() . '.git')) {
            $this->logger->debug(sprintf('Update sources %s', $repository->getSourcesPath()));

            $process = new Process(['git', 'remote', 'set-url', 'origin', $url], $repository->getSourcesPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }

            $refsToFetch = sprintf('+refs/heads/%s:refs/remotes/origin/%s', $repository->getCommitBranch(), $repository->getCommitBranch());
            $process = new Process(['git', 'config', 'remote.origin.fetch', $refsToFetch], $repository->getSourcesPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }

            $process = new Process(['git', 'fetch', '--depth', 1, 'origin'], $repository->getSourcesPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }

            $process = new Process(['git', 'reset', '--hard', 'origin/' . $repository->getCommitBranch()], $repository->getSourcesPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        } else {
            $this->logger->debug(sprintf('Checkout source %s', $url));

            $process = new Process(['git', 'clone', '--depth', 1, '-b', $repository->getCommitBranch(), $url, $repository->getSourcesPath()]);
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        }
    }

    /**
     * @param Repository $repository
     * @param GitSource $source
     * @return void
     */
    protected function buildDefaultSettings(Repository $repository, GitSource $source)
    {
        $class = new \ReflectionClass($this);
        $className = strtolower($class->getShortName());
        $filename = $className . '.yml';

        $pathAndFilename = dirname(__DIR__) . '/config/' . $filename;
        if (file_exists($pathAndFilename)) {
            $this->defaultSettings = Yaml::parse(file_get_contents($pathAndFilename));
        } else {
            $this->defaultSettings = [];
        }

        $this->defaultSettings['docs-branch'] = 'gh-pages';
    }

    /**
     * @param Repository $repository
     * @param Repository|null $docsRepository
     * @param GitSource $source
     * @return void
     */
    protected function buildSettings(Repository $repository, Repository &$docsRepository = null, GitSource $source)
    {
        $class = new \ReflectionClass($this);
        $className = strtolower($class->getShortName());
        $filename = $className . '.yml';

        if (!file_exists($repository->getSourcesPath() . '/' . $filename)) {
            throw new \RuntimeException($filename . ' is missing, skip');
        }

        $this->settings = Yaml::parse(file_get_contents($repository->getSourcesPath() . '/' . $filename));

        if ($this->settings === null) {
            $this->settings = [];
        }

        // use the master branch
        if (!empty($this->settings['branch'])) {
            $this->settings['src-branch'] = $this->settings['branch'];
        } else {
            if (empty($this->settings['branch'])) {
                $this->settings['src-branch'] = $repository->getMasterBranch();
            }
        }

        # merge with defaults
        $this->settings = array_merge(
            $this->defaultSettings,
            $this->settings
        );

        if (isset($this->settings['docs-repository'])) {
            list($ownerName, $repositoryName) = explode('/', $this->settings['docs-repository']);
            $docsRepository = clone $repository;
            $docsRepository->setOwnerName($ownerName);
            $docsRepository->setRepositoryName($repositoryName);
        } else {
            $docsRepository = $repository;
        }

        # build default base url
        if (!array_key_exists('base-url', $this->settings)) {
            $this->settings['base-url'] = $source->getPagesUrl($docsRepository);
        }

        # set default title
        if (!array_key_exists('title', $this->settings)) {
            $this->settings['title'] = $repository->getRepository();
        }

        $this->logger->debug(
            sprintf('Build settings for %s/%s', $repository->getOwnerName(), $repository->getRepositoryName()),
            $this->settings
        );
    }

    /**
     * @param Repository $repository
     * @param Repository $docsRepository
     * @param GitSource $source
     * @return void
     */
    protected function checkBranch(Repository $repository, Repository $docsRepository, GitSource $source)
    {
        if ($this->settings['src-branch'] != $repository->getCommitBranch()) {
            throw new \RuntimeException(
                'Skip branch ' . $repository->getCommitBranch() . ', expect branch ' . $this->settings['src-branch']
            );
        }
    }

    /**
     * @param Repository $repository
     * @param Repository $docsRepository
     * @param GitSource $source
     * @return void
     */
    protected function initDocsPath(Repository $repository, Repository $docsRepository, GitSource $source)
    {
        $this->logger->debug(sprintf('Init docs directory %s', $docsRepository->getDocsPath()));

        if (!$this->fs->exists($docsRepository->getDocsPath())) {
            $this->fs->mkdir($docsRepository->getDocsPath());
        }
    }

    /**
     * @param Repository $repository
     * @param Repository $docsRepository
     * @param GitSource $source
     * @return void
     */
    protected function prepareDocs(Repository $repository, Repository $docsRepository, GitSource $source)
    {
        $url = $source->getRepositoryUrl($docsRepository);

        if ($this->fs->exists($docsRepository->getDocsPath() . '.git')) {
            $process = new Process(['git', 'remote', 'set-url', 'origin', $url], $docsRepository->getDocsPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        } else {
            $process = new Process(['git', 'init', $docsRepository->getDocsPath()]);
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }

            $process = new Process(['git', 'remote', 'add', 'origin', $url], $docsRepository->getDocsPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        }

        $process = new Process(['git', 'fetch', '--depth', 1, 'origin'], $docsRepository->getDocsPath());
        $this->logger->debug('exec ' . $process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
        }

        $process = new Process(['git', 'branch', '-a'], $docsRepository->getDocsPath());
        $this->logger->debug('exec ' . $process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
        }
        $branches = explode("\n", $process->getOutput());
        $branches = array_map(
            function ($branch) {
                return ltrim($branch, '*');
            },
            $branches
        );
        $branches = array_map('trim', $branches);

        if (in_array('remotes/origin/' . $this->settings['docs-branch'], $branches, true)) {
            $this->logger->debug(sprintf('Update docs %s', $url));

            $process = new Process(['git', 'checkout', '-f', '-B', $this->settings['docs-branch']], $docsRepository->getDocsPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }

            $process = new Process(['git', 'reset', '--hard', 'origin/' . $this->settings['docs-branch']], $docsRepository->getDocsPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        } else {
            if (!in_array($this->settings['docs-branch'], $branches, true)) {
                $this->logger->debug(sprintf('Initialise empty docs %s', $url));

                $process = new Process(['git', 'checkout', '--orphan', $this->settings['docs-branch']], $docsRepository->getDocsPath());
                $this->logger->debug('exec ' . $process->getCommandLine());
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
                }
            } else {
                $this->logger->debug(sprintf('Reuse local docs branch %s', $url));

                $process = new Process(['git', 'checkout', '-Bf', $this->settings['docs-branch']], $docsRepository->getDocsPath());
                $this->logger->debug('exec ' . $process->getCommandLine());
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
                }
            }
        }
    }

    /**
     * @param Repository $repository
     * @param Repository $docsRepository
     * @param GitSource $source
     * @return void
     */
    protected function pushDocs(Repository $repository, Repository $docsRepository, GitSource $source)
    {
        $this->logger->debug('Push docs');

        $process = new Process(['git', 'status', '-s'], $docsRepository->getDocsPath());
        $this->logger->debug('exec ' . $process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
        }

        $changes = $process->getOutput();

        if ($changes) {
            $changes = explode("\n", $changes);
            $changes = array_map('trim', $changes);
            $changes = array_filter($changes);

            foreach ($changes as $change) {
                list($status, $file) = explode(' ', $change, 2);

                $process = new Process(['git', $status == 'D' ? 'rm' : 'add', $file], $docsRepository->getDocsPath());
                $this->logger->debug('exec ' . $process->getCommandLine());
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
                }
            }

            $process = new Process(['git', 'commit', '-m', $repository->getCommitMessage()], $docsRepository->getDocsPath());
            $this->logger->debug('exec ' . $process->getCommandLine());
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
            }
        }

        $process = new Process(['git', 'push', 'origin', $this->settings['docs-branch']], $docsRepository->getDocsPath());
        $this->logger->debug('exec ' . $process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getCommandLine() . ': ' . ($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
