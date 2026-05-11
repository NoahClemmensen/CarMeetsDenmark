<?php

namespace App\Twig;

//use App\Security\Voter\SupportBypassVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SupportExtension extends AbstractExtension
{
    public function __construct(
        //        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_support_mode', [$this, 'isSupportMode']),
        ];
    }

    public function isSupportMode(): bool
    {
        //        return $this->security->isGranted(SupportBypassVoter::SUPPORT_ACTIVE);
        return false;
    }
}
