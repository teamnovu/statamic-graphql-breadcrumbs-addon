<?php

namespace Teamnovu\GraphqlBreadcrumbs;

use Statamic\Entries\Entry;
use Statamic\Facades\Structure;

class BreadcrumbService
{
    /**
     * Retrieve breadcrumbs for a given entry based on provided arguments.
     *
     * @param Entry $entry The entry for which breadcrumbs are to be fetched.
     * @param array $args Additional arguments to determine the breadcrumb source.
     * @return array List of breadcrumbs.
     */
    public static function getBreadcrumbs($entry, $args)
    {
        $breadcrumbs = [];
        // get the structure from navigation
        if (isset($args['use_navigation_structure'])) {
            $breadcrumbs = self::getParentPagesFromNavigation($args['use_navigation_structure'], $entry);
        }

        // get the structure from collection
        if (!$breadcrumbs) {
            $breadcrumbs = self::getParentPages($entry);

            // check for mounting page and if so get parent pages of it as well
            if ($entry->collection->mount() !== null) {
                $mountEntry = $entry->collection->mount()->in($entry->locale());
            }

            if (isset($mountEntry)) {
                $mountPageBreadcrumbs = self::getParentPages($mountEntry);

                if ($args['get_full_path_of_mount_page'] ?? false) {
                    $breadcrumbs = array_merge($mountPageBreadcrumbs, $breadcrumbs);
                } else {
                    array_unshift($breadcrumbs, $mountEntry); // add mount
                    array_unshift($breadcrumbs, $mountPageBreadcrumbs[0]); // add top level page of mount e.g. "home"
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Fetch breadcrumbs for an entry based on its parent pages.
     *
     * @param Entry $entry The entry for which parent pages are to be fetched.
     * @return array List of parent pages as breadcrumbs.
     */
    public static function getParentPages(Entry $entry)
    {
        // current page
        $breadcrumbs[] = [
            'id' => $entry->id(),
            'title' => $entry->title,
            'slug' => $entry->slug,
            'url' => $entry->url,
            'permalink' => $entry->permalink,
            'blueprint' => $entry->blueprint->handle,
            'entry' => $entry,
        ];

        // parent pages
        while ($entry->parent) {
            $parent = $entry->parent;
            $breadcrumbs[] = [
                'id' => $parent->id(),
                'title' => $parent->title,
                'slug' => $parent->slug,
                'url' => $parent->url,
                'permalink' => $parent->permalink,
                'blueprint' => $parent->blueprint->handle,
                'entry' => $parent->entry(),
            ];

            $entry = $parent;
        }

        // invert it to have the current page last (top down)
        return array_reverse($breadcrumbs);
    }


    /**
     * Fetch breadcrumbs for an entry using a specified navigation structure.
     *
     * @param string $navHandle Handle of the navigation structure.
     * @param Entry $entry The entry for which breadcrumbs are to be fetched.
     * @return array List of breadcrumbs.
     */
    public static function getParentPagesFromNavigation(string $navHandle, Entry $entry)
    {
        $structure = Structure::findByHandle($navHandle);
        if (!$structure) {
            \Log::error('Navigation "' . $navHandle . '" not found -> fallback to collection tree');
            return [];
        }

        // Get the tree for the current locale
        $tree = $structure->in($entry->locale());

        // Find the current branch in the tree
        $breadcrumbsBranch = self::findEntryInTree($tree->tree(), $entry->id());

        // Flatten the branch to get the entry IDs
        $flatBreadcrumbs = self::flattenBranch($breadcrumbsBranch);

        // Get the entry objects from the IDs
        $breadcrumbs = [];
        foreach ($flatBreadcrumbs as $branch) {
            if (isset($branch['entry'])) {
                $entryObject = Entry::find($branch['entry']);

                $breadcrumbs[] = [
                    'id' => $entryObject->id(),
                    'title' => $entryObject->title,
                    'slug' => $entryObject->slug,
                    'url' => $entryObject->url,
                    'permalink' => $entryObject->permalink,
                    'blueprint' => $entryObject->blueprint->handle,
                    'entry' => $entryObject,
                ];
            } else {
                $breadcrumbs[] = [
                    'id' => null,
                    'title' => $branch['title'],
                    'slug' => null,
                    'url' => $branch['url'],
                    'permalink' => $branch['url'],
                    'blueprint' => null,
                    'entry' => null,
                ];
            }
        }

        // if no breadcrumbs found, return empty array
        if (empty($breadcrumbs)) {
            return [];
        }

        // Get the Homepage entry
        $homepage = Entry::query()
            ->where('url', '/')
            ->where('locale', $entry->locale())
            ->first();

        // Add the homepage to the beginning of the breadcrumbs
        if ($homepage) {
            $homepageArray = [
                'title' => $homepage->title,
                'slug' => $homepage->slug,
                'url' => $homepage->url,
                'permalink' => $homepage->permalink,
                'blueprint' => $homepage->blueprint->handle,
                'entry' => $homepage,
            ];

            array_unshift($breadcrumbs, $homepageArray);
        }

        return $breadcrumbs;
    }

    /**
     * Find and return the branch containing the specified entry within a tree.
     *
     * @param array $tree The tree structure to search within.
     * @param string $entryId ID of the entry to find.
     * @return array|null The branch containing the entry, or null if not found.
     */
    public static function findEntryInTree($tree, $entryId)
    {
        foreach ($tree as $node) {
            // A nav element can be a reference to a entry or just a nav element with a id and url
            if ((isset($node['entry']) && $node['entry'] == $entryId) || (isset($node['id']) && $node['id'] == $entryId)) {
                // Entry found, return the node (without children, as this is the target node)
                unset($node['children']);
                return [$node];
            } elseif (isset($node['children'])) {
                // Search in child nodes
                $found = self::findEntryInTree($node['children'], $entryId);
                if ($found) {
                    // If found in children, return the current node (as parent) with the found child
                    $node['children'] = $found;
                    return [$node];
                }
            }
        }
        return [];
    }

    /**
     * Flatten a tree branch to retrieve a list of entry IDs.
     *
     * @param array $branch The tree branch to flatten.
     * @param array $flattened An array to accumulate the flattened result.
     * @return array List of flattened entry IDs.
     */
    public static function flattenBranch($branch, &$flattened = [])
    {
        foreach ($branch as $node) {
            // if its a reference to a entry
            if (isset($node['entry'])) {
                $flattened[] = ['entry' => $node['entry']];
            } else {
                // if its just a nav element with a id and url
                $flattened[] = [
                    'title' => $node['title'],
                    'url' => $node['url'] ?? null,
                ];
            }

            if (isset($node['children'])) {
                self::flattenBranch($node['children'], $flattened);
            }
        }
        return $flattened;
    }
}
