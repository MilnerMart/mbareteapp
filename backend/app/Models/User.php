<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\EntityStatus;
use App\Db\DbAdapter;
use App\Db\DbConnector;
use App\Db\DbSchema;
use App\Helpers\BaseHelper;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use stdClass;

#[Fillable(['name', 'last_name', 'email', 'password', 'age', 'height', 'weight', 'profile_image_url', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    private const myTable = DbSchema::tableUsers;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    /**
     * Da de alta el usuario junto a su rol en una sola transaccion
     * y devuelve el modelo autenticable (necesario para Sanctum).
     */
    static function registerNew(DbConnector $dbConnector, array $userData, Role $role): self{
        $userId = $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $userData, $role){
            $writeArray = (array)self::user2Row($dbConnector, $userData);
            $userId = self::allocDbTable($dbConnector)->insertGetId($writeArray);
            UserRole::addNewUserRole($dbConnector, $userId, $role->getEntityId());
            return $userId;
        });

        return self::findOrFail($userId);
    }

    static private function user2Row(DbAdapter $dbAdapter, array $userData): stdClass{
        $now = $dbAdapter->toDbDate(BaseHelper::utcNow());
        $row = new stdClass();
        $row->name = $userData['name'];
        $row->last_name = $userData['last_name'];
        $row->email = $userData['email'];
        $row->password = Hash::make($userData['password']);
        $row->age = $userData['age'];
        $row->height = $userData['height'];
        $row->weight = $userData['weight'];
        $row->status = EntityStatus::statusIdActive;
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }
}
