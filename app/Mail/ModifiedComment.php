<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ModifiedComment extends Mailable
{
    use Queueable, SerializesModels;

    public $comment;
    public $gist;
    public $body;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($comment, $gist, $body, $user)
    {
        $this->comment = $comment;
        $this->gist = $gist;
        $this->body = $body;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.edit-comment')
            ->subject('A comment on one of your gists was modified!');
    }
}
