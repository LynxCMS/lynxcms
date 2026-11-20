<?php

declare(strict_types=1);

namespace WritesFixture;

use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use LynxCms\Core\Actions\Action;

final class Post extends Model {}

abstract class SavesPosts extends Action
{
    public function write(Post $post): void
    {
        $post->save();
        Post::query()->where('id', 1)->update(['title' => 'x']);
    }
}

final class Screen
{
    public function write(Post $post): void
    {
        $post->save();
        Post::create(['title' => 'x']);
        Post::query()->where('id', 1)->delete();
    }

    public function read(Post $post): void
    {
        Post::query()->where('id', 1)->get();
        collect([1])->push(2);
        $post->getAttribute('title');
    }
}

final class Passwords implements UpdatesUserPasswords
{
    /** @param  array<string, mixed>  $input */
    public function update(Post $user, array $input): void
    {
        $user->save();
    }
}

final class ListedWriter
{
    public function write(Post $post): void
    {
        $post->delete();
    }
}
