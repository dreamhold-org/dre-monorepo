<?php

namespace Espo\Modules\RealEstate\Tools\Image\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\ORM\EntityManager;
use Espo\Entities\Attachment;

class GetPublicImage implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private FileStorageManager $fileStorageManager,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam("id");

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

        // Разрешаем ТОЛЬКО картинки из RealEstateProperty.images
        if (
            $attachment->get("parentType") !== "RealEstateProperty" ||
            $attachment->get("field") !== "images"
        ) {
            throw new Forbidden("Access denied.");
        }

        /** @var \Espo\Modules\RealEstate\Entities\RealEstateProperty|null $property */
        $property = $this->entityManager->getEntityById(
            "RealEstateProperty",
            $attachment->get("parentId"),
        );

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

        // Получаем stream файла
        $stream = $this->fileStorageManager->getStream($attachment);
        $fileSize = $stream->getSize() ?? $this->fileStorageManager->getSize($attachment);

        // Создаём response
        $response = new \Espo\Core\Api\ResponseWrapper(
            new \Slim\Psr7\Response()
        );

        $response->setBody($stream);
        $response->setHeader("Content-Type", $attachment->getType() ?? "application/octet-stream");
        $response->setHeader("Content-Disposition", 'inline; filename="' . $attachment->getName() . '"');
        $response->setHeader("Content-Length", (string) $fileSize);
        $response->setHeader("Cache-Control", "public, max-age=31536000, immutable");

        return $response;
    }
}
