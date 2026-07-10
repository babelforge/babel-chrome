#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reports that a module cannot be packaged for installation.
 */
final class ModuleShippingException extends \RuntimeException
{
}

/**
 * Defines supported BabelChrome module runtime type names.
 */
final class ModuleRuntimeType
{
    public const STATIC_WEB = 'static-web';

    public const PROCESS_WEB = 'process-web';

    public const PROCESS_RUNTIME = 'process-runtime';

    /**
     * Returns whether the runtime type is supported by the native host contract.
     *
     * @param string $runtimeType the runtime type
     *
     * @return bool true when the runtime type is supported
     */
    public static function isSupported(string $runtimeType): bool
    {
        return in_array($runtimeType, [
            self::STATIC_WEB,
            self::PROCESS_WEB,
            self::PROCESS_RUNTIME,
        ], true);
    }

    /**
     * Returns whether the runtime type is static-web.
     *
     * @param string $runtimeType the runtime type
     *
     * @return bool true when the runtime type is static-web
     */
    public static function isStaticWeb(string $runtimeType): bool
    {
        return self::STATIC_WEB === $runtimeType;
    }

    /**
     * Returns whether the runtime type is process-web.
     *
     * @param string $runtimeType the runtime type
     *
     * @return bool true when the runtime type is process-web
     */
    public static function isProcessWeb(string $runtimeType): bool
    {
        return self::PROCESS_WEB === $runtimeType;
    }

    /**
     * Returns whether the runtime type is process-runtime.
     *
     * @param string $runtimeType the runtime type
     *
     * @return bool true when the runtime type is process-runtime
     */
    public static function isProcessRuntime(string $runtimeType): bool
    {
        return self::PROCESS_RUNTIME === $runtimeType;
    }
}

/**
 * Packages a BabelChrome module into an installable production zip.
 */
final class ModulePackageShipper
{
    /**
     * @var list<string>
     */
    private const EXCLUDED_NAMES = [
        '.DS_Store',
        '.git',
        '.gitignore',
        '.idea',
        '.php-cs-fixer.cache',
        '.phpunit.cache',
        '.phpunit.result.cache',
        'ai',
        'build',
        'coverage',
        'node_modules',
        'tests',
        'var',
    ];

    /**
     * @var list<string>
     */
    private const EXCLUDED_ROOT_PATHS = [
        '.php-cs-fixer.dist.php',
        'assets',
        'bin',
        'importmap.php',
        'phpstan.neon',
        'phpunit.xml.dist',
    ];

    /**
     * @var list<string>
     */
    private const PROCESS_RUNTIME_EXCLUDED_ROOT_PATHS = [
        '.php-cs-fixer.dist.php',
        'phpstan.neon',
        'phpunit.xml.dist',
    ];

    /**
     * Packages a module directory into a zip archive.
     *
     * @param string      $sourceDirectory the module source directory
     * @param string|null $targetPath      the optional target zip path
     *
     * @return string the generated zip path
     *
     * @throws ModuleShippingException when the module cannot be packaged
     */
    public function ship(string $sourceDirectory, ?string $targetPath = null): string
    {
        $resolvedSourceDirectory = $this->absolutePath($sourceDirectory);
        if (null === $resolvedSourceDirectory || !is_dir($resolvedSourceDirectory)) {
            throw new ModuleShippingException('Module source directory not found.');
        }

        $manifestPath = $resolvedSourceDirectory.'/manifest.json';
        if (!is_file($manifestPath)) {
            throw new ModuleShippingException('Missing manifest.json.');
        }

        $manifest = $this->manifest($manifestPath);
        if (is_file($resolvedSourceDirectory.'/composer.json') && !is_dir($resolvedSourceDirectory.'/vendor')) {
            throw new ModuleShippingException('Missing module vendor directory. Run composer install --no-dev inside the module before shipping.');
        }

        $this->validateRuntimeLayout($resolvedSourceDirectory, $manifest);

        $resolvedTargetPath = $targetPath ?? $this->defaultTargetPath($resolvedSourceDirectory, $manifest);
        $this->ensureTargetDirectory($resolvedTargetPath);
        $this->writeZip($resolvedSourceDirectory, $resolvedTargetPath, $manifest['runtimeType']);

        return $resolvedTargetPath;
    }

