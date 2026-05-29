<?php

namespace App\Dto;

use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDTO
{
    #[Assert\NotBlank(message: 'Please enter your current password.')]
    #[SecurityAssert\UserPassword(message: 'Your current password is incorrect.')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'Please enter a new password.')]
    #[Assert\Length(
        min: 6,
        max: 4096,
        minMessage: 'Your password should be at least {{ limit }} characters',
    )]
    public ?string $newPassword = null;
}
