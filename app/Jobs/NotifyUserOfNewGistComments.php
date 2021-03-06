<?php

namespace App\Jobs;

use App\GistClient;
use App\NotifiedComment;
use Exception;
use Github\Client as GitHubClient;
use Github\Exception\ExceptionInterface as GithubException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyUserOfNewGistComments extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels, DispatchesJobs;

    public $tries = 5;
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function handle(GistClient $gistClient, GitHubClient $githubClient)
    {
        Log::debug('Notifying user ' . $this->user->id . ' of new comments');

        try {
            foreach ($gistClient->all($this->user) as $gist) {
                Log::debug('Checking if user ' . $this->user->id . ' has new comments for gist ' . $gist['id']);
                foreach ($githubClient->api('gist')->comments()->all($gist['id']) as $comment) {
                    $this->handleComment($comment, $gist, $this->user);
                }
            }
        } catch (GithubException $e) {
            Log::info(sprintf(
                'Attempting to queue "get comments" for user %s after GitHub exception. Delayed execution for 60 minutes after (%d) attempts. Message: [%s] Exception class: [%s]',
                $this->user->id,
                $this->attempts(),
                $e->getMessage(),
                get_class($e)
            ));

            $this->handleGitHubException($e);
        } catch (Exception $e) {
            Log::info(sprintf(
                'Attempting to queue "get comments" for user %s after generic exception. Delayed execution for 2 seconds after (%d) attempts. Message: [%s] Exception class: [%s]',
                $this->user->id,
                $this->attempts(),
                $e->getMessage(),
                get_class($e)
            ));

            $this->release(2);
        }
    }

    private function handleComment($comment, $gist, $user)
    {
        Log::debug('Notifying user ' . $this->user->id . ' of new comments for gist ' . $gist['id'] . ', comment ' . $comment['id'] . '?');

        if ($this->commentNeedsNotification($comment, $user)) {
            $this->notifyComment($comment, $gist, $user);
        }
    }

    private function commentNeedsNotification($comment, $user)
    {
        if ($comment['updated_at'] < $user->created_at || $comment['user']['id'] == $user->github_id) {
            return false;
        }

        return NotifiedComment::where('github_id', $comment['id'])
            ->where('github_updated_at', $comment['updated_at'])
            ->count() == 0;
    }

    private function notifyComment($comment, $gist, $user)
    {
        Log::debug('Notifying user ' . $this->user->id . ' of new comments for gist ' . $gist['id'] . ', comment ' . $comment['id'] . ': NEEDS IT');

        $this->dispatch(new NotifyUserOfNewGistComment(
            $user,
            $comment,
            $gist
        ));
    }

    private function handleGitHubException($e)
    {
        if ($e->getMessage() == 'Bad credentials') {
            return $this->handleBrokenGitHubToken();
        }

        $this->release(3600);
    }

    private function handleBrokenGitHubToken()
    {
        $this->dispatch(new CancelUserForBadCredentials($this->user));

        $this->delete();
    }
}