    /**
     * Resolves an absolute path.
     *
     * @param string $path the input path
     *
     * @return string|null the absolute path when resolvable
     */
    private function absolutePath(string $path): ?string
    {
        $resolved = realpath($path);

        return false === $resolved ? null : $resolved;
    }

    /**
     * Reads and validates a module manifest.
     *
     * @param string $manifestPath the manifest path
     *
     * @return array{id: string, name: string, version: string, runtimeType: string, documentRoot: string, indexFile: string, processWebCommand: string, processRuntimeCommand: string} the decoded manifest
     *
     * @throws ModuleShippingException when the manifest cannot be read
     */
    private function manifest(string $manifestPath): array
    {
        $content = file_get_contents($manifestPath);
        if (false === $content) {
            throw new ModuleShippingException('Unable to read manifest.json.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new ModuleShippingException('Unable to decode manifest.json.');
        }

        $manifestData = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $manifestData[$key] = $value;
            }
        }

        foreach (['id', 'name', 'version'] as $requiredField) {
            if (!isset($manifestData[$requiredField]) || !is_string($manifestData[$requiredField]) || '' === trim($manifestData[$requiredField])) {
                throw new ModuleShippingException(sprintf('Manifest field "%s" is required.', $requiredField));
            }
        }

        $runtimeType = $this->runtimeType($manifestData);

