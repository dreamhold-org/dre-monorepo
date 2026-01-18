<?php

namespace Espo\Modules\RealEstate\Tools\Image\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\Repositories\Attachment as AttachmentRepository;

use GdImage;

class GetPublicImage implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
        private FileManager $fileManager,
        private Metadata $metadata,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam("id");
        $size = $request->getQueryParam("size");

        if (!$id) {
            throw new NotFound("Image ID not provided.");
        }

        /** @var Attachment|null $attachment */
        $attachment = $this->entityManager
            ->getRDBRepository(Attachment::ENTITY_TYPE)
            ->where(["id" => $id])
            ->findOne();

        if (!$attachment) {
            throw new NotFound("Image not found.");
        }

        // Разрешаем картинки из RealEstateProperty.images и cPrimaryImage
        $allowedFields = ['images', 'cPrimaryImage'];
        $field = $attachment->get("field");

        if (!in_array($field, $allowedFields, true)) {
            throw new Forbidden("Access denied.");
        }

        /** @var \Espo\Modules\RealEstate\Entities\RealEstateProperty|null $property */
        $property = null;

        if ($attachment->get("parentType") === "RealEstateProperty" && $attachment->get("parentId")) {
            // Для attachmentMultiple (images) — parentId напрямую указывает на Property
            $property = $this->entityManager->getEntityById(
                "RealEstateProperty",
                $attachment->get("parentId"),
            );
        } elseif ($field === 'cPrimaryImage') {
            // Для image (cPrimaryImage) — ищем Property по cPrimaryImageId
            $property = $this->entityManager
                ->getRDBRepository("RealEstateProperty")
                ->where(["cPrimaryImageId" => $id])
                ->findOne();
        }

        if (!$property) {
            throw new Forbidden("Parent entity not found.");
        }

        // Только опубликованные объекты
        if ($property->get("status") !== "Listed") {
            throw new Forbidden("Property not published.");
        }

        // Проверяем существование файла в storage
        if (!$this->fileStorageManager->exists($attachment)) {
            throw new NotFound("File not found.");
        }

        $fileType = $attachment->getType() ?? "application/octet-stream";
        $fileName = $attachment->getName() ?? "image";

        // Создаём response
        $response = new \Espo\Core\Api\ResponseWrapper(
            new \Slim\Psr7\Response()
        );

        // Проверяем нужен ли resize
        $toResize = $size && in_array($fileType, $this->getResizableFileTypeList());

        if ($toResize) {
            $contents = $this->getThumbContents($attachment, $size);

            if ($contents) {
                $fileName = $size . '-' . $fileName;
                $fileSize = strlen($contents);

                $response->writeBody($contents);
                $response->setHeader("Content-Length", (string) $fileSize);
            } else {
                $toResize = false;
            }
        }

        if (!$toResize) {
            $stream = $this->fileStorageManager->getStream($attachment);
            $fileSize = $stream->getSize() ?? $this->fileStorageManager->getSize($attachment);

            $response->setBody($stream);
            $response->setHeader("Content-Length", (string) $fileSize);
        }

        $response->setHeader("Content-Type", $fileType);
        $response->setHeader("Content-Disposition", 'inline; filename="' . $fileName . '"');
        $response->setHeader("Cache-Control", "public, max-age=31536000, immutable");

        return $response;
    }

    private function getThumbContents(Attachment $attachment, string $size): ?string
    {
        $sizes = $this->getSizes();

        if (!array_key_exists($size, $sizes)) {
            throw new BadRequest("Invalid size: $size. Available: " . implode(", ", array_keys($sizes)));
        }

        $sourceId = $attachment->getSourceId();
        $cacheFilePath = "data/upload/thumbs/{$sourceId}_$size";

        // Проверяем кэш
        if ($this->fileManager->isFile($cacheFilePath)) {
            return $this->fileManager->getContents($cacheFilePath);
        }

        $filePath = $this->getAttachmentRepository()->getFilePath($attachment);

        if (!$this->fileManager->isFile($filePath)) {
            throw new NotFound("File not found on disk.");
        }

        $fileType = $attachment->getType() ?? '';
        $targetImage = $this->createThumbImage($filePath, $fileType, $size);

        if (!$targetImage) {
            return null;
        }

        ob_start();

        switch ($fileType) {
            case 'image/jpeg':
                imagejpeg($targetImage, null, 90);
                break;
            case 'image/png':
                imagepng($targetImage);
                break;
            case 'image/gif':
                imagegif($targetImage);
                break;
            case 'image/webp':
                imagewebp($targetImage);
                break;
        }

        $contents = ob_get_contents() ?: '';
        ob_end_clean();
        imagedestroy($targetImage);

        // Сохраняем в кэш
        $this->fileManager->putContents($cacheFilePath, $contents);

        return $contents;
    }

    private function createThumbImage(string $filePath, string $fileType, string $size): ?GdImage
    {
        $imageSize = @getimagesize($filePath);

        if (!is_array($imageSize)) {
            return null;
        }

        [$originalWidth, $originalHeight] = $imageSize;
        [$width, $height] = $this->getSizes()[$size];

        if ($originalWidth <= $width && $originalHeight <= $height) {
            $targetWidth = $originalWidth;
            $targetHeight = $originalHeight;
        } else {
            if ($originalWidth > $originalHeight) {
                $targetWidth = $width;
                $targetHeight = (int) ($originalHeight / ($originalWidth / $width));

                if ($targetHeight > $height) {
                    $targetHeight = $height;
                    $targetWidth = (int) ($originalWidth / ($originalHeight / $height));
                }
            } else {
                $targetHeight = $height;
                $targetWidth = (int) ($originalWidth / ($originalHeight / $height));

                if ($targetWidth > $width) {
                    $targetWidth = $width;
                    $targetHeight = (int) ($originalHeight / ($originalWidth / $width));
                }
            }
        }

        if ($targetWidth < 1 || $targetHeight < 1) {
            return null;
        }

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($targetImage === false) {
            return null;
        }

        $sourceImage = match ($fileType) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/gif' => @imagecreatefromgif($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
            default => false,
        };

        if ($sourceImage === false) {
            return null;
        }

        // Для PNG сохраняем прозрачность
        if ($fileType === 'image/png') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight, $originalWidth, $originalHeight
        );

        return $targetImage;
    }

    /**
     * @return array<string, array{int, int}>
     */
    private function getSizes(): array
    {
        return $this->metadata->get(['app', 'image', 'sizes']) ?? [
            'small' => [64, 64],
            'medium' => [128, 128],
            'large' => [256, 256],
            'x-large' => [512, 512],
            'xx-large' => [864, 864],
        ];
    }

    /**
     * @return string[]
     */
    private function getResizableFileTypeList(): array
    {
        return $this->metadata->get(['app', 'image', 'resizableFileTypeList']) ?? [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
    }

    private function getAttachmentRepository(): AttachmentRepository
    {
        /** @var AttachmentRepository */
        return $this->entityManager->getRepository(Attachment::ENTITY_TYPE);
    }
}
