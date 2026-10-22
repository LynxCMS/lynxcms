<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * Web server rules that send stored pages without PHP: only GET and HEAD requests with no query
 * string and none of the bypass cookies, when the file exists.
 *
 * @internal
 */
final readonly class ServerRules
{
    public const array SERVERS = ['nginx', 'apache', 'caddy'];

    public function __construct(private PageCacheSettings $settings) {}

    public function for(string $server): ?string
    {
        return match ($server) {
            'nginx' => $this->nginx(),
            'apache' => $this->apache(),
            'caddy' => $this->caddy(),
            default => null,
        };
    }

    /** The cache folder as a URL path below public/, e.g. "/lynx-cache". */
    public function webPath(): string
    {
        $public = rtrim(public_path(), '/');
        $path = $this->settings->path();

        return str_starts_with($path, $public.'/') ? substr($path, strlen($public)) : '/lynx-cache';
    }

    private function nginx(): string
    {
        $cookies = implode('|', array_map(preg_quote(...), $this->settings->bypassCookies()));

        return <<<NGINX
        # In the server block, before location /. The header goes only on stored pages: nginx
        # leaves out a header whose value is empty.
        set \$lynx_cache "{$this->webPath()}/\$host\$uri/index.html";
        set \$lynx_cache_control "{$this->cacheControl()}";
        if (\$request_method !~ ^(GET|HEAD)\$) { set \$lynx_cache /.lynx-no-cache; }
        if (\$args != "") { set \$lynx_cache /.lynx-no-cache; }
        if (\$http_cookie ~* "({$cookies})") { set \$lynx_cache /.lynx-no-cache; }
        if (!-f \$document_root\$lynx_cache) { set \$lynx_cache_control ""; }

        location / {
            add_header Cache-Control \$lynx_cache_control;
            try_files \$lynx_cache \$uri \$uri/ /index.php?\$query_string;
        }
        NGINX;
    }

    private function apache(): string
    {
        $cookies = implode('|', array_map(preg_quote(...), $this->settings->bypassCookies()));
        $file = ltrim($this->webPath(), '/').'/%{HTTP_HOST}/$1/index.html';

        // Paths are relative to public/ (the fourth condition finds its folder from the request's
        // file name), not to the document root, so the rules also work when the host's web folder
        // is the app's root and a .htaccess there forwards to public/, as on shared hosting.
        return <<<APACHE
        # In public/.htaccess, after RewriteEngine On and before Laravel's rules. If page URLs end
        # with a slash (lynx.trailing_slash), remove Laravel's "Redirect Trailing Slashes" rule,
        # which would send each of them to the address without it.
        RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)\$
        RewriteCond %{QUERY_STRING} ^\$
        RewriteCond %{HTTP_COOKIE} !({$cookies})
        RewriteCond \$1::%{REQUEST_FILENAME} ^(.*)::(.*?)\\1\$
        RewriteCond %2{$file} -f
        RewriteRule ^(.*)\$ {$file} [L,E=LYNX_CACHE:1]
        <IfModule mod_headers.c>
            Header set Cache-Control "{$this->cacheControl()}" env=LYNX_CACHE
            Header set Cache-Control "{$this->cacheControl()}" env=REDIRECT_LYNX_CACHE
        </IfModule>
        APACHE;
    }

    private function caddy(): string
    {
        $cookies = implode("\n", array_map(fn (string $cookie): string => "    not header Cookie *{$cookie}*", $this->settings->bypassCookies()));
        $file = $this->webPath().'/{host}{path}/index.html';

        return <<<CADDY
        # In the site block, before php_fastcgi:
        @lynx_cache {
            method GET HEAD
            expression {query} == ""
        {$cookies}
            file {$file}
        }
        rewrite @lynx_cache {$file}
        header @lynx_cache Cache-Control "{$this->cacheControl()}"
        CADDY;
    }

    private function cacheControl(): string
    {
        return CachedPage::cacheControl($this->settings->edgeTtl());
    }
}
