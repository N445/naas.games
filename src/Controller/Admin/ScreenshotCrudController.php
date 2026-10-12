<?php

namespace App\Controller\Admin;

use App\Ai\TagScreenshot;
use App\Entity\Album;
use App\Entity\Screenshot;
use App\Repository\AlbumRepository;
use App\Repository\ScreenshotRepository;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @extends AbstractCrudController<Screenshot>
 */
final class ScreenshotCrudController extends AbstractCrudController
{
    private const string ALBUM_ASSIGNMENT_TOKEN = 'screenshot_album_assignment';

    public function __construct(
        private readonly ScreenshotRepository $screenshots,
        private readonly AlbumRepository $albums,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Screenshot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Screenshot')
            ->setEntityLabelInPlural('Photothèque')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Screenshot $screenshot): string => (string) $screenshot->getTitle())
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Screenshot $screenshot): string => sprintf('Modifier « %s »', $screenshot->getTitle()))
            ->setDefaultSort(['createdAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['title', 'description', 'slug', 'file.originalName', 'tags.name', 'albums.title'])
            ->setPaginatorPageSize(30)
            ->setDefaultRowAction(Action::EDIT)
            ->overrideTemplate('crud/edit', 'admin/screenshot/edit.html.twig')
            ->overrideTemplate('crud/detail', 'admin/screenshot/detail.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('path', 'Aperçu')->setTemplatePath('admin/field/screenshot_thumbnail.html.twig')->onlyOnIndex()->setSortable(false);

        yield FormField::addFieldset('Informations');
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug')->setTargetFieldName('title')->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex()->setNumOfRows(4);
        yield DateTimeField::new('takenAt', 'Prise le')->setHelp('Déduite de l\'EXIF ou du nom de fichier à l\'import.');

        yield FormField::addFieldset('Classement');
        yield AssociationField::new('game', 'Jeu');
        yield AssociationField::new('albums', 'Albums')->setFormTypeOption('by_reference', false)->autocomplete()->setTemplatePath('admin/field/badges.html.twig');
        yield AssociationField::new('tags', 'Tags')->setFormTypeOption('by_reference', false)->autocomplete()->setTemplatePath('admin/field/badges.html.twig');
        yield BooleanField::new('published', 'Publié')->renderAsSwitch();

        yield Field::new('palette', 'Couleurs')->setTemplatePath('admin/field/palette.html.twig')->hideOnForm()->setSortable(false);
        yield IntegerField::new('viewCount', 'Vues')->hideOnForm()->setTextAlign('right');
        yield IntegerField::new('downloadCount', 'Téléch.')->hideOnForm()->setTextAlign('right')->setHelp('Téléchargements');

        yield TextField::new('file.originalName', 'Fichier d\'origine')->onlyOnDetail();
        yield TextField::new('file.dimensions', 'Dimensions')->onlyOnDetail();
        yield Field::new('file.size', 'Poids')->setTemplatePath('admin/field/file_size.html.twig')->onlyOnDetail();
        yield TextField::new('file.mimeType', 'Format')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Importée le')->onlyOnDetail();
        yield TextField::new('createdBy', 'Importée par')->onlyOnDetail();
        yield DateTimeField::new('updatedAt', 'Modifiée le')->onlyOnDetail();
        yield DateTimeField::new('aiTaggedAt', 'Taguée par l\'IA le')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('game')
            ->add('albums')
            ->add('tags')
            ->add('published')
            ->add('takenAt')
            ->add('createdAt')
            ->add('viewCount')
            ->add('downloadCount');
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('upload', 'Importer des screenshots', 'fa fa-cloud-arrow-up')
            ->linkToRoute('APP_ADMIN_screenshot_upload_index')
            ->createAsGlobalAction()
            ->asPrimaryAction();
        $original = Action::new('original', 'Original', 'fa fa-up-right-from-square')
            ->linkToCrudAction('original')
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $original)
            ->add(Crud::PAGE_EDIT, $original)
            ->addBatchAction(Action::new('addToAlbum', 'Ajouter à un album', 'fa fa-book-open')->linkToCrudAction('addToAlbum'))
            ->addBatchAction(Action::new('publish', 'Publier', 'fa fa-eye')->linkToCrudAction('publish'))
            ->addBatchAction(Action::new('unpublish', 'Dépublier', 'fa fa-eye-slash')->linkToCrudAction('unpublish'))
            ->addBatchAction(Action::new('retag', 'Relancer le tagging IA', 'fa fa-wand-magic-sparkles')->linkToCrudAction('retag'));
    }

    #[AdminRoute('/{id}/original', name: 'original', options: ['methods' => ['GET']])]
    public function original(Screenshot $screenshot, ScreenshotStorage $storage): BinaryFileResponse
    {
        return $this->file($storage->absolutePath($screenshot), $screenshot->getFile()->getOriginalName(), ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[AdminRoute('/add-to-album', name: 'add_to_album', options: ['methods' => ['POST']])]
    public function addToAlbum(BatchActionDto $batchActionDto): Response
    {
        $this->assertBatchToken('addToAlbum', $batchActionDto);

        return $this->render('admin/screenshot/add_to_album.html.twig', [
            'screenshots' => $this->screenshots->findByIds($batchActionDto->getEntityIds()),
            'albums' => $this->albums->findAllByTitle(),
            'csrfTokenId' => self::ALBUM_ASSIGNMENT_TOKEN,
        ]);
    }

    #[AdminRoute('/assign-album', name: 'assign_album', options: ['methods' => ['POST']])]
    public function assignAlbum(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::ALBUM_ASSIGNMENT_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $album = $this->resolveAlbum($request);
        $screenshots = $this->screenshots->findByIds($request->request->all('screenshots'));
        array_walk($screenshots, static fn (Screenshot $screenshot) => $screenshot->addAlbum($album));
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%d screenshot(s) ajouté(s) à « %s ».', count($screenshots), $album->getTitle()));

        return $this->redirectToRoute('APP_ADMIN_album_detail', ['entityId' => $album->getId()]);
    }

    #[AdminRoute('/publish', name: 'publish', options: ['methods' => ['POST']])]
    public function publish(BatchActionDto $batchActionDto): Response
    {
        return $this->changePublication($batchActionDto, 'publish', true);
    }

    #[AdminRoute('/unpublish', name: 'unpublish', options: ['methods' => ['POST']])]
    public function unpublish(BatchActionDto $batchActionDto): Response
    {
        return $this->changePublication($batchActionDto, 'unpublish', false);
    }

    #[AdminRoute('/retag', name: 'retag', options: ['methods' => ['POST']])]
    public function retag(BatchActionDto $batchActionDto): Response
    {
        $this->assertBatchToken('retag', $batchActionDto);
        $screenshots = $this->screenshots->findByIds($batchActionDto->getEntityIds());
        foreach ($screenshots as $screenshot) {
            $screenshot->requestAiTagging($this->clock->now());
        }
        $this->entityManager->flush();
        array_walk($screenshots, fn (Screenshot $screenshot) => $this->bus->dispatch(new TagScreenshot((int) $screenshot->getId(), explicitlyRequested: true)));
        $this->addFlash('success', sprintf('%d screenshot(s) envoyé(s) à l\'IA. Les tags apparaîtront d\'ici quelques instants.', count($screenshots)));

        return $this->redirectToRoute('APP_ADMIN_screenshot_index');
    }

    private function changePublication(BatchActionDto $batchActionDto, string $actionName, bool $published): Response
    {
        $this->assertBatchToken($actionName, $batchActionDto);
        $screenshots = $this->screenshots->findByIds($batchActionDto->getEntityIds());
        array_walk($screenshots, static fn (Screenshot $screenshot) => $screenshot->setPublished($published));
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%d screenshot(s) %s.', count($screenshots), $published ? 'publié(s)' : 'dépublié(s)'));

        return $this->redirectToRoute('APP_ADMIN_screenshot_index');
    }

    private function resolveAlbum(Request $request): Album
    {
        $newAlbumTitle = trim($request->request->getString('new_album'));
        if ('' === $newAlbumTitle) {
            return $this->albums->find($request->request->getInt('album')) ?? throw $this->createNotFoundException('Album introuvable.');
        }

        $album = (new Album())->setTitle($newAlbumTitle);
        $this->entityManager->persist($album);

        return $album;
    }

    private function assertBatchToken(string $actionName, BatchActionDto $batchActionDto): void
    {
        if (!$this->isCsrfTokenValid('ea-batch-action-'.$actionName.'-'.Screenshot::class, $batchActionDto->getCsrfToken())) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
