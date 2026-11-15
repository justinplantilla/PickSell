<?php

namespace App\Support;

/**
 * The Terms of Service and Privacy Policy (resources/views/legal/*) for every surface: the
 * website includes the views directly; the buyer app gets them from GET /legal/{document} as
 * plain text (it shows a Text widget) plus HTML; the rider app opens the website pages.
 */
final class LegalDocuments
{
    public const DOCUMENTS = ['terms' => 'Terms of Service', 'privacy' => 'Privacy Policy'];

    public static function html(string $document): string
    {
        return view("legal.{$document}")->render();
    }

    /** Readable plain text: headings on their own lines, list items as bullets, links as their words. */
    public static function text(string $document): string
    {
        $html = preg_replace('/\{\{--.*?--\}\}/s', '', self::html($document));
        $html = preg_replace('#<h2[^>]*>(.*?)</h2>#si', "\n\n$1\n", $html);
        $html = preg_replace('#<h3[^>]*>(.*?)</h3>#si', "\n\n$1\n", $html);
        $html = preg_replace('#<li[^>]*>#i', "\n• ", $html);
        $html = preg_replace('#</(p|ul|li)>#i', "\n", $html);
        $html = preg_replace('#<p[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
