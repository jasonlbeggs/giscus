<?php

namespace Tests;

use App\GistClient;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class GistClientTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @test
     * @requires ApiTest
     *
     * Note: This test is not particularly useful unless you have a token for a user
     * who has more than 30 gists, which is why it requires you to pass in a flag
     * in order for it to run.
     *
     * Sadly, I don't have the time or energy to look up passing in a flag right now,
     * so right now it's just set in the .env.test file.
     */
    public function itPullsMoreThan30GistsFromTestingUsersAccount()
    {
        $token = env('TESTING_USER_GITHUB_API_TOKEN');
        $user = factory(User::class)->create([
            'token' => $token
        ]);
        $client = $this->app->make(GistClient::class);
        $this->assertGreaterThan(30, count($client->all($user)));
    }
}
