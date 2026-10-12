<?php

namespace App\Controller\Admin;

use App\Repository\AlbumRepository;
use App\Repository\ScreenshotRepository;
use App\Statistics\MonthlyUploads;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '%app.admin_path%', routeName: 'APP_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ScreenshotRepository $screenshots,
        private readonly AlbumRepository $albums,
        private readonly MonthlyUploads $monthlyUploads,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'summary' => $this->screenshots->summarize(),
            'albumCount' => $this->albums->count(),
            'monthlyUploads' => $this->monthlyUploads->lastMonths(12),
            'mostViewed' => $this->screenshots->findMostViewed(5),
            'mostDownloaded' => $this->screenshots->findMostDownloaded(5),
            'latest' => $this->screenshots->findLatest(8),
            'topAlbums' => $this->albums->findMostViewed(5),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Naas Games')
            ->setFaviconPath('favicon/favicon-96x96.png');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-chart-line');

        yield MenuItem::section('Screenshots');
        yield MenuItem::linkTo(ScreenshotUploadController::class, 'Importer', 'fa fa-cloud-arrow-up')->setAction('index');
        yield MenuItem::linkTo(ScreenshotCrudController::class, 'Photothèque', 'fa fa-images');
        yield MenuItem::linkTo(AlbumCrudController::class, 'Albums', 'fa fa-book-open');
        yield MenuItem::linkTo(TagTreeController::class, 'Tags', 'fa fa-tags')->setAction('index');
        yield MenuItem::linkTo(GameCrudController::class, 'Jeux', 'fa fa-gamepad');
        yield MenuItem::linkTo(ShipCrudController::class, 'Vaisseaux SC', 'fa fa-rocket');

        yield MenuItem::section('Site');
        yield MenuItem::linkTo(HomepageController::class, 'Page d’accueil', 'fa fa-house')->setAction('index');
        yield MenuItem::linkTo(LinkCrudController::class, 'Liens', 'fa fa-link');

        yield MenuItem::section('Administration');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');
        yield MenuItem::linkToUrl('Voir le site', 'fa fa-arrow-up-right-from-square', '/')->setLinkTarget('_blank');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }
}
