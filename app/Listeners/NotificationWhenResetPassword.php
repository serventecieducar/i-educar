<?php

namespace App\Listeners;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationWhenResetPassword implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle($event)
    {
        try {
            $event->user->notify(new ResetPasswordNotification);
        } catch (Throwable $e) {
            // SMTP mal configurado (ex.: 530 Authentication required) não deve impedir a troca de senha.
            Log::warning('Não foi possível enviar o e-mail de confirmação de alteração de senha.', [
                'message' => $e->getMessage(),
                'user_id' => $event->user->getKey(),
            ]);
        }
    }
}
