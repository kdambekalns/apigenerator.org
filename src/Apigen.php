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

namespace ApiGeneratorOrg;

use Symfony\Component\Process\ProcessBuilder;

class Apigen extends AbstractGenerator
{
    const PARAM_SOURCE_FILE = 'source-file';

    const PARAM_DOCS_FILE = 'docs-file';

    const PARAM_STRING = 'string';

    const PARAM_BOOL = 'bool';

    protected function generateDocs(Repository $repository, Repository $docsRepository, GitSource $source)
    {
        $this->logger->debug('Generate docs');

        $args = [dirname(__DIR__) . '/vendor/bin/apigen'];
        $args[] = 'generate';
        foreach (
            [
                'config' => static::PARAM_SOURCE_FILE,
                'template-config' => static::PARAM_SOURCE_FILE,
                'extensions' => static::PARAM_STRING,
                'exclude' => static::PARAM_STRING,
                'skip-doc-path' => static::PARAM_STRING,
                'main' => static::PARAM_STRING,
                'title' => static::PARAM_STRING,
                'base-url' => static::PARAM_STRING,
                'google-cse-id' => static::PARAM_STRING,
                'google-analytics' => static::PARAM_STRING,
                'template-theme' => static::PARAM_STRING,
                'groups' => static::PARAM_STRING,
                'charset' => static::PARAM_STRING,
                'access-levels' => static::PARAM_STRING,
                'annotation-groups' => static::PARAM_STRING,
                'internal' => static::PARAM_BOOL,
                'php' => static::PARAM_BOOL,
                'tree' => static::PARAM_BOOL,
                'deprecated' => static::PARAM_BOOL,
                'no-source-code' => static::PARAM_BOOL,
                'todo' => static::PARAM_BOOL,
                'download' => static::PARAM_BOOL,
            ] as $parameter => $type
        ) {
            if (array_key_exists($parameter, $this->settings)) {
                $value = $this->settings[$parameter];
                switch ($type) {
                    case static::PARAM_SOURCE_FILE:
                        $value = $repository->getSourcesPath() . '/' . ltrim($value, '/');
                    break;
                    case static::PARAM_DOCS_FILE:
                        $value = $repository->getDocsPath() . '/' . ltrim($value, '/');
                    break;
                    case static::PARAM_STRING:
                        // do nothing
                    break;
                    case static::PARAM_BOOL:
                        if ($value) {
                            $args[] = '--' . $parameter;
                        }
                        continue 2;
                    default:
                        $this->logger->warning(sprintf('Parameter %s has an illegal type %s', $parameter, $type));
                        // skip
                        continue;
                }

                $args[] = '--' . $parameter;
                $args[] = $value;
            }
        }
        $args[] = '--source';
        $args[] = $repository->getSourcesPath() . (array_key_exists('src-path', $this->settings) ? '/' . ltrim($this->settings['src-path'], '/') : '');
        $args[] = '--destination';
        $args[] = $docsRepository->getDocsPath() . (array_key_exists('docs-path', $this->settings) ? '/' . ltrim($this->settings['docs-path'], '/') : '');

        $process = ProcessBuilder::create($args)->getProcess();
        $process->setTimeout(null);
        $this->logger->debug('exec ' . $process->getCommandLine());
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getCommandLine() . ': ' . $process->getErrorOutput() ?: $process->getOutput());
        }
    }
}
