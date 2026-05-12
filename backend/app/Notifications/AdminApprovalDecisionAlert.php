<?php

namespace App\Notifications;

use App\Models\AdminApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the organization admin when a super admin approves or rejects their request.
 */
class AdminApprovalDecisionAlert extends Notification
{
    public function __construct(
        public AdminApprovalRequest $requestRow,
        public string $decision,
        public ?string $reviewerNote
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
        $approved = $this->decision === 'approved';
        $type = str_replace('_', ' ', $this->requestRow->type);

        return [
            'title' => $approved ? 'Request approved' : 'Request declined',
            'body' => $this->bodyText($type, $approved),
            'kind' => 'approval_request_decided',
            'path' => '/approvals',
            'request_id' => $this->requestRow->id,
            'decision' => $this->decision,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->decision === 'approved';
        $type = str_replace('_', ' ', $this->requestRow->type);
        $mail = (new MailMessage)
            ->subject(
                ($approved ? 'Approved: ' : 'Declined: ').config('app.name')
            )
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->bodyText($type, $approved));

        if ($this->reviewerNote) {
            $mail->line('Note from reviewer: '.$this->reviewerNote);
        }

        return $mail->line('You can review details under “My requests” in the app.')->salutation(' ');
    }

    private function bodyText(string $typeLabel, bool $approved): string
    {
        if ($approved) {
            return "Your {$typeLabel} request has been approved and applied by a platform super administrator.";
        }

        return "Your {$typeLabel} request has been declined by a platform super administrator.";
    }
}
