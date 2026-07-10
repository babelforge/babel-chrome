#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Writes the module release metadata files from module manifests and current zip artifacts.
 */
final readonly class ModuleReleaseMetadataWriter
{
    private string $modulesDirectory;

    private string $zipDirectory;

    /**
     * Creates the release metadata writer.
     *
     * @param string $projectDirectory The meta workspace directory.
     */
    public function __construct(private string $projectDirectory)
    {
        $this->modulesDirectory = $this->projectDirectory.'/modules';
        $this->zipDirectory = $this->projectDirectory.'/zip';
    }

    /**
     * Writes SHA256SUMS and modules-release-manifest.json for every module.
     *
     * @throws RuntimeException When a manifest or zip artifact is invalid.
     */
    public function write(): void
    {
        $modules = $this->releaseModules();

        if (false === is_dir($this->zipDirectory) && false === mkdir($this->zipDirectory, 0777, true) && false === is_dir($this->zipDirectory)) {
            throw new RuntimeException(sprintf('Unable to create zip directory: %s', $this->zipDirectory));
        }

        $this->writeChecksums($modules);
        $this->writeManifest($modules);
    }

    /**
     * Builds release module entries from every module manifest.
     *
     * @return list<array{id: string, name: string, version: string, zip: string, sha256: string, source: string, filemtime: int, size: int}>
     *
     * @throws RuntimeException When a module manifest or zip artifact is invalid.
     */
    private function releaseModules(): array
    {
        $modules = [];

        foreach ($this->moduleDirectories() as $moduleDirectory) {
            $manifestPath = $moduleDirectory.'/manifest.json';
            $manifest = $this->manifest($manifestPath);
            $moduleIdentifier = $this->stringManifestValue($manifest, 'id', $manifestPath);
            $moduleVersion = $this->stringManifestValue($manifest, 'version', $manifestPath);
            $manifestName = $manifest['name'] ?? null;
            $moduleName = is_string($manifestName) && '' !== $manifestName ? $manifestName : $moduleIdentifier;
            $zipName = sprintf('%s-%s.zip', $moduleIdentifier, $moduleVersion);
            $zipPath = $this->zipDirectory.'/'.$zipName;

            if (false === is_file($zipPath)) {
                throw new RuntimeException(sprintf('Missing module zip: %s', $zipPath));
            }

            $checksum = hash_file('sha256', $zipPath);
            $filemtime = filemtime($zipPath);
            $size = filesize($zipPath);

            if (false === $checksum || false === $filemtime || false === $size) {
                throw new RuntimeException(sprintf('Unable to inspect module zip: %s', $zipPath));
            }

            $modules[] = [
                'id' => $moduleIdentifier,
                'name' => $moduleName,
                'version' => $moduleVersion,
                'zip' => $zipName,
                'sha256' => $checksum,
                'source' => $this->relativePath($moduleDirectory),
                'filemtime' => $filemtime,
                'size' => $size,
            ];
        }

        usort(
            $modules,
            static fn (array $left, array $right): int => strcmp($left['id'], $right['id']),
        );

        return $modules;
    }

    /**
     * Returns the module source directories that contain a manifest.
     *
     * @return list<string>
     */
    private function moduleDirectories(): array
    {
        $manifestPaths = glob($this->modulesDirectory.'/*/manifest.json') ?: [];
        $moduleDirectories = [];

        foreach ($manifestPaths as $manifestPath) {
            $moduleDirectories[] = dirname($manifestPath);
        }

        sort($moduleDirectories);

        return array_values($moduleDirectories);
    }

    /**
     * Reads and validates one module manifest.
     *
     * @param string $manifestPath The manifest path.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the manifest cannot be decoded.
     */
    private function manifest(string $manifestPath): array
    {
        $contents = file_get_contents($manifestPath);

        if (false === $contents) {
            throw new RuntimeException(sprintf('Unable to read module manifest: %s', $manifestPath));
        }

        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (false === is_array($manifest)) {
            throw new RuntimeException(sprintf('Module manifest is not a JSON object: %s', $manifestPath));
        }

        return $manifest;
    }

    /**
     * Returns a required string manifest value.
     *
     * @param array<string, mixed> $manifest The decoded manifest.
     * @param string              $key      The manifest key.
     * @param string              $path     The manifest path used in diagnostics.
     *
     * @throws RuntimeException When the value is missing or empty.
     */
    private function stringManifestValue(array $manifest, string $key, string $path): string
    {
        $value = $manifest[$key] ?? null;

        if (false === is_string($value) || '' === $value) {
            throw new RuntimeException(sprintf('Invalid module manifest value "%s" in %s', $key, $path));
        }

        return $value;
    }

    /**
     * Writes the checksum file.
     *
     * @param list<array{id: string, name: string, version: string, zip: string, sha256: string, source: string, filemtime: int, size: int}> $modules The release modules.
     *
     * @throws RuntimeException When the checksum file cannot be written.
     */
    private function writeChecksums(array $modules): void
    {
        $lines = [];

        foreach ($modules as $module) {
            $lines[] = sprintf('%s  %s', $module['sha256'], $module['zip']);
        }

        $checksumPath = $this->zipDirectory.'/SHA256SUMS';
        if (false === file_put_contents($checksumPath, implode("\n", $lines)."\n")) {
            throw new RuntimeException(sprintf('Unable to write checksum file: %s', $checksumPath));
        }
    }

    /**
     * Writes the module release manifest.
     *
     * @param list<array{id: string, name: string, version: string, zip: string, sha256: string, source: string, filemtime: int, size: int}> $modules The release modules.
     *
     * @throws RuntimeException When the release manifest cannot be encoded or written.
     */
    private function writeManifest(array $modules): void
    {
        $release = [
            'generatedAt' => date(DATE_ATOM),
            'modules' => $modules,
        ];

        $encoded = json_encode($release, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $manifestPath = $this->zipDirectory.'/modules-release-manifest.json';
        if (false === file_put_contents($manifestPath, $encoded."\n")) {
            throw new RuntimeException(sprintf('Unable to write release manifest: %s', $manifestPath));
        }
    }

    /**
     * Returns a workspace-relative path when possible.
     *
     * @param string $path The absolute path.
     */
    private function relativePath(string $path): string
    {
        $prefix = rtrim($this->projectDirectory, '/').'/';

        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }
}

try {
    $projectDirectory = dirname(__DIR__);
    (new ModuleReleaseMetadataWriter($projectDirectory))->write();

    echo "Updated module release metadata:\n";
    echo sprintf("  %s\n", $projectDirectory.'/zip/SHA256SUMS');
    echo sprintf("  %s\n", $projectDirectory.'/zip/modules-release-manifest.json');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
