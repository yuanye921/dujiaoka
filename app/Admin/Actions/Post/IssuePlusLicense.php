<?php

namespace App\Admin\Actions\Post;

use Dcat\Admin\Grid\RowAction;

class IssuePlusLicense extends RowAction
{
    protected $title = '手动发放 Plus';

    public function href()
    {
        return admin_url('carmis/' . $this->getKey() . '/issue-plus');
    }
}
