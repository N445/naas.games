<?php

namespace App\Command\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:edit',
    description: 'Modifier un utilisateur existant avec un système interactif',
)]
class EditUserCommand extends Command
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

        $io->title('Modification d\'un utilisateur existant');

        // Rechercher l'utilisateur à modifier
        $userIdentifier = $helper->ask($input, $output, new Question('Nom d\'utilisateur de l\'utilisateur à modifier : '));

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['username' => $userIdentifier]);

        if (!$user) {
            $io->error('Aucun utilisateur trouvé avec cet identifiant.');
            return Command::FAILURE;
        }

        $io->success('Utilisateur trouvé : ' . $user->getUserIdentifier());

        // Demander quels champs modifier
        $fieldsQuestion = new ChoiceQuestion(
            'Quels champs souhaitez-vous modifier ? (séparés par une virgule)',
            ['password'],
            null
        );
        $fieldsQuestion->setMultiselect(true);
        $fieldsToModify = $helper->ask($input, $output, $fieldsQuestion);

        // Modification des champs sélectionnés
        foreach ($fieldsToModify as $field) {
            switch ($field) {
                case 'password':
                    $passwordQuestion = new Question('Nouveau mot de passe : ');
                    $passwordQuestion->setHidden(true);
                    $passwordQuestion->setHiddenFallback(false);
                    $newPassword = $helper->ask($input, $output, $passwordQuestion);

                    $confirmPasswordQuestion = new Question('Confirmez le nouveau mot de passe : ');
                    $confirmPasswordQuestion->setHidden(true);
                    $confirmPasswordQuestion->setHiddenFallback(false);
                    $confirmPassword = $helper->ask($input, $output, $confirmPasswordQuestion);

                    if ($newPassword !== $confirmPassword) {
                        $io->error('Les mots de passe ne correspondent pas. Le mot de passe n\'a pas été modifié.');
                    } else {
                        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
                    }
                    break;
            }
        }

        // Mise à jour de la date de modification
        $user->setUpdatedAt(new \DateTime());

        // Sauvegarder les modifications
        try {
            $this->entityManager->flush();
            $io->success('L\'utilisateur a été modifié avec succès.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la modification de l\'utilisateur : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
