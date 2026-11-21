<?php

namespace App\Announcement\Twig;

use App\Account\Entity\User;
use App\Announcement\Entity\Announcement;
use App\Announcement\Repository\AnnouncementRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final class AnnouncementExtension
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
        private readonly Security $security,
    ) {}

    // The announcement the logged-in account hasn't seen yet, if any (base.html.twig).
    #[AsTwigFunction('pending_announcement')]
    public function pendingAnnouncement(): ?Announcement
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->announcements->findPendingFor($user) : null;
    }
}
