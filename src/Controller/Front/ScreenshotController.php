<?php

namespace App\Controller\Front;

use App\Entity\Screenshot;
use App\Screenshot\ScreenshotStorage;
use App\Statistics\StatisticRecorder;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ScreenshotController extends AbstractController
{
    #[Route('/screenshots/{slug}/download', name: 'APP_SCREENSHOT_DOWNLOAD', methods: ['GET'])]
    public function download(
        #[MapEntity(mapping: ['slug' => 'slug'])] Screenshot $screenshot,
        ScreenshotStorage $storage,
        StatisticRecorder $statistics,
    ): BinaryFileResponse {
        if (!$screenshot->isPublished()) {
            throw $this->createNotFoundException();
        }

        $statistics->recordDownload($screenshot);

        return $this->file($storage->absolutePath($screenshot), $screenshot->getFile()->getOriginalName(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
