<?php

namespace App\Controller\Admin\Filter;

use App\Entity\Screenshot;
use App\Form\Admin\TagTreeFilterType;
use App\Tagging\TagBranch;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

final class TagTreeFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'tagTree', string $label = 'Tag (avec sous-tags)'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TagTreeFilterType::class);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $branch = $filterDataDto->getValue();
        if (!$branch instanceof TagBranch) {
            return;
        }

        $parameter = $filterDataDto->getParameterName();
        $queryBuilder
            ->andWhere(sprintf('%s.id IN (SELECT tagged.id FROM %s tagged JOIN tagged.tags tagged_tag WHERE tagged_tag.id IN (:%s))', $filterDataDto->getEntityAlias(), Screenshot::class, $parameter))
            ->setParameter($parameter, $branch->tagIds);
    }
}
