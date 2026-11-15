<?php

namespace App\Controller\Admin;

use App\Entity\ScreenshotOriginal;
use App\Screenshot\ScreenshotStorage;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Asset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @extends AbstractCrudController<ScreenshotOriginal>
 */
final class ScreenshotOriginalCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ScreenshotOriginal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Original 32:9')
            ->setEntityLabelInPlural('Originaux 32:9')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (ScreenshotOriginal $original): string => $original->getFile()->getOriginalName())
            ->setPageTitle(Crud::PAGE_INDEX, 'Originaux 32:9')
            ->setHelp(Crud::PAGE_INDEX, 'Captures 32:9 non retouchées. Elles ne sont jamais publiées seules : on les rattache à leur final (même nom de fichier, ou à la main depuis la fiche du screenshot). Un original sans final attend que celui-ci soit importé.')
            ->setDefaultSort(['takenAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['file.originalName', 'matchKey', 'finals.title'])
            ->setPaginatorPageSize(30)
            ->setDefaultRowAction(Action::DETAIL)
            ->overrideTemplate('crud/detail', 'admin/original/detail.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('path', 'Aperçu')
            ->setTemplatePath('admin/field/original_thumbnail.html.twig')
            ->addJsFiles(Asset::fromEasyAdminAssetPackage('field-image.js'))
            ->onlyOnIndex()
            ->setSortable(false);
        yield TextField::new('file.originalName', 'Fichier')->hideOnForm();
        yield TextField::new('file.dimensions', 'Dimensions')->hideOnForm();
        yield AssociationField::new('finals', 'Finals')->hideOnForm()->setTemplatePath('admin/field/finals.html.twig')->setSortable(false);
        yield DateTimeField::new('takenAt', 'Prise le');
        yield BooleanField::new('downloadable', 'Téléchargeable')->renderAsSwitch()->setHelp('Propose le téléchargement « original 32:9 » sur le site (si l’option est activée globalement).');
        yield Field::new('file.size', 'Poids')->setTemplatePath('admin/field/file_size.html.twig')->onlyOnDetail();
        yield TextField::new('file.mimeType', 'Format')->onlyOnDetail();
        yield TextField::new('matchKey', 'Clé de correspondance')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Importé le')->hideOnForm();
        yield TextField::new('createdBy', 'Importé par')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('downloadable', 'Téléchargeable'))
            ->add(DateTimeFilter::new('takenAt', 'Prise le'))
            ->add(DateTimeFilter::new('createdAt', 'Importé le'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $view = Action::new('view', 'Voir le fichier', 'fa fa-up-right-from-square')
            ->linkToCrudAction('view')
            ->setHtmlAttributes(['target' => '_blank']);
        $upload = Action::new('upload', 'Importer des originaux', 'fa fa-cloud-arrow-up')
            ->linkToRoute('APP_ADMIN_screenshot_upload_index')
            ->createAsGlobalAction()
            ->asPrimaryAction();

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $view);
    }

    #[AdminRoute('/{id}/view', name: 'view', options: ['methods' => ['GET']])]
    public function view(ScreenshotOriginal $original, ScreenshotStorage $storage): BinaryFileResponse
    {
        return $this->file($storage->absolutePath($original), $original->getFile()->getOriginalName(), ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
