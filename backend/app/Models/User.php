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

    private ?array $permitListCache = null;

    function queryRoleList(DbConnector $dbConnector): array{
        return Role::queryListByUserId($dbConnector, $this->id);
    }

    function queryPermitList(DbConnector $dbConnector): array{
        if($this->permitListCache === null){
            $permitList = [];
            foreach ($this->queryRoleList($dbConnector) as $role) {
                /**  @var Role $role */
                $permitList = array_merge($permitList, $role->getRolePermits());
            }
            $this->permitListCache = array_values(array_unique($permitList));
        }
        return $this->permitListCache;
    }

    /**
     * El permiso total del admin habilita cualquier otro permiso.
     */
    function hasPermit(DbConnector $dbConnector, string $permitSlug): bool{
        $permitList = $this->queryPermitList($dbConnector);
        return in_array(Permit::seeAllPermitSlug, $permitList, true) || in_array($permitSlug, $permitList, true);
    }

    function hasSeeAllPermit(DbConnector $dbConnector): bool{
        return $this->hasPermit($dbConnector, Permit::seeAllPermitSlug);
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    /**
     * Da de alta el usuario junto a su rol y su gimnasio en una sola transaccion
     * y devuelve el modelo autenticable (necesario para Sanctum).
     */
    static function registerNew(DbConnector $dbConnector, array $userData, Role $role, Gym $gym): self{
        $userId = $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $userData, $role, $gym){
            $writeArray = (array)self::user2Row($dbConnector, $userData);
            $userId = self::allocDbTable($dbConnector)->insertGetId($writeArray);
            UserRole::addNewUserRole($dbConnector, $userId, $role->getEntityId());
            GymUser::addNewGymUser($dbConnector, $gym->getEntityId(), $userId);
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
