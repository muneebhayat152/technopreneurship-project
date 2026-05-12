<?php

namespace App\Notifications;

use App\Models\AdminApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to platform super administrators when an organization admin submits a request.
 * Synchronous so in-app + mail work without a queue worker for this path alone.
 */
class NewAdminApprovalRequestAlert extends Notification
{
    public function __construct(
        public AdminApprovalRequest $requestRow
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
        $this->requestRow->loadMissing('company:id,name', 'requester:id,name,email');

        return [
            'title' => 'New approval request',
            'body' => $this->summaryLine(),
            'kind' => 'approval_request_new',
            'path' => '/approvals',
            'request_id' => $this->requestRow->id,
            'request_type' => $this->requestRow->type,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New approval request — '.config('app.name'))
            ->line($this->summaryLine())
            ->line('Open the Approval queue in the app to approve or reject.')
            ->salutation(' ');
    }

    private function summaryLine(): string
    {
        $this->requestRow->loadMissing('company:id,name', 'requester:id,name,email');
        $type = str_replace('_', ' ', $this->requestRow->type);
        $company = $this->requestRow->company?->name ?? 'Organization #'.$this->requestRow->company_id;
        $by = $this->requestRow->requester?->email ?? 'unknown';

        return "Type: {$type}. Organization: {$company}. Requested by: {$by}. Request #{$this->requestRow->id}.";
    }
}
