<?php

namespace Mita\UranusHttpServer\Traits;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;
use Mita\UranusHttpServer\Configs\Config;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\UploadedFileInterface;

trait HasStorageTrait
{
    protected ?FilesystemOperator $filesystem = null;
    protected static ?ContainerInterface $container = null;

    /**
     * Validate file trước khi upload
     */
    protected function validateFile(UploadedFileInterface $file): void
    {
        $rules = $this->getValidationRules();
        if (empty($rules)) {
            return;
        }

        $errors = [];
        
        // Validate file size
        if (isset($rules['max_size'])) {
            $maxSize = $rules['max_size'] * 1024 * 1024; // Convert MB to bytes
            if ($file->getSize() > $maxSize) {
                $errors[] = sprintf('File size must not exceed %dMB', $rules['max_size']);
            }
        }

        // Validate mime type
        if (isset($rules['mime_types']) && is_array($rules['mime_types'])) {
            $mimeType = $file->getClientMediaType();
            if (!in_array($mimeType, $rules['mime_types'])) {
                $errors[] = sprintf(
                    'File type must be one of: %s', 
                    implode(', ', $rules['mime_types'])
                );
            }
        }

        // Validate file extension
        if (isset($rules['extensions']) && is_array($rules['extensions'])) {
            $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
            if (!in_array($extension, $rules['extensions'])) {
                $errors[] = sprintf(
                    'File extension must be one of: %s',
                    implode(', ', $rules['extensions'])
                );
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(PHP_EOL, $errors));
        }
    }

    /**
     * Set container instance
     */
    public static function setContainer(ContainerInterface $container): void
    {
        static::$container = $container;
    }

    /**
     * Get container instance
     */
    protected static function getContainer(): ContainerInterface
    {
        if (static::$container === null) {
            throw new \RuntimeException('Container has not been set');
        }
        return static::$container;
    }

    protected function getDefaultDisk(): string
    {
        return 'local';
    }

    protected function getDefaultPath(): string
    {
        return 'uploads/' . strtolower(class_basename($this));
    }

    public function getStoragePath(): string
    {
        return $this->storagePath ?? $this->getDefaultPath();
    }

    public function getStorageDisk(): string
    {
        return $this->storageDisk ?? $this->getDefaultDisk();
    }

    public function getValidationRules(): array
    {
        return $this->validationRules ?? [];
    }

    protected function getFilesystem(): FilesystemOperator
    {
        if ($this->filesystem === null) {
            $this->filesystem = static::getContainer()->get(FilesystemOperator::class);
        }
        return $this->filesystem;
    }

    public function storeFile(UploadedFileInterface $file, string $name = null): string
    {
        // Validate trước khi store
        $this->validateFile($file);

        $this->beforeStore($file);

        try {
            // Tạo tên file unique nếu không được chỉ định
            $filename = $name ?? $this->generateUniqueFilename($file);
            
            // Tạo đường dẫn đầy đủ
            $path = trim($this->getStoragePath(), '/') . '/' . $filename;

            // Đọc content từ PSR-7 UploadedFile
            $stream = $file->getStream();
            $content = $stream->getContents();
            
            // Lưu file sử dụng Flysystem
            $this->getFilesystem()->write($path, $content, [
                'visibility' => $this->getVisibility()
            ]);

            $this->afterStore($path);

            return $path;

        } catch (UnableToWriteFile $e) {
            throw new \RuntimeException("Không thể lưu file: " . $e->getMessage());
        }
    }

    public function deleteFile(string $path): bool
    {
        $this->beforeDelete();

        try {
            $this->getFilesystem()->delete($path);
            $this->afterDelete();
            return true;
        } catch (UnableToDeleteFile $e) {
            return false;
        }
    }

    public function getFileUrl(string $path): ?string
    {
        try {
            if (!$this->getFilesystem()->fileExists($path)) {
                return null;
            }

            $config = static::getContainer()->get(Config::class);
            $baseUrl = rtrim($config->get('base_url'), '/');
            $storagePath = 'storage';

            return "{$baseUrl}/{$storagePath}/{$path}";
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getFileMetadata(string $path): array
    {
        try {
            if (!$this->getFilesystem()->fileExists($path)) {
                return [];
            }

            $mimeType = $this->getFilesystem()->mimeType($path);
            $size = $this->getFilesystem()->fileSize($path);
            $lastModified = $this->getFilesystem()->lastModified($path);

            return [
                'mime_type' => $mimeType,
                'size' => $size,
                'last_modified' => $lastModified,
                'path' => $path
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function generateUniqueFilename(UploadedFileInterface $file): string
    {
        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    protected function getVisibility(): string
    {
        return $this->visibility ?? 'private';
    }

    // Hook methods
    public function beforeStore($file): void {}
    public function afterStore(string $path): void {}
    public function beforeDelete(): void {}
    public function afterDelete(): void {}
}