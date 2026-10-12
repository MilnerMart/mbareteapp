<?php

namespace App\Services;

use App\Models\User;
use App\PublicException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetService extends BaseService {

    private const linkLifetimeSeconds = 3600, resendCooldownSeconds = 60;

    private const tokensTable = 'password_reset_tokens';

    public function sendResetLink(string $email): void{
        $user = User::where('email', $email)->first();
        if(!$user){
            return;
        }
        $previous = DB::table(self::tokensTable)->where('email', $user->email)->first();
        if($previous && $previous->created_at && time() - Carbon::parse($previous->created_at)->getTimestamp() < self::resendCooldownSeconds){
            return;
        }

        $token = Str::random(64);
        DB::table(self::tokensTable)->updateOrInsert(['email' => $user->email], ['token' => hash('sha256', $token), 'created_at' => now()]);

        $viewData = [
            'userName' => $user->name,
            'resetUrl' => rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]),
            'expiresInMinutes' => intdiv(self::linkLifetimeSeconds, 60),
        ];
        try {
            (new NotificationService($this->getDbConnecto()))->sendEmail(
                $user->email,
                trim($user->name.' '.$user->last_name),
                'Cambia tu contraseña de '.config('app.name'),
                view('emails.reset-password', $viewData)->render(),
                view('emails.reset-password-text', $viewData)->render(),
            );
        } catch (PublicException $exception) {
            Log::warning('password_reset.not_sent', ['userId' => $user->id, 'code' => $exception->getInfoCode(), 'error' => $exception->getText()]);
        }
    }

    public function resetPassword(string $email, string $token, string $password): User{
        $user = User::where('email', $email)->first();
        $row = $user ? DB::table(self::tokensTable)->where('email', $user->email)->first() : null;
        if(!$row || !hash_equals($row->token, hash('sha256', $token))){
            throw PublicException::validationError('El enlace para cambiar la contraseña no es valido o ya se uso. Pide uno nuevo.', 'password_reset.invalid');
        }
        if(!$row->created_at || Carbon::parse($row->created_at)->getTimestamp() + self::linkLifetimeSeconds < time()){
            DB::table(self::tokensTable)->where('email', $user->email)->delete();
            throw PublicException::validationError('El enlace para cambiar la contraseña vencio. Pide uno nuevo.', 'password_reset.expired');
        }

        DB::transaction(function() use ($user, $password){
            $user->password = $password;
            if(!$user->email_verified_at){
                $user->email_verified_at = now();
            }
            $user->save();
            $user->tokens()->delete();
            DB::table(self::tokensTable)->where('email', $user->email)->delete();
        });
        return $user;
    }
}
