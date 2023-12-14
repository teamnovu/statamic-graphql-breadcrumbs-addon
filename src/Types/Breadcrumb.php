<?php

declare(strict_types=1);

namespace Teamnovu\GraphqlBreadcrumbs\Types;

use Rebing\GraphQL\Support\Type as GraphQLType;
use Statamic\Facades\GraphQL;

class Breadcrumb extends GraphQLType
{
    public const NAME = 'breadcrumbs';

    protected $attributes = [
        'name' => self::NAME,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' =>GraphQL::string(),
            ],
            'slug' => [
                'type' =>GraphQL::string(),
            ],
            'title' => [
                'type' => GraphQL::nonNull(GraphQL::string()),
            ],
            'url' => [
                'type' => GraphQL::string(),
            ],
            'permalink' => [
                'type' => GraphQL::string(),
            ],
            'blueprint' => [
                'type' => GraphQL::string(),
            ],
        ];
    }
}
