<?php
namespace App;

use App\Models\Ticket;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Musculos y ejercicios propuestos por entrenadores: tienen dueño, pueden ser privados
 * y no aparecen en las listas hasta que el admin aprueba su ticket.
 */
trait HasCatalogReview
{
    private ?int $ownerId = null;

    private bool $isPublic = true;

    private int $reviewState = Ticket::stateIdApproved;

    function getOwnerId(): ?int{
        return $this->ownerId;
    }

    function setOwnerId(?int $ownerId): void{
        $this->ownerId = $ownerId;
    }

    function isPublic(): bool{
        return $this->isPublic;
    }

    function setIsPublic(bool $isPublic): void{
        $this->isPublic = $isPublic;
    }

    function getReviewState(): int{
        return $this->reviewState;
    }

    function setReviewState(int $reviewState): void{
        $this->reviewState = $reviewState;
    }

    function isApproved(): bool{
        return $this->reviewState === Ticket::stateIdApproved;
    }

    /**
     * Aprobado y publico lo ve cualquiera; aprobado y privado, solo su dueño.
     * El dueño ve tambien lo que tiene pendiente o rechazado, y el admin ve todo.
     */
    function canBeSeenBy(?int $viewerId, bool $isAdmin): bool{
        if($isAdmin || ($viewerId && $this->ownerId === $viewerId)){
            return true;
        }
        return $this->isApproved() && $this->isPublic;
    }

    /**
     * Filtro de las listas: aprobados que son publicos o del usuario que mira.
     */
    static function applyVisibleScope(Builder $query, ?int $viewerId, string $alias = ''): Builder{
        $column = fn(string $name) => $alias ? $alias.'.'.$name : $name;
        return $query->where($column('review_state'), Ticket::stateIdApproved)
            ->where(function(Builder $where) use ($viewerId, $column){
                $where->where($column('is_public'), true);
                if($viewerId){
                    $where->orWhere($column('owner_id'), $viewerId);
                }
            });
    }

    protected function loadReviewFromRow(stdClass $row): void{
        $this->ownerId = isset($row->owner_id) ? (int)$row->owner_id : null;
        $this->isPublic = (bool)($row->is_public ?? true);
        $this->reviewState = (int)($row->review_state ?? Ticket::stateIdApproved);
    }

    protected function writeReviewToRow(stdClass $row): void{
        $row->owner_id = $this->ownerId;
        $row->is_public = $this->isPublic;
        $row->review_state = $this->reviewState;
    }

    protected function buildReviewApiModel(): array{
        return [
            'ownerId' => $this->ownerId,
            'isPublic' => $this->isPublic,
            'reviewState' => Ticket::stateSlugFromId($this->reviewState),
        ];
    }
}
