<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SavePostDTO;
use App\Entity\Event;
use App\Entity\Post;
use App\Entity\PostImage;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

class PostService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $fileUploader,
        private readonly EmbedParser $embedParser,
        #[Autowire('%event_feed_directory%')]
        private readonly string $feedDirectory,
    ) {
    }

    public function createFromDto(Event $event, User $author, SavePostDTO $dto): Post
    {
        $post = new Post($event, $author);
        $post->setBody($dto->body !== null && $dto->body !== '' ? $dto->body : null);
        $post->setLink($dto->link !== null && $dto->link !== '' ? $dto->link : null);

        if ($dto->embedUrl !== null && $dto->embedUrl !== '') {
            $parsed = $this->embedParser->parse($dto->embedUrl);
            if ($parsed === null) {
                throw new \DomainException('Unsupported embed URL.');
            }
            $post->setEmbedProvider($parsed->provider);
            $post->setEmbedExternalId($parsed->externalId);
        }

        $uploaded = [];
        try {
            $position = 0;
            foreach ($dto->imageFiles as $file) {
                if ($file === null) {
                    continue;
                }
                $newName = $this->fileUploader->upload($file, $this->feedDirectory);
                $uploaded[] = $newName;
                $post->addImage(new PostImage($post, $newName, $position));
                $position++;
            }
        } catch (Throwable $e) {
            foreach ($uploaded as $name) {
                $this->fileUploader->remove($this->feedDirectory, $name);
            }
            throw $e;
        }

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    public function editBody(Post $post, ?string $body): Post
    {
        $post->setBody($body !== null && $body !== '' ? $body : null);
        $post->setEditedAt(new DateTimeImmutable());
        $this->em->flush();
        return $post;
    }

    public function softDelete(Post $post): void
    {
        $post->setIsDeleted(true);
        $this->em->flush();
    }

    /** Returns true if now pinned, false if now unpinned. */
    public function togglePin(Post $post): bool
    {
        if ($post->isPinned()) {
            $post->setPinnedAt(null);
            $this->em->flush();
            return false;
        }
        $post->setPinnedAt(new DateTimeImmutable());
        $this->em->flush();
        return true;
    }
}
