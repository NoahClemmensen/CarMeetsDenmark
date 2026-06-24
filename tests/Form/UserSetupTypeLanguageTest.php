<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Dto\UserSetupDTO;
use App\Form\UserSetupType;
use App\Tests\EnsuresSymfonyEnv;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;

final class UserSetupTypeLanguageTest extends KernelTestCase
{
    use EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    private function factory(): FormFactoryInterface
    {
        self::bootKernel();

        return static::getContainer()->get('form.factory');
    }

    public function testLanguageIsHiddenByDefault(): void
    {
        $form = $this->factory()->create(UserSetupType::class, new UserSetupDTO(), [
            'include_avatar' => false,
        ]);

        self::assertInstanceOf(
            HiddenType::class,
            $form->get('language')->getConfig()->getType()->getInnerType(),
        );
    }

    public function testLanguageIsAChoiceWhenSelectLanguageEnabled(): void
    {
        $form = $this->factory()->create(UserSetupType::class, new UserSetupDTO(), [
            'include_avatar' => false,
            'select_language' => true,
        ]);

        $type = $form->get('language')->getConfig()->getType()->getInnerType();
        self::assertInstanceOf(ChoiceType::class, $type);
        self::assertSame(
            ['English' => 'en', 'Dansk' => 'da'],
            $form->get('language')->getConfig()->getOption('choices'),
        );
    }
}
