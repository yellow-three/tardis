<?php

declare(strict_types=1);

namespace Tardis\Assets;

use Illuminate\Support\Str;

/**
 * A stylesheet or script the admin pages load: a URL (http(s) or root-relative)
 * or an inline block. Built with the named constructors; the asset manager
 * validates URLs and integrity values when it writes them.
 *
 * An asset can also be a file shipped inside a package (Asset::file()). Tardis
 * serves it from a content-hash URL with an immutable cache header, so the
 * package author never has to publish anything, and a changed file gets a new URL.
 *
 * `scope`, `routes` and `ability` declare where it is wanted: `admin` pages,
 * `auth` pages (login...) or `both`; route-name patterns (`tardis.bread.*`);
 * and an ability the user must hold. An asset that does not match is not written.
 */
final class Asset
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $url,
        public readonly ?string $inline,
        public readonly ?string $integrity = null,
        public readonly bool $defer = true,
        public readonly ?string $file = null,
        public readonly string $scope = 'admin',
        /** @var list<string> */
        public readonly array $routes = [],
        public readonly ?string $ability = null,
    ) {}

    /**
     * A stylesheet or script file inside a package; the type comes from the extension.
     */
    public static function file(string $path): self
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['css', 'js'], true)) {
            throw new \InvalidArgumentException("Asset::file() takes a .css or .js file, got [{$path}].");
        }

        return new self($extension, null, null, file: $path);
    }

    /** Where the asset is wanted: admin, auth or both. */
    public function scope(string $scope): self
    {
        if (! in_array($scope, ['admin', 'auth', 'both'], true)) {
            throw new \InvalidArgumentException("Unknown asset scope [{$scope}].");
        }

        return $this->with(scope: $scope);
    }

    /** @param  list<string>|string  $patterns  route names, `*` wildcards allowed */
    public function routes(array|string $patterns): self
    {
        return $this->with(routes: array_values((array) $patterns));
    }

    public function ability(string $ability): self
    {
        return $this->with(ability: $ability);
    }

    /** Short content hash; the URL changes whenever the file does. */
    public function hash(): ?string
    {
        if ($this->file === null || ! is_file($this->file) || ! is_readable($this->file)) {
            return null;
        }

        return substr((string) hash_file('sha256', $this->file), 0, 16);
    }

    /**
     * Whether the asset is wanted on this kind of page, route and for this user.
     *
     * @param  callable(string): bool  $can  answers an ability check
     */
    public function wantedOn(string $scope, ?string $routeName, callable $can): bool
    {
        if ($this->scope !== 'both' && $this->scope !== $scope) {
            return false;
        }

        if ($this->routes !== [] && ($routeName === null || ! Str::is($this->routes, $routeName))) {
            return false;
        }

        return $this->ability === null || $can($this->ability);
    }

    private function with(...$changes): self
    {
        return new self(
            $this->type,
            $this->url,
            $this->inline,
            $this->integrity,
            $this->defer,
            ...array_merge(['file' => $this->file, 'scope' => $this->scope, 'routes' => $this->routes, 'ability' => $this->ability], $changes),
        );
    }

    public static function css(string $url, ?string $integrity = null): self
    {
        return new self('css', $url, null, $integrity);
    }

    public static function js(string $url, ?string $integrity = null, bool $defer = true): self
    {
        return new self('js', $url, null, $integrity, $defer);
    }

    public static function inlineCss(string $css): self
    {
        return new self('css', null, $css);
    }

    public static function inlineJs(string $js): self
    {
        return new self('js', null, $js);
    }
}
