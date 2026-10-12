<?php

namespace App\Controller\Admin\Filter;

use App\Form\Admin\ClassificationFilterType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

final class ClassificationFilter implements FilterInterface
{
    use FilterTrait;

    public const string WITHOUT_ALBUM = 'without_album';
    public const string WITHOUT_TAG = 'without_tag';
    public const string WITHOUT_GAME = 'without_game';
    public const string NEVER_TAGGED_BY_AI = 'never_ai';

    private const array CONDITIONS = [
        self::WITHOUT_ALBUM => '%s.albums IS EMPTY',
        self::WITHOUT_TAG => '%s.tags IS EMPTY',
        self::WITHOUT_GAME => '%s.game IS NULL',
        self::NEVER_TAGGED_BY_AI => '%s.aiTaggedAt IS NULL',
    ];

    public static function new(string $propertyName = 'classification', string $label = 'À ranger'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ClassificationFilterType::class);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $condition = self::CONDITIONS[$filterDataDto->getValue()] ?? null;
        if (null !== $condition) {
            $queryBuilder->andWhere(sprintf($condition, $filterDataDto->getEntityAlias()));
        }
    }
}
