<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Security\Role;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('username', 'Identifiant');
        yield ChoiceField::new('roles', 'Rôles')
            ->setChoices(array_combine(array_column(Role::cases(), 'name'), array_column(Role::cases(), 'value')))
            ->allowMultipleChoices()
            ->renderAsBadges();
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield TextField::new('createdBy', 'Créé par')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
        yield TextField::new('updatedBy', 'Modifié par')->hideOnForm();
    }
}
