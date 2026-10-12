<?php

namespace App\Controller\Admin;

use App\Entity\Link;
use App\Field\IconField;
use App\Repository\LinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Link>
 */
final class LinkCrudController extends AbstractCrudController
{
    public const string SORT_TOKEN = 'link-sort';

    private const array ICON_SETS = [
        'fa7-brands' => 'Font Awesome · marques',
        'fa7-solid' => 'Font Awesome · pleines',
        'simple-icons' => 'Simple Icons (logos)',
        'mdi' => 'Material Design',
    ];

    public function __construct(private readonly LinkRepository $links)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Link::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lien')
            ->setEntityLabelInPlural('Liens')
            ->setDefaultSort(['position' => 'ASC'])
            ->setPaginatorPageSize(100)
            ->setHelp(Crud::PAGE_INDEX, 'Glissez les lignes par la poignée pour changer l\'ordre d\'affichage sur la page /links.')
            ->overrideTemplate('crud/index', 'admin/link/index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $preview = Action::new('preview', 'Voir la page', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('APP_LINKS')
            ->setHtmlAttributes(['target' => '_blank'])
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, $preview);
    }

    public function createEntity(string $entityFqcn): Link
    {
        return (new Link())->moveTo($this->links->nextPosition());
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('position', false)->onlyOnIndex()->setSortable(false)->setTemplatePath('admin/field/drag_handle.html.twig');
        yield Field::new('title', 'Lien')->onlyOnIndex()->setSortable(false)->setTemplatePath('admin/field/link_preview.html.twig');
        yield UrlField::new('url', 'URL')->onlyOnIndex()->setSortable(false);
        yield BooleanField::new('enabled', 'Visible')->onlyOnIndex()->renderAsSwitch()->setSortable(false);

        yield FormField::addColumn(8);
        yield FormField::addFieldset('Contenu', 'fa fa-pen');
        yield IconField::new('icon', 'Icône')->setSets(self::ICON_SETS)->hideOnIndex();
        yield TextField::new('title', 'Titre')->hideOnIndex();
        yield TextField::new('subtitle', 'Sous-titre')->hideOnIndex()->setHelp('Ex. : « @naas.bsky.social » ou « Mes screenshots ».');
        yield FormField::addColumn(4);
        yield FormField::addFieldset('Lien', 'fa fa-link');
        yield ColorField::new('color', 'Couleur')->hideOnIndex()->showValue();
        yield UrlField::new('url', 'URL')->hideOnIndex();
        yield BooleanField::new('enabled', 'Visible')->hideOnIndex();
    }

    #[AdminRoute('/sort', name: 'sort', options: ['methods' => ['POST']])]
    public function sort(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid(self::SORT_TOKEN, $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $orderedIds = array_map(intval(...), $request->getPayload()->all('ids'));
        $links = $this->links->findIndexedByIds($orderedIds);
        $positions = array_map(static fn (Link $link): int => $link->getPosition(), array_values($links));
        sort($positions);

        foreach (array_values(array_filter($orderedIds, static fn (int $id): bool => isset($links[$id]))) as $rank => $id) {
            $links[$id]->moveTo($positions[$rank]);
        }
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
