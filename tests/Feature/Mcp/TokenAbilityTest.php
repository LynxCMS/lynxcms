<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LynxCms\Mcp\Http\RequireTokenAbility;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * The MCP web route takes only Sanctum tokens made for it, so a token the app issued for another
 * use is not also a key to the site's content.
 */

/** A request from a user signed in with a token carrying $abilities, or with a session for null. */
function mcpRequest(?array $abilities): Request
{
    $token = $abilities === null ? null : tap(new class extends Model
    {
        protected $casts = ['abilities' => 'array'];
    }, fn (Model $model) => $model->setAttribute('abilities', $abilities));

    $user = new class(['id' => 1], $token) extends GenericUser
    {
        public function __construct(array $attributes, private readonly ?Model $token)
        {
            parent::__construct($attributes);
        }

        public function currentAccessToken(): ?Model
        {
            return $this->token;
        }
    };

    return tap(Request::create('/mcp/lynx', 'POST'), fn (Request $request) => $request->setUserResolver(fn (): object => $user));
}

function throughAbilityCheck(Request $request): int
{
    return (new RequireTokenAbility)->handle($request, fn (): Response => new Response('ok'))->getStatusCode();
}

it('accepts a token made for the MCP server, and session sign-ins', function (): void {
    expect(throughAbilityCheck(mcpRequest(['lynx:mcp'])))->toBe(200)
        ->and(throughAbilityCheck(mcpRequest(null)))->toBe(200);
});

it('refuses tokens made for something else, also tokens with every ability', function (array $abilities): void {
    expect(fn (): int => throughAbilityCheck(mcpRequest($abilities)))->toThrow(HttpException::class, 'create one with the [lynx:mcp] ability');
})->with([[['orders:read']], [['*']]]);

it('accepts any token when the site turns the check off', function (): void {
    config(['lynx.mcp.web.ability' => '']);

    expect(throughAbilityCheck(mcpRequest(['orders:read'])))->toBe(200);
});
