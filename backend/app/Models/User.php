<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\CoreModel;
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

#[Fillable(['name', 'last_name', 'email', 'password', 'age', 'height', 'weight', 'profile_image_url', 'status', 'goal', 'gender', 'theme', 'rest_days'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    private const myTable = DbSchema::tableUsers;

    const goalSlugList = ['hypertrophy', 'fat-loss', 'strength', 'endurance', 'toning', 'health'];

    const genderSlugList = ['male', 'female', 'unspecified'];

    const themeSlugList = ['dark', 'light'];

    const weekDayList = [1, 2, 3, 4, 5, 6, 7];

    const maxRestDays = 6;

    const defaultRestDayList = [6, 7];

    const minDeactivationReasonLength = 5, maxDeactivationReasonLength = 500;

    // datos de perfil que la campanita le pide completar; se agrega aca cualquier campo nuevo del perfil
    const requiredProfileFieldList = ['gender', 'weight', 'height', 'age', 'goal'];

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
            'rest_days' => 'array',
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
                $permitList = array_merge($permitList, $role->getRolePermits() ?? []);
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

    function getFullName(): string{
        return trim($this->name.' '.$this->last_name);
    }

    function isDeactivated(): bool{
        return (int)$this->status === EntityStatus::statusIdDeleted;
    }

    function deactivate(DbConnector $dbConnector, User $admin, string $reason): void{
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $admin, $reason){
            $this->status = EntityStatus::statusIdDeleted;
            $this->deactivated_at = $dbConnector->toDbDate(BaseHelper::utcNow());
            $this->deactivated_by = $admin->id;
            $this->deactivation_reason = $reason;
            $this->save();
            $this->tokens()->delete();
            EntityLog::addNewForRef($dbConnector, $admin->id, CoreModel::userModelId, $this->id, EntityLog::actionIdDeactivated, $this->getFullName(), [
                'reason' => $reason,
            ]);
        });
    }

    // la baja pide ver la ficha y el permiso de baja (el admin tiene los dos); a un admin solo lo da de baja otro admin
    function canBeDeactivatedBy(DbConnector $dbConnector, User $viewer): bool{
        return !$this->isDeactivated() && (int)$this->id !== (int)$viewer->id
            && $viewer->hasPermit($dbConnector, Permit::seeUserDetailPermitSlug)
            && $viewer->hasPermit($dbConnector, Permit::deactivateUserPermitSlug)
            && (!$this->hasSeeAllPermit($dbConnector) || $viewer->hasSeeAllPermit($dbConnector));
    }

        function buildDeactivationApiModel(DbConnector $dbConnector): ?array{
        if(!$this->isDeactivated()){
            return null;
        }
        return [
            'at' => BaseHelper::toDisplayDate($this->deactivated_at),
            'byName' => $this->deactivated_by ? self::queryFullNameById($dbConnector, (int)$this->deactivated_by) : null,
            'reason' => $this->deactivation_reason,
        ];
    }

    function getRestDayList(): array{
        return $this->rest_days === null ? self::defaultRestDayList : self::normalizeRestDayList($this->rest_days);
    }

    static function normalizeRestDayList(array $restDayList): array{
        $restDayList = array_values(array_unique(array_intersect(array_map('intval', $restDayList), self::weekDayList)));
        sort($restDayList);
        return $restDayList;
    }

    static function queryRestDayMapByIds(DbConnector $dbConnector, array $userIdList): array{
        if(!$userIdList){
            return [];
        }
        return array_map(fn(?string $restDays) => $restDays === null ? self::defaultRestDayList : self::normalizeRestDayList(BaseHelper::fromDbJson($restDays) ?? []),
            self::allocDbTable($dbConnector)->whereIn('id', $userIdList)->pluck('rest_days', 'id')->all());
    }

    function getPendingProfileFieldList(): array{
        return array_values(array_filter(self::requiredProfileFieldList, fn(string $field) => $this->{$field} === null || $this->{$field} === ''));
    }

    // por la conexion de los modelos: ve al usuario recien creado dentro de la transaccion del registro
    static function queryFullNameById(DbConnector $dbConnector, int $userId): ?string{
        $row = self::allocDbTable($dbConnector)->where('id', $userId)->first(['name', 'last_name']);
        return $row ? trim($row->name.' '.$row->last_name) : null;
    }

    static function queryIdListWithPermit(DbConnector $dbConnector, string $permitSlug): array{
        $roleIdList = [];
        foreach (Role::queryList($dbConnector) as $role) {
            /**  @var Role $role */
            if(in_array($permitSlug, $role->getRolePermits() ?? [], true)){
                $roleIdList[] = $role->getEntityId();
            }
        }
        return UserRole::queryUserIdListByRoleIds($dbConnector, $roleIdList);
    }

    /**
     * Datos visibles para otros usuarios: sin email ni medidas.
     */
    function buildPublicApiModel(): array{
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'profile_image_url' => $this->profile_image_url ? asset($this->profile_image_url) : null,
        ];
    }

    static function allocDbTable(DbConnector $dbConnect, ?string $alias = null): Builder{
        return $dbConnect->getEnvConecction()->table(self::myTable, $alias);
    }

    /**
     * Da de alta el usuario junto a su rol y su gimnasio en una sola transaccion
     * y devuelve el modelo autenticable (necesario para Sanctum).
     * Siempre entra como alumno y al gimnasio base. Si pidio ser entrenador se abre un ticket para el admin,
     * y si se registro con el codigo de otro gimnasio se abre un ticket para el dueño de ese gimnasio.
     */
    static function registerNew(DbConnector $dbConnector, array $userData, Role $traineeRole, Gym $baseGym,
        ?Gym $requestedGym, bool $requestsTrainerRole): self{
        $userId = $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $userData, $traineeRole, $baseGym, $requestedGym, $requestsTrainerRole){
            $writeArray = (array)self::user2Row($dbConnector, $userData);
            $userId = self::allocDbTable($dbConnector)->insertGetId($writeArray);
            UserRole::addNewUserRole($dbConnector, $userId, $traineeRole->getEntityId());
            GymUser::addNewGymUser($dbConnector, $baseGym->getEntityId(), $userId);
            if($requestedGym){
                Ticket::addNewTicket($dbConnector, Ticket::typeIdGymJoin, $userId, $requestedGym->getEntityId());
            }
            if($requestsTrainerRole){
                Ticket::addNewTicket($dbConnector, Ticket::typeIdTrainerRequest, $userId);
            }
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
        $row->rest_days = BaseHelper::jsonEncode(self::defaultRestDayList);
        $row->created_at = $now;
        $row->updated_at = $now;
        return $row;
    }
}
