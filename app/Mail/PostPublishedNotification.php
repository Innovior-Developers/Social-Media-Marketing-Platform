<?php
// app/Mail/PostPublishedNotification.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\SocialMediaPost;

class PostPublishedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $post;
    public $platform;
    public $result;

    public function __construct(SocialMediaPost $post, string $platform, array $result)
    {
        $this->post = $post;
        $this->platform = $platform;
        $this->result = $result;
    }

    public function build()
    {
        $subject = $this->result['success'] 
            ? "✅ Post Published Successfully on " . ucfirst($this->platform)
            : "❌ Post Publishing Failed on " . ucfirst($this->platform);

        return $this->subject($subject)
                    ->markdown('emails.post-published')
                    ->with([
                        'postTitle' => $this->post->content['title'] ?? 'Untitled Post',
                        'platform' => $this->platform,
                        'success' => $this->result['success'] ?? false,
                        'publishedAt' => $this->result['published_at'] ?? now(),
                        'postUrl' => $this->result['url'] ?? '#',
                        'error' => $this->result['error'] ?? null,
                        'mode' => $this->result['mode'] ?? 'unknown'
                    ]);
    }
}