<?php

namespace App\Notifications;

use App\Models\VoucherRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class VoucherRequestApprovalRequestedToOwner extends Notification
{
    use Queueable;

    protected VoucherRequest $voucher;

    public function __construct(VoucherRequest $voucher)
    {
        $this->voucher = $voucher;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $url = config('app.url') . '/admin/resources/voucher-requests/' . $this->voucher->id . '/edit';

        return (new MailMessage)
                    ->subject('Permintaan Approval Voucher: ' . $this->voucher->voucher_number)
                    ->greeting('Halo ' . ($notifiable->name ?? 'Owner'))
                    ->line('Sebuah voucher telah diajukan dan membutuhkan persetujuan Anda.')
                    ->line('Nomor: ' . $this->voucher->voucher_number)
                    ->line('Nominal: Rp ' . number_format($this->voucher->amount, 2, ',', '.'))
                    ->action('Lihat Pengajuan', $url)
                    ->line('Terima kasih.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'voucher_request_id' => $this->voucher->id,
            'voucher_number' => $this->voucher->voucher_number,
            'amount' => $this->voucher->amount,
            'message' => 'Permintaan approval voucher: ' . $this->voucher->voucher_number,
            'url' => '/admin/resources/voucher-requests/' . $this->voucher->id . '/edit',
        ];
    }
}
