<?php

namespace App\Twig\Components;

use App\Entity\Link\Stats;
use App\Repository\Link\LinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Links
{
    use DefaultActionTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LinkRepository         $linkRepository,
    )
    {
    }

    public function getLinks(): array
    {
        return $this->linkRepository->createQueryBuilder('l')
                              ->orderBy('l.display_order', 'ASC')
                              ->getQuery()
                              ->getResult()
        ;
    }

    #[LiveAction]
    public function addStats(#[LiveArg] int $linkId, Request $request): void
    {
        if(!$link = $this->linkRepository->find($linkId)) {
            return;
        }

        $link->addStat(new Stats($request->getClientIp()));
        $this->em->flush();
    }
}
