<?php

namespace App\Controller\Admin;

use App\Entity\Ship;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Ship>
 */
final class ShipCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Ship::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Vaisseau')
            ->setEntityLabelInPlural('Vaisseaux Star Citizen')
            ->setHelp(Crud::PAGE_INDEX, 'Référence importée depuis la Ship Matrix avec « bin/console app:ships:import ». Sert de piste à la reconnaissance visuelle.')
            ->setDefaultSort(['manufacturer' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'manufacturer', 'focus'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('manufacturer')->add('size');
    }

    public function configureFields(string $pageName): iterable
    {
        yield ImageField::new('imageUrl', 'Visuel');
        yield TextField::new('name', 'Nom');
        yield TextField::new('manufacturer', 'Constructeur');
        yield TextField::new('focus', 'Rôle');
        yield TextField::new('size', 'Taille');
    }
}
