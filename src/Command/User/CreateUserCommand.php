<?php

namespace App\Command\User;


use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Créer un nouvel utilisateur avec un système interactif',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('Création d\'un nouvel utilisateur');

        // Collecter les informations de l'utilisateur
        $username = $helper->ask($input, $output, new Question('Nom d\'utilisateur : '));

        // Question pour le mot de passe avec masquage
        $passwordQuestion = new Question('Mot de passe : ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);

        // Confirmation du mot de passe
        $confirmPasswordQuestion = new Question('Confirmez le mot de passe : ');
        $confirmPasswordQuestion->setHidden(true);
        $confirmPasswordQuestion->setHiddenFallback(false);
        $confirmPassword = $helper->ask($input, $output, $confirmPasswordQuestion);

        if ($password !== $confirmPassword) {
            $io->error('Les mots de passe ne correspondent pas.');
            return Command::FAILURE;
        }

        try {
            $user = new User();
            $user->setUsername($username);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles([User::ROLE_ADMIN]);

            // Sauvegarder l'utilisateur
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('L\'utilisateur a été créé avec succès.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la création de l\'utilisateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
