<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un utilisateur')]
final readonly class CreateUserCommand
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Identifiant de connexion')] ?string $username = null,
    ): int {
        $username ??= $io->ask('Identifiant', validator: $this->validateUsername(...));
        $plainPassword = $io->askHidden('Mot de passe', $this->validatePassword(...));
        $role = Role::from($io->choice('Rôle', array_column(Role::cases(), 'value'), Role::User->value));

        $user = new User($this->validateUsername($username));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->grant($role);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur "%s" créé avec le rôle %s.', $username, $role->value));

        return Command::SUCCESS;
    }

    private function validateUsername(?string $username): string
    {
        if (null === $username || '' === trim($username)) {
            throw new \RuntimeException('L\'identifiant ne peut pas être vide.');
        }

        if ($this->userRepository->exists($username)) {
            throw new \RuntimeException(sprintf('L\'utilisateur "%s" existe déjà.', $username));
        }

        return $username;
    }

    private function validatePassword(?string $password): string
    {
        if (null === $password || strlen($password) < 8) {
            throw new \RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        return $password;
    }
}
