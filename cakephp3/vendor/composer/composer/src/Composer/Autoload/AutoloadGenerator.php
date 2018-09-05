<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Autoload;

use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use Composer\Script\ScriptEvents;

/**
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class AutoloadGenerator
{
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var bool
     */
    private $devMode = false;

    /**
     * @var bool
     */
    private $classMapAuthoritative = false;

    /**
     * @var bool
     */
    private $apcu = false;

    /**
     * @var bool
     */
    private $runScripts = false;

    public function __construct(EventDispatcher $eventDispatcher, IOInterface $io = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->io = $io;
    }

    public function setDevMode($devMode = true)
    {
        $this->devMode = (bool) $devMode;
    }

    /**
     * Whether or not generated autoloader considers the class map
     * authoritative.
     *
     * @param bool $classMapAuthoritative
     */
    public function setClassMapAuthoritative($classMapAuthoritative)
    {
        $this->classMapAuthoritative = (bool) $classMapAuthoritative;
    }

    /**
     * Whether or not generated autoloader considers APCu caching.
     *
     * @param bool $apcu
     */
    public function setApcu($apcu)
    {
        $this->apcu = (bool) $apcu;
    }

    /**
     * Set whether to run scripts or not
     *
     * @param bool $runScripts
     */
    public function setRunScripts($runScripts = true)
    {
        $this->runScripts = (bool) $runScripts;
    }

    public function dump(Config $config, InstalledRepositoryInterface $localRepo, PackageInterface $mainPackage, InstallationManager $installationManager, $targetDir, $scanPsr0Packages = false, $suffix = '')
    {
        if ($this->classMapAuthoritative) {
            // Force scanPsr0Packages when classmap is authoritative
            $scanPsr0Packages = true;
        }
        if ($this->runScripts) {
            $this->eventDispatcher->dispatchScript(ScriptEvents::PRE_AUTOLOAD_DUMP, $this->devMode, array(), array(
                'optimize' => (bool) $scanPsr0Packages,
            ));
        }

        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($config->get('vendor-dir'));
        // Do not remove double realpath() calls.
        // Fixes failing Windows realpath() implementation.
        // See https://bugs.php.net/bug.php?id=72738
        $basePath = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));
        $useGlobalIncludePath = (bool) $config->get('use-include-path');
        $prependAutoloader = $config->get('prepend-autoloader') === false ? 'false' : 'true';
        $targetDir = $vendorPath.'/'.$targetDir;
        $filesystem->ensureDirectoryExists($targetDir);

        $vendorPathCode = $filesystem->findShortestPathCode(realpath($targetDir), $vendorPath, true);
        $vendorPathCode52 = str_replace('__DIR__', 'dirname(__FILE__)', $vendorPathCode);
        $vendorPathToTargetDirCode = $filesystem->findShortestPathCode($vendorPath, realpath($targetDir), true);

        $appBaseDirCode = $filesystem->findShortestPathCode($vendorPath, $basePath, true);
        $appBaseDirCode = str_replace('__DIR__', '$vendorDir', $appBaseDirCode);

        $namespacesFile = <<<EOF
<?php

// autoload_namespaces.php @generated by Composer

\$vendorDir = $vendorPathCode52;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        $psr4File = <<<EOF
<?php

// autoload_psr4.php @generated by Composer

\$vendorDir = $vendorPathCode52;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        // Collect information from all packages.
        $packageMap = $this->buildPackageMap($installationManager, $mainPackage, $localRepo->getCanonicalPackages());
        $autoloads = $this->parseAutoloads($packageMap, $mainPackage);

        // Process the 'psr-0' base directories.
        foreach ($autoloads['psr-0'] as $namespace => $paths) {
            $exportedPaths = array();
            foreach ($paths as $path) {
                $exportedPaths[] = $this->getPathCode($filesystem, $basePath, $vendorPath, $path);
            }
            $exportedPrefix = var_export($namespace, true);
            $namespacesFile .= "    $exportedPrefix => ";
            $namespacesFile .= "array(".implode(', ', $exportedPaths)."),\n";
        }
        $namespacesFile .= ");\n";

        // Process the 'psr-4' base directories.
        foreach ($autoloads['psr-4'] as $namespace => $paths) {
            $exportedPaths = array();
            foreach ($paths as $path) {
                $exportedPaths[] = $this->getPathCode($filesystem, $basePath, $vendorPath, $path);
            }
            $exportedPrefix = var_export($namespace, true);
            $psr4File .= "    $exportedPrefix => ";
            $psr4File .= "array(".implode(', ', $exportedPaths)."),\n";
        }
        $psr4File .= ");\n";

        $classmapFile = <<<EOF
