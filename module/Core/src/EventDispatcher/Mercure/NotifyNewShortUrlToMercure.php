<?php

declare(strict_types=1);

namespace Shlinkio\Shlink\Core\EventDispatcher\Mercure;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Shlinkio\Shlink\Core\Entity\ShortUrl;
use Shlinkio\Shlink\Core\EventDispatcher\Event\ShortUrlCreated;
use Shlinkio\Shlink\Core\Mercure\MercureUpdatesGeneratorInterface;
use Symfony\Component\Mercure\HubInterface;
use Throwable;

class NotifyNewShortUrlToMercure
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly MercureUpdatesGeneratorInterface $updatesGenerator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ShortUrlCreated $shortUrlCreated): void
    {
        $shortUrlId = $shortUrlCreated->shortUrlId;
        $shortUrl = $this->em->find(ShortUrl::class, $shortUrlId);

        if ($shortUrl === null) {
            $this->logger->warning(
                'Tried to notify Mercure for new short URL with id "{shortUrlId}", but it does not exist.',
                ['shortUrlId' => $shortUrlId],
            );
            return;
        }

        try {
            $this->hub->publish($this->updatesGenerator->newShortUrlUpdate($shortUrl));
        } catch (Throwable $e) {
            $this->logger->debug('Error while trying to notify mercure hub with new short URL. {e}', [
                'e' => $e,
            ]);
        }
    }
}
