<?php

namespace App\Field;

use App\Form\IconType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

final class IconField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/icon.html.twig')
            ->setFormType(IconType::class)
            ->addFormTheme('form/icon.html.twig')
            ->addCssClass('field-icon');
    }

    /**
     * @param array<string, string> $sets prefix => label
     */
    public function setSets(array $sets): self
    {
        $this->setFormTypeOption('sets', $sets);

        return $this;
    }
}