<?php

// autoload_classmap.php @generated by Composer

\$vendorDir = $vendorPathCode52;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        // add custom psr-0 autoloading if the root package has a target dir
        $targetDirLoader = null;
        $mainAutoload = $mainPackage->getAutoload();
        if ($mainPackage->getTargetDir() && !empty($mainAutoload['psr-0'])) {
            $levels = substr_count($filesystem->normalizePath($mainPackage->getTargetDir()), '/') + 1;
            $prefixes = implode(', ', array_map(function ($prefix) {
                return var_export($prefix, true);
            }, array_keys($mainAutoload['psr-0'])));
            $baseDirFromTargetDirCode = $filesystem->findShortestPathCode($targetDir, $basePath, true);

            $targetDirLoader = <<<EOF

    public static function autoload(\$class)
    {
        \$dir = $baseDirFromTargetDirCode . '/';
        \$prefixes = array($prefixes);
        foreach (\$prefixes as \$prefix) {
            if (0 !== strpos(\$class, \$prefix)) {
                continue;
            }
            \$path = \$dir . implode('/', array_slice(explode('\\\\', \$class), $levels)).'.php';
            if (!\$path = stream_resolve_include_path(\$path)) {
                return false;
            }
            require \$path;

            return true;
        }
    }

EOF;
        }

        $blacklist = null;
        if (!empty($autoloads['exclude-from-classmap'])) {
            $blacklist = '{(' . implode('|', $autoloads['exclude-from-classmap']) . ')}';
        }

        // flatten array
        $classMap = array();
        if ($scanPsr0Packages) {
            $namespacesToScan = array();

            // Scan the PSR-0/4 directories for class files, and add them to the class map
            foreach (array('psr-0', 'psr-4') as $psrType) {
                foreach ($autoloads[$psrType] as $namespace => $paths) {
                    $namespacesToScan[$namespace][] = array('paths' => $paths, 'type' => $psrType);
                }
            }

            krsort($namespacesToScan);

            foreach ($namespacesToScan as $namespace => $groups) {
                foreach ($groups as $group) {
                    foreach ($group['paths'] as $dir) {
                        $dir = $filesystem->normalizePath($filesystem->isAbsolutePath($dir) ? $dir : $basePath.'/'.$dir);
                        if (!is_dir($dir)) {
                            continue;
                        }

                        $namespaceFilter = $namespace === '' ? null : $namespace;
                        $classMap = $this->addClassMapCode($filesystem, $basePath, $vendorPath, $dir, $blacklist, $namespaceFilter, $classMap);
                    }
                }
            }
        }

        foreach ($autoloads['classmap'] as $dir) {
            $classMap = $this->addClassMapCode($filesystem, $basePath, $vendorPath, $dir, $blacklist, null, $classMap);
        }

        ksort($classMap);
        foreach ($classMap as $class => $code) {
            $classmapFile .= '    '.var_export($class, true).' => '.$code;
        }
        $classmapFile .= ");\n";

        if (!$suffix) {
            if (!$config->get('autoloader-suffix') && is_readable($vendorPath.'/autoload.php')) {
                $content = file_get_contents($vendorPath.'/autoload.php');
                if (preg_match('{ComposerAutoloaderInit([^:\s]+)::}', $content, $match)) {
                    $suffix = $match[1];
                }
            }

            if (!$suffix) {
                $suffix = $config->get('autoloader-suffix') ?: md5(uniqid('', true));
            }
        }

        file_put_contents($targetDir.'/autoload_namespaces.php', $namespacesFile);
        file_put_contents($targetDir.'/autoload_psr4.php', $psr4File);
        file_put_contents($targetDir.'/autoload_classmap.php', $classmapFile);
        $includePathFilePath = $targetDir.'/include_paths.php';
        if ($includePathFileContents = $this->getIncludePathsFile($packageMap, $filesystem, $basePath, $vendorPath, $vendorPathCode52, $appBaseDirCode)) {
            file_put_contents($includePathFilePath, $includePathFileContents);
        } elseif (file_exists($includePathFilePath)) {
            unlink($includePathFilePath);
        }
        $includeFilesFilePath = $targetDir.'/autoload_files.php';
        if ($includeFilesFileContents = $this->getIncludeFilesFile($autoloads['files'], $filesystem, $basePath, $vendorPath, $vendorPathCode52, $appBaseDirCode)) {
            file_put_contents($includeFilesFilePath, $includeFilesFileContents);
        } elseif (file_exists($includeFilesFilePath)) {
            unlink($includeFilesFilePath);
        }
        file_put_contents($targetDir.'/autoload_static.php', $this->getStaticFile($suffix, $targetDir, $vendorPath, $basePath, $staticPhpVersion));
        file_put_contents($vendorPath.'/autoload.php', $this->getAutoloadFile($vendorPathToTargetDirCode, $suffix));
        file_put_contents($targetDir.'/autoload_real.php', $this->getAutoloadRealFile(true, (bool) $includePathFileContents, $targetDirLoader, (bool) $includeFilesFileContents, $vendorPathCode, $appBaseDirCode, $suffix, $useGlobalIncludePath, $prependAutoloader, $staticPhpVersion));

        $this->safeCopy(__DIR__.'/ClassLoader.php', $targetDir.'/ClassLoader.php');
        $this->safeCopy(__DIR__.'/../../../LICENSE', $targetDir.'/LICENSE');

        if ($this->runScripts) {
            $this->eventDispatcher->dispatchScript(ScriptEvents::POST_AUTOLOAD_DUMP, $this->devMode, array(), array(
                'optimize' => (bool) $scanPsr0Packages,
            ));
        }
    }

    private function addClassMapCode($filesystem, $basePath, $vendorPath, $dir, $blacklist = null, $namespaceFilter = null, array $classMap = array())
    {
        foreach ($this->generateClassMap($dir, $blacklist, $namespaceFilter) as $class => $path) {
            $pathCode = $this->getPathCode($filesystem, $basePath, $vendorPath, $path).",\n";
            if (!isset($classMap[$class])) {
                $classMap[$class] = $pathCode;
            } elseif ($this->io && $classMap[$class] !== $pathCode && !preg_match('{/(test|fixture|example|stub)s?/}i', strtr($classMap[$class].' '.$path, '\\', '/'))) {
                $this->io->writeError(
                    '<warning>Warning: Ambiguous class resolution, "'.$class.'"'.
                    ' was found in both "'.str_replace(array('$vendorDir . \'', "',\n"), array($vendorPath, ''), $classMap[$class]).'" and "'.$path.'", the first will be used.</warning>'
                );
            }
        }

        return $classMap;
    }

    private function generateClassMap($dir, $blacklist = null, $namespaceFilter = null, $showAmbiguousWarning = true)
    {
        return ClassMapGenerator::createMap($dir, $blacklist, $showAmbiguousWarning ? $this->io : null, $namespaceFilter);
    }

    public function buildPackageMap(InstallationManager $installationManager, PackageInterface $mainPackage, array $packages)
    {
        // build package => install path map
        $packageMap = array(array($mainPackage, ''));

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                continue;
            }
            $this->validatePackage($package);

            $packageMap[] = array(
                $package,
                $installationManager->getInstallPath($package),
            );
        }

        return $packageMap;
    }

    /**
     * @param PackageInterface $package
     *
     * @throws \InvalidArgumentException Throws an exception, if the package has illegal settings.
     */
    protected function validatePackage(PackageInterface $package)
    {
        $autoload = $package->getAutoload();
        if (!empty($autoload['psr-4']) && null !== $package->getTargetDir()) {
            $name = $package->getName();
            $package->getTargetDir();
            throw new \InvalidArgumentException("PSR-4 autoloading is incompatible with the target-dir property, remove the target-dir in package '$name'.");
        }
        if (!empty($autoload['psr-4'])) {
            foreach ($autoload['psr-4'] as $namespace => $dirs) {
                if ($namespace !== '' && '\\' !== substr($namespace, -1)) {
                    throw new \InvalidArgumentException("psr-4 namespaces must end with a namespace separator, '$namespace' does not, use '$namespace\\'.");
                }
            }
        }
    }

    /**
     * Compiles an ordered list of namespace => path mappings
     *
     * @param  array            $packageMap  array of array(package, installDir-relative-to-composer.json)
     * @param  PackageInterface $mainPackage root package instance
     * @return array            array('psr-0' => array('Ns\\Foo' => array('installDir')))
     */
    public function parseAutoloads(array $packageMap, PackageInterface $mainPackage)
    {
        $mainPackageMap = array_shift($packageMap);
        $packageMap = $this->filterPackageMap($packageMap, $mainPackage);
        $sortedPackageMap = $this->sortPackageMap($packageMap);
        $sortedPackageMap[] = $mainPackageMap;
        array_unshift($packageMap, $mainPackageMap);

        $psr0 = $this->parseAutoloadsType($packageMap, 'psr-0', $mainPackage);
        $psr4 = $this->parseAutoloadsType($packageMap, 'psr-4', $mainPackage);
        $classmap = $this->parseAutoloadsType(array_reverse($sortedPackageMap), 'classmap', $mainPackage);
        $files = $this->parseAutoloadsType($sortedPackageMap, 'files', $mainPackage);
        $exclude = $this->parseAutoloadsType($sortedPackageMap, 'exclude-from-classmap', $mainPackage);

        krsort($psr0);
        krsort($psr4);

        return array(
            'psr-0' => $psr0,
            'psr-4' => $psr4,
            'classmap' => $classmap,
            'files' => $files,
            'exclude-from-classmap' => $exclude,
        );
    }

    /**
     * Registers an autoloader based on an autoload map returned by parseAutoloads
     *
     * @param  array       $autoloads see parseAutoloads return value
     * @return ClassLoader
     */
    public function createLoader(array $autoloads)
    {
        $loader = new ClassLoader();

        if (isset($autoloads['psr-0'])) {
            foreach ($autoloads['psr-0'] as $namespace => $path) {
                $loader->add($namespace, $path);
            }
        }

        if (isset($autoloads['psr-4'])) {
            foreach ($autoloads['psr-4'] as $namespace => $path) {
                $loader->addPsr4($namespace, $path);
            }
        }

        if (isset($autoloads['classmap'])) {
            $blacklist = null;
            if (!empty($autoloads['exclude-from-classmap'])) {
                $blacklist = '{(' . implode('|', $autoloads['exclude-from-classmap']) . ')}';
            }

            foreach ($autoloads['classmap'] as $dir) {
                try {
                    $loader->addClassMap($this->generateClassMap($dir, $blacklist, null, false));
                } catch (\RuntimeException $e) {
                    $this->io->writeError('<warning>'.$e->getMessage().'</warning>');
                }
            }
        }

        return $loader;
    }

    protected function getIncludePathsFile(array $packageMap, Filesystem $filesystem, $basePath, $vendorPath, $vendorPathCode, $appBaseDirCode)
    {
        $includePaths = array();

        foreach ($packageMap as $item) {
            list($package, $installPath) = $item;

            if (null !== $package->getTargetDir() && strlen($package->getTargetDir()) > 0) {
                $installPath = substr($installPath, 0, -strlen('/'.$package->getTargetDir()));
            }

            foreach ($package->getIncludePaths() as $includePath) {
                $includePath = trim($includePath, '/');
                $includePaths[] = empty($installPath) ? $includePath : $installPath.'/'.$includePath;
            }
        }

        if (!$includePaths) {
            return;
        }

        $includePathsCode = '';
        foreach ($includePaths as $path) {
            $includePathsCode .= "    " . $this->getPathCode($filesystem, $basePath, $vendorPath, $path) . ",\n";
        }

        return <<<EOF
<?php

// include_paths.php @generated by Composer

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;

return array(
$includePathsCode);

EOF;
    }

    protected function getIncludeFilesFile(array $files, Filesystem $filesystem, $basePath, $vendorPath, $vendorPathCode, $appBaseDirCode)
    {
        $filesCode = '';
        foreach ($files as $fileIdentifier => $functionFile) {
            $filesCode .= '    ' . var_export($fileIdentifier, true) . ' => '
                . $this->getPathCode($filesystem, $basePath, $vendorPath, $functionFile) . ",\n";
        }

        if (!$filesCode) {
            return false;
        }

        return <<<EOF
<?php

// autoload_files.php @generated by Composer

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;

return array(
$filesCode);

EOF;
    }

    protected function getPathCode(Filesystem $filesystem, $basePath, $vendorPath, $path)
    {
        if (!$filesystem->isAbsolutePath($path)) {
            $path = $basePath . '/' . $path;
        }
        $path = $filesystem->normalizePath($path);

        $baseDir = '';
        if (strpos($path.'/', $vendorPath.'/') === 0) {
            $path = substr($path, strlen($vendorPath));
            $baseDir = '$vendorDir';

            if ($path !== false) {
                $baseDir .= " . ";
            }
        } else {
            $path = $filesystem->normalizePath($filesystem->findShortestPath($basePath, $path, true));
            if (!$filesystem->isAbsolutePath($path)) {
                $baseDir = '$baseDir . ';
                $path = '/' . $path;
            }
        }

        if (preg_match('/\.phar.+$/', $path)) {
            $baseDir = "'phar://' . " . $baseDir;
        }

        return $baseDir . (($path !== false) ? var_export($path, true) : "");
    }

    protected function getAutoloadFile($vendorPathToTargetDirCode, $suffix)
    {
        $lastChar = $vendorPathToTargetDirCode[strlen($vendorPathToTargetDirCode) - 1];
        if ("'" === $lastChar || '"' === $lastChar) {
            $vendorPathToTargetDirCode = substr($vendorPathToTargetDirCode, 0, -1).'/autoload_real.php'.$lastChar;
        } else {
            $vendorPathToTargetDirCode .= " . '/autoload_real.php'";
        }

        return <<<AUTOLOAD
<?php

// autoload.php @generated by Composer

require_once $vendorPathToTargetDirCode;

return ComposerAutoloaderInit$suffix::getLoader();

AUTOLOAD;
    }

    protected function getAutoloadRealFile($useClassMap, $useIncludePath, $targetDirLoader, $useIncludeFiles, $vendorPathCode, $appBaseDirCode, $suffix, $useGlobalIncludePath, $prependAutoloader, $staticPhpVersion = 70000)
    {
        $file = <<<HEADER
<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit$suffix
{
    private static \$loader;

    public static function loadClassLoader(\$class)
    {
        if ('Composer\\Autoload\\ClassLoader' === \$class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    public static function getLoader()
    {
        if (null !== self::\$loader) {
            return self::\$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInit$suffix', 'loadClassLoader'), true, $prependAutoloader);
        self::\$loader = \$loader = new \\Composer\\Autoload\\ClassLoader();
        spl_autoload_unregister(array('ComposerAutoloaderInit$suffix', 'loadClassLoader'));


HEADER;

        if ($useIncludePath) {
            $file .= <<<'INCLUDE_PATH'
        $includePaths = require __DIR__ . '/include_paths.php';
        $includePaths[] = get_include_path();
        set_include_path(implode(PATH_SEPARATOR, $includePaths));


INCLUDE_PATH;
        }

        $file .= <<<STATIC_INIT
        \$useStaticLoader = PHP_VERSION_ID >= $staticPhpVersion && !defined('HHVM_VERSION') && (!function_exists('zend_loader_file_encoded') || !zend_loader_file_encoded());
        if (\$useStaticLoader) {
            require_once __DIR__ . '/autoload_static.php';

            call_user_func(\Composer\Autoload\ComposerStaticInit$suffix::getInitializer(\$loader));
        } else {

STATIC_INIT;

        if (!$this->classMapAuthoritative) {
            $file .= <<<'PSR04'
            $map = require __DIR__ . '/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                $loader->set($namespace, $path);
            }

            $map = require __DIR__ . '/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                $loader->setPsr4($namespace, $path);
            }


PSR04;
        }

        if ($useClassMap) {
            $file .= <<<'CLASSMAP'
            $classMap = require __DIR__ . '/autoload_classmap.php';
            if ($classMap) {
                $loader->addClassMap($classMap);
            }

CLASSMAP;
        }

        $file .= "        }\n\n";

        if ($this->classMapAuthoritative) {
            $file .= <<<'CLASSMAPAUTHORITATIVE'
        $loader->setClassMapAuthoritative(true);

CLASSMAPAUTHORITATIVE;
        }

        if ($this->apcu) {
            $apcuPrefix = substr(base64_encode(md5(uniqid('', true), true)), 0, -3);
            $file .= <<<APCU
        \$loader->setApcuPrefix('$apcuPrefix');

APCU;
        }

        if ($useGlobalIncludePath) {
            $file .= <<<'INCLUDEPATH'
        $loader->setUseIncludePath(true);

INCLUDEPATH;
        }

        if ($targetDirLoader) {
            $file .= <<<REGISTER_TARGET_DIR_AUTOLOAD
        spl_autoload_register(array('ComposerAutoloaderInit$suffix', 'autoload'), true, true);


REGISTER_TARGET_DIR_AUTOLOAD;
        }

        $file .= <<<REGISTER_LOADER
        \$loader->register($prependAutoloader);


REGISTER_LOADER;

        if ($useIncludeFiles) {
            $file .= <<<INCLUDE_FILES
        if (\$useStaticLoader) {
            \$includeFiles = Composer\Autoload\ComposerStaticInit$suffix::\$files;
        } else {
            \$includeFiles = require __DIR__ . '/autoload_files.php';
        }
        foreach (\$includeFiles as \$fileIdentifier => \$file) {
            composerRequire$suffix(\$fileIdentifier, \$file);
        }


INCLUDE_FILES;
        }

        $file .= <<<METHOD_FOOTER
        return \$loader;
    }

METHOD_FOOTER;

        $file .= $targetDirLoader;

        if ($useIncludeFiles) {
            return $file . <<<FOOTER
}

function composerRequire$suffix(\$fileIdentifier, \$file)
{
    if (empty(\$GLOBALS['__composer_autoload_files'][\$fileIdentifier])) {
        require \$file;

        \$GLOBALS['__composer_autoload_files'][\$fileIdentifier] = true;
    }
}

FOOTER;
        }

        return $file . <<<FOOTER
}

FOOTER;
    }

    protected function getStaticFile($suffix, $targetDir, $vendorPath, $basePath, &$staticPhpVersion)
    {
        $staticPhpVersion = 50600;

        $file = <<<HEADER
<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit$suffix
{

HEADER;

        $loader = new ClassLoader();

        $map = require $targetDir . '/autoload_namespaces.php';
        foreach ($map as $namespace => $path) {
            $loader->set($namespace, $path);
        }

        $map = require $targetDir . '/autoload_psr4.php';
        foreach ($map as $namespace => $path) {
            $loader->setPsr4($namespace, $path);
        }

        $classMap = require $targetDir . '/autoload_classmap.php';
        if ($classMap) {
            $loader->addClassMap($classMap);
        }

        $filesystem = new Filesystem();

        $vendorPathCode = ' => ' . $filesystem->findShortestPathCode(realpath($targetDir), $vendorPath, true, true) . " . '/";
        $appBaseDirCode = ' => ' . $filesystem->findShortestPathCode(realpath($targetDir), $basePath, true, true) . " . '/";

        $absoluteVendorPathCode = ' => ' . substr(var_export(rtrim($vendorDir, '\\/') . '/', true), 0, -1);
        $absoluteAppBaseDirCode = ' => ' . substr(var_export(rtrim($baseDir, '\\/') . '/', true), 0, -1);

        $initializer = '';
        $prefix = "\0Composer\Autoload\ClassLoader\0";
        $prefixLen = strlen($prefix);
        if (file_exists($targetDir . '/autoload_files.php')) {
            $maps = array('files' => require $targetDir . '/autoload_files.php');
        } else {
            $maps = array();
        }

        foreach ((array) $loader as $prop => $value) {
            if ($value && 0 === strpos($prop, $prefix)) {
                $maps[substr($prop, $prefixLen)] = $value;
            }
        }

        foreach ($maps as $prop => $value) {
            if (count($value) > 32767) {
                // Static arrays are limited to 32767 values on PHP 5.6
                // See https://bugs.php.net/68057
                $staticPhpVersion = 70000;
            }
            $value = var_export($value, true);
            $value = str_replace($absoluteVendorPathCode, $vendorPathCode, $value);
            $value = str_replace($absoluteAppBaseDirCode, $appBaseDirCode, $value);
            $value = ltrim(preg_replace('/^ */m', '    $0$0', $value));

            $file .= sprintf("    public static $%s = %s;\n\n", $prop, $value);
            if ('files' !== $prop) {
                $initializer .= "            \$loader->$prop = ComposerStaticInit$suffix::\$$prop;\n";
            }
        }

        return $file . <<<INITIALIZER
    public static function getInitializer(ClassLoader \$loader)
    {
        return \Closure::bind(function () use (\$loader) {
$initializer
        }, null, ClassLoader::class);
    }
}

INITIALIZER;
    }

    protected function parseAutoloadsType(array $packageMap, $type, PackageInterface $mainPackage)
    {
        $autoloads = array();

        foreach ($packageMap as $item) {
            list($package, $installPath) = $item;

            $autoload = $package->getAutoload();
            if ($this->devMode && $package === $mainPackage) {
                $autoload = array_merge_recursive($autoload, $package->getDevAutoload());
            }

            // skip misconfigured packages
            if (!isset($autoload[$type]) || !is_array($autoload[$type])) {
                continue;
            }
            if (null !== $package->getTargetDir() && $package !== $mainPackage) {
                $installPath = substr($installPath, 0, -strlen('/'.$package->getTargetDir()));
            }

            foreach ($autoload[$type] as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    if (($type === 'files' || $type === 'classmap' || $type === 'exclude-from-classmap') && $package->getTargetDir() && !is_readable($installPath.'/'.$path)) {
                        // remove target-dir from file paths of the root package
                        if ($package === $mainPackage) {
                            $targetDir = str_replace('\\<dirsep\\>', '[\\\\/]', preg_quote(str_replace(array('/', '\\'), '<dirsep>', $package->getTargetDir())));
                            $path = ltrim(preg_replace('{^'.$targetDir.'}', '', ltrim($path, '\\/')), '\\/');
                        } else {
                            // add target-dir from file paths that don't have it
                            $path = $package->getTargetDir() . '/' . $path;
                        }
                    }

                    if ($type === 'exclude-from-classmap') {
                        // first escape user input
                        $path = preg_replace('{/+}', '/', preg_quote(trim(strtr($path, '\\', '/'), '/')));

                        // add support for wildcards * and **
                        $path = str_replace('\\*\\*', '.+?', $path);
                        $path = str_replace('\\*', '[^/]+?', $path);

                        // add support for up-level relative paths
                        $updir = null;
                        $path = preg_replace_callback(
                            '{^((?:(?:\\\\\\.){1,2}+/)+)}',
                            function ($matches) use (&$updir) {
                                if (isset($matches[1])) {
                                    // undo preg_quote for the matched string
                                    $updir = str_replace('\\.', '.', $matches[1]);
                                }

                                return '';
                            },
                            $path
                        );
                        if (empty($installPath)) {
                            $installPath = strtr(getcwd(), '\\', '/');
                        }

                        $resolvedPath = realpath($installPath . '/' . $updir);
                        $autoloads[] = preg_quote(strtr($resolvedPath, '\\', '/')) . '/' . $path;
                        continue;
                    }

                    $relativePath = empty($installPath) ? (empty($path) ? '.' : $path) : $installPath.'/'.$path;

                    if ($type === 'files') {
                        $autoloads[$this->getFileIdentifier($package, $path)] = $relativePath;
                        continue;
                    } elseif ($type === 'classmap') {
                        $autoloads[] = $relativePath;
                        continue;
                    }

                    $autoloads[$namespace][] = $relativePath;
                }
            }
        }

        return $autoloads;
    }

    protected function getFileIdentifier(PackageInterface $package, $path)
    {
        return md5($package->getName() . ':' . $path);
    }

    /**
     * Filters out dev-dependencies when not in dev-mode
     *
     * @param  array            $packageMap
     * @param  PackageInterface $mainPackage
     * @return array
     */
    protected function filterPackageMap(array $packageMap, PackageInterface $mainPackage)
    {
        if ($this->devMode === true) {
            return $packageMap;
        }

        $packages = array();
        $include = array();

        foreach ($packageMap as $item) {
            $package = $item[0];
            $name = $package->getName();
            $packages[$name] = $package;
        }

        $add = function (PackageInterface $package) use (&$add, $packages, &$include) {
            foreach ($package->getRequires() as $link) {
                $target = $link->getTarget();
                if (!isset($include[$target])) {
                    $include[$target] = true;
                    if (isset($packages[$target])) {
                        $add($packages[$target]);
                    }
                }
            }
        };
        $add($mainPackage);

        return array_filter(
            $packageMap,
            function ($item) use ($include) {
                $package = $item[0];
                $name = $package->getName();

                return isset($include[$name]);
            }
        );
    }

    /**
     * Sorts packages by dependency weight
     *
     * Packages of equal weight retain the original order
     *
     * @param  array $packageMap
     * @return array
     */
    protected function sortPackageMap(array $packageMap)
    {
        $packages = array();
        $paths = array();
        $usageList = array();

        foreach ($packageMap as $item) {
            list($package, $path) = $item;
            $name = $package->getName();
            $packages[$name] = $package;
            $paths[$name] = $path;

            foreach (array_merge($package->getRequires(), $package->getDevRequires()) as $link) {
                $target = $link->getTarget();
                $usageList[$target][] = $name;
            }
        }

        $computing = array();
        $computed = array();
        $computeImportance = function ($name) use (&$computeImportance, &$computing, &$computed, $usageList) {
            // reusing computed importance
            if (isset($computed[$name])) {
                return $computed[$name];
            }

            // canceling circular dependency
            if (isset($computing[$name])) {
                return 0;
            }

            $computing[$name] = true;
            $weight = 0;

            if (isset($usageList[$name])) {
                foreach ($usageList[$name] as $user) {
                    $weight -= 1 - $computeImportance($user);
                }
            }

            unset($computing[$name]);
            $computed[$name] = $weight;

            return $weight;
        };

        $weightList = array();

        foreach ($packages as $name => $package) {
            $weight = $computeImportance($name);
            $weightList[$name] = $weight;
        }

        $stable_sort = function (&$array) {
            static $transform, $restore;

            $i = 0;

            if (!$transform) {
                $transform = function (&$v, $k) use (&$i) {
                    $v = array($v, ++$i, $k, $v);
                };

                $restore = function (&$v, $k) {
                    $v = $v[3];
                };
            }

            array_walk($array, $transform);
            asort($array);
            array_walk($array, $restore);
        };

        $stable_sort($weightList);

        $sortedPackageMap = array();

        foreach (array_keys($weightList) as $name) {
            $sortedPackageMap[] = array($packages[$name], $paths[$name]);
        }

        return $sortedPackageMap;
    }

    /**
     * Copy file using stream_copy_to_stream to work around https://bugs.php.net/bug.php?id=6463
     *
     * @param string $source
     * @param string $target
     */
    protected function safeCopy($source, $target)
    {
        $source = fopen($source, 'r');
        $target = fopen($target, 'w+');

        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
    }
}