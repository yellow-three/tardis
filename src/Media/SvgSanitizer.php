<?php

declare(strict_types=1);

namespace Tardis\Media;

use Illuminate\Http\UploadedFile;

/**
 * Removes what lets an SVG run code or reach out: scripts, event handlers,
 * javascript:/data: links, foreign HTML, external references, DOCTYPEs and
 * entities. Applied to every SVG upload (media screen and file, image and
 * media fields), so an allowed "svg" no longer means stored XSS.
 */
final class SvgSanitizer
{
    /** Elements that are dropped with everything inside them. */
    private const FORBIDDEN_ELEMENTS = ['script', 'style', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video', 'canvas', 'handler', 'listener', 'set', 'animate', 'animatemotion', 'animatetransform'];

    public static function isSvg(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'svg'
            || in_array($file->getMimeType(), ['image/svg+xml', 'image/svg'], true);
    }

    /**
     * Clean an uploaded SVG in place, before it is stored.
     *
     * @throws \InvalidArgumentException when it is not a readable SVG
     */
    public static function sanitizeUpload(UploadedFile $file): void
    {
        if (! self::isSvg($file)) {
            return;
        }

        $clean = self::clean((string) file_get_contents($file->getRealPath()));

        if ($clean === null) {
            throw new \InvalidArgumentException('The SVG file could not be read safely.');
        }

        file_put_contents($file->getRealPath(), $clean);
    }

    /** The cleaned document, or null when it is not an SVG that can be read safely. */
    public static function clean(string $svg): ?string
    {
        // Entities and DOCTYPEs are how XXE and "billion laughs" get in.
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg) === 1) {
            return null;
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $document->documentElement === null || strtolower($document->documentElement->localName) !== 'svg') {
            return null;
        }

        self::cleanElement($document->documentElement);

        $output = $document->saveXML($document->documentElement);

        return $output === false ? null : $output;
    }

    private static function cleanElement(\DOMElement $element): void
    {
        $elementName = self::localName($element->localName);

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                if (in_array(self::localName($child->localName), self::FORBIDDEN_ELEMENTS, true)) {
                    $element->removeChild($child);

                    continue;
                }

                self::cleanElement($child);
            } elseif ($child instanceof \DOMProcessingInstruction) {
                $element->removeChild($child);
            }
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var \DOMAttr $attribute */
            // An undeclared prefix stays in localName ("xlink:href"), so the
            // part after the last colon is the name the checks must use.
            $name = self::localName($attribute->localName);
            $value = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', $attribute->value) ?? '');

            // xml:base changes how every relative href in the subtree resolves,
            // so it must not survive even when the link itself looks harmless.
            $xmlBase = $name === 'base'
                && ($attribute->namespaceURI === 'http://www.w3.org/XML/1998/namespace' || $attribute->name === 'xml:base');

            // An <a> may point to the web; <use>, <image> and <feImage> fetch
            // external resources into the document, so they only take #ids.
            $referenceOnly = in_array($elementName, ['use', 'image', 'feimage'], true)
                && in_array($name, ['href', 'src'], true);

            $unsafe = str_starts_with($name, 'on')
                || (in_array($name, ['href', 'src', 'action', 'formaction'], true)
                    && ! ($referenceOnly ? self::fragmentLink($value) : self::safeLink($value)))
                || ($name === 'style' && (str_contains($value, 'javascript:') || str_contains($value, 'expression(') || preg_match('/url\((?!["\']?#)/', $value) === 1));

            if ($xmlBase || $unsafe) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    /** Only a link inside the document (#id) or a plain web link is kept. */
    private static function safeLink(string $value): bool
    {
        return $value === '' || str_starts_with($value, '#') || preg_match('#^https?://#', $value) === 1 && ! str_contains($value, '<');
    }

    /** Resource references may not leave the document. */
    private static function fragmentLink(string $value): bool
    {
        return str_starts_with($value, '#') && ! str_contains($value, '<');
    }

    /** DOM keeps undeclared prefixes in localName; check the unprefixed name. */
    private static function localName(string $name): string
    {
        $colon = strrpos($name, ':');

        return strtolower($colon === false ? $name : substr($name, $colon + 1));
    }
}
