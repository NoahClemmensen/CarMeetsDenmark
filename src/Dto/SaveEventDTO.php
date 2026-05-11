<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SaveEventDTO
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public string $name = '';

    public ?string $description = '';
}
