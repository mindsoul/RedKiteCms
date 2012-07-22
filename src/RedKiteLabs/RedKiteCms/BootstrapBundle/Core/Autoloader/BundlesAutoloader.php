<?php
/*
 * This file is part of the AlphaLemonBootstrapBundle and it is distributed
 * under the MIT License. To use this bundle you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://alphalemon.com
 *
 * @license    MIT License
 */

namespace AlphaLemon\BootstrapBundle\Core\Autoloader;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use AlphaLemon\BootstrapBundle\Core\Exception\InvalidProjectException;
use AlphaLemon\ThemeEngineBundle\Core\Autoloader\Exception\InvalidAutoloaderException;
use AlphaLemon\BootstrapBundle\Core\Json\JsonAutoloader;
use AlphaLemon\BootstrapBundle\Core\Event\BootstrapperEvents;
use AlphaLemon\BootstrapBundle\Core\Event\PackageInstalledEvent;
use AlphaLemon\BootstrapBundle\Core\Event\PackageUninstalledEvent;

/**
 * Parses the bundles installed by composer, checks if the bundle has an autoload.json file in its main root
 * and when the file is present, copies the autoloader.json and the routing.yml and config.yml under the
 * app/config/bundles folder, to autoconfigure the bundle.
 *
 * @author AlphaLemon <webmaster@alphalemon.com>
 */
class BundlesAutoloader
{
    private $bundles;
    private $instantiatedBundles;
    private $environment;
    private $kernelDir;
    private $vendorDir;
    private $dispatcher;
    private $autoloaders = array();
    private $installedBundles = array();
    private $environmentsBundles = array();
    private $listeners = array();

    private $basePath;
    private $autoloadersPath;
    private $configPath;
    private $routingPath;

    /**
     * Constructor
     */
    public function __construct($environment, $kernelDir, array $bundles)
    {
        $this->environment = $environment;
        $this->kernelDir = $kernelDir;
        $this->vendorDir = $this->kernelDir . '/../vendor';

        $this->bundles = $bundles;
        foreach ($this->bundles as $bundle) {
            $this->instantiatedBundles[] = get_class($bundle);
        }

        $this->dispatcher = new EventDispatcher();
    }

    /**
     * Returns the instantiated bundles
     *
     * @return array
     */
    public function getBundles()
    {
        $this->run();

        return $this->bundles;
    }

    public function setVendorDir($v)
    {
        $this->vendorDir = $v;

        return $this;
    }

    protected function run()
    {
        $this->setupFolders();
        $this->retrieveInstalledBundles();
        $this->parseComposer();
        $this->uninstall();
        $this->writeListenersFile();
        $this->autoload();
    }

    /**
     * Instantates the bundles
     */
    protected function autoload()
    {
        $bundles = $this->arrangeBundlesForEnvironment();
        $this->register('all');
        $this->register($this->environment);
    }

    /**
     * Parsers the autoloaders and arranges the bundles for environment
     */
    protected function arrangeBundlesForEnvironment()
    {
        foreach ($this->autoloaders as $autoloader) {
            $autoloaderBundles = $autoloader->getBundles();
            foreach ($autoloaderBundles as $environment => $bundles) {
                foreach ($bundles as $bundle) {
                    $this->environmentsBundles[$environment][] = $bundle;
                }
            }
        }

        // A bundle enabled by one or more environments, must be removed from all section
        $all = $this->environmentsBundles['all'];
        unset($this->environmentsBundles['all']);
        foreach ($this->environmentsBundles as $bundles) {
            $all = array_diff($all, $bundles);
        }
        $this->environmentsBundles['all'] = $all;
    }

    /**
     * Registers the bundles for the given environment
     *
     * @param string $environment
     */
    protected function register($environment)
    {
        if (isset($this->environmentsBundles[$environment])) {
            foreach ($this->environmentsBundles[$environment] as $bundle) {
                if (empty($this->instantiatedBundles) || !in_array($bundle, $this->instantiatedBundles)) {
                    if (!class_exists($bundle)) {
                        throw new InvalidAutoloaderException(sprintf("The bundle class %s does not exist. Check the bundle's autoload.json to fix the problem.", $bundle, get_class($this)));
                    }

                    $instantiatedBundle = new $bundle();
                    $this->bundles[] = $instantiatedBundle;
                    $this->instantiatedBundles[] = $bundle;
                }
            }
        }
    }

    /**
     * Instantiates the bundles that must be autoconfigured, parsing the autoload_namespaces.php file
     * generated by composer
     */
    protected function parseComposer()
    {
        //$path = __DIR__ . '/../../../../../../../vendor/.composer';
        $path = $this->vendorDir . '/composer';
        if (is_dir($path)) {
            $map = require $path . '/autoload_namespaces.php';

            foreach ($map as $namespace => $path) {
                $dir = $path . str_replace('\\', '/', $namespace);
                $bundleName = $this->getBundleName($dir);
                if (null !== $bundleName && $this->hasAutoloader($dir)) {
                    $bundleName = strtolower($bundleName);
                    $autoloader = $dir . '/autoload.json';
                    $jsonAutoloader = new JsonAutoloader($bundleName, $autoloader);
                    $this->autoloaders[] = $jsonAutoloader;
                    $listener = $jsonAutoloader->getInstallScript();
                    if(null !== $listener) $this->dispatcher->addListener('package-installed', array(new $listener(), 'onPackageInstalled'));
                    $this->install($dir, $jsonAutoloader);
                }
            }
        }
        else {
            throw new InvalidProjectException('composer folder has not been found. Be sure to use this bundle on a project managed by Composer');
        }
    }

