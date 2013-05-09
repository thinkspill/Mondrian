<?php

/*
 * Mondrian
 */

namespace Trismegiste\Mondrian\Config;

use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Helper is a Facade for the config heavy machinery
 */
class Helper
{

    public function getConfig($dir)
    {
        // load
        try {
            // all this stuff is not really necessary but this component is kewl 
            // and I want to use it.
            // A better configuration handling => better programing
            $delegatingLoader = new DelegatingLoader(new LoaderResolver(array(new Loader())));
            $config = $delegatingLoader->load($dir);
        } catch (FileLoaderLoadException $e) {
            $config = array();
        }
        // validates
        $processor = new Processor();
        $configuration = new Validator();
        try {
            $processedConfig = $processor->processConfiguration($configuration, array($config));
        } catch (InvalidConfigurationException $e) {
            throw new \DomainException($e->getMessage());
        }

        return $processedConfig;
    }

}