<?php

namespace App\Twig\Components\Admin;

use App\Entity\Screenshot;
use App\Statistics\DailySeries;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class StatisticsChart
{
    use DefaultActionTrait;

    public const array PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '3 mois', 365 => '1 an'];

    #[LiveProp]
    public int $days = 30;

    #[LiveProp]
    public ?Screenshot $screenshot = null;

    public function __construct(private readonly DailySeries $dailySeries)
    {
    }

    #[LiveAction]
    public function showPeriod(#[LiveArg] int $days): void
    {
        $this->days = array_key_exists($days, self::PERIODS) ? $days : 30;
    }

    /**
     * @return array{labels: list<string>, views: list<int>, downloads: list<int>}
     */
    public function getSeries(): array
    {
        return $this->dailySeries->lastDays($this->days, $this->screenshot);
    }
}