    /**
     * Installs the autoloader.json, the routing and config files
     *
     * @param string $sourceFolder          The source folder where the autoloader is placed
     * @param JsonAutoloader $autoloader    The generated autoloader object
     */
    protected function install($sourceFolder, JsonAutoloader $autoloader)
    {
        $bundleName = $autoloader->getBundleName();

        // The bundle is still active
        unset($this->installedBundles[$bundleName]);

        $target = $this->autoloadersPath . '/' . $bundleName  . '.json';
        $res = $this->copy($autoloader->getFilename(), $target);

        if ($res) {
            if (null !== $autoloader->getInstallScript($autoloader))
            {
                $this->listeners[BootstrapperEvents::PACKAGE_INSTALLED][] = $autoloader->getInstallScript();
            }
        }

        $sourceFolder = $sourceFolder . '/Resources/config';

        $filename = '/' . $bundleName . '.yml';
        $this->copy($sourceFolder . '/config.yml', $this->configPath . $filename);
        $this->copy($sourceFolder . '/routing.yml', $this->routingPath . $filename);
    }

    /**
     * Retrieves the current installed bundles
     */
    protected function retrieveInstalledBundles()
    {
        $finder = new Finder();
        $autoloaders = $finder->files()->depth(0)->name('*.json')->in(realpath($this->autoloadersPath));
        foreach ($autoloaders as $autoloader) {
            $bundleName = strtolower(basename($autoloader->getFilename(), '.json'));
            $jsonAutoloader = new JsonAutoloader($bundleName, (string)$autoloader);
            $this->installedBundles[$bundleName] = $jsonAutoloader;
            $listener = $jsonAutoloader->getUninstallScript();
            if(null !== $listener) $this->dispatcher->addListener('package-uninstalled', array(new $listener(), 'onPackageUninstalled'));
        }
    }

    /**
     * Retrieves the current bundle class
     *
     * @param string $path The bundle's path
     * @return string
     */
    protected function getBundleName($path)
    {
        if (is_dir($path)) {
            $finder = new Finder();
            $bundles = $finder->files()->depth(0)->name('*Bundle.php')->in($path);
            foreach ($bundles as $bundle) {
                return basename($bundle->getFilename(), 'Bundle.php');
            }
        }

        return null;
    }

    /**
     * Checks if the bundle has an autoloader.json file
     *
     * @param string $path The bundle's path
     * @return boolean
     */
    protected function hasAutoloader($path)
    {
        if (is_dir($path)) {
            $finder = new Finder();
            $bundles = $finder->files()->depth(0)->name('autoload.json')->in($path);
            foreach ($bundles as $bundle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copies the source file to the target
     *
     * @param string $source
     * @param string $target
     *
     * @return boolean
     */
    protected function copy($source, $target)
    {
        if (is_file($source)) {
            $exists = is_file($target) ? true :false;
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->copy($source, $target);

            if (!$exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the autoloader and config from the app/config/bundles folder
     */
    protected function uninstall()
    {
        if (!empty($this->installedBundles)) {
            foreach ($this->installedBundles as $autoloader) {
                @unlink($this->autoloadersPath . '/' . $autoloader->getBundleName() . '.json');
                $filename = '/' . $autoloader->getBundleName() . '.yml';
                @unlink($this->configPath . $filename);
                @unlink($this->routingPath . $filename);

                $this->listeners[BootstrapperEvents::PACKAGE_UNINSTALLED][] = $autoloader->getUninstallScript();
            }
        }
    }

    /**
     * Sets up the paths and creates the folder if they not exist
     */
    private function setupFolders()
    {
        $this->basePath = $this->kernelDir . '/config/bundles';
        if (!is_dir($this->basePath)) $this->createFolder($this->basePath);

        $this->autoloadersPath = $this->basePath . '/autoloaders';
        if (!is_dir($this->autoloadersPath)) $this->createFolder($this->autoloadersPath);

        $this->configPath = $this->basePath . '/config';
        if (!is_dir($this->configPath)) $this->createFolder($this->configPath);

        $this->routingPath = $this->basePath . '/routing';
        if (!is_dir($this->routingPath)) $this->createFolder($this->routingPath);
    }

    /**
     * Tries to create a folder
     *
     * @param string $dir
     */
    private function createFolder($dir)
    {
        if (!@mkdir($dir)) {
            throw new \RuntimeException(sprintf("The folder %s cannot be created. Please be sure to have the permissions to create a new folder", $dir));
        }
    }

    /**
     * Stores the listener classes for the events required in the autoloader file in a temporary hidden json file
     */
    private function writeListenersFile()
    {
        if (!empty($this->listeners)) {
            file_put_contents($this->basePath . '/.listeners', json_encode($this->listeners));
        }
    }
}