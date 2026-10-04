<?php

namespace App\Services;

use App\Db\DbConnector;
use App\Db\MysqlAdapter;

class BaseService {


    private DbConnector $dbConnect;


    function __construct(?DbConnector $dbDriver = null) {
        $this->dbConnect = $dbDriver ?? MysqlAdapter::getInstanceForSite();
    }

    function getDbConnecto():DbConnector{
        return $this->dbConnect;
    }

}