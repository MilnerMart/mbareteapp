<?php

namespace App\Services;

use App\Models\User;
use App\PublicException;
use Illuminate\Support\Facades\Log;

class EmailVerificationService extends BaseService {

    private const linkLifetimeSeconds = 172800;

    public function sendVerificationEmail(User $user): bool{
        if($user->email_verified_at){
            return false;
        }
        $verificationUrl = $this->buildVerificationUrl($user);
        $viewData = ['userName' => $user->name, 'verificationUrl' => $verificationUrl, 'expiresInHours' => intdiv(self::linkLifetimeSeconds, 3600)];
        try {
            (new NotificationService($this->getDbConnecto()))->sendEmail(
                $user->email,
                trim($user->name.' '.$user->last_name),
                'Confirma tu correo en '.config('app.name'),
                view('emails.verify-email', $viewData)->render(),
                view('emails.verify-email-text', $viewData)->render(),
            );
            return true;
        } catch (PublicException $exception) {
            Log::warning('email_verification.not_sent', ['userId' => $user->id, 'code' => $exception->getInfoCode(), 'error' => $exception->getText()]);
            return false;
        }
    }

    public function verify(int $userId, int $expires, string $signature): User{
        if($expires < time()){
            throw PublicException::validationError('El enlace para confirmar tu correo vencio. Pide uno nuevo desde la app.', 'email_verification.expired');
        }
        $user = User::find($userId);
        if(!$user || !hash_equals($this->buildSignature($user, $expires), $signature)){
            throw PublicException::validationError('El enlace para confirmar tu correo no es valido.', 'email_verification.invalid');
        }
        if(!$user->email_verified_at){
            $user->email_verified_at = now();
            $user->save();
        }
        return $user;
    }

    private function buildVerificationUrl(User $user): string{
        $expires = time() + self::linkLifetimeSeconds;
        return rtrim(config('app.frontend_url'), '/').'/verify-email?'.http_build_query([
            'user' => $user->id,
            'expires' => $expires,
            'signature' => $this->buildSignature($user, $expires),
        ]);
    }

    private function buildSignature(User $user, int $expires): string{
        return hash_hmac('sha256', implode('|', ['verify-email', $user->id, mb_strtolower($user->email), $expires]), config('app.key'));
    }
}
