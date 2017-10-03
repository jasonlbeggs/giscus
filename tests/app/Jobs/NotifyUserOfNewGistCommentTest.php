<?php

namespace tests\App\Jobs;

use App\GitHubMarkdownParser;
use App\Jobs\NotifyUserOfNewGistComment;
use App\NotifiedComment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use TestCase;

class NotifyUserOfNewGistCommentTest extends TestCase
{
    use DatabaseTransactions;

    protected $mailSendData;

    public function setUp()
    {
        parent::setUp();

        $gitHubMarkdownParserMock = $this->createMock(GitHubMarkdownParser::class);
        $this->app->instance(GitHubMarkdownParser::class, $gitHubMarkdownParserMock);

        $notifiedComment = new NotifiedComment();
        $notifiedComment->github_id = 1;
        $notifiedComment->github_updated_at = '2017-10-03 01:02:03';
        $notifiedComment->save();

        Mail::shouldReceive('send')
            ->andReturnUsing(function ($view, $data, $callback) {
                $this->mailSendData = [$view, $data, $callback];
            });
    }

    /**
     * @test
     */
    public function itSendsNewCommentEmailWhenNewCommentHasBeenAdded()
    {
        $comment = [
            'id' => 2,
            'updated_at' => '2017-10-03T02:03:04Z',
            'body' => 'body',
        ];

        $job = new NotifyUserOfNewGistComment($user = null, $comment, $gist = null);
        $job->handle();

        $this->assertEquals('emails.new-comment', $this->mailSendData[0]);
    }

    /**
     * @test
     */
    public function itSendsEditCommentEmailWhenACommentHasBeenEdited()
    {
        $comment = [
            'id' => 1,
            'updated_at' => '2017-10-03T02:03:04Z',
            'body' => 'body',
        ];

        $job = new NotifyUserOfNewGistComment($user = null, $comment, $gist = null);
        $job->handle();

        $this->assertEquals('emails.edit-comment', $this->mailSendData[0]);
    }
}
