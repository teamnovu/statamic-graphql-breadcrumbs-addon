<?php

namespace Teamnovu\GraphqlBreadcrumbs;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Facades\GraphQL;
use Teamnovu\GraphqlBreadcrumbs\Types\Breadcrumb;
use Teamnovu\GraphqlBreadcrumbs\BreadcrumbService;

class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        GraphQL::addType(Breadcrumb::class);
        GraphQL::addField('EntryInterface', 'breadcrumbs', function () {
            return [
                'type' => GraphQL::listOf(GraphQL::type(Breadcrumb::NAME)),
                'args' => [
                    'use_navigation_structure' => [
                        'type' => GraphQL::string(),
                        'description' => 'Provide the handle of the navigation structure to retrieve the structure from the navigation instead of the collection.',
                    ],
                    'get_full_path_of_mount_page' => [
                        'type' => GraphQL::boolean(),
                        'description' => "Applicable when the entries collection is mounted and 'use_navigation_structure' is false. If true, returns the full path of the mounted page. If false, returns the mount page and its immediate parent (e.g., 'home'). Default is false.",
                    ],
                ],
                'resolve' => function ($entry, $args) {
                    return BreadcrumbService::getBreadcrumbs($entry, $args);
                },
            ];
        });
    }
}
