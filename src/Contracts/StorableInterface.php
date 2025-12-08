<?php

namespace Mita\UranusHttpServer\Contracts;

use Psr\Http\Message\UploadedFileInterface;

interface StorableInterface
{
    /**
     * Lưu file và trả về đường dẫn
     */
    public function storeFile(UploadedFileInterface $file): string;

    /**
     * Xóa file theo đường dẫn
     */
    public function deleteFile(string $path): bool;

    /**
     * Xử lý trước khi xóa model
     */
    public function beforeDelete(): void;

    /**
     * Xử lý sau khi xóa model
     */
    public function afterDelete(): void;

    /**
     * Xử lý trước khi lưu file
     */
    public function beforeStore(UploadedFileInterface $file): void;

    /**
     * Xử lý sau khi lưu file
     */
    public function afterStore(string $path): void;

    /**
     * Lấy các rules validation cho file
     */
    public function getValidationRules(): array;
}