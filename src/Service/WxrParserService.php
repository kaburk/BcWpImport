<?php
declare(strict_types=1);

namespace BcWpImport\Service;

use InvalidArgumentException;
use SimpleXMLElement;

class WxrParserService
{
    public function analyze(string $filePath): array
    {
        $xml = $this->loadXml($filePath);
        $wp = $xml->getNamespaces(true)['wp'] ?? null;
        $items = $xml->channel->item ?? [];
        $itemCounts = [];
        $authors = [];
        $categories = [];
        $tags = [];
        $unsupportedTypes = [];

        foreach ($items as $item) {
            $postType = 'unknown';
            if ($wp) {
                $namespaced = $item->children($wp);
                $postType = (string) ($namespaced->post_type ?? 'unknown');
                $authorLogin = trim((string) ($namespaced->post_author ?? ''));
                if ($authorLogin !== '' && !in_array($authorLogin, $authors, true)) {
                    $authors[] = $authorLogin;
                }
            }
            $itemCounts[$postType] = ($itemCounts[$postType] ?? 0) + 1;

            foreach ($item->category ?? [] as $category) {
                $domain = (string) $category['domain'];
                $nicename = trim((string) $category['nicename']);
                $label = trim((string) $category);
                if ($domain === 'category' && $label !== '' && !in_array($label, $categories, true)) {
                    $categories[] = $label;
                }
                if ($domain === 'post_tag') {
                    $tag = $label !== '' ? $label : $nicename;
                    if ($tag !== '' && !in_array($tag, $tags, true)) {
                        $tags[] = $tag;
                    }
                }
            }

            if (!in_array($postType, ['post', 'page', 'unknown'], true) && !in_array($postType, $unsupportedTypes, true)) {
                $unsupportedTypes[] = $postType;
            }
        }

        sort($authors);
        sort($categories);
        sort($tags);
        sort($unsupportedTypes);

        return [
            'wxr_version' => $wp ? (string) ($xml->channel->children($wp)->wxr_version ?? '') : '',
            'channel_title' => (string) ($xml->channel->title ?? ''),
            'language' => (string) ($xml->channel->language ?? ''),
            'item_counts' => $itemCounts,
            'authors' => $authors,
            'categories' => $categories,
            'tags' => $tags,
            'unsupported_types' => $unsupportedTypes,
        ];
    }

    public function parseItems(string $filePath): array
    {
        $xml = $this->loadXml($filePath);
        $namespaces = $xml->getNamespaces(true);
        $wp = $namespaces['wp'] ?? '';
        $content = $namespaces['content'] ?? '';
        $excerpt = $namespaces['excerpt'] ?? '';
        $items = [];

        foreach ($xml->channel->item ?? [] as $item) {
            $wpItem = $wp ? $item->children($wp) : null;
            $contentItem = $content ? $item->children($content) : null;
            $excerptItem = $excerpt ? $item->children($excerpt) : null;
            $categories = [];
            $tags = [];

            foreach ($item->category ?? [] as $category) {
                $domain = (string) $category['domain'];
                $slug = trim((string) $category['nicename']);
                $label = trim((string) $category);
                $row = [
                    'slug' => $slug,
                    'label' => $label !== '' ? $label : $slug,
                ];
                if ($domain === 'category') {
                    $categories[] = $row;
                }
                if ($domain === 'post_tag') {
                    $tags[] = $row;
                }
            }

            $items[] = [
                'wp_post_id'    => (int) ($wpItem->post_id ?? 0),
                'wp_post_parent' => (int) ($wpItem->post_parent ?? 0),
                'title' => trim((string) ($item->title ?? '')),
                'post_type' => trim((string) ($wpItem->post_type ?? 'unknown')),
                'post_status' => trim((string) ($wpItem->status ?? 'publish')),
                'post_name' => trim((string) ($wpItem->post_name ?? '')),
                'post_date' => trim((string) ($wpItem->post_date ?? '')),
                'post_date_gmt' => trim((string) ($wpItem->post_date_gmt ?? '')),
                'post_author' => trim((string) ($wpItem->post_author ?? '')),
                'post_excerpt' => trim((string) ($excerptItem->encoded ?? '')),
                'post_content' => trim((string) ($contentItem->encoded ?? '')),
                'categories' => $categories,
                'tags' => $tags,
            ];
        }

        return $items;
    }

    private function loadXml(string $filePath): SimpleXMLElement
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('WXR file not found.');
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($filePath);
        if (!$xml instanceof SimpleXMLElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
            throw new InvalidArgumentException('Failed to parse WXR file.');
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        return $xml;
    }
}
