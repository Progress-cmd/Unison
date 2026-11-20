<?php

namespace App\Admin\Controller;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use App\Account\Service\AccountRemover;
use App\Account\Service\PasswordLinkSender;
use App\Admin\Form\UserCreateType;
use App\Admin\Form\UserDeleteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/admin/accounts', name: 'app_admin_accounts')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/accounts.html.twig', [
            'rows' => $userRepository->findAllWithCounts(),
        ]);
    }

    #[Route('/admin/accounts/new', name: 'app_admin_accounts_new')]
    public function create(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, PasswordLinkSender $passwordLinkSender): Response
    {
        $user = new User();
        $form = $this->createForm(UserCreateType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // The column can't be empty: a random password nobody knows, until the person chooses theirs.
            $user->setPassword($hasher->hashPassword($user, bin2hex(random_bytes(32))));

            if ($form->get('admin')->getData()) {
                $user->setRoles(['ROLE_ADMIN']);
            }

            $em->persist($user);
            $em->flush();

            try {
                $passwordLinkSender->sendWelcome($user);
                $this->addFlash('success', 'Compte « ' . $user->getUsername() . ' » créé. Un e-mail est parti à ' . $user->getEmail() . ' pour choisir son mot de passe.');
            } catch (TransportExceptionInterface) {
                $this->addFlash('error', 'Compte « ' . $user->getUsername() . ' » créé, mais l\'e-mail n\'a pas pu partir. Réessaie avec « Réinitialiser le mot de passe » sur sa page.');
            }

            return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
        }

        return $this->render('admin/accounts_new.html.twig', [
            'form' => $form,
        ]);
    }

    // One account: its numbers and everything an admin can do with it.
    #[Route('/admin/accounts/{id}', name: 'app_admin_account', requirements: ['id' => '\d+'])]
    public function show(Request $request, User $user, UserRepository $userRepository, AccountRemover $accountRemover): Response
    {
        $deleteForm = $this->createForm(UserDeleteType::class, ['heir' => $this->getUser()], [
            'exclude' => $user,
            'action' => $this->generateUrl('app_admin_account', ['id' => $user->getId()]),
        ]);

        $deleteForm->handleRequest($request);

        if ($deleteForm->isSubmitted() && $deleteForm->isValid() && !$this->isSelf($user)) {
            $heir = $deleteForm->get('heir')->getData();
            $username = $user->getUsername();

            $accountRemover->remove($user, $heir);

            $this->addFlash('success', sprintf('Compte « %s » supprimé. Son contenu est passé à %s.', $username, $heir->getUsername()));

            return $this->redirectToRoute('app_admin_accounts');
        }

        return $this->render('admin/account.html.twig', [
            'user' => $user,
            'counts' => $userRepository->countsFor($user),
            'isSelf' => $this->isSelf($user),
            'deleteForm' => $deleteForm,
        ]);
    }

    #[Route('/admin/accounts/{id}/reset-password', name: 'app_admin_accounts_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, User $user, PasswordLinkSender $passwordLinkSender): Response
    {
        if (!$this->isCsrfTokenValid('account_reset_password', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $passwordLinkSender->sendReset($user);
            $this->addFlash('success', 'Lien envoyé à ' . $user->getEmail() . '.');
        } catch (TransportExceptionInterface) {
            $this->addFlash('error', 'L\'e-mail n\'a pas pu partir. Réessaie plus tard.');
        }

        return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
    }

    // Gives or takes back the admin role. The change applies at once: on the person's next
    // page, Symfony sees that their roles changed and logs them out (the "Se souvenir de moi"
    // cookie is erased too). They log back in with the new roles.
    #[Route('/admin/accounts/{id}/toggle-admin', name: 'app_admin_accounts_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('account_toggle_admin', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Never on yourself: the site must always keep an admin.
        if ($this->isSelf($user)) {
            $this->addFlash('error', 'Tu ne peux pas changer ton propre rôle.');

            return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
        }

        // getRoles() always adds ROLE_USER: only the other roles are stored.
        $roles = array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']);

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('success', $user->getUsername() . ' n\'est plus admin.');
        } else {
            $roles[] = 'ROLE_ADMIN';
            $this->addFlash('success', $user->getUsername() . ' est maintenant admin.');
        }

        $user->setRoles(array_values($roles));
        $em->flush();

        return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
    }

    // Disables or enables the account. Disabled: the UserChecker refuses the login and
    // DisabledAccountListener logs out the sessions already open. Its content stays.
    #[Route('/admin/accounts/{id}/toggle-disabled', name: 'app_admin_accounts_toggle_disabled', methods: ['POST'])]
    public function toggleDisabled(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('account_toggle_disabled', $request->getPayload()->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isSelf($user)) {
            $this->addFlash('error', 'Tu ne peux pas désactiver ton propre compte.');

            return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
        }

        if ($user->isDisabled()) {
            $user->enable();
            $this->addFlash('success', $user->getUsername() . ' peut de nouveau se connecter.');
        } else {
            $user->disable();
            $this->addFlash('success', 'Compte de ' . $user->getUsername() . ' désactivé.');
        }

        $em->flush();

        return $this->redirectToRoute('app_admin_account', ['id' => $user->getId()]);
    }

    private function isSelf(User $user): bool
    {
        return $user === $this->getUser();
    }
}