        return [
            'id' => $manifestData['id'],
            'name' => $manifestData['name'],
            'version' => $manifestData['version'],
            'runtimeType' => $runtimeType,
            'documentRoot' => $this->documentRoot($manifestData, $runtimeType),
            'indexFile' => $this->indexFile($manifestData, $runtimeType),
            'processWebCommand' => $this->processWebCommand($manifestData, $runtimeType),
            'processRuntimeCommand' => $this->processRuntimeCommand($manifestData, $runtimeType),
        ];
    }

    /**
     * Validates the runtime-specific module layout.
     *
     * @param string                                                                                                                                             $sourceDirectory the module source directory
     * @param array{id: string, name: string, version: string, runtimeType: string, documentRoot: string, indexFile: string, processWebCommand: string, processRuntimeCommand: string} $manifest        the module manifest
     *
     * @throws ModuleShippingException when the runtime layout is invalid
     */
    private function validateRuntimeLayout(string $sourceDirectory, array $manifest): void
    {
        if (ModuleRuntimeType::isStaticWeb($manifest['runtimeType'])) {
            $documentRoot = realpath($sourceDirectory.'/'.ltrim($manifest['documentRoot'], '/'));
            if (false === $documentRoot || !is_dir($documentRoot)) {
                throw new ModuleShippingException('Missing static web document root.');
            }

            $indexPath = realpath($documentRoot.'/'.ltrim($manifest['indexFile'], '/'));
            if (false === $indexPath || !is_file($indexPath) || !str_starts_with($indexPath, rtrim($documentRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new ModuleShippingException('Missing static web index file.');
            }

            return;
        }

        if (ModuleRuntimeType::isProcessWeb($manifest['runtimeType'])) {
            if ('' === $manifest['processWebCommand']) {
                throw new ModuleShippingException('Missing process-web runtime command.');
            }

            return;
        }

        if (ModuleRuntimeType::isProcessRuntime($manifest['runtimeType'])) {
            if ('' === $manifest['processRuntimeCommand']) {
                throw new ModuleShippingException('Missing process-runtime command.');
            }

            return;
        }

        throw new ModuleShippingException(sprintf('Unsupported runtime "%s".', $manifest['runtimeType']));
    }

    /**
     * Reads the manifest runtime type.
     *
     * @param array<string, mixed> $manifest the decoded manifest
     *
     * @return string the runtime type
     *
     * @throws ModuleShippingException when the runtime type is missing or unsupported
     */
    private function runtimeType(array $manifest): string
    {
        $runtime = $manifest['runtime'] ?? null;
        if (is_array($runtime)) {
            $type = $runtime['type'] ?? null;
            $runtimeType = is_string($type) ? trim($type) : '';
        } else {
            $runtimeType = is_string($runtime) ? trim($runtime) : '';
        }

        if ('' === $runtimeType) {
            throw new ModuleShippingException('Manifest field "runtime.type" is required.');
        }

        if (!ModuleRuntimeType::isSupported($runtimeType)) {
            throw new ModuleShippingException(sprintf('Unsupported runtime "%s". Rebuild the module with static-web, process-web, or process-runtime.', $runtimeType));
        }

        return $runtimeType;
    }

    /**
     * Reads the static web document root.
     *
     * @param array<string, mixed> $manifest    the decoded manifest
     * @param string               $runtimeType the module runtime type
     *
     * @return string the static web document root
     */
    private function documentRoot(array $manifest, string $runtimeType): string
    {
        if (!ModuleRuntimeType::isStaticWeb($runtimeType)) {
            return '';
        }

        $runtime = $manifest['runtime'] ?? null;
        if (!is_array($runtime)) {
            return 'public';
        }

        $documentRoot = $runtime['documentRoot'] ?? $runtime['document-root'] ?? null;

        return is_string($documentRoot) && '' !== trim($documentRoot) ? trim($documentRoot) : 'public';
    }

    /**
     * Reads the static web index file.
     *
     * @param array<string, mixed> $manifest    the decoded manifest
     * @param string               $runtimeType the module runtime type
     *
     * @return string the static web index file
     */
    private function indexFile(array $manifest, string $runtimeType): string
    {
        if (!ModuleRuntimeType::isStaticWeb($runtimeType)) {
            return '';
        }

        $runtime = $manifest['runtime'] ?? null;
        if (!is_array($runtime)) {
            return 'index.html';
        }

        $indexFile = $runtime['index'] ?? $runtime['indexFile'] ?? null;

        return is_string($indexFile) && '' !== trim($indexFile) ? trim($indexFile) : 'index.html';
    }

    /**
     * Reads the process web command.
     *
     * @param array<string, mixed> $manifest    the decoded manifest
     * @param string               $runtimeType the module runtime type
     *
     * @return string the process web command
     */
    private function processWebCommand(array $manifest, string $runtimeType): string
    {
        if (!ModuleRuntimeType::isProcessWeb($runtimeType)) {
            return '';
        }

        $runtime = $manifest['runtime'] ?? null;
        if (!is_array($runtime)) {
            return '';
        }

        $command = $runtime['command'] ?? null;

        return is_string($command) ? trim($command) : '';
    }

    /**
     * Reads the process runtime command.
     *
     * @param array<string, mixed> $manifest    the decoded manifest
     * @param string               $runtimeType the module runtime type
     *
     * @return string the process runtime command
     */
    private function processRuntimeCommand(array $manifest, string $runtimeType): string
    {
        if (!ModuleRuntimeType::isProcessRuntime($runtimeType)) {
            return '';
        }

        $runtime = $manifest['runtime'] ?? null;
        if (!is_array($runtime)) {
            return '';
        }

        $command = $runtime['command'] ?? null;

        return is_string($command) ? trim($command) : '';
    }

    /**
     * Builds the default target path.
     *
     * @param string                                                                                                                                             $sourceDirectory the module source directory
     * @param array{id: string, name: string, version: string, runtimeType: string, documentRoot: string, indexFile: string, processWebCommand: string, processRuntimeCommand: string} $manifest        the module manifest
     *
     * @return string the target zip path
     */
    private function defaultTargetPath(string $sourceDirectory, array $manifest): string
    {
        return dirname($sourceDirectory).'/'.$manifest['id'].'-'.$manifest['version'].'.zip';
    }

    /**
     * Ensures the target directory exists.
     *
     * @param string $targetPath the target zip path
     *
     * @throws ModuleShippingException when the target directory cannot be created
     */
    private function ensureTargetDirectory(string $targetPath): void
    {
        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0o775, true) && !is_dir($targetDirectory)) {
            throw new ModuleShippingException('Unable to create target directory.');
        }
    }

    /**
     * Writes the package zip archive.
     *
     * @param string $sourceDirectory the module source directory
     * @param string $targetPath      the target zip path
     * @param string $runtimeType     the module runtime type
     *
     * @throws ModuleShippingException when the zip cannot be written
     */
    private function writeZip(string $sourceDirectory, string $targetPath, string $runtimeType): void
    {
        if (!class_exists('ZipArchive')) {
            throw new ModuleShippingException('The PHP Zip extension is required.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($targetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new ModuleShippingException('Unable to open target zip.');
        }

        foreach ($this->files($sourceDirectory, $runtimeType) as $filePath) {
            $relativePath = substr($filePath, strlen($sourceDirectory) + 1);
            if ('' === $relativePath) {
                continue;
            }

            $zip->addFile($filePath, str_replace(DIRECTORY_SEPARATOR, '/', $relativePath));
        }

        $zip->close();
    }

    /**
     * Returns package files.
     *
     * @param string $sourceDirectory the module source directory
     * @param string $runtimeType     the module runtime type
     *
     * @return \Generator<int, string> the package files
     */
    private function files(string $sourceDirectory, string $runtimeType): \Generator
    {
        $directory = new \RecursiveDirectoryIterator(
            $sourceDirectory,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO,
        );
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $path = $fileInfo->getPathname();
            if ($this->isExcluded($sourceDirectory, $path, $runtimeType)) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * Returns whether a file should be excluded from the package.
     *
     * @param string $sourceDirectory the module source directory
     * @param string $path            the candidate path
     * @param string $runtimeType     the module runtime type
     *
     * @return bool true when the file should be excluded
     */
    private function isExcluded(string $sourceDirectory, string $path, string $runtimeType): bool
    {
        $relativePath = substr($path, strlen($sourceDirectory) + 1);
        $excludedRootPaths = $this->excludedRootPaths($runtimeType);

        foreach ($excludedRootPaths as $excludedRootPath) {
            if ($relativePath === $excludedRootPath || str_starts_with($relativePath, $excludedRootPath.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        $excludedNames = $this->excludedNames($runtimeType);
        foreach ($parts as $part) {
            if (in_array($part, $excludedNames, true)) {
                return true;
            }
        }

        return str_ends_with($relativePath, '.zip');
    }

    /**
     * Returns excluded root paths for one runtime.
     *
     * @param string $runtimeType the module runtime type
     *
     * @return list<string> the excluded root paths
     */
    private function excludedRootPaths(string $runtimeType): array
    {
        if (!$this->isProcessRuntimeFamily($runtimeType)) {
            return self::EXCLUDED_ROOT_PATHS;
        }

        return self::PROCESS_RUNTIME_EXCLUDED_ROOT_PATHS;
    }

    /**
     * Returns excluded path names for one runtime.
     *
     * @param string $runtimeType the module runtime type
     *
     * @return list<string> the excluded names
     */
    private function excludedNames(string $runtimeType): array
    {
        if (!$this->isProcessRuntimeFamily($runtimeType)) {
            return self::EXCLUDED_NAMES;
        }

        return array_values(array_filter(
            self::EXCLUDED_NAMES,
            static fn (string $name): bool => !in_array($name, ['node_modules', 'var'], true),
        ));
    }

    /**
     * Returns whether the runtime is backed by a module-owned process.
     *
     * @param string $runtimeType the module runtime type
     *
     * @return bool true when the runtime owns process files
     */
    private function isProcessRuntimeFamily(string $runtimeType): bool
    {
        return ModuleRuntimeType::isProcessWeb($runtimeType) || ModuleRuntimeType::isProcessRuntime($runtimeType);
    }
}

/**
 * Prints command usage.
 */
function babelchrome_shipper_usage(): void
{
    fwrite(STDOUT, <<<'USAGE'
Usage:
  php tools/ship-module.php <module-directory> [target.zip]

Every module directory must contain:
  manifest.json

The supported module runtimes are:
  static-web
  process-web
  process-runtime

The produced zip excludes dev-only directories such as tests, ai, .git, build, and coverage.

USAGE);
}

if (2 > $argc || in_array($argv[1], ['--help', '-h'], true)) {
    babelchrome_shipper_usage();

    exit(0);
}

try {
    $targetPath = (new ModulePackageShipper())->ship($argv[1], $argv[2] ?? null);
    fwrite(STDOUT, $targetPath."\n");

    exit(0);
} catch (ModuleShippingException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");

    exit(65);
}
