<?php

declare(strict_types=1);

namespace App\Dto;

use App\Service\EmbedParser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SavePostDTO
{
    #[Assert\Length(max: 2000, maxMessage: 'Post body cannot exceed 2000 characters.')]
    public ?string $body = null;

    #[Assert\Length(max: 500)]
    #[Assert\Url(protocols: ['https'], message: 'Link must be a valid https URL.')]
    public ?string $link = null;

    #[Assert\Length(max: 500)]
    public ?string $embedUrl = null;

    /** @var UploadedFile[] */
    #[Assert\Count(max: 4, maxMessage: 'You can attach at most 4 images.')]
    #[Assert\All([
        new Assert\Image(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            mimeTypesMessage: 'Each image must be JPEG, PNG, GIF, or WEBP under 5MB.',
        ),
    ])]
    public array $imageFiles = [];

    #[Assert\Callback]
    public function validateMutualExclusion(ExecutionContextInterface $context): void
    {
        $hasBody = $this->body !== null && trim($this->body) !== '';
        $hasLink = $this->link !== null && $this->link !== '';
        $hasEmbed = $this->embedUrl !== null && $this->embedUrl !== '';
        $hasImages = count(array_filter($this->imageFiles)) > 0;

        if (!$hasBody && !$hasLink && !$hasEmbed && !$hasImages) {
            $context->buildViolation('Post cannot be empty. Add text, a link, an embed, or images.')
                ->atPath('body')
                ->addViolation();
            return;
        }

        if ($hasEmbed && $hasImages) {
            $context->buildViolation('A post can contain images OR an embed, not both.')
                ->atPath('embedUrl')
                ->addViolation();
        }

        if ($hasEmbed) {
            $parser = new EmbedParser();
            if ($parser->parse($this->embedUrl) === null) {
                $context->buildViolation('Unsupported embed URL. We currently support YouTube and Instagram.')
                    ->atPath('embedUrl')
                    ->addViolation();
            }
        }
    }
}
