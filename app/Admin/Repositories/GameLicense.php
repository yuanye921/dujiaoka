<?php

namespace App\Admin\Repositories;

use App\Models\GameLicense as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class GameLicense extends EloquentRepository
{
    protected $eloquentClass = Model::class;
}
