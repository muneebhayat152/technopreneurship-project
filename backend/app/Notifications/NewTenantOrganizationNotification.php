<?php

namespace App\Notifications;

use App\Models\Company;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTenantOrganizationNotification extends Notification
{
    public function __construct(
        public Company $company,
        public User $admin
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
        $this->company->loadMissing('id', 'name', 'email', 'industry', 'country');

        return [
            'title' => 'New organization awaiting approval',
            'body' => $this->summaryLine(),
            'kind' => 'tenant_registered',
            'path' => '/companies',
            'company_id' => $this->company->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approve new organization — '.config('app.name'))
            ->line($this->summaryLine())
            ->line('This tenant is on the Free plan until you approve them. In Organizations, approve as Free or Premium, or reject.')
            ->salutation(' ');
    }

    private function summaryLine(): string
    {
        $c = $this->company;
        $ind = $c->industry ? "{$c->industry}, " : '';
        $co = $c->country ? "{$c->country}" : '';

        return "Organization \"{$c->name}\" ({$c->email}) registered and is pending platform approval. Primary admin: {$this->admin->email}. {$ind}{$co}";
    }
}
