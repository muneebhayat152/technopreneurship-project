<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComplaintSubmittedNotification extends Notification
{
    public function __construct(
        public Complaint $complaint
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->complaint->loadMissing('user:id,name,email', 'company:id,name');

        $preview = mb_substr((string) $this->complaint->complaint_text, 0, 140);
        if (mb_strlen((string) $this->complaint->complaint_text) > 140) {
            $preview .= '…';
        }

        return [
            'title' => 'New complaint submitted',
            'body' => $this->summaryLine($preview),
            'kind' => 'complaint_new',
            'path' => '/complaints',
            'complaint_id' => $this->complaint->id,
            'company_id' => $this->complaint->company_id,
            'sentiment' => $this->complaint->sentiment,
            'sentiment_score' => $this->complaint->sentiment_score,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->complaint->loadMissing('user:id,name,email', 'company:id,name');
        $preview = mb_substr((string) $this->complaint->complaint_text, 0, 300);

        return (new MailMessage)
            ->subject('New complaint — '.config('app.name'))
            ->line($this->summaryLine($preview))
            ->line('Open Complaints in the app to review.')
            ->salutation(' ');
    }

    private function summaryLine(string $preview): string
    {
        $org = $this->complaint->company?->name ?? 'Organization #'.$this->complaint->company_id;
        $by = $this->complaint->user?->email ?? 'unknown';
        $sent = $this->complaint->sentiment ?? 'n/a';
        $score = $this->complaint->sentiment_score !== null
            ? sprintf(' (score %.2f)', (float) $this->complaint->sentiment_score)
            : '';

        return "{$org}. By {$by}. Sentiment: {$sent}{$score}. #{$this->complaint->id}. {$preview}";
    }
}
