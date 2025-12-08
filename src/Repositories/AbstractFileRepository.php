<?php

namespace Mita\UranusHttpServer\Repositories;

use Illuminate\Database\Eloquent\Model;
use Mita\UranusHttpServer\Contracts\StorableInterface;
use Mita\UranusHttpServer\Exceptions\FileValidationException;
use Psr\Http\Message\UploadedFileInterface;

abstract class AbstractFileRepository extends AbstractRepository
{
    /**
     * Validate file trước khi upload
     */
    protected function validateFile(UploadedFileInterface $file, array $rules): void
    {
        if (empty($rules)) {
            return;
        }

        $errors = [];
        
        // Validate file size
        if (isset($rules['max_size'])) {
            $maxSize = $rules['max_size'] * 1024 * 1024;
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
            throw new FileValidationException($errors);
        }
    }

    /**
     * Upload và lưu file mới
     */
    public function createWithFile(array $data, UploadedFileInterface $file): Model
    {
        // Lấy thông tin từ file
        $fileData = [
            'mime_type' => $file->getClientMediaType(),
            'size' => $file->getSize(),
            'name' => $file->getClientFilename(),
            'path' => null,
        ];

        /** @var StorableInterface */
        $model = $this->create(array_merge($fileData, $data));
        
        try {
            $this->validateFile($file, $model->getValidationRules());
            $path = $model->storeFile($file);
            return $this->update($model, ['path' => $path]);
        } catch (\Throwable $e) {
            $this->delete($model);
            throw $e;
        }
    }

    /**
     * Cập nhật với file mới
     */
    public function updateWithFile($model, array $data, ?UploadedFileInterface $file = null): Model
    {
        /** @var StorableInterface */
        $storable = is_string($model) ? $this->find($model) : $model;
        
        if (!$storable) {
            throw new \RuntimeException('Model not found');
        }

        if ($file) {
            // Lấy thông tin từ file mới
            $fileData = [
                'mime_type' => $file->getClientMediaType(),
                'size' => $file->getSize(),
                'name' => $file->getClientFilename(),
            ];
            $data = array_merge($fileData, $data);

            $oldPath = $storable->path;
            try {
                $this->validateFile($file, $storable->getValidationRules());
                $path = $storable->storeFile($file);
                $data['path'] = $path;
                
                if ($oldPath) {
                    $storable->deleteFile($oldPath);
                }
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        return parent::update($storable, $data);
    }

    /**
     * Override delete để xóa cả file
     */
    public function delete($model): bool
    {
        if (is_string($model)) {
            $model = $this->find($model);
        }

        if (!$model) {
            return false;
        }

        /** @var StorableInterface */
        $storable = $model;
        $storable->beforeDelete();
        
        return parent::delete($model);
    }

    /**
     * Xóa nhiều files
     */
    public function deleteMany(array $ids): bool
    {
        try {
            foreach ($ids as $id) {
                $this->delete($id);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}