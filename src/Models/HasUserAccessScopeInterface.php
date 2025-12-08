<?php 
namespace Mita\UranusHttpServer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;

interface HasUserAccessScopeInterface extends Scope
{
    public function scopeWithUserAccess(Builder $query, string|int $userId): void;
}
