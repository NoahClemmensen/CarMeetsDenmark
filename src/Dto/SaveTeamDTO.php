<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SaveTeamDTO
{
    #[Assert\NotBlank(message: 'Team name is required.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Description is required.')]
    #[Assert\Length(max: 500)]
    public string $description = '';
}
