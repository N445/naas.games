<?php

namespace App\Controller\Admin;

use App\Entity\Game;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Game>
 */
final class GameCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Game::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Jeu')
            ->setEntityLabelInPlural('Jeux')
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextField::new('slug', 'Identifiant')->hideOnForm();
        yield TextareaField::new('aiContext', 'Contexte pour l\'IA')
            ->hideOnIndex()
            ->setNumOfRows(18)
            ->setHelp('Ce que l\'IA doit savoir pour reconnaître le jeu : vaisseaux, machines, personnages, lieux, factions, style visuel. Une liste par ligne suffit.');
    }
}
