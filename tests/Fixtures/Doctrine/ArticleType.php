<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Doctrine;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\LessThan;

/**
 * @extends AbstractType<Article>
 */
final class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['empty_data' => ''])
            ->add('published', CheckboxType::class, ['required' => false])
            ->add('views', IntegerType::class, [
                'required' => false,
                'constraints' => [new LessThan(1000)],
            ])
            ->add('category', EntityType::class, ['class' => Category::class, 'required' => false])
            ->add('tags', EntityType::class, ['class' => Tag::class, 'multiple' => true, 'required' => false, 'by_reference' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}
