<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Album>
 */
final class AlbumCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Album::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Album')
            ->setEntityLabelInPlural('Albums')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Album $album): string => (string) $album->getTitle())
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'description'])
            ->setDefaultRowAction(Action::DETAIL)
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/detail', 'admin/album/detail.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('cover', 'Couverture')->setTemplatePath('admin/field/album_cover.html.twig')->onlyOnIndex()->setSortable(false);
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug')->setTargetFieldName('title')->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex()->setNumOfRows(4);
        yield AssociationField::new('screenshots', 'Screenshots')->hideOnForm()->hideOnDetail();
        yield BooleanField::new('published', 'Publié')->renderAsSwitch();
        yield IntegerField::new('viewCount', 'Vues')->hideOnForm()->setTextAlign('right');
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield TextField::new('createdBy', 'Créé par')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('published')->add('createdAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('upload', 'Importer dans cet album', 'fa fa-cloud-arrow-up')
            ->linkToRoute('APP_ADMIN_screenshot_upload_index', static fn (Album $album): array => ['album' => $album->getId()])
            ->asPrimaryAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $upload);
    }
}
