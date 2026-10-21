<?php

namespace App\Services\Admin;

use App\Models\OutboundDispatch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class OutboundChannel
{
    public function send(string $channel, User $recipient, string $body, ?string $subject, User $actor): OutboundDispatch
    {
        $error = $this->configurationError($channel);
        $dispatch = OutboundDispatch::query()->create([
            'user_id' => $recipient->id,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'status' => $error ? 'configuration_required' : 'queued',
            'error' => $error,
            'created_by' => $actor->id,
        ]);

        if ($error) {
            return $dispatch;
        }

        if ($channel === 'email') {
            try {
                Mail::raw($body, function ($message) use ($recipient, $subject): void {
                    $message->to($recipient->email)->subject($subject ?: 'TARWIQA');
                });
            } catch (\Throwable) {
                $dispatch->update([
                    'status' => 'failed',
                    'error' => 'The email transport rejected the message. It was not delivered.',
                ]);

                return $dispatch->refresh();
            }

            $dispatch->update(['status' => 'sent', 'error' => null]);

            return $dispatch->refresh();
        }

        $dispatch->update([
            'status' => 'not_implemented',
            'error' => 'No '.$channel.' provider adapter is implemented. The message was not sent.',
        ]);

        return $dispatch->refresh();
    }

    public function configurationError(string $channel): ?string
    {
        return match ($channel) {
            'push' => filled(config('services.push.key')) ? null : 'Push delivery is not configured. Set PUSH_PROVIDER_KEY.',
            'whatsapp' => filled(config('services.whatsapp.token')) ? null : 'WhatsApp delivery is not configured. Set WHATSAPP_TOKEN.',
            'email' => config('mail.default') === 'log' ? 'Email delivery is not configured. MAIL_MAILER is log, so no message was sent.' : null,
            default => 'Unknown delivery channel.',
        };
    }
}
